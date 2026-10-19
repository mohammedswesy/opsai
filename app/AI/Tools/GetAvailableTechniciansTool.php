<?php

namespace App\AI\Tools;

use App\AI\Data\AIToolContext;
use App\AI\Tools\Concerns\ScopesBranch;
use App\Enums\AvailabilityStatus;
use App\Enums\RecordStatus;
use App\Models\Technician;
use App\Models\User;
use App\Services\Operations\OperationsDashboard;

class GetAvailableTechniciansTool extends ApplicationTool
{
    use ScopesBranch;

    public function name(): string
    {
        return 'get_available_technicians';
    }

    public function description(): string
    {
        return 'Technicians who are active and available.';
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
        $technicians = $this->scopeBranch(Technician::query(), $context)
            ->with('user:id,name')
            ->where('status', RecordStatus::Active)
            ->where('availability_status', AvailabilityStatus::Available)
            ->orderBy('employee_code')
            ->limit(20)
            ->get();

        return $this->ok([
            'count' => $metrics['available_technicians'],
            'technicians' => $technicians->map(fn (Technician $technician) => [
                'id' => $technician->id,
                'name' => $technician->user?->name,
                'employee_code' => $technician->employee_code,
            ])->all(),
        ], 'technicians');
    }
}
