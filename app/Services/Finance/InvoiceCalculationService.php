<?php

namespace App\Services\Finance;

use App\Support\Money;
use Illuminate\Validation\ValidationException;

class InvoiceCalculationService
{
    /**
     * @param  list<array{description: string, quantity: string, unit_price: string, service_id?: string|null}>  $items
     * @return array{items: list<array<string, mixed>>, subtotal: string, discount: string, tax: string, total: string}
     */
    public function calculate(array $items, string $discount, string $taxRate): array
    {
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => __('Add at least one invoice item.'),
            ]);
        }

        $lines = [];
        $subtotal = '0.00';
        $tax = '0.00';

        foreach ($items as $index => $item) {
            $quantity = Money::normalize((string) $item['quantity']);
            $unitPrice = Money::normalize((string) $item['unit_price']);

            if (Money::compare($quantity, '0.01') < 0) {
                throw ValidationException::withMessages([
                    "items.$index.quantity" => __('Quantity must be at least 0.01.'),
                ]);
            }

            if (Money::compare($unitPrice, '0') < 0) {
                throw ValidationException::withMessages([
                    "items.$index.unit_price" => __('Unit price cannot be negative.'),
                ]);
            }

            $lineSubtotal = Money::mul($quantity, $unitPrice);
            $lineTax = Money::percentage($lineSubtotal, $taxRate);
            $lineTotal = Money::add($lineSubtotal, $lineTax);
            $subtotal = Money::add($subtotal, $lineSubtotal);
            $tax = Money::add($tax, $lineTax);

            $lines[] = [
                'service_id' => $item['service_id'] ?? null,
                'description' => $item['description'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'tax_rate' => Money::normalize($taxRate),
                'subtotal' => $lineSubtotal,
                'tax' => $lineTax,
                'total' => $lineTotal,
            ];
        }

        $discount = Money::normalize($discount);
        if (Money::compare($discount, '0') < 0 || Money::compare($discount, $subtotal) > 0) {
            throw ValidationException::withMessages([
                'discount' => __('Discount must be between zero and the subtotal.'),
            ]);
        }

        return [
            'items' => $lines,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax' => $tax,
            'total' => Money::add(Money::sub($subtotal, $discount), $tax),
        ];
    }
}
