<?php

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

function createMember(OrganizationRole $role = OrganizationRole::Owner, ?Organization $organization = null): array
{
    $user = User::factory()->create();
    $organization ??= Organization::factory()->create();
    $organization->members()->attach($user->id, ['role' => $role->value]);

    return [$user, $organization];
}

function actingInOrganization(User $user, Organization $organization): TestCase
{
    return test()->actingAs($user)->withSession([
        'current_organization_id' => $organization->id,
    ]);
}
