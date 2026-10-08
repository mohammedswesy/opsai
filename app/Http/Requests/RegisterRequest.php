<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RegisterRequest extends FormRequest
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
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'organization_name' => ['required', 'string', 'max:255'],
            'country' => ['required', 'string', 'size:2', 'alpha'],
            'timezone' => ['required', 'timezone'],
            'currency' => ['required', 'string', 'size:3', 'alpha'],
            'locale' => ['required', Rule::in(config('opsai.locales'))],
        ];
    }
}
