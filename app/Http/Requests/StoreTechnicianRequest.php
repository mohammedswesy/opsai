<?php

namespace App\Http\Requests;

use App\Enums\AvailabilityStatus;
use App\Enums\RecordStatus;
use App\Enums\TechnicianSkillName;
use App\Models\Technician;
use App\Support\OrganizationContext;
use App\Support\OrganizationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTechnicianRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Technician::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'string', OrganizationRules::member(), Rule::unique('technicians', 'user_id')->where('organization_id', OrganizationContext::id())],
            'branch_id' => ['nullable', 'string', OrganizationRules::exists('branches', true)],
            'employee_code' => ['nullable', 'string', 'max:32', Rule::unique('technicians', 'employee_code')->where('organization_id', OrganizationContext::id())],
            'availability_status' => ['required', Rule::in(AvailabilityStatus::values())],
            'current_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'current_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(RecordStatus::values())],
            'skills' => ['array'],
            'skills.*.skill' => ['required', Rule::in(TechnicianSkillName::values())],
            'skills.*.service_id' => ['nullable', 'string', OrganizationRules::exists('services', true)],
        ];
    }
}
