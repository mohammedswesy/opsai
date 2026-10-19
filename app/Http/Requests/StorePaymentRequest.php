<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Support\OrganizationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Payment::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'invoice_id' => ['required', 'string', OrganizationRules::exists('invoices', true)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', Rule::in(PaymentMethod::values())],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
