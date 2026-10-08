<?php

use App\Enums\OrganizationRole;
use App\Enums\OrganizationStatus;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

it('sends guests to the login page', function () {
    $this->get('/')->assertRedirect(route('login'));
});

it('registers an owner and organization', function () {
    $response = $this->post('/register', [
        'name' => 'Amina Owner',
        'email' => 'amina@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'organization_name' => 'North Wind HVAC',
        'country' => 'sa',
        'timezone' => 'Asia/Riyadh',
        'currency' => 'sar',
        'locale' => 'ar',
    ]);

    $response->assertRedirect(route('dashboard'));

    $user = User::query()->where('email', 'amina@example.test')->first();
    $organization = Organization::query()->where('slug', 'north-wind-hvac')->first();

    expect($user)->not->toBeNull()
        ->and($organization)->not->toBeNull()
        ->and($organization->country)->toBe('SA')
        ->and($organization->currency)->toBe('SAR')
        ->and($user->roleIn($organization))->toBe(OrganizationRole::Owner);

    $this->get('/')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->where('organization.role', 'owner')
        ->where('locale', 'ar')
    );
});

it('rejects invalid credentials', function () {
    User::factory()->create([
        'email' => 'person@example.test',
        'password' => 'password123',
    ]);

    $this->post('/login', [
        'email' => 'person@example.test',
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

it('issues a sanctum token for the api user endpoint', function () {
    $user = User::factory()->create();
    $token = $user->createToken('device')->plainTextToken;

    $this->getJson('/api/user')->assertUnauthorized();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('email', $user->email);
});

it('resets a password without revealing whether the email exists', function () {
    $this->post('/forgot-password', ['email' => 'missing@example.test'])
        ->assertSessionHas('status');
});

it('changes a password with a valid reset token', function () {
    $user = User::factory()->create(['email' => 'reset@example.test']);
    $token = Password::createToken($user);

    $this->post('/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertRedirect(route('login'));

    expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue();
});

it('isolates branches between organizations', function () {
    [$ownerA, $organizationA] = createMember();
    [$ownerB, $organizationB] = createMember();

    $branchA = Branch::factory()->create(['organization_id' => $organizationA->id, 'code' => 'MAIN']);
    $branchB = Branch::factory()->create(['organization_id' => $organizationB->id, 'code' => 'MAIN']);

    actingInOrganization($ownerA, $organizationA)
        ->get('/branches')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Branches/Index')
            ->has('branches', 1)
            ->where('branches.0.id', $branchA->id)
        );

    actingInOrganization($ownerA, $organizationA)
        ->put('/branches/'.$branchB->id, [
            'name' => 'Stolen',
            'code' => 'MAIN',
            'country' => 'SA',
            'timezone' => 'Asia/Riyadh',
            'status' => 'active',
        ])
        ->assertNotFound();

    expect(Branch::withoutGlobalScope('organization')->find($branchB->id)?->name)->toBe($branchB->name);
});

it('lets a manager create a branch and blocks a technician', function () {
    [$manager, $organization] = createMember(OrganizationRole::Manager);
    [$technician] = createMember(OrganizationRole::Technician, $organization);

    actingInOrganization($technician, $organization)
        ->post('/branches', branchPayload())
        ->assertForbidden();

    actingInOrganization($manager, $organization)
        ->post('/branches', branchPayload())
        ->assertRedirect(route('branches.index'));

    expect(Branch::withoutGlobalScope('organization')->where('organization_id', $organization->id)->count())->toBe(1);
});

it('stops an admin from granting the owner role', function () {
    [$admin, $organization] = createMember(OrganizationRole::Admin);

    actingInOrganization($admin, $organization)
        ->post('/users', [
            'name' => 'New Owner',
            'email' => 'new-owner@example.test',
            'password' => 'password123',
            'role' => OrganizationRole::Owner->value,
        ])
        ->assertSessionHasErrors('role');
});

it('keeps the last owner', function () {
    [$owner, $organization] = createMember();

    actingInOrganization($owner, $organization)
        ->delete('/users/'.$owner->id)
        ->assertSessionHasErrors('user');

    expect($organization->members()->count())->toBe(1);
});

it('blocks non-managers when an organization is suspended', function () {
    [$owner, $organization] = createMember();
    [$technician] = createMember(OrganizationRole::Technician, $organization);
    $organization->update(['status' => OrganizationStatus::Suspended]);

    actingInOrganization($technician, $organization)->get('/')->assertForbidden();
    actingInOrganization($owner, $organization)->get('/')->assertOk();
});

it('seeds the demo organizations', function () {
    $this->seed();

    expect(Organization::query()->where('slug', 'riyadh-cooling-services')->exists())->toBeTrue()
        ->and(Organization::query()->where('slug', 'jeddah-maintenance-pro')->exists())->toBeTrue()
        ->and(User::query()->where('email', 'owner@riyadh-cooling.test')->exists())->toBeTrue()
        ->and(Branch::withoutGlobalScope('organization')->where('code', 'RYD-MAIN')->exists())->toBeTrue();
});

function branchPayload(): array
{
    return [
        'name' => 'Olaya',
        'code' => 'olaya',
        'country' => 'sa',
        'city' => 'Riyadh',
        'timezone' => 'Asia/Riyadh',
        'status' => 'active',
    ];
}
