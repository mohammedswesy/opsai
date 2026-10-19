<?php

namespace App\Services\Finance;

use App\Enums\InvoiceStatus;
use App\Enums\WorkOrderStatus;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\WorkOrder;
use App\Services\Operations\ActivityLogger;
use App\Support\Money;
use App\Support\OrganizationContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvoiceLifecycleService
{
    public function __construct(
        private readonly ActivityLogger $activities,
        private readonly InvoiceCalculationService $calculator,
        private readonly InvoiceNumberService $numbers,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $items
     */
    public function create(array $data, array $items): Invoice
    {
        return DB::transaction(function () use ($data, $items) {
            $invoice = $this->store(new Invoice, $data, $items, $this->numbers->next());
            $this->activities->log($invoice, 'invoice.created', $invoice->invoice_number);
            $this->activities->log($invoice->customer, 'invoice.created', $invoice->invoice_number, [
                'invoice_id' => $invoice->id,
            ]);

            return $invoice;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $items
     */
    public function updateDraft(Invoice $invoice, array $data, array $items): Invoice
    {
        $this->guardDraft($invoice);

        return DB::transaction(function () use ($invoice, $data, $items) {
            return $this->store($invoice, $data, $items, $invoice->invoice_number);
        });
    }

    public function issue(Invoice $invoice): Invoice
    {
        if (! $invoice->status->canIssue()) {
            throw ValidationException::withMessages([
                'status' => __('Only a draft invoice can be issued.'),
            ]);
        }

        $invoice->status = InvoiceStatus::Issued;
        $invoice->save();
        $this->activities->log($invoice, 'invoice.issued', $invoice->invoice_number);

        return $invoice;
    }

    public function cancel(Invoice $invoice): Invoice
    {
        if (! $invoice->status->canCancel() || Money::compare((string) $invoice->amount_paid, '0') > 0) {
            throw ValidationException::withMessages([
                'status' => __('This invoice cannot be cancelled.'),
            ]);
        }

        $invoice->status = InvoiceStatus::Cancelled;
        $invoice->amount_due = '0.00';
        $invoice->save();
        $this->activities->log($invoice, 'invoice.cancelled', $invoice->invoice_number);

        return $invoice;
    }

    public function generateFromWorkOrder(WorkOrder $workOrder): Invoice
    {
        return DB::transaction(function () use ($workOrder) {
            $locked = WorkOrder::query()->whereKey($workOrder->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== WorkOrderStatus::Completed) {
                throw ValidationException::withMessages([
                    'work_order_id' => __('Only a completed work order can be invoiced.'),
                ]);
            }

            $existing = Invoice::query()
                ->where('work_order_id', $locked->id)
                ->where('status', '!=', InvoiceStatus::Cancelled->value)
                ->first();

            if ($existing) {
                return $existing;
            }

            $locked->load('serviceRequest.service');
            $service = $locked->serviceRequest?->service;
            $price = $service?->base_price !== null ? (string) $service->base_price : '0.00';

            return $this->create([
                'branch_id' => $locked->branch_id,
                'customer_id' => $locked->customer_id,
                'work_order_id' => $locked->id,
                'due_date' => now(OrganizationContext::current()->organization->timezone)->addDays(14)->toDateString(),
                'discount' => '0',
                'notes' => $locked->title,
            ], [[
                'service_id' => $service?->id,
                'description' => $locked->title,
                'quantity' => '1',
                'unit_price' => $price,
            ]]);
        });
    }

    public function markOverdue(): int
    {
        $count = 0;

        Invoice::withoutGlobalScope('organization')
            ->with('organization')
            ->whereIn('status', [InvoiceStatus::Issued->value, InvoiceStatus::PartiallyPaid->value])
            ->where('amount_due', '>', 0)
            ->whereNotNull('due_date')
            ->orderBy('id')
            ->each(function (Invoice $invoice) use (&$count): void {
                $today = Carbon::now($invoice->organization->timezone)->toDateString();

                if ($invoice->due_date->toDateString() < $today) {
                    $invoice->status = InvoiceStatus::Overdue;
                    $invoice->save();
                    $count++;
                }
            });

        return $count;
    }

    public function syncBalances(Invoice $invoice): Invoice
    {
        if (in_array($invoice->status, [InvoiceStatus::Draft, InvoiceStatus::Cancelled], true)) {
            return $invoice;
        }

        $paid = Money::normalize((string) $invoice->payments()->where('status', 'completed')->sum('amount'));
        $due = Money::sub((string) $invoice->total, $paid);

        if (Money::compare($due, '0') < 0) {
            throw ValidationException::withMessages([
                'amount' => __('Payments cannot exceed the invoice total.'),
            ]);
        }

        $invoice->amount_paid = $paid;
        $invoice->amount_due = $due;
        $pastDue = $invoice->due_date !== null
            && $invoice->due_date->toDateString() < now(OrganizationContext::current()->organization->timezone)->toDateString();

        if (Money::compare($due, '0') === 0) {
            $invoice->status = InvoiceStatus::Paid;
            $invoice->paid_at = $invoice->paid_at ?? now();
        } else {
            $invoice->paid_at = null;
            $invoice->status = match (true) {
                $pastDue => InvoiceStatus::Overdue,
                Money::compare($paid, '0') > 0 => InvoiceStatus::PartiallyPaid,
                default => InvoiceStatus::Issued,
            };
        }

        $invoice->save();

        return $invoice;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $items
     */
    private function store(Invoice $invoice, array $data, array $items, string $number): Invoice
    {
        if (! empty($data['work_order_id'])) {
            $workOrder = WorkOrder::query()->whereKey($data['work_order_id'])->first();
            if ($workOrder === null || $workOrder->customer_id !== $data['customer_id']) {
                throw ValidationException::withMessages([
                    'work_order_id' => __('The work order does not belong to this customer.'),
                ]);
            }
        }

        $calculated = $this->calculator->calculate($items, (string) ($data['discount'] ?? '0'), OrganizationContext::current()->organization->taxRate());
        $today = now(OrganizationContext::current()->organization->timezone)->toDateString();

        $invoice->fill([
            'branch_id' => $data['branch_id'] ?? null,
            'customer_id' => $data['customer_id'],
            'work_order_id' => $data['work_order_id'] ?? null,
            'invoice_number' => $number,
            'status' => $invoice->exists ? $invoice->status : InvoiceStatus::Draft,
            'issue_date' => $invoice->exists ? $invoice->issue_date : $today,
            'due_date' => $data['due_date'] ?? null,
            'currency' => $invoice->exists ? $invoice->currency : OrganizationContext::current()->organization->currency,
            'subtotal' => $calculated['subtotal'],
            'discount' => $calculated['discount'],
            'tax' => $calculated['tax'],
            'total' => $calculated['total'],
            'amount_paid' => $invoice->exists ? $invoice->amount_paid : '0.00',
            'amount_due' => $calculated['total'],
            'notes' => $data['notes'] ?? null,
        ]);
        $invoice->save();
        $invoice->items()->delete();

        foreach ($calculated['items'] as $item) {
            InvoiceItem::query()->create([
                'invoice_id' => $invoice->id,
                ...$item,
            ]);
        }

        return $invoice->load('items');
    }

    private function guardDraft(Invoice $invoice): void
    {
        if (! $invoice->status->canEdit()) {
            throw ValidationException::withMessages([
                'status' => __('Only a draft invoice can be edited.'),
            ]);
        }
    }
}
