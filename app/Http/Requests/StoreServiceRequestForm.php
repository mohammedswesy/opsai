<?php

namespace App\Http\Requests;

use App\Enums\Priority;
use App\Enums\RequestSource;
use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Support\OrganizationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreServiceRequestForm extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ServiceRequest::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['nullable', 'string', OrganizationRules::exists('branches', true)],
            'customer_id' => ['required', 'string', OrganizationRules::exists('customers', true)],
            'service_id' => ['nullable', 'string', OrganizationRules::exists('services', true)],
            'source' => ['required', Rule::in(RequestSource::values())],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'priority' => ['required', Rule::in(Priority::values())],
            'status' => ['required', Rule::in(ServiceRequestStatus::values())],
            'requested_at' => ['nullable', 'date'],
            'preferred_date' => ['nullable', 'date'],
            'preferred_time' => ['nullable', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:255'],
        ];
    }
}
