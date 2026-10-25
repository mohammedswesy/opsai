<?php

namespace App\Http\Controllers;

use App\Enums\OrganizationRole;
use App\Enums\OrganizationStatus;
use App\Http\Requests\UpdateOrganizationRequest;
use App\Models\Organization;
use App\Support\OrganizationContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function index(Request $request): Response
    {
        $currentId = $request->session()->get('current_organization_id');

        if (is_string($currentId)) {
            $current = $request->user()->organizations()->whereKey($currentId)->first();
            $role = $current ? $request->user()->roleIn($current) : null;

            if ($current !== null && $role !== null) {
                OrganizationContext::set($current, $role);
            }
        }

        $organizations = $request->user()
            ->organizations()
            ->orderBy('name')
            ->get()
            ->map(fn (Organization $organization) => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'country' => $organization->country,
                'status' => $organization->status->value,
                'role' => $organization->pivot->role,
                'is_current' => $organization->id === $currentId,
            ]);

        return Inertia::render('Organizations/Index', [
            'organizations' => $organizations,
        ]);
    }

    public function switch(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'organization_id' => ['required', 'string'],
        ]);

        $organization = $request->user()->organizations()->whereKey($data['organization_id'])->first();

        if ($organization === null) {
            abort(403);
        }

        $request->session()->put('current_organization_id', $organization->id);
        $request->session()->put('locale', $organization->locale);

        return redirect()->route('dashboard');
    }

    public function edit(): Response
    {
        $organization = OrganizationContext::current()->organization;
        $this->authorize('view', $organization);

        return Inertia::render('Organizations/Settings', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'legal_name' => $organization->legal_name,
                'tax_number' => $organization->tax_number,
                'phone' => $organization->phone,
                'email' => $organization->email,
                'country' => $organization->country,
                'timezone' => $organization->timezone,
                'currency' => $organization->currency,
                'locale' => $organization->locale,
                'status' => $organization->status->value,
                'tax_rate' => $organization->taxRate(),
            ],
            'countries' => config('opsai.countries'),
            'currencies' => config('opsai.currencies'),
            'locales' => config('opsai.locales'),
            'statuses' => OrganizationStatus::values(),
            'canUpdate' => request()->user()?->can('update', $organization) ?? false,
            'canChangeStatus' => OrganizationContext::current()->role === OrganizationRole::Owner,
        ]);
    }

    public function update(UpdateOrganizationRequest $request): RedirectResponse
    {
        $organization = OrganizationContext::current()->organization;
        $data = $request->validated();
        $data['country'] = strtoupper($data['country']);
        $data['currency'] = strtoupper($data['currency']);

        $role = OrganizationContext::current()->role;
        if ($role !== OrganizationRole::Owner) {
            unset($data['status']);
        }

        $settings = $organization->settings ?? [];
        $settings['tax_rate'] = $data['tax_rate'] ?? ($settings['tax_rate'] ?? '0');
        unset($data['tax_rate']);
        $data['settings'] = $settings;

        $organization->update($data);

        $request->session()->put('locale', $organization->locale);

        return redirect()->route('organization.edit')->with('success', __('Organization updated.'));
    }
}
