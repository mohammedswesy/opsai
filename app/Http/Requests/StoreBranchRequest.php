<?php

namespace App\Http\Requests;

use App\Enums\BranchStatus;
use App\Models\Branch;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBranchRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper((string) $this->input('code')),
            'country' => strtoupper((string) $this->input('country')),
            'latitude' => $this->filled('latitude') ? $this->input('latitude') : null,
            'longitude' => $this->filled('longitude') ? $this->input('longitude') : null,
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('create', Branch::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $organizationId = OrganizationContext::id();

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:32',
                Rule::unique('branches', 'code')->where('organization_id', $organizationId),
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['required', 'string', 'size:2', 'alpha'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'phone' => ['nullable', 'string', 'max:32'],
            'timezone' => ['required', 'timezone'],
            'status' => ['required', Rule::in(BranchStatus::values())],
        ];
    }
}
