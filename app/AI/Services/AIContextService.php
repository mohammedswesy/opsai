<?php

namespace App\AI\Services;

use App\Models\Branch;
use App\Models\Customer;
use App\Support\OrganizationContext;

class AIContextService
{
    /**
     * @return array<string, mixed>
     */
    public function describe(?string $branchId): array
    {
        $current = OrganizationContext::current();
        $organization = $current->organization;
        $branch = null;

        if (is_string($branchId) && $branchId !== '') {
            $branch = Branch::query()->whereKey($branchId)->first(['id', 'name']);
        }

        return [
            'organization_name' => $organization->name,
            'locale' => $organization->locale,
            'currency' => $organization->currency,
            'timezone' => $organization->timezone,
            'current_time' => now($organization->timezone)->toIso8601String(),
            'role' => $current->role->value,
            'branch_name' => $branch?->name,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function describeCustomer(?Customer $customer): array
    {
        $organization = OrganizationContext::current()->organization;

        return [
            'organization_name' => $organization->name,
            'locale' => $organization->locale,
            'timezone' => $organization->timezone,
            'current_time' => now($organization->timezone)->toIso8601String(),
            'actor' => 'customer',
            'customer_name' => $customer?->name,
        ];
    }
}
