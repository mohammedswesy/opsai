<?php

namespace App\Policies;

use App\Models\User;
use App\Services\OrganizationAccess;
use App\Support\OrganizationContext;

abstract class TenantPolicy
{
    public function __construct(protected OrganizationAccess $access) {}

    protected function can(User $user, string $permission): bool
    {
        $organization = OrganizationContext::current()?->organization;

        return $organization !== null && $this->access->allows($user, $organization, $permission);
    }

    protected function inTenant(object $model): bool
    {
        return OrganizationContext::id() !== null
            && isset($model->organization_id)
            && OrganizationContext::id() === $model->organization_id;
    }
}
