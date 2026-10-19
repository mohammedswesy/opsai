<?php

use App\Enums\InvoiceStatus;
use App\Enums\OrganizationRole;
use App\Enums\WorkOrderStatus;
use App\Models\Activity;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\WorkOrder;
use App\Services\Finance\InvoiceCalculationService;
use App\Support\OrganizationContext;
use Illuminate\Support\Facades\Auth;

it('calculates tax and money without floating point drift', function () {
    $result = app(InvoiceCalculationService::class)->calculate([
        ['description' => 'Visit', 'quantity' => '1', 'unit_price' => '1000'],
        ['description' => 'Parts', 'quantity' => '3', 'unit_price' => '10.10'],
    ], '0', '15');

    expect($result['subtotal'])->toBe('1030.30')
        ->and($result['tax'])->toBe('154.55')
        ->and($result['total'])->toBe('1184.85');
});

it('creates a draft invoice with server totals and an organization number', function () {
    [$owner, $organization, $customer] = financeContext();

    actingInOrganization($owner, $organization)->post('/invoices', invoicePayload($customer, [
        'organization_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
        'invoice_number' => 'CLIENT-1',
        'status' => 'paid',
        'total' => '1.00',
        'amount_paid' => '1.00',
        'amount_due' => '0.00',
        'currency' => 'USD',
    ]))->assertRedirect();

    $invoice = Invoice::query()->firstOrFail();
    expect($invoice->organization_id)->toBe($organization->id)
        ->and($invoice->invoice_number)->toStartWith('INV-')
        ->and($invoice->status)->toBe(InvoiceStatus::Draft)
        ->and($invoice->currency)->toBe('SAR')
        ->and($invoice->subtotal)->toBe('1000.00')
        ->and($invoice->tax)->toBe('150.00')
        ->and($invoice->total)->toBe('1150.00')
        ->and($invoice->amount_paid)->toBe('0.00')
        ->and($invoice->amount_due)->toBe('1150.00');

    expect(Activity::query()->where('action', 'invoice.created')->exists())->toBeTrue();
});

it('keeps invoice numbers unique inside an organization', function () {
    [$owner, $organization, $customer] = financeContext();

    foreach (range(1, 3) as $ignored) {
        actingInOrganization($owner, $organization)->post('/invoices', invoicePayload($customer))->assertRedirect();
    }

    $numbers = Invoice::query()->pluck('invoice_number');
    expect($numbers)->toHaveCount(3)
        ->and($numbers->unique())->toHaveCount(3)
        ->and($numbers->last())->toBe($numbers->first() === 'INV-'.now($organization->timezone)->year.'-000001'
            ? 'INV-'.now($organization->timezone)->year.'-000003'
            : $numbers->last());
});

it('blocks a technician and a dispatcher from creating invoices', function (OrganizationRole $role) {
    [$user, $organization, $customer] = financeContext($role);

    actingInOrganization($user, $organization)->post('/invoices', invoicePayload($customer))->assertForbidden();
})->with([
    OrganizationRole::Technician,
    OrganizationRole::Dispatcher,
    OrganizationRole::CustomerService,
]);

it('lets an accountant manage invoices and payments', function () {
    [$accountant, $organization, $customer] = financeContext(OrganizationRole::Accountant);

    actingInOrganization($accountant, $organization)->post('/invoices', invoicePayload($customer))->assertRedirect();
    $invoice = Invoice::query()->firstOrFail();
    actingInOrganization($accountant, $organization)->post('/invoices/'.$invoice->id.'/issue')->assertRedirect();
    actingInOrganization($accountant, $organization)->post('/payments', [
        'invoice_id' => $invoice->id,
        'amount' => '500',
        'method' => 'cash',
        'status' => 'refunded',
        'paid_at' => '2000-01-01 00:00:00',
        'created_by' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
        'organization_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV',
    ])->assertRedirect();

    $invoice->refresh();
    expect($invoice->status)->toBe(InvoiceStatus::PartiallyPaid)
        ->and($invoice->amount_paid)->toBe('500.00')
        ->and($invoice->amount_due)->toBe('650.00')
        ->and($invoice->payments()->first()->status->value)->toBe('completed')
        ->and($invoice->payments()->first()->created_by)->toBe($accountant->id);
});

it('hides another organization invoice and payment', function () {
    [$owner, $organization, $customer] = financeContext();
    [$other, $otherOrganization] = createMember();

    actingInOrganization($owner, $organization)->post('/invoices', invoicePayload($customer))->assertRedirect();
    $invoice = Invoice::query()->firstOrFail();

    actingInOrganization($other, $otherOrganization)->get('/invoices/'.$invoice->id)->assertNotFound();
    actingInOrganization($other, $otherOrganization)->post('/payments', [
        'invoice_id' => $invoice->id,
        'amount' => '10',
        'method' => 'cash',
    ])->assertInvalid(['invoice_id']);
});

it('issues, partially pays, then pays an invoice', function () {
    [$owner, $organization, $customer] = financeContext();
    actingInOrganization($owner, $organization)->post('/invoices', invoicePayload($customer, [
        'due_date' => now()->addWeek()->toDateString(),
    ]))->assertRedirect();
    $invoice = Invoice::query()->firstOrFail();

    actingInOrganization($owner, $organization)->post('/invoices/'.$invoice->id.'/issue')->assertRedirect();
    actingInOrganization($owner, $organization)->post('/payments', [
        'invoice_id' => $invoice->id,
        'amount' => '500',
        'method' => 'cash',
        'reference' => 'R-500',
    ])->assertRedirect();

    $invoice->refresh();
    expect($invoice->status)->toBe(InvoiceStatus::PartiallyPaid)
        ->and($invoice->amount_due)->toBe('650.00');

    actingInOrganization($owner, $organization)->post('/payments', [
        'invoice_id' => $invoice->id,
        'amount' => '650',
        'method' => 'card',
    ])->assertRedirect();

    $invoice->refresh();
    expect($invoice->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->amount_paid)->toBe('1150.00')
        ->and($invoice->amount_due)->toBe('0.00')
        ->and($invoice->paid_at)->not->toBeNull();
    expect(Activity::query()->where('action', 'payment.completed')->exists())->toBeTrue();
});

it('rejects a payment above the balance and a payment on a cancelled invoice', function () {
    [$owner, $organization, $customer] = financeContext();
    actingInOrganization($owner, $organization)->post('/invoices', invoicePayload($customer))->assertRedirect();
    $invoice = Invoice::query()->firstOrFail();
    actingInOrganization($owner, $organization)->post('/invoices/'.$invoice->id.'/issue')->assertRedirect();

    actingInOrganization($owner, $organization)->post('/payments', [
        'invoice_id' => $invoice->id,
        'amount' => '2000',
        'method' => 'cash',
    ])->assertInvalid(['amount']);

    actingInOrganization($owner, $organization)->post('/invoices/'.$invoice->id.'/cancel')->assertRedirect();
    actingInOrganization($owner, $organization)->post('/payments', [
        'invoice_id' => $invoice->id,
        'amount' => '10',
        'method' => 'cash',
    ])->assertInvalid(['invoice_id']);

    $invoice->refresh();
    expect($invoice->status)->toBe(InvoiceStatus::Cancelled)
        ->and($invoice->amount_due)->toBe('0.00');
    expect(Activity::query()->where('action', 'invoice.cancelled')->exists())->toBeTrue();
});

it('does not create a second invoice for the same completed work order', function () {
    [$owner, $organization, $customer] = financeContext();
    $order = completedOrder($customer);

    actingInOrganization($owner, $organization)->post('/work-orders/'.$order->id.'/invoice')->assertRedirect();
    actingInOrganization($owner, $organization)->post('/work-orders/'.$order->id.'/invoice')->assertRedirect();

    expect(Invoice::query()->where('work_order_id', $order->id)->count())->toBe(1);
    $invoice = Invoice::query()->firstOrFail();
    expect($invoice->total)->toBe('1150.00')
        ->and($invoice->currency)->toBe('SAR');
});

it('marks overdue invoices and can run the command twice', function () {
    [$owner, $organization, $customer] = financeContext();
    actingInOrganization($owner, $organization)->post('/invoices', invoicePayload($customer, [
        'due_date' => now()->subDay()->toDateString(),
    ]))->assertRedirect();
    $invoice = Invoice::query()->firstOrFail();
    actingInOrganization($owner, $organization)->post('/invoices/'.$invoice->id.'/issue')->assertRedirect();

    $this->artisan('invoices:mark-overdue')->assertSuccessful();
    $this->artisan('invoices:mark-overdue')->assertSuccessful();

    expect($invoice->refresh()->status)->toBe(InvoiceStatus::Overdue);
    actingInOrganization($owner, $organization)->get('/invoices?overdue=1')->assertOk()->assertSee($invoice->invoice_number);
});

it('shows customer balances from stored invoices', function () {
    [$owner, $organization, $customer] = financeContext();
    actingInOrganization($owner, $organization)->post('/invoices', invoicePayload($customer, [
        'due_date' => now()->addWeek()->toDateString(),
    ]))->assertRedirect();
    $invoice = Invoice::query()->firstOrFail();
    actingInOrganization($owner, $organization)->post('/invoices/'.$invoice->id.'/issue')->assertRedirect();
    actingInOrganization($owner, $organization)->post('/payments', [
        'invoice_id' => $invoice->id,
        'amount' => '500',
        'method' => 'cash',
    ])->assertRedirect();

    actingInOrganization($owner, $organization)->get('/customers/'.$customer->id)->assertOk()->assertInertia(fn ($page) => $page
        ->where('finance.invoiced', '1150.00')
        ->where('finance.paid', '500.00')
        ->where('finance.outstanding', '650.00')
        ->where('finance.currency', 'SAR'));
});

it('filters dashboard finance metrics by branch', function () {
    [$owner, $organization, $customer] = financeContext();
    $branch = Branch::query()->create([
        'name' => 'North Yard',
        'code' => 'NY',
        'city' => 'Riyadh',
        'country' => 'SA',
        'timezone' => 'Asia/Riyadh',
        'status' => 'active',
    ]);
    $other = Branch::query()->create([
        'name' => 'South Yard',
        'code' => 'SY',
        'city' => 'Riyadh',
        'country' => 'SA',
        'timezone' => 'Asia/Riyadh',
        'status' => 'active',
    ]);

    actingInOrganization($owner, $organization)->post('/invoices', invoicePayload($customer, [
        'branch_id' => $branch->id,
        'due_date' => now()->addWeek()->toDateString(),
    ]))->assertRedirect();
    actingInOrganization($owner, $organization)->post('/invoices', invoicePayload($customer, [
        'branch_id' => $other->id,
        'due_date' => now()->addWeek()->toDateString(),
    ]))->assertRedirect();

    Invoice::query()->each(function (Invoice $invoice) use ($owner, $organization): void {
        actingInOrganization($owner, $organization)->post('/invoices/'.$invoice->id.'/issue')->assertRedirect();
    });

    actingInOrganization($owner, $organization)->get('/?branch_id='.$branch->id)->assertOk()->assertInertia(fn ($page) => $page
        ->where('finance.invoiced', '1150.00')
        ->where('finance.outstanding', '1150.00')
        ->where('finance.currency', 'SAR'));
});

it('rejects a work order that belongs to another customer', function () {
    [$owner, $organization, $customer] = financeContext();
    $other = Customer::query()->create([
        'type' => 'individual',
        'name' => 'Other Customer',
        'phone' => '0550001998',
        'status' => 'active',
    ]);
    $order = completedOrder($other);

    actingInOrganization($owner, $organization)->post('/invoices', invoicePayload($customer, [
        'work_order_id' => $order->id,
    ]))->assertInvalid(['work_order_id']);
});

function financeContext(OrganizationRole $role = OrganizationRole::Owner): array
{
    [$user, $organization] = createMember($role);
    $organization->update([
        'currency' => 'SAR',
        'timezone' => 'Asia/Riyadh',
        'settings' => ['tax_rate' => '15'],
    ]);
    OrganizationContext::set($organization->fresh(), $role);
    Auth::login($user);

    $customer = Customer::query()->create([
        'type' => 'individual',
        'name' => 'Finance Customer',
        'phone' => '0550001771',
        'status' => 'active',
    ]);

    return [$user, $organization->fresh(), $customer];
}

function invoicePayload(Customer $customer, array $overrides = []): array
{
    return array_replace_recursive([
        'customer_id' => $customer->id,
        'discount' => '0',
        'items' => [[
            'description' => 'AC Maintenance',
            'quantity' => '1',
            'unit_price' => '1000',
        ]],
    ], $overrides);
}

function completedOrder(Customer $customer): WorkOrder
{
    $service = Service::query()->create([
        'name' => 'Cooling visit',
        'category' => 'HVAC',
        'base_price' => '1000',
        'estimated_duration' => 60,
        'status' => 'active',
    ]);
    $request = ServiceRequest::query()->create([
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'source' => 'manual',
        'subject' => 'Cooling visit',
        'description' => 'Ready to invoice.',
        'priority' => 'normal',
        'status' => 'completed',
        'requested_at' => now(),
    ]);

    return WorkOrder::query()->create([
        'customer_id' => $customer->id,
        'service_request_id' => $request->id,
        'title' => 'Cooling visit',
        'priority' => 'normal',
        'status' => WorkOrderStatus::Completed,
        'completed_at' => now(),
    ]);
}
