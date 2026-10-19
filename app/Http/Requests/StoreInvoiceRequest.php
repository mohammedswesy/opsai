<?php

namespace App\Http\Requests;

use App\Models\Invoice;
use App\Support\OrganizationRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Invoice::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['nullable', 'string', OrganizationRules::exists('branches', true)],
            'customer_id' => ['required', 'string', OrganizationRules::exists('customers', true)],
            'work_order_id' => ['nullable', 'string', OrganizationRules::exists('work_orders')],
            'due_date' => ['nullable', 'date'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.service_id' => ['nullable', 'string', OrganizationRules::exists('services', true)],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
