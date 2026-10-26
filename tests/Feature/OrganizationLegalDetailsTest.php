<?php

use App\Enums\OrganizationRole;
use App\Models\Customer;
use App\Models\Organization;
use App\Services\Finance\InvoiceLifecycleService;
use App\Support\OrganizationContext;
use Illuminate\Support\Facades\Auth;

function legalDetailsPayload(Organization $organization, array $overrides = []): array
{
    return array_replace([
        'name' => $organization->name,
        'legal_name' => 'Riyadh Cooling Maintenance Co.',
        'tax_number' => '310123456700003',
        'phone' => '+966110000000',
        'email' => 'billing@example.test',
        'address' => 'King Fahd Road, Al Olaya',
        'city' => 'Riyadh',
        'country' => 'SA',
        'timezone' => 'Asia/Riyadh',
        'currency' => 'SAR',
        'locale' => 'en',
        'status' => 'active',
        'tax_rate' => '15',
    ], $overrides);
}

it('saves the legal details used on the invoice letterhead', function () {
    [$owner, $organization] = createMember();

    actingInOrganization($owner, $organization)
        ->put('/organization', legalDetailsPayload($organization))
        ->assertRedirect('/organization')
        ->assertSessionHasNoErrors();

    expect($organization->fresh())
        ->legal_name->toBe('Riyadh Cooling Maintenance Co.')
        ->tax_number->toBe('310123456700003')
        ->address->toBe('King Fahd Road, Al Olaya')
        ->city->toBe('Riyadh');
});

it('accepts an empty tax number and clears the optional fields', function () {
    [$owner, $organization] = createMember();
    $organization->update(['tax_number' => '310123456700003', 'address' => 'Old address', 'city' => 'Jeddah']);

    actingInOrganization($owner, $organization)
        ->put('/organization', legalDetailsPayload($organization, ['tax_number' => '', 'address' => '', 'city' => '']))
        ->assertSessionHasNoErrors();

    expect($organization->fresh())
        ->tax_number->toBeNull()
        ->address->toBeNull()
        ->city->toBeNull();
});

it('rejects a tax number that is not a Saudi VAT number', function (string $taxNumber) {
    [$owner, $organization] = createMember();

    actingInOrganization($owner, $organization)
        ->put('/organization', legalDetailsPayload($organization, ['tax_number' => $taxNumber]))
        ->assertInvalid(['tax_number' => 'The tax number must be 15 digits that start and end with 3.']);

    expect($organization->fresh()->tax_number)->toBeNull();
})->with([
    'too short' => '31012345670003',
    'too long' => '3101234567000003',
    'does not start with 3' => '210123456700003',
    'does not end with 3' => '310123456700001',
    'letters' => '31012345670000A',
    'spaces' => '310 123 456 703',
    'arabic-indic digits' => '٣١٠١٢٣٤٥٦٧٠٠٠٠٣',
]);

it('explains the tax number rule in Arabic', function () {
    [$owner, $organization] = createMember();

    test()->actingAs($owner)
        ->withSession(['current_organization_id' => $organization->id, 'locale' => 'ar'])
        ->put('/organization', legalDetailsPayload($organization, ['tax_number' => '123']))
        ->assertInvalid(['tax_number' => 'يجب أن يتكون الرقم الضريبي من 15 رقماً يبدأ وينتهي بالرقم 3.']);
});

it('trims surrounding whitespace from the tax number before checking it', function () {
    [$owner, $organization] = createMember();

    actingInOrganization($owner, $organization)
        ->put('/organization', legalDetailsPayload($organization, ['tax_number' => " 310123456700003\n"]))
        ->assertSessionHasNoErrors();

    expect($organization->fresh()->tax_number)->toBe('310123456700003');
});

it('lets an admin change the legal details', function () {
    [$admin, $organization] = createMember(OrganizationRole::Admin);

    actingInOrganization($admin, $organization)
        ->put('/organization', legalDetailsPayload($organization))
        ->assertRedirect('/organization');

    expect($organization->fresh()->city)->toBe('Riyadh');
});

it('stops roles without organization.update from changing the legal details', function (OrganizationRole $role) {
    [$user, $organization] = createMember($role);

    actingInOrganization($user, $organization)
        ->put('/organization', legalDetailsPayload($organization))
        ->assertForbidden();

    expect($organization->fresh())
        ->tax_number->toBeNull()
        ->city->toBeNull();
})->with([
    OrganizationRole::Manager,
    OrganizationRole::Dispatcher,
    OrganizationRole::Technician,
    OrganizationRole::Accountant,
    OrganizationRole::CustomerService,
]);

it('shows the legal details on the invoice letterhead', function () {
    [$owner, $organization] = createMember();
    $organization->update(['legal_name' => 'Legal Co.', 'tax_number' => '310123456700003', 'address' => 'King Fahd Road', 'city' => 'Riyadh']);
    OrganizationContext::set($organization->fresh(), OrganizationRole::Owner);
    Auth::login($owner);
    $customer = Customer::query()->create(['type' => 'individual', 'name' => 'Letterhead Customer', 'phone' => '0550001772', 'status' => 'active']);
    $invoice = app(InvoiceLifecycleService::class)->create(['customer_id' => $customer->id, 'discount' => '0'], [
        ['description' => 'Visit', 'quantity' => '1', 'unit_price' => '100'],
    ]);
    OrganizationContext::forget();

    actingInOrganization($owner, $organization)
        ->get('/invoices/'.$invoice->id)
        ->assertInertia(fn ($page) => $page
            ->where('letterhead.legal_name', 'Legal Co.')
            ->where('letterhead.tax_number', '310123456700003')
            ->where('letterhead.address', 'King Fahd Road')
            ->where('letterhead.city', 'Riyadh'));
});
