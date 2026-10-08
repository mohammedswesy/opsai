<?php

namespace App\Http\Requests;

use App\Enums\OrganizationRole;
use App\Support\OrganizationContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationUserRequest extends FormRequest
{
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
        $role = OrganizationContext::current()?->role;

        return [
            'role' => ['required', Rule::in(array_map(
                fn (OrganizationRole $item) => $item->value,
                $role?->assignableRoles() ?? [],
            ))],
            'branch_ids' => ['array'],
            'branch_ids.*' => ['string'],
        ];
    }
}
