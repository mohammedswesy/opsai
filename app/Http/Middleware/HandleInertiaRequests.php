<?php

namespace App\Http\Middleware;

use App\Support\OrganizationContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'locale' => fn () => app()->getLocale(),
            'dir' => fn () => app()->getLocale() === 'ar' ? 'rtl' : 'ltr',
            'auth' => [
                'user' => fn () => $request->user() === null ? null : [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                ],
            ],
            'organization' => fn () => ($context = OrganizationContext::current()) === null ? null : [
                'id' => $context->organization->id,
                'name' => $context->organization->name,
                'slug' => $context->organization->slug,
                'locale' => $context->organization->locale,
                'currency' => $context->organization->currency,
                'country' => $context->organization->country,
                'timezone' => $context->organization->timezone,
                'status' => $context->organization->status->value,
                'role' => $context->role->value,
            ],
            'permissions' => fn () => OrganizationContext::current()?->role->permissions() ?? [],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'status' => fn () => $request->session()->get('status'),
            ],
        ];
    }
}
