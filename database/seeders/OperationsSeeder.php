<?php

namespace Database\Seeders;

use App\Enums\OrganizationRole;
use App\Models\Branch;
use App\Models\Integration;
use App\Models\Organization;
use App\Models\User;
use App\Services\Operations\AppointmentService;
use App\Services\Operations\CatalogService;
use App\Services\Operations\CustomerService;
use App\Services\Operations\ServiceRequestService;
use App\Services\Operations\TaskService;
use App\Services\Operations\TechnicianService;
use App\Services\Operations\WorkOrderService;
use App\Support\OrganizationContext;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class OperationsSeeder extends Seeder
{
    public function run(): void
    {
        $riyadh = Organization::query()->where('slug', 'riyadh-cooling-services')->firstOrFail();
        $jeddah = Organization::query()->where('slug', 'jeddah-maintenance-pro')->firstOrFail();
        $riyadhBranch = Branch::withoutGlobalScope('organization')->where('code', 'RYD-MAIN')->firstOrFail();
        $jeddahBranch = Branch::withoutGlobalScope('organization')->where('code', 'JED-MAIN')->firstOrFail();

        $this->seedRiyadh($riyadh, $riyadhBranch);
        $this->seedJeddah($jeddah, $jeddahBranch);

        Auth::logout();
        OrganizationContext::forget();
    }

    private function seedRiyadh(Organization $organization, Branch $branch): void
    {
        $owner = User::query()->where('email', 'owner@riyadh-cooling.test')->firstOrFail();
        $technicianUser = User::query()->where('email', 'technician@riyadh-cooling.test')->firstOrFail();
        $this->act($organization, $owner);

        $customers = app(CustomerService::class);
        $catalog = app(CatalogService::class);
        $villa = $customers->create([
            'branch_id' => $branch->id,
            'type' => 'individual',
            'name' => 'North Wind Villa',
            'phone' => '0550001001',
            'city' => 'Riyadh',
            'country' => 'SA',
            'status' => 'active',
            'source' => 'phone',
        ]);
        $customers->create([
            'branch_id' => $branch->id,
            'type' => 'company',
            'name' => 'Al Noor Trading Demo',
            'company_name' => 'Al Noor Trading Demo',
            'phone' => '0550001002',
            'city' => 'Riyadh',
            'country' => 'SA',
            'status' => 'active',
            'source' => 'manual',
        ]);

        $maintenance = $catalog->create([
            'name' => 'AC Maintenance',
            'category' => 'HVAC',
            'description' => 'Filter, coil, and drain check.',
            'base_price' => 250,
            'estimated_duration' => 90,
            'status' => 'active',
        ]);
        $catalog->create(['name' => 'AC Installation', 'category' => 'HVAC', 'base_price' => 1800, 'estimated_duration' => 240, 'status' => 'active']);
        $catalog->create(['name' => 'Electrical Repair', 'category' => 'Electrical', 'base_price' => 180, 'estimated_duration' => 60, 'status' => 'active']);
        $catalog->create(['name' => 'Plumbing Visit', 'category' => 'Plumbing', 'base_price' => 160, 'estimated_duration' => 60, 'status' => 'active']);
        $catalog->create(['name' => 'Deep Cleaning', 'category' => 'Cleaning', 'base_price' => 320, 'estimated_duration' => 180, 'status' => 'active']);

        $technician = app(TechnicianService::class)->create([
            'user_id' => $technicianUser->id,
            'branch_id' => $branch->id,
            'employee_code' => 'TEC-100',
            'availability_status' => 'available',
            'hourly_rate' => 75,
            'status' => 'active',
        ], [
            ['skill' => 'hvac', 'service_id' => $maintenance->id],
            ['skill' => 'electrical'],
        ]);

        $request = app(ServiceRequestService::class)->create([
            'branch_id' => $branch->id,
            'customer_id' => $villa->id,
            'service_id' => $maintenance->id,
            'source' => 'phone',
            'subject' => 'Villa AC not cooling',
            'description' => 'The living room split unit runs but the air stays warm.',
            'priority' => 'high',
            'status' => 'new',
            'requested_at' => now(),
            'location' => 'North Wind Villa, Riyadh',
        ]);

        $start = Carbon::now($organization->timezone)->setTime(15, 0);
        $appointment = app(AppointmentService::class)->create([
            'branch_id' => $branch->id,
            'customer_id' => $villa->id,
            'technician_id' => $technician->id,
            'service_request_id' => $request->id,
            'scheduled_start' => $start->copy()->utc(),
            'scheduled_end' => $start->copy()->addHour()->utc(),
            'status' => 'scheduled',
            'notes' => 'Customer will be on site.',
        ]);

        app(WorkOrderService::class)->create([
            'branch_id' => $branch->id,
            'customer_id' => $villa->id,
            'service_request_id' => $request->id,
            'appointment_id' => $appointment->id,
            'technician_id' => $technician->id,
            'title' => 'Inspect villa split unit',
            'description' => 'Check filters, gas, and drain.',
            'priority' => 'high',
        ]);

        app(TaskService::class)->create([
            'branch_id' => $branch->id,
            'assigned_to' => $owner->id,
            'title' => 'Confirm spare filters are in the van',
            'priority' => 'normal',
            'status' => 'todo',
            'due_at' => $start->copy()->subHour()->utc(),
        ]);

        Integration::query()->create([
            'provider' => 'fake',
            'type' => 'whatsapp',
            'status' => 'active',
            'credentials' => ['access_token' => 'seed-token-not-real'],
            'metadata' => [
                'phone_number_id' => '100000000000001',
                'display_phone' => '+966110000000',
            ],
        ]);
    }

    private function seedJeddah(Organization $organization, Branch $branch): void
    {
        $owner = User::query()->where('email', 'owner@jeddah-maintenance.test')->firstOrFail();
        $this->act($organization, $owner);

        $customer = app(CustomerService::class)->create([
            'branch_id' => $branch->id,
            'type' => 'company',
            'name' => 'Harbor Office Demo',
            'company_name' => 'Harbor Office Demo',
            'phone' => '0550002001',
            'city' => 'Jeddah',
            'country' => 'SA',
            'status' => 'active',
        ]);
        $service = app(CatalogService::class)->create([
            'name' => 'Harbor Coil Check',
            'category' => 'HVAC',
            'base_price' => 220,
            'estimated_duration' => 90,
            'status' => 'active',
        ]);
        $request = app(ServiceRequestService::class)->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'source' => 'manual',
            'subject' => 'Office thermostat check',
            'description' => 'The meeting room stays warmer than the setpoint.',
            'priority' => 'normal',
            'status' => 'new',
            'requested_at' => now(),
        ]);
        $technician = app(TechnicianService::class)->create([
            'user_id' => $owner->id,
            'branch_id' => $branch->id,
            'employee_code' => 'JED-TEC',
            'availability_status' => 'available',
            'status' => 'active',
        ], [
            ['skill' => 'hvac', 'service_id' => $service->id],
        ]);
        $start = Carbon::now($organization->timezone)->addDay()->setTime(10, 0);
        $appointment = app(AppointmentService::class)->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'technician_id' => $technician->id,
            'service_request_id' => $request->id,
            'scheduled_start' => $start->copy()->utc(),
            'scheduled_end' => $start->copy()->addHour()->utc(),
            'status' => 'scheduled',
        ]);
        app(WorkOrderService::class)->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'service_request_id' => $request->id,
            'appointment_id' => $appointment->id,
            'technician_id' => $technician->id,
            'title' => 'Harbor thermostat visit',
            'priority' => 'normal',
        ]);

        Integration::query()->create([
            'provider' => 'fake',
            'type' => 'whatsapp',
            'status' => 'active',
            'credentials' => ['access_token' => 'seed-token-jeddah'],
            'metadata' => [
                'phone_number_id' => '100000000000002',
                'display_phone' => '+971400000000',
            ],
        ]);
    }

    private function act(Organization $organization, User $user): void
    {
        Auth::login($user);
        OrganizationContext::set($organization, OrganizationRole::Owner);
    }
}
