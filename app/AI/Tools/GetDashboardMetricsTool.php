<?php

namespace App\AI\Tools;

use App\AI\Data\AIToolContext;
use App\AI\Tools\Concerns\ScopesBranch;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Finance\FinancialMetricsService;
use App\Services\Operations\OperationsDashboard;
use App\Support\OrganizationContext;

class GetDashboardMetricsTool extends ApplicationTool
{
    use ScopesBranch;

    public function name(): string
    {
        return 'get_dashboard_metrics';
    }

    public function description(): string
    {
        return 'Operational counts and financial totals for the current organization. Revenue follows the selected period. Outstanding and overdue are current balances.';
    }

    public function schema(): array
    {
        return $this->objectSchema([
            'period' => ['type' => 'string', 'enum' => ['recent', 'this_month']],
        ]);
    }

    public function authorize(User $user): bool
    {
        return $user->can('viewAny', Branch::class);
    }

    public function execute(array $arguments, AIToolContext $context): array
    {
        $timezone = OrganizationContext::current()->organization->timezone;
        $query = [];

        if (($arguments['period'] ?? 'recent') === 'this_month') {
            $query['from'] = now($timezone)->startOfMonth()->toDateString();
            $query['to'] = now($timezone)->toDateString();
        }

        $request = $this->metricsRequest($context, $query);
        $operations = app(OperationsDashboard::class)->metrics($request);
        $finance = $context->user->can('viewAny', Invoice::class)
            ? app(FinancialMetricsService::class)->metrics($request)
            : null;

        return $this->ok([
            'open_requests' => $operations['open_requests'],
            'todays_appointments' => $operations['todays_appointments'],
            'active_work_orders' => $operations['active_work_orders'],
            'completed_work_orders' => $operations['completed_work_orders'],
            'available_technicians' => $operations['available_technicians'],
            'pending_tasks' => $operations['pending_tasks'],
            'currency' => $finance['currency'] ?? OrganizationContext::current()->organization->currency,
            'revenue' => $finance['revenue'] ?? null,
            'invoiced' => $finance['invoiced'] ?? null,
            'paid' => $finance['paid'] ?? null,
            'outstanding' => $finance['outstanding'] ?? null,
            'overdue' => $finance['overdue'] ?? null,
            'todays_payments' => $finance['todays_payments'] ?? null,
        ], 'financial_metrics');
    }
}
