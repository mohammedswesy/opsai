<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Support\OrganizationContext;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $this->authorize('viewAny', Branch::class);

        $context = OrganizationContext::current();

        return Inertia::render('Dashboard', [
            'stats' => [
                'branches' => Branch::query()->count(),
                'members' => $context->organization->members()->count(),
            ],
        ]);
    }
}
