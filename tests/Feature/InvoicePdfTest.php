<?php

use App\Enums\OrganizationRole;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Organization;
use App\Models\User;
use App\Services\Finance\InvoiceLifecycleService;
use App\Support\OrganizationContext;
use Illuminate\Support\Facades\Auth;

/**
 * An issued invoice with Arabic and English text in a Riyadh organization.
 *
 * @return array{0: User, 1: Organization, 2: Invoice}
 */
function pdfInvoice(): array
{
    [$owner, $organization] = createMember();
    $organization->update([
        'legal_name' => 'شركة تبريد الرياض',
        'tax_number' => '310123456700003',
        'address' => 'طريق الملك فهد',
        'city' => 'الرياض',
        'currency' => 'SAR',
        'timezone' => 'Asia/Riyadh',
        'settings' => ['tax_rate' => '15'],
    ]);
    OrganizationContext::set($organization->fresh(), OrganizationRole::Owner);
    Auth::login($owner);

    $customer = Customer::query()->create(['type' => 'individual', 'name' => 'محمد العتيبي', 'phone' => '0550001771', 'status' => 'active']);
    $invoices = app(InvoiceLifecycleService::class);
    $invoice = $invoices->create(['customer_id' => $customer->id, 'discount' => '0'], [
        ['description' => 'صيانة المكيف', 'quantity' => '1', 'unit_price' => '1000'],
        ['description' => 'Filter (MERV 8)', 'quantity' => '2', 'unit_price' => '45.50'],
    ]);
    $invoices->issue($invoice);

    OrganizationContext::forget();
    Auth::logout();

    return [$owner, $organization->fresh(), $invoice->fresh()];
}

it('downloads the invoice as a PDF named after the invoice number', function (string $locale) {
    [$owner, $organization, $invoice] = pdfInvoice();

    $response = actingInOrganization($owner, $organization)
        ->withSession(['current_organization_id' => $organization->id, 'locale' => $locale])
        ->get('/invoices/'.$invoice->id.'/pdf');

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename="'.$invoice->invoice_number.'.pdf"');

    expect($response->getContent())->toStartWith('%PDF-')
        ->and($invoice->invoice_number)->toMatch('/^INV-[A-Za-z0-9-]+$/');
})->with(['en', 'ar']);

it('lets every role that can view invoices download the PDF', function (OrganizationRole $role) {
    [, $organization, $invoice] = pdfInvoice();
    [$user] = createMember($role, $organization);

    actingInOrganization($user, $organization)->get('/invoices/'.$invoice->id.'/pdf')->assertOk();
})->with([OrganizationRole::Accountant, OrganizationRole::Dispatcher, OrganizationRole::CustomerService]);

it('forbids the PDF to a role without invoices.view', function () {
    [, $organization, $invoice] = pdfInvoice();
    [$technician] = createMember(OrganizationRole::Technician, $organization);

    actingInOrganization($technician, $organization)->get('/invoices/'.$invoice->id.'/pdf')->assertForbidden();
});

it('hides another organization invoice PDF', function () {
    [, , $invoice] = pdfInvoice();
    [$other, $otherOrganization] = createMember();

    actingInOrganization($other, $otherOrganization)->get('/invoices/'.$invoice->id.'/pdf')->assertNotFound();
});

it('requires sign-in for the PDF', function () {
    [, , $invoice] = pdfInvoice();

    $this->get('/invoices/'.$invoice->id.'/pdf')->assertRedirect('/login');
});
