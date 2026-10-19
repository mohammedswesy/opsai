<?php

namespace App\Http\Requests;

use App\Enums\Priority;
use App\Models\WorkOrder;
use App\Support\OrganizationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', WorkOrder::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['nullable', 'string', OrganizationRules::exists('branches', true)],
            'customer_id' => ['required', 'string', OrganizationRules::exists('customers', true)],
            'service_request_id' => ['required', 'string', OrganizationRules::exists('service_requests')],
            'appointment_id' => ['nullable', 'string', OrganizationRules::exists('appointments')],
            'technician_id' => ['nullable', 'string', OrganizationRules::exists('technicians', true)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['required', Rule::in(Priority::values())],
        ];
    }
}
