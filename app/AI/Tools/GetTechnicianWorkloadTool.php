<?php

namespace App\AI\Tools;

use App\AI\Data\AIToolContext;
use App\AI\Tools\Concerns\ScopesBranch;
use App\Models\Technician;
use App\Models\User;
use App\Services\Operations\OperationsDashboard;

class GetTechnicianWorkloadTool extends ApplicationTool
{
    use ScopesBranch;

    public function name(): string
    {
        return 'get_technician_workload';
    }

    public function description(): string
    {
        return 'Active work-order counts per technician from the operations dashboard.';
    }

    public function schema(): array
    {
        return $this->objectSchema([]);
    }

    public function authorize(User $user): bool
    {
        return $user->can('viewAny', Technician::class);
    }

    public function execute(array $arguments, AIToolContext $context): array
    {
        $metrics = app(OperationsDashboard::class)->metrics($this->metricsRequest($context));
        $workload = collect($metrics['technician_workload'])->sortByDesc('count')->values();

        return $this->ok([
            'technicians' => $workload->all(),
            'highest' => $workload->first(),
        ], 'technician_workload');
    }
}
