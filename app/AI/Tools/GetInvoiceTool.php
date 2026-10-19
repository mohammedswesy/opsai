<?php

namespace App\AI\Tools;

use App\AI\Data\AIToolContext;
use App\Models\Invoice;
use App\Models\User;

class GetInvoiceTool extends ApplicationTool
{
    public function name(): string
    {
        return 'get_invoice';
    }

    public function description(): string
    {
        return 'One invoice in the current organization by id or invoice number.';
    }

    public function schema(): array
    {
        return $this->objectSchema([
            'invoice_id' => ['type' => 'string'],
            'invoice_number' => ['type' => 'string'],
        ]);
    }

    public function authorize(User $user): bool
    {
        return $user->can('viewAny', Invoice::class);
    }

    public function execute(array $arguments, AIToolContext $context): array
    {
        $query = Invoice::query()->with('customer:id,name');
        $id = $arguments['invoice_id'] ?? null;
        $number = $arguments['invoice_number'] ?? null;

        if (is_string($id) && $id !== '') {
            $invoice = $query->whereKey($id)->first();
        } elseif (is_string($number) && $number !== '') {
            $invoice = $query->where('invoice_number', $number)->first();
        } else {
            return ['success' => false, 'data' => null, 'message' => 'An invoice is required.'];
        }

        if ($invoice === null || ! $context->user->can('view', $invoice)) {
            return ['success' => false, 'data' => null, 'message' => 'That invoice was not found in this organization.'];
        }

        return $this->ok([
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'customer' => $invoice->customer?->name,
            'status' => $invoice->status->value,
            'currency' => $invoice->currency,
            'total' => (string) $invoice->total,
            'amount_paid' => (string) $invoice->amount_paid,
            'amount_due' => (string) $invoice->amount_due,
            'due_date' => $invoice->due_date?->toDateString(),
        ], 'invoices');
    }
}
