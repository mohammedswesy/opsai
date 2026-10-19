<?php

namespace App\Http\Requests;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Support\OrganizationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Appointment::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['nullable', 'string', OrganizationRules::exists('branches', true)],
            'customer_id' => ['required', 'string', OrganizationRules::exists('customers', true)],
            'technician_id' => ['required', 'string', OrganizationRules::exists('technicians', true)],
            'service_request_id' => ['nullable', 'string', OrganizationRules::exists('service_requests')],
            'scheduled_start' => ['required', 'date'],
            'scheduled_end' => ['required', 'date', 'after:scheduled_start'],
            'status' => ['required', Rule::in(AppointmentStatus::values())],
            'notes' => ['nullable', 'string'],
        ];
    }
}
