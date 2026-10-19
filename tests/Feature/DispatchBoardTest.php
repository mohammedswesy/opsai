<?php

use App\Enums\OrganizationRole;
use App\Models\Branch;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\TechnicianSkill;
use App\Models\User;
use App\Support\RecordReference;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

it('requires authentication for the dispatch board', function () {
    $this->get('/dispatch')->assertRedirect('/login');
});

it('keeps the dispatch board inside the organization and counts the day', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-10 12:00:00', 'Asia/Riyadh'));

    [$owner, $organization] = createMember();
    [$otherOwner, $otherOrganization] = createMember();
    $branch = Branch::factory()->create(['organization_id' => $organization->id, 'name' => 'Main']);
    $otherBranch = Branch::factory()->create(['organization_id' => $organization->id, 'name' => 'North']);
    $customer = dispatchCustomer($organization, 'Olaya Villa');
    $otherCustomer = dispatchCustomer($otherOrganization, 'Jeddah Villa');
    $service = Service::query()->create([
        'organization_id' => $organization->id,
        'name' => 'AC maintenance',
        'category' => 'hvac',
        'estimated_duration' => 60,
        'status' => 'active',
    ]);
    $first = dispatchTechnician($organization, User::factory()->create(['name' => 'Faisal Tech']), $branch->id);
    $second = dispatchTechnician($organization, User::factory()->create(['name' => 'Noura Tech']), $otherBranch->id, 'busy');
    TechnicianSkill::query()->create(['technician_id' => $first->id, 'skill' => 'hvac']);
    $openRequest = ServiceRequest::query()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'branch_id' => $branch->id,
        'source' => 'manual',
        'subject' => 'Weak cooling',
        'description' => 'The room stays warm.',
        'priority' => 'high',
        'status' => 'new',
        'preferred_date' => '2026-10-10',
        'preferred_time' => '14:00:00',
        'location' => 'Al Olaya',
        'requested_at' => Carbon::parse('2026-10-10 08:00:00', 'Asia/Riyadh'),
    ]);
    $scheduledRequest = ServiceRequest::query()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'branch_id' => $branch->id,
        'source' => 'manual',
        'subject' => 'Morning service',
        'description' => 'Scheduled visit.',
        'priority' => 'normal',
        'status' => 'scheduled',
        'preferred_date' => '2026-10-10',
        'requested_at' => Carbon::parse('2026-10-10 08:00:00', 'Asia/Riyadh'),
    ]);
    $morning = dispatchAppointment($organization, $customer, $first, 'scheduled', '2026-10-10 09:00:00', '2026-10-10 11:00:00', $branch->id, $scheduledRequest->id);
    dispatchAppointment($organization, $customer, $second, 'completed', '2026-10-10 09:00:00', '2026-10-10 10:00:00', $otherBranch->id);
    dispatchAppointment($otherOrganization, $otherCustomer, dispatchTechnician($otherOrganization), 'scheduled', '2026-10-10 09:00:00', '2026-10-10 10:00:00');

    actingInOrganization($owner, $organization)
        ->get('/dispatch?date=2026-10-10')
        ->assertOk()
        ->assertDontSee('Jeddah Villa')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dispatch/Index')
            ->where('board.date', '2026-10-10')
            ->where('board.working_minutes', 480)
            ->where('board.hours.work_start', '08:00')
            ->where('board.hours.work_end', '16:00')
            ->where('board.hours.working_minutes', 480)
            ->where('board.hours.grid_start', '07:00')
            ->where('board.hours.grid_end', '17:00')
            ->where('board.hours.grid_padding_minutes', 60)
            ->where('board.nearest_day', null)
            ->where('board.counts.total', 3)
            ->where('board.counts.completed', 1)
            ->where('board.counts.unassigned', 1)
            ->where('board.counts.overdue', 1)
            ->where('board.unassigned.0.title', 'Weak cooling')
            ->where('board.unassigned.0.area', 'Al Olaya')
            ->where('board.unassigned.0.reference', RecordReference::fromId('REQ', $openRequest->id))
            ->where('board.technicians.0.appointments.0.reference', RecordReference::fromId('APT', $morning->id))
            ->has('board.technicians', 2));

    actingInOrganization($owner, $organization)
        ->get('/dispatch?date=2026-10-10&branch_id='.$branch->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('board.counts.total', 2)
            ->where('board.counts.completed', 0)
            ->has('board.technicians', 1)
            ->where('board.technicians.0.name', 'Faisal Tech')
            ->where('board.technicians.0.utilization', 25)
            ->where('board.technicians.0.skills.0', 'hvac'));

    actingInOrganization($otherOwner, $otherOrganization)
        ->get('/dispatch?date=2026-10-10')
        ->assertOk()
        ->assertDontSee('Olaya Villa')
        ->assertDontSee('Weak cooling');
});

it('shows a technician only their own jobs', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-10 12:00:00', 'Asia/Riyadh'));

    [$technicianUser, $organization] = createMember(OrganizationRole::Technician);
    [$owner] = createMember(OrganizationRole::Owner, $organization);
    $customer = dispatchCustomer($organization, 'Own Customer');
    $otherCustomer = dispatchCustomer($organization, 'Hidden Customer');
    $own = dispatchTechnician($organization, $technicianUser);
    $other = dispatchTechnician($organization);
    dispatchAppointment($organization, $customer, $own, 'scheduled', '2026-10-10 09:00:00', '2026-10-10 11:00:00');
    dispatchAppointment($organization, $otherCustomer, $other, 'completed', '2026-10-10 09:00:00', '2026-10-10 10:00:00');
    ServiceRequest::query()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'source' => 'manual',
        'subject' => 'Unassigned for dispatch',
        'description' => 'Waiting for a technician.',
        'priority' => 'normal',
        'status' => 'new',
        'preferred_date' => '2026-10-10',
        'requested_at' => Carbon::parse('2026-10-10 08:00:00', 'Asia/Riyadh'),
    ]);

    actingInOrganization($technicianUser, $organization)
        ->get('/dispatch?date=2026-10-10')
        ->assertOk()
        ->assertDontSee('Hidden Customer')
        ->assertDontSee('Unassigned for dispatch')
        ->assertInertia(fn (Assert $page) => $page
            ->where('board.counts.total', 1)
            ->where('board.counts.completed', 0)
            ->where('board.counts.unassigned', 0)
            ->where('board.counts.overdue', 1)
            ->has('board.technicians', 1)
            ->where('board.technicians.0.id', $own->id)
            ->where('board.technicians.0.utilization', 25)
            ->where('board.unassigned', []));

    actingInOrganization($owner, $organization)
        ->get('/dispatch?date=2026-10-10')
        ->assertInertia(fn (Assert $page) => $page
            ->where('board.counts.unassigned', 1)
            ->has('board.technicians', 2));
});

it('hides other technicians from a customer service user', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-10 12:00:00', 'Asia/Riyadh'));

    [$agent, $organization] = createMember(OrganizationRole::CustomerService);
    $customer = dispatchCustomer($organization, 'Restricted Villa');
    $technician = dispatchTechnician($organization);
    dispatchAppointment($organization, $customer, $technician, 'scheduled', '2026-10-10 09:00:00', '2026-10-10 10:00:00');

    actingInOrganization($agent, $organization)
        ->get('/dispatch?date=2026-10-10')
        ->assertOk()
        ->assertDontSee('Restricted Villa')
        ->assertInertia(fn (Assert $page) => $page
            ->where('board.technicians', [])
            ->where('board.unassigned', [])
            ->where('board.counts.total', 0)
            ->where('board.nearest_day', null));
});

it('points an empty day at the nearest appointment', function () {
    Carbon::setTestNow(Carbon::parse('2026-10-10 12:00:00', 'Asia/Riyadh'));

    [$owner, $organization] = createMember();
    $customer = dispatchCustomer($organization, 'Later Villa');
    $technician = dispatchTechnician($organization);
    $branch = Branch::factory()->create(['organization_id' => $organization->id]);
    $otherBranch = Branch::factory()->create(['organization_id' => $organization->id]);
    dispatchAppointment($organization, $customer, $technician, 'scheduled', '2026-10-08 09:00:00', '2026-10-08 10:00:00', $branch->id);
    dispatchAppointment($organization, $customer, $technician, 'scheduled', '2026-10-12 09:00:00', '2026-10-12 10:00:00', $branch->id);
    dispatchAppointment($organization, $customer, $technician, 'scheduled', '2026-10-11 09:00:00', '2026-10-11 10:00:00', $otherBranch->id);

    actingInOrganization($owner, $organization)
        ->get('/dispatch?date=2026-10-10&branch_id='.$branch->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('board.counts.total', 0)
            ->where('board.nearest_day', '2026-10-12'));
});
