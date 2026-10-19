<?php

use App\Enums\OrganizationRole;
use App\Models\Activity;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\ServiceRequest;
use App\Models\Task;
use App\Models\Technician;
use App\Models\WorkOrder;

it('requires authentication for operations pages', function (string $path) {
    $this->get($path)->assertRedirect('/login');
})->with([
    '/customers',
    '/services',
    '/technicians',
    '/requests',
    '/appointments',
    '/work-orders',
    '/tasks',
]);

it('keeps customers inside the current organization', function () {
    [$owner, $organization] = createMember();
    [$other, $otherOrganization] = createMember();

    actingInOrganization($owner, $organization)->post('/customers', [
        'type' => 'individual',
        'name' => 'North Wind Villa',
        'phone' => '0550001001',
        'status' => 'active',
        'organization_id' => $otherOrganization->id,
    ])->assertRedirect();

    $customer = Customer::withoutGlobalScope('organization')->where('name', 'North Wind Villa')->first();
    expect($customer)->not->toBeNull()
        ->and($customer->organization_id)->toBe($organization->id);

    actingInOrganization($other, $otherOrganization)->get('/customers/'.$customer->id)->assertNotFound();
    actingInOrganization($owner, $organization)->get('/customers?search=North+Wind')->assertOk()->assertSee('North Wind Villa');
});

it('rejects an incomplete customer', function () {
    [$owner, $organization] = createMember();

    actingInOrganization($owner, $organization)->post('/customers', [
        'type' => 'individual',
        'name' => 'Missing Phone',
        'status' => 'active',
    ])->assertInvalid(['phone']);
});

it('stops a technician from creating customers', function () {
    [$technician, $organization] = createMember(OrganizationRole::Technician);

    actingInOrganization($technician, $organization)->post('/customers', [
        'type' => 'individual',
        'name' => 'Blocked',
        'phone' => '0550001999',
        'status' => 'active',
    ])->assertForbidden();
});

it('archives a customer', function () {
    [$owner, $organization] = createMember();
    actingInOrganization($owner, $organization)->post('/customers', [
        'type' => 'company',
        'name' => 'Al Noor Trading Demo',
        'phone' => '0550001002',
        'status' => 'active',
    ])->assertRedirect();

    $customer = Customer::query()->where('name', 'Al Noor Trading Demo')->firstOrFail();
    actingInOrganization($owner, $organization)->delete('/customers/'.$customer->id)->assertRedirect('/customers');
    expect(Customer::query()->find($customer->id))->toBeNull();
});

it('manages the service catalog inside the organization', function () {
    [$owner, $organization] = createMember();
    [$other, $otherOrganization] = createMember();

    actingInOrganization($owner, $organization)->post('/services', [
        'name' => 'AC Maintenance',
        'category' => 'HVAC',
        'base_price' => 250,
        'estimated_duration' => 90,
        'status' => 'active',
        'organization_id' => $otherOrganization->id,
    ])->assertRedirect('/services');

    actingInOrganization($other, $otherOrganization)->get('/services')->assertOk()->assertDontSee('AC Maintenance');
    actingInOrganization($owner, $organization)->get('/services?search=AC')->assertOk()->assertSee('AC Maintenance');
});

it('refuses a technician user from another organization', function () {
    [$owner, $organization] = createMember();
    [$outsider] = createMember();

    actingInOrganization($owner, $organization)->post('/technicians', [
        'user_id' => $outsider->id,
        'availability_status' => 'available',
        'status' => 'active',
        'skills' => [['skill' => 'hvac']],
    ])->assertInvalid(['user_id']);
});

it('creates a technician for a member of the same organization', function () {
    [$owner, $organization] = createMember();
    [$member] = createMember(OrganizationRole::Technician, $organization);

    actingInOrganization($owner, $organization)->post('/technicians', [
        'user_id' => $member->id,
        'employee_code' => 'TEC-200',
        'availability_status' => 'available',
        'status' => 'active',
        'skills' => [['skill' => 'plumbing']],
    ])->assertRedirect();

    expect(Technician::query()->where('user_id', $member->id)->exists())->toBeTrue();
});

it('rejects overlapping appointments and accepts a later slot', function () {
    [$owner, $organization] = createMember();
    $customerId = customerId($owner, $organization);
    $technicianId = technicianId($owner, $organization);

    actingInOrganization($owner, $organization)->post('/appointments', appointmentPayload($customerId, $technicianId, '2026-10-09T09:00', '2026-10-09T11:00'))
        ->assertRedirect('/appointments');

    actingInOrganization($owner, $organization)->post('/appointments', appointmentPayload($customerId, $technicianId, '2026-10-09T10:00', '2026-10-09T12:00'))
        ->assertInvalid(['scheduled_start']);

    actingInOrganization($owner, $organization)->post('/appointments', appointmentPayload($customerId, $technicianId, '2026-10-09T11:00', '2026-10-09T12:00'))
        ->assertRedirect('/appointments');

    expect(Appointment::query()->count())->toBe(2);
});

it('rejects an appointment for another organization customer or technician', function () {
    [$owner, $organization] = createMember();
    [$otherOwner, $otherOrganization] = createMember();
    $customerId = customerId($owner, $organization);
    $technicianId = technicianId($owner, $organization);
    $foreignCustomer = customerId($otherOwner, $otherOrganization);
    $foreignTechnician = technicianId($otherOwner, $otherOrganization);

    actingInOrganization($owner, $organization)->post('/appointments', appointmentPayload($foreignCustomer, $technicianId, '2026-10-10T09:00', '2026-10-10T10:00'))
        ->assertInvalid(['customer_id']);

    actingInOrganization($owner, $organization)->post('/appointments', appointmentPayload($customerId, $foreignTechnician, '2026-10-10T09:00', '2026-10-10T10:00'))
        ->assertInvalid(['technician_id']);
});

it('moves a work order only through allowed statuses', function () {
    [$owner, $organization] = createMember();
    $customerId = customerId($owner, $organization);
    $requestId = requestId($owner, $organization, $customerId);

    actingInOrganization($owner, $organization)->post('/work-orders', [
        'customer_id' => $customerId,
        'service_request_id' => $requestId,
        'title' => 'Inspect villa split unit',
        'priority' => 'high',
        'status' => 'completed',
    ])->assertRedirect();

    $workOrder = WorkOrder::query()->where('title', 'Inspect villa split unit')->firstOrFail();
    expect($workOrder->status->value)->toBe('assigned');

    foreach (['accepted', 'on_the_way', 'arrived', 'in_progress', 'completed'] as $status) {
        actingInOrganization($owner, $organization)->post('/work-orders/'.$workOrder->id.'/transition', [
            'status' => $status,
        ])->assertRedirect();
    }

    expect($workOrder->refresh()->status->value)->toBe('completed')
        ->and(ServiceRequest::query()->find($requestId)->status->value)->toBe('completed');

    actingInOrganization($owner, $organization)->post('/work-orders/'.$workOrder->id.'/transition', [
        'status' => 'assigned',
    ])->assertInvalid(['status']);
});

it('hides another organization service request', function () {
    [$owner, $organization] = createMember();
    [$other, $otherOrganization] = createMember();
    $customerId = customerId($owner, $organization);
    $requestId = requestId($owner, $organization, $customerId);

    actingInOrganization($other, $otherOrganization)->get('/requests/'.$requestId)->assertNotFound();
});

it('records who did what for operational events', function () {
    [$owner, $organization] = createMember();

    actingInOrganization($owner, $organization)->post('/customers', [
        'type' => 'individual',
        'name' => 'North Wind Villa',
        'phone' => '0550001001',
        'status' => 'active',
    ])->assertRedirect();

    $customer = Customer::query()->where('name', 'North Wind Villa')->firstOrFail();
    $activity = Activity::query()->where('action', 'customer.created')->firstOrFail();

    expect($activity->actor_id)->toBe($owner->id)
        ->and($activity->organization_id)->toBe($organization->id)
        ->and($activity->subject_type)->toBe('customer')
        ->and($activity->subject_id)->toBe($customer->id)
        ->and($activity->properties['actor_name'])->toBe($owner->name)
        ->and($activity->properties['resource_id'])->toBe($customer->id)
        ->and($activity->created_at)->not->toBeNull();
});

it('logs a completed task', function () {
    [$owner, $organization] = createMember();

    actingInOrganization($owner, $organization)->post('/tasks', [
        'title' => 'Confirm spare filters',
        'priority' => 'normal',
        'status' => 'todo',
    ])->assertRedirect();

    $task = Task::query()->where('title', 'Confirm spare filters')->firstOrFail();

    actingInOrganization($owner, $organization)->put('/tasks/'.$task->id, [
        'title' => 'Confirm spare filters',
        'priority' => 'normal',
        'status' => 'completed',
    ])->assertRedirect();

    expect(Activity::query()->where('action', 'task.completed')->where('subject_id', $task->id)->exists())->toBeTrue();
});

it('keeps a chosen locale on the next request', function () {
    [$owner, $organization] = createMember();
    $organization->update(['locale' => 'ar']);

    actingInOrganization($owner, $organization)->post('/locale', ['locale' => 'en'])->assertRedirect();

    actingInOrganization($owner, $organization)->get('/')->assertOk()->assertInertia(fn ($page) => $page->where('locale', 'en'));
});

it('creates an internal task for a member', function () {
    [$owner, $organization] = createMember();

    actingInOrganization($owner, $organization)->post('/tasks', [
        'title' => 'Confirm spare filters',
        'priority' => 'normal',
        'status' => 'todo',
        'assigned_to' => $owner->id,
    ])->assertRedirect('/tasks');

    actingInOrganization($owner, $organization)->get('/')->assertOk()->assertInertia(fn ($page) => $page
        ->component('Dashboard')
        ->where('metrics.pending_tasks', 1)
        ->where('organization.role', 'owner'));
});

function customerId($owner, $organization): string
{
    actingInOrganization($owner, $organization)->post('/customers', [
        'type' => 'individual',
        'name' => 'North Wind Villa '.fake()->unique()->numerify('###'),
        'phone' => fake()->unique()->numerify('055#######'),
        'status' => 'active',
    ])->assertRedirect();

    return Customer::query()->latest('created_at')->value('id');
}

function technicianId($owner, $organization): string
{
    [$member] = createMember(OrganizationRole::Technician, $organization);

    actingInOrganization($owner, $organization)->post('/technicians', [
        'user_id' => $member->id,
        'availability_status' => 'available',
        'status' => 'active',
        'skills' => [['skill' => 'hvac']],
    ])->assertRedirect();

    return Technician::query()->where('user_id', $member->id)->value('id');
}

function requestId($owner, $organization, string $customerId): string
{
    actingInOrganization($owner, $organization)->post('/requests', [
        'customer_id' => $customerId,
        'source' => 'manual',
        'subject' => 'Villa AC not cooling',
        'description' => 'The living room split unit stays warm.',
        'priority' => 'high',
        'status' => 'new',
    ])->assertRedirect();

    return ServiceRequest::query()->where('customer_id', $customerId)->value('id');
}

function appointmentPayload(string $customerId, string $technicianId, string $start, string $end): array
{
    return [
        'customer_id' => $customerId,
        'technician_id' => $technicianId,
        'scheduled_start' => $start,
        'scheduled_end' => $end,
        'status' => 'scheduled',
    ];
}
