<?php

use App\Enums\OrganizationRole;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\Technician;
use App\Models\User;
use Carbon\Carbon;
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

function dispatchCustomer(Organization $organization, string $name): Customer
{
    return Customer::query()->create([
        'organization_id' => $organization->id,
        'type' => 'individual',
        'name' => $name,
        'phone' => '0550001001',
        'country' => 'SA',
        'status' => 'active',
    ]);
}

function dispatchTechnician(Organization $organization, ?User $user = null, ?string $branchId = null, string $availability = 'available'): Technician
{
    return Technician::query()->create([
        'organization_id' => $organization->id,
        'user_id' => $user?->id ?? User::factory()->create()->id,
        'branch_id' => $branchId,
        'availability_status' => $availability,
        'status' => 'active',
    ]);
}

function dispatchAppointment(Organization $organization, Customer $customer, Technician $technician, string $status, string $start, string $end, ?string $branchId = null, ?string $requestId = null): Appointment
{
    return Appointment::query()->create([
        'organization_id' => $organization->id,
        'branch_id' => $branchId,
        'customer_id' => $customer->id,
        'technician_id' => $technician->id,
        'service_request_id' => $requestId,
        'scheduled_start' => Carbon::parse($start, 'Asia/Riyadh')->utc(),
        'scheduled_end' => Carbon::parse($end, 'Asia/Riyadh')->utc(),
        'status' => $status,
    ]);
}
