<?php

namespace App\Http\Requests;

use App\Enums\CustomerType;
use App\Enums\RecordStatus;
use App\Enums\RequestSource;
use App\Models\Customer;
use App\Support\OrganizationRules;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Customer::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('country')) {
            $this->merge(['country' => strtoupper((string) $this->input('country'))]);
        }
    }

    public function rules(): array
    {
        return [
            'branch_id' => ['nullable', 'string', OrganizationRules::exists('branches', true)],
            'type' => ['required', Rule::in(CustomerType::values())],
            'name' => ['required', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:32'],
            'whatsapp_number' => ['nullable', 'string', 'max:32'],
            'whatsapp_opt_in' => ['sometimes', 'boolean'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'size:2', 'alpha'],
            'notes' => ['nullable', 'string'],
            'source' => ['nullable', Rule::in(RequestSource::values())],
            'status' => ['required', Rule::in(RecordStatus::values())],
        ];
    }

    /**
     * Validated fields for the customer, with the WhatsApp opt-in checkbox stored as the
     * time consent was recorded (kept when it was already given, cleared when unticked).
     *
     * @return array<string, mixed>
     */
    public function customerData(?Customer $current = null): array
    {
        $data = $this->validated();
        unset($data['whatsapp_opt_in']);

        if ($this->has('whatsapp_opt_in')) {
            $data['whatsapp_opt_in_at'] = $this->boolean('whatsapp_opt_in')
                ? ($current?->whatsapp_opt_in_at ?? now())
                : null;
        }

        return $data;
    }
}
