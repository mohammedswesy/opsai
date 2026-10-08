<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;
use App\Services\OrganizationAccess;
use App\Support\OrganizationContext;

class BranchPolicy
{
    public function __construct(private readonly OrganizationAccess $access) {}

    public function viewAny(User $user): bool
    {
        $context = OrganizationContext::current();

        return $context !== null && $this->access->allows($user, $context->organization, 'branches.view');
    }

    public function view(User $user, Branch $branch): bool
    {
        return $this->belongsToCurrentOrganization($branch)
            && $this->access->allows($user, $branch->organization, 'branches.view');
    }

    public function create(User $user): bool
    {
        $context = OrganizationContext::current();

        return $context !== null && $this->access->allows($user, $context->organization, 'branches.manage');
    }

    public function update(User $user, Branch $branch): bool
    {
        return $this->belongsToCurrentOrganization($branch)
            && $this->access->allows($user, $branch->organization, 'branches.manage');
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $this->update($user, $branch);
    }

    private function belongsToCurrentOrganization(Branch $branch): bool
    {
        return OrganizationContext::id() === $branch->organization_id;
    }
}
