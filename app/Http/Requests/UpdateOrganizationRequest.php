<?php

namespace App\Http\Requests;

use App\Enums\OrganizationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'country' => strtoupper((string) $this->input('country')),
            'currency' => strtoupper((string) $this->input('currency')),
        ]);
    }

    public function authorize(): bool
    {
        $organization = $this->user()?->organizations()
            ->whereKey($this->session()->get('current_organization_id'))
            ->first();

        return $organization !== null && ($this->user()?->can('update', $organization) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:64'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:255'],
            'country' => ['required', 'string', 'size:2', 'alpha'],
            'timezone' => ['required', 'timezone'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'locale' => ['required', Rule::in(config('opsai.locales'))],
            'status' => ['required', Rule::in(OrganizationStatus::values())],
        ];
    }
}
