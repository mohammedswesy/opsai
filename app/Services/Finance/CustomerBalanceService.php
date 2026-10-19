<?php

namespace App\Services\Finance;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\Money;

class CustomerBalanceService
{
    /**
     * @return array<string, mixed>
     */
    public function summary(Customer $customer): array
    {
        $invoices = Invoice::query()
            ->where('customer_id', $customer->id)
            ->whereNotIn('status', [InvoiceStatus::Draft->value, InvoiceStatus::Cancelled->value])
            ->get(['id', 'total', 'amount_due', 'status', 'currency']);

        $invoiced = '0.00';
        $outstanding = '0.00';
        $overdue = '0.00';

        foreach ($invoices as $invoice) {
            $invoiced = Money::add($invoiced, (string) $invoice->total);
            if ($invoice->status->isOutstanding()) {
                $outstanding = Money::add($outstanding, (string) $invoice->amount_due);
            }
            if ($invoice->status === InvoiceStatus::Overdue) {
                $overdue = Money::add($overdue, (string) $invoice->amount_due);
            }
        }

        $paid = '0.00';
        Payment::query()
            ->where('status', PaymentStatus::Completed)
            ->whereIn('invoice_id', $invoices->pluck('id'))
            ->pluck('amount')
            ->each(function (mixed $amount) use (&$paid): void {
                $paid = Money::add($paid, (string) $amount);
            });

        return [
            'currency' => $invoices->first()?->currency ?? $customer->organization->currency,
            'invoiced' => $invoiced,
            'paid' => $paid,
            'outstanding' => $outstanding,
            'overdue' => $overdue,
            'invoices' => Invoice::query()->where('customer_id', $customer->id)->latest()->limit(8)->get(['id', 'invoice_number', 'status', 'total', 'amount_due', 'currency', 'issue_date']),
            'payments' => Payment::query()->whereIn('invoice_id', Invoice::query()->where('customer_id', $customer->id)->select('id'))->with('invoice:id,invoice_number')->latest()->limit(8)->get(),
        ];
    }
}
