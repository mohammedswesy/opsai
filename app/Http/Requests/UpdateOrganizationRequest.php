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
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tax_number.regex' => __('The tax number must be 15 digits that start and end with 3.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            // Saudi VAT registration number: 15 digits, first and last digit 3.
            'tax_number' => ['nullable', 'string', 'regex:/^3\d{13}3\z/'],
            'phone' => ['nullable', 'string', 'max:32'],
            'email' => ['nullable', 'string', 'lowercase', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:120'],
            'country' => ['required', 'string', 'size:2', 'alpha'],
            'timezone' => ['required', 'timezone'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'locale' => ['required', Rule::in(config('opsai.locales'))],
            'status' => ['required', Rule::in(OrganizationStatus::values())],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
