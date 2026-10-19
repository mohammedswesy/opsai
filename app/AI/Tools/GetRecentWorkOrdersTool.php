<?php

namespace App\AI\Tools;

use App\AI\Data\AIToolContext;
use App\AI\Tools\Concerns\ScopesBranch;
use App\Models\Technician;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Operations\WorkOrderService;

class GetRecentWorkOrdersTool extends ApplicationTool
{
    use ScopesBranch;

    public function name(): string
    {
        return 'get_recent_work_orders';
    }

    public function description(): string
    {
        return 'The latest work orders the user is allowed to see.';
    }

    public function schema(): array
    {
        return $this->objectSchema([
            'limit' => ['type' => 'integer'],
        ]);
    }

    public function authorize(User $user): bool
    {
        return $user->can('viewAny', WorkOrder::class);
    }

    public function execute(array $arguments, AIToolContext $context): array
    {
        $limit = min(10, max(1, (int) ($arguments['limit'] ?? 5)));
        $page = app(WorkOrderService::class)->paginate(
            $this->metricsRequest($context),
            $this->ownTechnicianId($context),
        );

        $orders = collect($page->items())->take($limit);

        return $this->ok([
            'work_orders' => $orders->map(fn (WorkOrder $order) => [
                'id' => $order->id,
                'title' => $order->title,
                'status' => $order->status->value,
                'priority' => $order->priority->value,
                'customer' => $order->customer?->name,
                'technician' => $order->technician?->user?->name,
            ])->all(),
        ], 'work_orders');
    }

    private function ownTechnicianId(AIToolContext $context): ?string
    {
        if ($context->user->can('create', WorkOrder::class)) {
            return null;
        }

        return Technician::query()->where('user_id', $context->user->id)->value('id') ?? '';
    }
}
