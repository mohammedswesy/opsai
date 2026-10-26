<?php

use App\Enums\OrganizationRole;
use App\Models\Activity;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\ServiceRequest;
use App\Models\Technician;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\RecordReference;
use Carbon\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-10-10 12:00:00', 'Asia/Riyadh'));
});

function payloadRequest(Organization $organization, Customer $customer, string $subject, string $status = 'new', ?string $branchId = null): ServiceRequest
{
    return ServiceRequest::query()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'branch_id' => $branchId,
        'source' => 'manual',
        'subject' => $subject,
        'description' => 'Payload test.',
        'priority' => 'normal',
        'status' => $status,
        'location' => 'Al Olaya',
        'requested_at' => now(),
    ]);
}

function payloadWorkOrder(Organization $organization, Customer $customer, string $title, ?Technician $technician, string $status = 'assigned', array $extra = []): WorkOrder
{
    $request = payloadRequest($organization, $customer, $title, 'scheduled', $extra['branch_id'] ?? null);

    return WorkOrder::query()->create([
        'organization_id' => $organization->id,
        'customer_id' => $customer->id,
        'service_request_id' => $request->id,
        'technician_id' => $technician?->id,
        'title' => $title,
        'priority' => 'high',
        'status' => $status,
        ...$extra,
    ]);
}

it('adds unassigned, overdue and today counts to the owner dashboard inside the organization', function () {
    [$owner, $organization] = createMember();
    [$otherOwner, $otherOrganization] = createMember();
    $branch = Branch::factory()->create(['organization_id' => $organization->id]);
    $customer = dispatchCustomer($organization, 'Olaya Villa');
    $otherCustomer = dispatchCustomer($otherOrganization, 'Jeddah Villa');
    $technician = dispatchTechnician($organization, User::factory()->create(['name' => 'Faisal Tech']));
    $otherTechnician = dispatchTechnician($otherOrganization);

    payloadRequest($organization, $customer, 'Waiting request', 'new', $branch->id);
    payloadRequest($organization, $customer, 'Qualified request', 'qualified');
    $booked = payloadRequest($organization, $customer, 'Booked request', 'qualified');
    dispatchAppointment($organization, $customer, $technician, 'scheduled', '2026-10-11 09:00:00', '2026-10-11 10:00:00', null, $booked->id);
    payloadWorkOrder($organization, $customer, 'No technician yet', null);
    payloadWorkOrder($organization, $customer, 'Cancelled without technician', null, 'cancelled');
    payloadRequest($otherOrganization, $otherCustomer, 'Other org request');
    payloadWorkOrder($otherOrganization, $otherCustomer, 'Other org order', null);

    dispatchAppointment($organization, $customer, $technician, 'scheduled', '2026-10-10 08:00:00', '2026-10-10 09:00:00', $branch->id);
    dispatchAppointment($organization, $customer, $technician, 'completed', '2026-10-10 09:00:00', '2026-10-10 10:00:00');
    $next = dispatchAppointment($organization, $customer, $technician, 'confirmed', '2026-10-10 14:00:00', '2026-10-10 15:00:00', $branch->id);
    dispatchAppointment($organization, $customer, $technician, 'scheduled', '2026-10-10 16:00:00', '2026-10-10 17:00:00');
    dispatchAppointment($organization, $customer, $technician, 'cancelled', '2026-10-10 13:00:00', '2026-10-10 14:00:00');
    dispatchAppointment($otherOrganization, $otherCustomer, $otherTechnician, 'scheduled', '2026-10-10 08:00:00', '2026-10-10 09:00:00');
    dispatchAppointment($otherOrganization, $otherCustomer, $otherTechnician, 'scheduled', '2026-10-10 13:00:00', '2026-10-10 14:00:00');

    actingInOrganization($owner, $organization)
        ->get('/')
        ->assertOk()
        ->assertDontSee('Jeddah Villa')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('metrics.unassigned_jobs', 3)
            ->where('metrics.overdue_appointments', 1)
            ->has('upcoming_today', 2)
            ->where('upcoming_today.0.id', $next->id)
            ->where('upcoming_today.0.reference', RecordReference::fromId('APT', $next->id))
            ->where('upcoming_today.0.type', 'appointment')
            ->where('upcoming_today.0.customer_name', 'Olaya Villa')
            ->where('upcoming_today.0.technician_name', 'Faisal Tech')
            ->where('upcoming_today.0.start', '2026-10-10T14:00:00+03:00')
            ->where('upcoming_today.0.status', 'confirmed')
            ->where('my_day', null)
            ->has('metrics.open_requests'));

    actingInOrganization($owner, $organization)
        ->get('/?branch_id='.$branch->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.unassigned_jobs', 1)
            ->where('metrics.overdue_appointments', 1)
            ->has('upcoming_today', 1));

    actingInOrganization($otherOwner, $otherOrganization)
        ->get('/')
        ->assertOk()
        ->assertDontSee('Olaya Villa')
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.unassigned_jobs', 2)
            ->where('metrics.overdue_appointments', 1)
            ->has('upcoming_today', 1));
});

it('limits the dashboard schedule to five appointments', function () {
    [$owner, $organization] = createMember();
    $customer = dispatchCustomer($organization, 'Busy Villa');
    $technician = dispatchTechnician($organization);

    foreach (range(13, 19) as $hour) {
        dispatchAppointment($organization, $customer, $technician, 'scheduled', "2026-10-10 {$hour}:00:00", "2026-10-10 {$hour}:30:00");
    }

    actingInOrganization($owner, $organization)
        ->get('/')
        ->assertInertia(fn (Assert $page) => $page
            ->has('upcoming_today', 5)
            ->where('upcoming_today.0.start', '2026-10-10T13:00:00+03:00'));
});

it('gives a technician only their own day on the dashboard', function () {
    [$technicianUser, $organization] = createMember(OrganizationRole::Technician);
    $customer = dispatchCustomer($organization, 'Own Customer');
    $otherCustomer = dispatchCustomer($organization, 'Hidden Customer');
    $own = dispatchTechnician($organization, $technicianUser);
    $other = dispatchTechnician($organization);

    $morning = dispatchAppointment($organization, $customer, $own, 'scheduled', '2026-10-10 08:00:00', '2026-10-10 09:00:00');
    $afternoon = dispatchAppointment($organization, $customer, $own, 'confirmed', '2026-10-10 14:00:00', '2026-10-10 15:00:00');
    dispatchAppointment($organization, $customer, $own, 'scheduled', '2026-10-11 09:00:00', '2026-10-11 10:00:00');
    dispatchAppointment($organization, $otherCustomer, $other, 'scheduled', '2026-10-10 08:00:00', '2026-10-10 09:00:00');
    dispatchAppointment($organization, $otherCustomer, $other, 'scheduled', '2026-10-10 13:00:00', '2026-10-10 14:00:00');
    $walkIn = payloadWorkOrder($organization, $customer, 'Walk-in repair', $own, 'in_progress', [
        'started_at' => Carbon::parse('2026-10-10 10:00:00', 'Asia/Riyadh')->utc(),
    ]);
    $linked = payloadWorkOrder($organization, $customer, 'Linked to afternoon', $own, 'assigned', ['appointment_id' => $afternoon->id]);
    payloadWorkOrder($organization, $otherCustomer, 'Someone else', $other, 'in_progress', [
        'started_at' => Carbon::parse('2026-10-10 10:00:00', 'Asia/Riyadh')->utc(),
    ]);
    payloadWorkOrder($organization, $otherCustomer, 'Nobody yet', null);
    $yesterday = dispatchAppointment($organization, $customer, $own, 'completed', '2026-10-09 09:00:00', '2026-10-09 10:00:00');
    $carried = payloadWorkOrder($organization, $customer, 'Carried over', $own, 'completed', [
        'appointment_id' => $yesterday->id,
        'started_at' => Carbon::parse('2026-10-10 11:00:00', 'Asia/Riyadh')->utc(),
        'completed_at' => Carbon::parse('2026-10-10 11:30:00', 'Asia/Riyadh')->utc(),
    ]);

    actingInOrganization($technicianUser, $organization)
        ->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.unassigned_jobs', null)
            ->where('metrics.overdue_appointments', 1)
            ->has('upcoming_today', 1)
            ->where('upcoming_today.0.id', $afternoon->id)
            ->has('my_day', 4)
            ->where('my_day.0.id', $morning->id)
            ->where('my_day.1.id', $walkIn->id)
            ->where('my_day.1.type', 'work_order')
            ->where('my_day.1.reference', RecordReference::fromId('WO', $walkIn->id))
            ->where('my_day.1.status', 'in_progress')
            ->where('my_day.1.location', 'Al Olaya')
            ->where('my_day.2.id', $carried->id)
            ->where('my_day.2.start', '2026-10-10T11:00:00+03:00')
            ->where('my_day.2.end', '2026-10-10T11:30:00+03:00')
            ->where('my_day.3.id', $afternoon->id)
            ->where('my_day.3.type', 'appointment')
            ->where('my_day.3.customer_name', 'Own Customer')
            ->where('my_day', fn ($items) => collect($items)->doesntContain('id', $linked->id)
                && collect($items)->doesntContain('customer_name', 'Hidden Customer')));
});

it('hides the schedule from roles without appointment access and my day from managers', function () {
    [$accountant, $organization] = createMember(OrganizationRole::Accountant);
    [$dispatcher] = createMember(OrganizationRole::Dispatcher, $organization);
    $customer = dispatchCustomer($organization, 'Some Villa');
    $technician = dispatchTechnician($organization, $dispatcher);
    dispatchAppointment($organization, $customer, $technician, 'scheduled', '2026-10-10 14:00:00', '2026-10-10 15:00:00');

    actingInOrganization($accountant, $organization)
        ->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.unassigned_jobs', null)
            ->where('metrics.overdue_appointments', null)
            ->where('upcoming_today', null)
            ->where('my_day', null));

    actingInOrganization($dispatcher, $organization)
        ->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.unassigned_jobs', 0)
            ->has('upcoming_today', 1)
            ->where('my_day', null));
});

it('lists work order activities with actor and status change', function () {
    [$owner, $organization] = createMember();
    [$otherOwner, $otherOrganization] = createMember();
    [$technicianUser] = createMember(OrganizationRole::Technician, $organization);
    $owner->update(['name' => 'Riyadh Owner']);
    $customer = dispatchCustomer($organization, 'Olaya Villa');
    $order = payloadWorkOrder($organization, $customer, 'Fix the unit', dispatchTechnician($organization));
    $requestActivity = Activity::query()->create([
        'organization_id' => $organization->id,
        'actor_id' => $owner->id,
        'subject_type' => (new ServiceRequest)->getMorphClass(),
        'subject_id' => $order->service_request_id,
        'action' => 'request.created',
    ]);
    Activity::query()->create([
        'organization_id' => $organization->id,
        'actor_id' => null,
        'subject_type' => $order->getMorphClass(),
        'subject_id' => $order->id,
        'action' => 'work_order.created',
        'description' => 'Fix the unit',
        'properties' => ['actor_name' => null],
    ]);
    $changed = Activity::query()->create([
        'organization_id' => $organization->id,
        'actor_id' => $owner->id,
        'subject_type' => $order->getMorphClass(),
        'subject_id' => $order->id,
        'action' => 'work_order.status_changed',
        'description' => 'accepted',
        'properties' => ['from' => 'assigned', 'to' => 'accepted'],
    ]);

    actingInOrganization($owner, $organization)
        ->get('/work-orders/'.$order->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('WorkOrders/Show')
            ->has('workOrder')
            ->has('activities', 2)
            ->where('activities.0.id', $changed->id)
            ->where('activities.0.action', 'work_order.status_changed')
            ->where('activities.0.actor', ['id' => $owner->id, 'name' => 'Riyadh Owner'])
            ->where('activities.0.from', 'assigned')
            ->where('activities.0.to', 'accepted')
            ->where('activities.0.created_at', '2026-10-10T09:00:00+00:00')
            ->where('activities.1.actor', null)
            ->where('activities.1.from', null)
            ->where('activities', fn ($items) => collect($items)->doesntContain('id', $requestActivity->id)));

    actingInOrganization($technicianUser, $organization)->get('/work-orders/'.$order->id)->assertForbidden();
    actingInOrganization($otherOwner, $otherOrganization)->get('/work-orders/'.$order->id)->assertNotFound();
});

it('adds the customer name to payment rows inside the organization', function () {
    [$owner, $organization] = createMember();
    [$otherOwner, $otherOrganization] = createMember();
    [$technicianUser] = createMember(OrganizationRole::Technician, $organization);
    $customer = dispatchCustomer($organization, 'Olaya Villa');
    $otherCustomer = dispatchCustomer($otherOrganization, 'Jeddah Villa');

    foreach ([[$owner, $organization, $customer, 'R-OWN'], [$otherOwner, $otherOrganization, $otherCustomer, 'R-OTHER']] as [$user, $org, $client, $reference]) {
        actingInOrganization($user, $org)->post('/invoices', [
            'customer_id' => $client->id,
            'discount' => '0',
            'items' => [['description' => 'AC Maintenance', 'quantity' => '1', 'unit_price' => '100']],
        ])->assertRedirect();
        $invoice = Invoice::withoutGlobalScopes()->where('customer_id', $client->id)->firstOrFail();
        actingInOrganization($user, $org)->post('/invoices/'.$invoice->id.'/issue')->assertRedirect();
        actingInOrganization($user, $org)->post('/payments', [
            'invoice_id' => $invoice->id,
            'amount' => '50',
            'method' => 'cash',
            'reference' => $reference,
        ])->assertRedirect();
    }
    $customer->delete();

    actingInOrganization($owner, $organization)
        ->get('/payments')
        ->assertOk()
        ->assertDontSee('Jeddah Villa')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Payments/Index')
            ->has('payments.data', 1)
            ->where('payments.data.0.reference', 'R-OWN')
            ->where('payments.data.0.customer_name', 'Olaya Villa')
            ->has('payments.data.0.invoice.invoice_number'));

    actingInOrganization($technicianUser, $organization)->get('/payments')->assertForbidden();
});

it('adds the organization letterhead to the invoice page', function () {
    [$owner, $organization] = createMember();
    [$otherOwner, $otherOrganization] = createMember();
    [$technicianUser] = createMember(OrganizationRole::Technician, $organization);
    $organization->update(['name' => 'Riyadh Cooling', 'legal_name' => null, 'tax_number' => '300000000000003', 'address' => 'King Fahd Road', 'city' => 'Riyadh']);
    $customer = dispatchCustomer($organization, 'Olaya Villa');

    actingInOrganization($owner, $organization)->post('/invoices', [
        'customer_id' => $customer->id,
        'discount' => '0',
        'items' => [['description' => 'AC Maintenance', 'quantity' => '1', 'unit_price' => '100']],
    ])->assertRedirect();
    $invoice = Invoice::withoutGlobalScopes()->firstOrFail();

    actingInOrganization($owner, $organization)
        ->get('/invoices/'.$invoice->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Invoices/Show')
            ->where('letterhead', [
                'name' => 'Riyadh Cooling',
                'legal_name' => null,
                'tax_number' => '300000000000003',
                'address' => 'King Fahd Road',
                'city' => 'Riyadh',
                'phone' => $organization->phone,
                'email' => $organization->email,
            ])
            ->has('invoice.invoice_number'));

    actingInOrganization($technicianUser, $organization)->get('/invoices/'.$invoice->id)->assertForbidden();
    actingInOrganization($otherOwner, $otherOrganization)->get('/invoices/'.$invoice->id)->assertNotFound();
});

it('adds the schedule and dispatch utilisation to the technician page', function () {
    [$owner, $organization] = createMember();
    [$otherOwner, $otherOrganization] = createMember();
    [$technicianUser] = createMember(OrganizationRole::Technician, $organization);
    $customer = dispatchCustomer($organization, 'Olaya Villa');
    $technician = dispatchTechnician($organization, User::factory()->create(['name' => 'Faisal Tech']));
    $colleague = dispatchTechnician($organization);

    dispatchAppointment($organization, $customer, $technician, 'completed', '2026-10-10 08:00:00', '2026-10-10 10:00:00');
    $next = dispatchAppointment($organization, $customer, $technician, 'scheduled', '2026-10-10 14:00:00', '2026-10-10 15:00:00');
    $tomorrow = dispatchAppointment($organization, $customer, $technician, 'confirmed', '2026-10-11 09:00:00', '2026-10-11 10:00:00');
    dispatchAppointment($organization, $customer, $technician, 'cancelled', '2026-10-10 15:00:00', '2026-10-10 16:00:00');
    dispatchAppointment($organization, $customer, $colleague, 'scheduled', '2026-10-10 13:00:00', '2026-10-10 14:00:00');
    $active = payloadWorkOrder($organization, $customer, 'Active job', $technician, 'on_the_way');
    payloadWorkOrder($organization, $customer, 'Finished job', $technician, 'completed');
    payloadWorkOrder($organization, $customer, 'Colleague job', $colleague, 'in_progress');

    actingInOrganization($owner, $organization)
        ->get('/technicians/'.$technician->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Technicians/Show')
            ->has('technician')
            ->has('schedule.upcoming_appointments', 2)
            ->where('schedule.upcoming_appointments.0.id', $next->id)
            ->where('schedule.upcoming_appointments.1.id', $tomorrow->id)
            ->has('schedule.active_work_orders', 1)
            ->where('schedule.active_work_orders.0.id', $active->id)
            ->where('schedule.active_work_orders.0.status', 'on_the_way')
            ->where('schedule.utilization', [
                'date' => '2026-10-10',
                'booked_minutes' => 180,
                'working_minutes' => 480,
                'percent' => 38,
            ]));

    actingInOrganization($owner, $organization)
        ->get('/dispatch?date=2026-10-10')
        ->assertInertia(fn (Assert $page) => $page->where('board.technicians', fn ($rows) => collect($rows)->firstWhere('id', $technician->id)['utilization'] === 38));

    actingInOrganization($technicianUser, $organization)->get('/technicians/'.$technician->id)->assertForbidden();
    actingInOrganization($otherOwner, $otherOrganization)->get('/technicians/'.$technician->id)->assertNotFound();
});

it('marks the current organization in the switcher list', function () {
    [$user, $first] = createMember();
    $second = Organization::factory()->create(['name' => 'Second Org']);
    $second->members()->attach($user->id, ['role' => OrganizationRole::Technician->value]);
    $outside = Organization::factory()->create(['name' => 'Not Mine']);

    actingInOrganization($user, $second)
        ->get('/organizations')
        ->assertOk()
        ->assertDontSee('Not Mine')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Organizations/Index')
            ->has('organizations', 2)
            ->where('organizations', fn ($rows) => collect($rows)->firstWhere('id', $second->id)['is_current'] === true
                && collect($rows)->firstWhere('id', $first->id)['is_current'] === false
                && collect($rows)->doesntContain('id', $outside->id)));
});
