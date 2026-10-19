<?php

namespace App\Http\Requests;

use App\Enums\AvailabilityStatus;
use App\Enums\RecordStatus;
use App\Enums\TechnicianSkillName;
use App\Support\OrganizationContext;
use App\Support\OrganizationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTechnicianRequest extends FormRequest
{
    public function authorize(): bool
    {
        $technician = $this->route('technician');

        return $technician !== null && ($this->user()?->can('update', $technician) ?? false);
    }

    public function rules(): array
    {
        $technicianId = $this->route('technician')?->id;

        return [
            'branch_id' => ['nullable', 'string', OrganizationRules::exists('branches', true)],
            'employee_code' => ['nullable', 'string', 'max:32', Rule::unique('technicians', 'employee_code')->where('organization_id', OrganizationContext::id())->ignore($technicianId)],
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
