<?php

namespace App\Http\Requests;

use App\Enums\OrganizationRole;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreOrganizationUserRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->input('password') === '') {
            $this->merge(['password' => null]);
        }
    }

    public function authorize(): bool
    {
        $organization = OrganizationContext::current()?->organization;

        return $organization !== null && ($this->user()?->can('manageUsers', $organization) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $existing = User::query()->where('email', $this->input('email'))->exists();
        $role = OrganizationContext::current()?->role;

        return [
            'name' => [$existing ? 'nullable' : 'required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'password' => [$existing ? 'nullable' : 'required', 'string', Password::min(8)],
            'role' => ['required', Rule::in(array_map(
                fn (OrganizationRole $item) => $item->value,
                $role?->assignableRoles() ?? [],
            ))],
            'branch_ids' => ['array'],
            'branch_ids.*' => ['string'],
        ];
    }
}
