<?php

namespace App\Services;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;

class OrganizationAccess
{
    public function role(User $user, Organization $organization): ?OrganizationRole
    {
        return $user->roleIn($organization);
    }

    public function allows(User $user, Organization $organization, string $permission): bool
    {
        return $this->role($user, $organization)?->allows($permission) ?? false;
    }
}
