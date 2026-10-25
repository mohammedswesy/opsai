<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\SharesLookups;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\Technician;
use App\Models\WorkOrder;
use App\Policies\DispatchBoardPolicy;
use App\Services\Finance\FinancialMetricsService;
use App\Services\Operations\OperationsDashboard;
use App\Services\Operations\ScheduleQueries;
use App\Support\OrganizationContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use SharesLookups;

    public function __invoke(Request $request, OperationsDashboard $dashboard, FinancialMetricsService $finance, ScheduleQueries $schedule): Response
    {
        $this->authorize('viewAny', Branch::class);
        $this->rememberBranch($request);

        // metrics() runs first: it rejects a branch_id from another organization.
        $metrics = $dashboard->metrics($request);
        $branchId = $request->string('branch_id')->toString() ?: null;
        $user = $request->user();
        $seeAllAppointments = $user->can('create', Appointment::class);
        $seeAllWorkOrders = $user->can('create', WorkOrder::class);
        $ownTechnician = Technician::query()->where('user_id', $user->id)->first();
        // Same rule as the dispatch board: managers see everyone, others only their own jobs.
        $appointmentOwner = $seeAllAppointments ? null : ($ownTechnician->id ?? '');
        $canSeeAppointments = $user->can('viewAny', Appointment::class);
        $dispatch = app(DispatchBoardPolicy::class);

        return Inertia::render('Dashboard', [
            'metrics' => [
                ...$metrics,
                'branches' => Branch::query()->count(),
                'members' => OrganizationContext::current()->organization->members()->count(),
                'unassigned_jobs' => ($seeAllAppointments || $seeAllWorkOrders)
                    ? $schedule->unassignedCount(
                        $branchId,
                        $dispatch->viewRequests($user),
                        $dispatch->viewWorkOrders($user),
                    )
                    : null,
                'overdue_appointments' => $canSeeAppointments ? $schedule->overdueAppointmentCount($branchId, $appointmentOwner) : null,
            ],
            'finance' => $user->can('viewAny', Invoice::class) ? $finance->metrics($request) : null,
            'branches' => $this->branchOptions(),
            'filters' => $request->only(['branch_id', 'from', 'to']),
            'upcoming_today' => $canSeeAppointments ? $schedule->upcomingToday($branchId, $appointmentOwner) : null,
            'my_day' => (! $seeAllWorkOrders && $ownTechnician !== null && $user->can('viewAny', WorkOrder::class))
                ? $schedule->myDay($ownTechnician)
                : null,
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
