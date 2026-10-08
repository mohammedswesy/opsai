<?php

namespace App\Services;

use App\Enums\OrganizationRole;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrganizationMembershipService
{
    /**
     * @param  list<string>  $branchIds
     */
    public function add(Organization $organization, array $attributes, array $branchIds = []): User
    {
        return DB::transaction(function () use ($organization, $attributes, $branchIds): User {
            $user = User::query()->where('email', $attributes['email'])->first();

            if ($user === null) {
                $user = User::query()->create([
                    'name' => $attributes['name'],
                    'email' => $attributes['email'],
                    'password' => $attributes['password'],
                ]);
            }

            if ($organization->members()->whereKey($user->id)->exists()) {
                throw ValidationException::withMessages([
                    'email' => __('This person is already a member of the organization.'),
                ]);
            }

            $organization->members()->attach($user->id, [
                'role' => $attributes['role'],
            ]);

            $this->syncBranches($organization, $user, $branchIds);

            return $user;
        });
    }

    /**
     * @param  list<string>  $branchIds
     */
    public function update(Organization $organization, User $member, OrganizationRole $role, array $branchIds): void
    {
        $current = $member->roleIn($organization);

        if ($current === null) {
            throw ValidationException::withMessages([
                'user' => __('This person is not a member of the organization.'),
            ]);
        }

        if ($current === OrganizationRole::Owner && $role !== OrganizationRole::Owner && $this->ownerCount($organization) <= 1) {
            throw ValidationException::withMessages([
                'role' => __('The organization must keep at least one owner.'),
            ]);
        }

        DB::transaction(function () use ($organization, $member, $role, $branchIds): void {
            $organization->members()->updateExistingPivot($member->id, [
                'role' => $role->value,
            ]);

            $this->syncBranches($organization, $member, $branchIds);
        });
    }

    public function remove(Organization $organization, User $member): void
    {
        $current = $member->roleIn($organization);

        if ($current === null) {
            throw ValidationException::withMessages([
                'user' => __('This person is not a member of the organization.'),
            ]);
        }

        if ($current === OrganizationRole::Owner && $this->ownerCount($organization) <= 1) {
            throw ValidationException::withMessages([
                'user' => __('The organization must keep at least one owner.'),
            ]);
        }

        DB::transaction(function () use ($organization, $member): void {
            $branchIds = Branch::withoutGlobalScope('organization')
                ->where('organization_id', $organization->id)
                ->pluck('id');
            $member->branches()->detach($branchIds);
            $organization->members()->detach($member->id);
        });
    }

    /**
     * @param  list<string>  $branchIds
     */
    private function syncBranches(Organization $organization, User $user, array $branchIds): void
    {
        $allowed = Branch::withoutGlobalScope('organization')
            ->where('organization_id', $organization->id)
            ->whereIn('id', $branchIds)
            ->pluck('id')
            ->all();

        $otherBranchIds = $user->branches()
            ->withoutGlobalScope('organization')
            ->where('organization_id', '!=', $organization->id)
            ->pluck('branches.id');

        $user->branches()->sync($otherBranchIds->merge($allowed)->all());
    }

    private function ownerCount(Organization $organization): int
    {
        return $organization->members()
            ->wherePivot('role', OrganizationRole::Owner->value)
            ->count();
    }
}
