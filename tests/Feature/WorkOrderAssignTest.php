<?php

use App\Enums\OrganizationRole;
use App\Models\ServiceRequest;
use App\Models\WorkOrder;
use Carbon\Carbon;

function assignableWorkOrder($organization, $customer): WorkOrder
{
    $request = ServiceRequest::query()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'source' => 'manual',
        'subject' => 'Open repair',
        'description' => 'Needs a technician.',
        'priority' => 'normal',
        'status' => 'qualified',
        'requested_at' => Carbon::parse('2026-10-10 08:00:00', 'Asia/Riyadh'),
    ]);

    return WorkOrder::query()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'service_request_id' => $request->id,
        'title' => 'Open repair',
        'priority' => 'normal',
        'status' => 'assigned',
    ]);
}

it('assigns a work order to a technician in the same organization', function () {
    [$owner, $organization] = createMember();
    $customer = dispatchCustomer($organization, 'Assign Villa');
    $technician = dispatchTechnician($organization);
    $workOrder = assignableWorkOrder($organization, $customer);

    actingInOrganization($owner, $organization)
        ->post('/work-orders/'.$workOrder->id.'/assign', [
            'technician_id' => $technician->id,
        ])
        ->assertRedirect();

    expect($workOrder->refresh()->technician_id)->toBe($technician->id);
});

it('rejects a technician from another organization and a missing technician', function () {
    [$owner, $organization] = createMember();
    [$otherOwner, $otherOrganization] = createMember();
    $customer = dispatchCustomer($organization, 'Assign Villa');
    $otherCustomer = dispatchCustomer($otherOrganization, 'Other Villa');
    $workOrder = assignableWorkOrder($organization, $customer);
    $foreign = dispatchTechnician($otherOrganization);
    $foreignOrder = assignableWorkOrder($otherOrganization, $otherCustomer);

    actingInOrganization($owner, $organization)
        ->post('/work-orders/'.$workOrder->id.'/assign', [
            'technician_id' => $foreign->id,
        ])
        ->assertSessionHasErrors('technician_id');

    actingInOrganization($owner, $organization)
        ->post('/work-orders/'.$workOrder->id.'/assign', [
            'technician_id' => '01notatechnician000000000',
        ])
        ->assertSessionHasErrors('technician_id');

    actingInOrganization($owner, $organization)
        ->post('/work-orders/'.$foreignOrder->id.'/assign', [
            'technician_id' => dispatchTechnician($organization)->id,
        ])
        ->assertNotFound();

    expect($workOrder->refresh()->technician_id)->toBeNull();
});

it('rejects a technician who already has an overlapping appointment', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-10 12:00:00', 'Asia/Riyadh'));

    [$owner, $organization] = createMember();
    $customer = dispatchCustomer($organization, 'Assign Villa');
    $busy = dispatchTechnician($organization);
    $free = dispatchTechnician($organization);
    $workOrder = assignableWorkOrder($organization, $customer);
    $linked = dispatchAppointment($organization, $customer, $free, 'scheduled', '2026-10-10 10:00:00', '2026-10-10 12:00:00');
    $workOrder->update(['appointment_id' => $linked->id]);
    dispatchAppointment($organization, $customer, $busy, 'scheduled', '2026-10-10 09:00:00', '2026-10-10 11:00:00');

    actingInOrganization($owner, $organization)
        ->post('/work-orders/'.$workOrder->id.'/assign', [
            'technician_id' => $busy->id,
        ])
        ->assertSessionHasErrors('scheduled_start');

    expect($workOrder->refresh()->technician_id)->toBeNull();

    actingInOrganization($owner, $organization)
        ->post('/work-orders/'.$workOrder->id.'/assign', [
            'technician_id' => $free->id,
        ])
        ->assertRedirect();

    expect($workOrder->refresh()->technician_id)->toBe($free->id);
});

it('does not treat another organization appointment as a conflict', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-10 12:00:00', 'Asia/Riyadh'));

    [$owner, $organization] = createMember();
    [, $otherOrganization] = createMember();
    $customer = dispatchCustomer($organization, 'Assign Villa');
    $otherCustomer = dispatchCustomer($otherOrganization, 'Other Villa');
    $technician = dispatchTechnician($organization);
    $foreign = dispatchTechnician($otherOrganization);
    $workOrder = assignableWorkOrder($organization, $customer);
    $linked = dispatchAppointment($organization, $customer, $technician, 'scheduled', '2026-10-10 10:00:00', '2026-10-10 12:00:00');
    $workOrder->update(['appointment_id' => $linked->id]);
    dispatchAppointment($otherOrganization, $otherCustomer, $foreign, 'scheduled', '2026-10-10 10:00:00', '2026-10-10 12:00:00');

    actingInOrganization($owner, $organization)
        ->post('/work-orders/'.$workOrder->id.'/assign', [
            'technician_id' => $technician->id,
        ])
        ->assertRedirect();

    expect($workOrder->refresh()->technician_id)->toBe($technician->id);
});

it('stops a technician and a customer service user from assigning work orders', function (OrganizationRole $role) {
    [$user, $organization] = createMember($role);
    $customer = dispatchCustomer($organization, 'Assign Villa');
    $technician = dispatchTechnician($organization);
    $workOrder = assignableWorkOrder($organization, $customer);

    actingInOrganization($user, $organization)
        ->post('/work-orders/'.$workOrder->id.'/assign', [
            'technician_id' => $technician->id,
        ])
        ->assertForbidden();

    expect($workOrder->refresh()->technician_id)->toBeNull();
})->with([
    OrganizationRole::Technician,
    OrganizationRole::CustomerService,
]);
