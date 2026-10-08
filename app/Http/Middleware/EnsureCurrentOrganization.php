<?php

namespace App\Http\Middleware;

use App\Enums\OrganizationStatus;
use App\Support\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCurrentOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $organizationId = $request->session()->get('current_organization_id');
        $organization = null;

        if (is_string($organizationId)) {
            $organization = $user->organizations()->whereKey($organizationId)->first();
        }

        if ($organization === null) {
            $organizations = $user->organizations()->get();

            if ($organizations->count() === 1) {
                $organization = $organizations->first();
                $request->session()->put('current_organization_id', $organization->id);
            } else {
                OrganizationContext::forget();

                return redirect()->route('organizations.index');
            }
        }

        $role = $user->roleIn($organization);

        if ($role === null) {
            $request->session()->forget('current_organization_id');
            OrganizationContext::forget();

            return redirect()->route('organizations.index');
        }

        if ($organization->status === OrganizationStatus::Suspended && ! $role->allows('organization.update')) {
            abort(403, __('This organization is suspended.'));
        }

        OrganizationContext::set($organization, $role);

        if (in_array($organization->locale, config('opsai.locales'), true)) {
            app()->setLocale($organization->locale);
            $request->session()->put('locale', $organization->locale);
        }

        return $next($request);
    }
}
