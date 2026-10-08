<?php

namespace App\Http\Controllers;

use App\Enums\OrganizationRole;
use App\Http\Requests\StoreOrganizationUserRequest;
use App\Http\Requests\UpdateOrganizationUserRequest;
use App\Models\Branch;
use App\Models\User;
use App\Services\OrganizationMembershipService;
use App\Support\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationUserController extends Controller
{
    public function index(): Response
    {
        $organization = OrganizationContext::current()->organization;
        $this->authorize('viewUsers', $organization);

        $members = $organization->members()->with('branches')->orderBy('name')->get()->map(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->pivot->role,
            'branch_ids' => $user->branches->pluck('id')->values(),
        ]);

        return Inertia::render('Users/Index', [
            'members' => $members,
            'branches' => Branch::query()->orderBy('name')->get(['id', 'name']),
            'canManage' => request()->user()?->can('manageUsers', $organization) ?? false,
            'assignableRoles' => array_map(
                fn (OrganizationRole $role) => $role->value,
                OrganizationContext::current()->role->assignableRoles(),
            ),
        ]);
    }

    public function store(StoreOrganizationUserRequest $request, OrganizationMembershipService $memberships): RedirectResponse
    {
        $organization = OrganizationContext::current()->organization;
        $data = $request->validated();

        $memberships->add($organization, [
            'name' => $data['name'] ?? $data['email'],
            'email' => $data['email'],
            'password' => $data['password'] ?? null,
            'role' => $data['role'],
        ], $data['branch_ids'] ?? []);

        return redirect()->route('users.index')->with('success', __('Team member saved.'));
    }

    public function update(UpdateOrganizationUserRequest $request, User $user, OrganizationMembershipService $memberships): RedirectResponse
    {
        $organization = OrganizationContext::current()->organization;
        $targetRole = $user->roleIn($organization);

        if ($targetRole === null) {
            abort(404);
        }

        if ($targetRole === OrganizationRole::Owner && OrganizationContext::current()->role !== OrganizationRole::Owner) {
            abort(403);
        }

        $data = $request->validated();

        $memberships->update(
            $organization,
            $user,
            OrganizationRole::from($data['role']),
            $data['branch_ids'] ?? [],
        );

        return redirect()->route('users.index')->with('success', __('Team member updated.'));
    }

    public function destroy(User $user, OrganizationMembershipService $memberships): RedirectResponse
    {
        $organization = OrganizationContext::current()->organization;
        $this->authorize('manageUsers', $organization);

        $targetRole = $user->roleIn($organization);

        if ($targetRole === null) {
            abort(404);
        }

        if ($targetRole === OrganizationRole::Owner && OrganizationContext::current()->role !== OrganizationRole::Owner) {
            abort(403);
        }

        $memberships->remove($organization, $user);

        return redirect()->route('users.index')->with('success', __('Team member removed.'));
    }
}
