<?php

namespace App\Services\Finance;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Operations\ActivityLogger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        private readonly ActivityLogger $activities,
        private readonly InvoiceLifecycleService $invoices,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function record(Invoice $invoice, array $data): Payment
    {
        return DB::transaction(function () use ($invoice, $data) {
            $locked = Invoice::query()->whereKey($invoice->id)->lockForUpdate()->firstOrFail();
            $this->guardPayable($locked, (string) $data['amount']);

            $payment = Payment::query()->create([
                'invoice_id' => $locked->id,
                'amount' => Money::normalize((string) $data['amount']),
                'currency' => $locked->currency,
                'method' => $data['method'],
                'status' => PaymentStatus::Completed,
                'reference' => $data['reference'] ?? null,
                'paid_at' => now(),
                'notes' => $data['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            $this->activities->log($payment, 'payment.created', $locked->invoice_number, [
                'invoice_id' => $locked->id,
                'amount' => (string) $payment->amount,
                'currency' => $payment->currency,
            ]);
            $this->activities->log($payment, 'payment.completed', $locked->invoice_number, [
                'invoice_id' => $locked->id,
                'amount' => (string) $payment->amount,
            ]);
            $this->invoices->syncBalances($locked);

            return $payment;
        });
    }

    public function refund(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment) {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== PaymentStatus::Completed) {
                throw ValidationException::withMessages([
                    'status' => __('Only a completed payment can be refunded.'),
                ]);
            }

            $locked->status = PaymentStatus::Refunded;
            $locked->save();
            $this->activities->log($locked, 'payment.refunded', $locked->invoice->invoice_number, [
                'invoice_id' => $locked->invoice_id,
                'amount' => (string) $locked->amount,
            ]);
            $this->invoices->syncBalances($locked->invoice);

            return $locked;
        });
    }

    private function guardPayable(Invoice $invoice, string $amount): void
    {
        if ($invoice->status === InvoiceStatus::Cancelled) {
            throw ValidationException::withMessages([
                'invoice_id' => __('A cancelled invoice cannot accept a payment.'),
            ]);
        }

        if (! $invoice->status->canAcceptPayment() || Money::compare((string) $invoice->amount_due, '0') <= 0) {
            throw ValidationException::withMessages([
                'invoice_id' => __('This invoice cannot accept a payment.'),
            ]);
        }

        $normalized = Money::normalize($amount);
        if (Money::compare($normalized, '0.01') < 0) {
            throw ValidationException::withMessages([
                'amount' => __('Enter a payment amount.'),
            ]);
        }

        if (Money::compare($normalized, (string) $invoice->amount_due) > 0) {
            throw ValidationException::withMessages([
                'amount' => __('The payment cannot exceed the remaining balance.'),
            ]);
        }
    }
}
