<?php

namespace Database\Seeders;

use App\Enums\OrganizationRole;
use App\Enums\WorkOrderStatus;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\Service;
use App\Models\Technician;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Finance\InvoiceLifecycleService;
use App\Services\Finance\PaymentService;
use App\Services\Operations\AppointmentService;
use App\Services\Operations\CatalogService;
use App\Services\Operations\ServiceRequestService;
use App\Services\Operations\WorkOrderService;
use App\Support\OrganizationContext;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class FinanceSeeder extends Seeder
{
    public function run(): void
    {
        $riyadh = Organization::query()->where('slug', 'riyadh-cooling-services')->firstOrFail();
        $jeddah = Organization::query()->where('slug', 'jeddah-maintenance-pro')->firstOrFail();

        $this->seedOrganization(
            $riyadh,
            Branch::withoutGlobalScope('organization')->where('code', 'RYD-MAIN')->firstOrFail(),
            User::query()->where('email', 'owner@riyadh-cooling.test')->firstOrFail(),
            Customer::withoutGlobalScope('organization')->where('name', 'North Wind Villa')->firstOrFail(),
            'Seeded completed visit',
            '1000',
        );

        $this->seedOrganization(
            $jeddah,
            Branch::withoutGlobalScope('organization')->where('code', 'JED-MAIN')->firstOrFail(),
            User::query()->where('email', 'owner@jeddah-maintenance.test')->firstOrFail(),
            Customer::withoutGlobalScope('organization')->where('name', 'Harbor Office Demo')->firstOrFail(),
            'Harbor billed visit',
            '200',
        );

        Auth::logout();
        OrganizationContext::forget();
    }

    private function seedOrganization(Organization $organization, Branch $branch, User $owner, Customer $customer, string $openTitle, string $openPrice): void
    {
        Auth::login($owner);
        OrganizationContext::set($organization, OrganizationRole::Owner);

        $service = app(CatalogService::class)->create([
            'name' => $openTitle.' service',
            'category' => 'HVAC',
            'base_price' => $openPrice,
            'estimated_duration' => 60,
            'status' => 'active',
        ]);

        $this->completedWorkOrder($organization, $branch, $customer, $service, $openTitle);

        $invoices = app(InvoiceLifecycleService::class);
        $payments = app(PaymentService::class);
        $due = Carbon::now($organization->timezone)->addDays(14)->toDateString();
        $past = Carbon::now($organization->timezone)->subDays(3)->toDateString();
        $items = [[
            'service_id' => $service->id,
            'description' => $openTitle,
            'quantity' => '1',
            'unit_price' => $openPrice,
        ]];

        $paid = $invoices->issue($invoices->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'due_date' => $due,
            'discount' => '0',
            'notes' => 'Seeded paid invoice',
        ], $items));
        $payment = $payments->record($paid, [
            'amount' => $paid->total,
            'method' => 'bank_transfer',
            'reference' => 'SEED-PAID',
        ]);
        $payment->paid_at = now()->subDay();
        $payment->save();

        $partial = $invoices->issue($invoices->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'due_date' => $due,
            'discount' => '0',
            'notes' => 'Seeded partial invoice',
        ], $items));
        $payment = $payments->record($partial, [
            'amount' => '100.00',
            'method' => 'cash',
            'reference' => 'SEED-PARTIAL',
        ]);
        $payment->paid_at = now()->subDay();
        $payment->save();

        $invoices->issue($invoices->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'due_date' => $past,
            'discount' => '0',
            'notes' => 'Seeded overdue invoice',
        ], $items));

        $invoices->markOverdue();
    }

    private function completedWorkOrder(Organization $organization, Branch $branch, Customer $customer, Service $service, string $title): WorkOrder
    {
        $request = app(ServiceRequestService::class)->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'source' => 'manual',
            'subject' => $title,
            'description' => 'Completed visit ready for invoicing.',
            'priority' => 'normal',
            'status' => 'new',
            'requested_at' => now(),
        ]);

        $technicianId = Technician::query()->where('branch_id', $branch->id)->value('id');
        $start = Carbon::now($organization->timezone)->subDay()->setTime(11, 0);
        $appointment = app(AppointmentService::class)->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'technician_id' => $technicianId,
            'service_request_id' => $request->id,
            'scheduled_start' => $start->copy()->utc(),
            'scheduled_end' => $start->copy()->addHour()->utc(),
            'status' => 'scheduled',
        ]);

        $order = app(WorkOrderService::class)->create([
            'branch_id' => $branch->id,
            'customer_id' => $customer->id,
            'service_request_id' => $request->id,
            'appointment_id' => $appointment->id,
            'technician_id' => $technicianId,
            'title' => $title,
            'priority' => 'normal',
        ]);

        foreach ([
            WorkOrderStatus::Accepted,
            WorkOrderStatus::OnTheWay,
            WorkOrderStatus::Arrived,
            WorkOrderStatus::InProgress,
            WorkOrderStatus::Completed,
        ] as $status) {
            $order = app(WorkOrderService::class)->transition($order, $status, 'Seeded completion');
        }

        return $order;
    }
}
