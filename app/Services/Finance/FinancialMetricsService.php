<?php

namespace App\Services\Finance;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\Money;
use App\Support\OrganizationContext;
use Carbon\Carbon;
use Illuminate\Http\Request;

class FinancialMetricsService
{
    /**
     * @return array<string, mixed>
     */
    public function metrics(Request $request): array
    {
        $branchId = $request->string('branch_id')->toString();
        if ($branchId !== '' && Branch::query()->whereKey($branchId)->doesntExist()) {
            abort(404);
        }

        $timezone = OrganizationContext::current()->organization->timezone;
        $from = $request->filled('from')
            ? Carbon::parse($request->string('from')->toString(), $timezone)->startOfDay()
            : Carbon::now($timezone)->subDays(13)->startOfDay();
        $to = $request->filled('to')
            ? Carbon::parse($request->string('to')->toString(), $timezone)->endOfDay()
            : Carbon::now($timezone)->endOfDay();

        $invoices = Invoice::query()
            ->when($branchId !== '', fn ($query) => $query->where('branch_id', $branchId));
        $payments = Payment::query()
            ->where('status', PaymentStatus::Completed)
            ->when($branchId !== '', fn ($query) => $query->whereHas('invoice', fn ($query) => $query->where('branch_id', $branchId)));

        $invoiced = $this->sum((clone $invoices)
            ->whereNotIn('status', [InvoiceStatus::Draft->value, InvoiceStatus::Cancelled->value])
            ->whereDate('issue_date', '>=', $from->toDateString())
            ->whereDate('issue_date', '<=', $to->toDateString())
            ->pluck('total'));

        $paidRows = (clone $payments)->whereBetween('paid_at', [$from->copy()->utc(), $to->copy()->utc()])->get(['amount', 'paid_at']);
        $paid = $this->sum($paidRows->pluck('amount'));

        $outstanding = $this->sum((clone $invoices)->outstanding()->pluck('amount_due'));
        $overdue = $this->sum((clone $invoices)->where('status', InvoiceStatus::Overdue)->pluck('amount_due'));
        $todayStart = Carbon::now($timezone)->startOfDay()->utc();
        $todayEnd = Carbon::now($timezone)->endOfDay()->utc();
        $today = $this->sum((clone $payments)->whereBetween('paid_at', [$todayStart, $todayEnd])->pluck('amount'));

        $paymentsByDay = $paidRows->groupBy(fn (Payment $payment) => $payment->paid_at->timezone($timezone)->toDateString());
        $issued = (clone $invoices)
            ->whereNotIn('status', [InvoiceStatus::Draft->value, InvoiceStatus::Cancelled->value])
            ->whereDate('issue_date', '>=', $from->toDateString())
            ->whereDate('issue_date', '<=', $to->toDateString())
            ->get(['issue_date', 'total']);
        $revenueByDay = $issued->groupBy(fn (Invoice $invoice) => $invoice->issue_date->toDateString());

        $days = collect();
        $cursor = $from->copy()->startOfDay();
        while ($cursor->lte($to)) {
            $date = $cursor->toDateString();
            $days->push([
                'date' => $date,
                'revenue' => $this->sum($revenueByDay->get($date, collect())->pluck('total')),
                'payments' => $this->sum($paymentsByDay->get($date, collect())->pluck('amount')),
            ]);
            $cursor->addDay();
        }

        $distribution = collect(InvoiceStatus::cases())->map(fn (InvoiceStatus $status) => [
            'status' => $status->value,
            'count' => (clone $invoices)->where('status', $status)->count(),
        ])->values();

        return [
            'currency' => OrganizationContext::current()->organization->currency,
            'revenue' => $invoiced,
            'invoiced' => $invoiced,
            'paid' => $paid,
            'outstanding' => $outstanding,
            'overdue' => $overdue,
            'todays_payments' => $today,
            'by_day' => $days->values(),
            'invoice_status' => $distribution,
        ];
    }

    /**
     * @param  iterable<int, mixed>  $amounts
     */
    private function sum(iterable $amounts): string
    {
        $total = '0.00';
        foreach ($amounts as $amount) {
            $total = Money::add($total, (string) $amount);
        }

        return $total;
    }
}
