<?php

namespace App\AI\Tools;

use App\AI\Data\AIToolContext;
use App\AI\Tools\Concerns\ScopesBranch;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Finance\FinancialMetricsService;

class GetOverdueInvoicesTool extends ApplicationTool
{
    use ScopesBranch;

    public function name(): string
    {
        return 'get_overdue_invoices';
    }

    public function description(): string
    {
        return 'Overdue invoice total from the finance metrics, plus the overdue invoice list.';
    }

    public function schema(): array
    {
        return $this->objectSchema([]);
    }

    public function authorize(User $user): bool
    {
        return $user->can('viewAny', Invoice::class);
    }

    public function execute(array $arguments, AIToolContext $context): array
    {
        $metrics = app(FinancialMetricsService::class)->metrics($this->metricsRequest($context));
        $overdue = $this->scopeBranch(Invoice::query(), $context)->overdue();
        $invoices = (clone $overdue)->with('customer:id,name')->latest('due_date')->limit(10)->get();

        return $this->ok([
            'count' => (clone $overdue)->count(),
            'total_outstanding' => $metrics['overdue'],
            'currency' => $metrics['currency'],
            'invoices' => $invoices->map(fn (Invoice $invoice) => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'customer' => $invoice->customer?->name,
                'status' => $invoice->status->value,
                'amount_due' => (string) $invoice->amount_due,
                'currency' => $invoice->currency,
                'due_date' => $invoice->due_date?->toDateString(),
            ])->all(),
        ], 'financial_metrics');
    }
}
