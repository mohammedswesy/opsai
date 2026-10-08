<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationAccess;

class OrganizationPolicy
{
    public function __construct(private readonly OrganizationAccess $access) {}

    public function view(User $user, Organization $organization): bool
    {
        return $this->access->allows($user, $organization, 'organization.view');
    }

    public function update(User $user, Organization $organization): bool
    {
        return $this->access->allows($user, $organization, 'organization.update');
    }

    public function viewUsers(User $user, Organization $organization): bool
    {
        return $this->access->allows($user, $organization, 'users.view');
    }

    public function manageUsers(User $user, Organization $organization): bool
    {
        return $this->access->allows($user, $organization, 'users.manage');
    }
}
