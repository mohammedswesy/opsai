<?php

namespace App\AI\Tools;

use App\AI\Data\AIToolContext;
use App\AI\Tools\Concerns\ScopesBranch;
use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Operations\OperationsDashboard;

class GetOpenServiceRequestsTool extends ApplicationTool
{
    use ScopesBranch;

    public function name(): string
    {
        return 'get_open_service_requests';
    }

    public function description(): string
    {
        return 'Count and list open service requests.';
    }

    public function schema(): array
    {
        return $this->objectSchema([]);
    }

    public function authorize(User $user): bool
    {
        return $user->can('viewAny', ServiceRequest::class);
    }

    public function execute(array $arguments, AIToolContext $context): array
    {
        $metrics = app(OperationsDashboard::class)->metrics($this->metricsRequest($context));
        $statuses = array_map(
            fn (ServiceRequestStatus $status) => $status->value,
            array_filter(ServiceRequestStatus::cases(), fn (ServiceRequestStatus $status) => $status->isOpen()),
        );

        $requests = $this->scopeBranch(ServiceRequest::query(), $context)
            ->with('customer:id,name')
            ->whereIn('status', $statuses)
            ->latest('requested_at')
            ->limit(10)
            ->get();

        return $this->ok([
            'count' => $metrics['open_requests'],
            'requests' => $requests->map(fn (ServiceRequest $request) => [
                'id' => $request->id,
                'subject' => $request->subject,
                'status' => $request->status->value,
                'priority' => $request->priority->value,
                'customer' => $request->customer?->name,
            ])->all(),
        ], 'service_requests');
    }
}
