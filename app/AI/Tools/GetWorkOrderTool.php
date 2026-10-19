<?php

namespace App\AI\Tools;

use App\AI\Data\AIToolContext;
use App\AI\Support\RecordResolver;
use App\Models\User;
use App\Models\WorkOrder;

class GetWorkOrderTool extends ApplicationTool
{
    public function name(): string
    {
        return 'get_work_order';
    }

    public function description(): string
    {
        return 'One work order in the current organization by id or exact title.';
    }

    public function schema(): array
    {
        return $this->objectSchema([
            'work_order_id' => ['type' => 'string'],
            'title' => ['type' => 'string'],
        ]);
    }

    public function authorize(User $user): bool
    {
        return $user->can('viewAny', WorkOrder::class);
    }

    public function execute(array $arguments, AIToolContext $context): array
    {
        $order = app(RecordResolver::class)->workOrder($arguments['work_order_id'] ?? null, $arguments['title'] ?? null);
        $order->load(['customer:id,name', 'technician.user:id,name']);

        if (! $context->user->can('view', $order)) {
            return ['success' => false, 'data' => null, 'message' => 'That work order was not found in this organization.'];
        }

        return $this->ok([
            'id' => $order->id,
            'title' => $order->title,
            'status' => $order->status->value,
            'priority' => $order->priority->value,
            'customer' => $order->customer?->name,
            'technician' => $order->technician?->user?->name,
        ], 'work_orders');
    }
}
