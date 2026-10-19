<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SharesLookups;
use App\Models\Branch;
use App\Models\Invoice;
use App\Services\Finance\FinancialMetricsService;
use App\Services\Operations\OperationsDashboard;
use App\Support\OrganizationContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use SharesLookups;

    public function __invoke(Request $request, OperationsDashboard $dashboard, FinancialMetricsService $finance): Response
    {
        $this->authorize('viewAny', Branch::class);
        $this->rememberBranch($request);

        return Inertia::render('Dashboard', [
            'metrics' => [
                ...$dashboard->metrics($request),
                'branches' => Branch::query()->count(),
                'members' => OrganizationContext::current()->organization->members()->count(),
            ],
            'finance' => $request->user()?->can('viewAny', Invoice::class) ? $finance->metrics($request) : null,
            'branches' => $this->branchOptions(),
            'filters' => $request->only(['branch_id', 'from', 'to']),
        ]);
    }

    private function rememberBranch(Request $request): void
    {
        if (! $request->exists('branch_id')) {
            return;
        }

        $branchId = $request->string('branch_id')->toString();

        if ($branchId === '') {
            $request->session()->forget('current_branch_id');

            return;
        }

        if (Branch::query()->whereKey($branchId)->exists()) {
            $request->session()->put('current_branch_id', $branchId);
        }
    }
}
