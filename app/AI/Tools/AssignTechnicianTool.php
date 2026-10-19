<?php

namespace App\AI\Tools;

use App\AI\Data\AIToolContext;
use App\AI\Support\RecordResolver;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Operations\WorkOrderService;

class AssignTechnicianTool extends ApplicationTool
{
    public function name(): string
    {
        return 'assign_technician';
    }

    public function description(): string
    {
        return 'Propose assigning a technician to a work order. It is saved only after the user confirms.';
    }

    public function schema(): array
    {
        return $this->objectSchema([
            'work_order_id' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'technician_name' => ['type' => 'string'],
        ], ['technician_name']);
    }

    public function authorize(User $user): bool
    {
        return $user->can('create', WorkOrder::class);
    }

    public function requiresConfirmation(): bool
    {
        return true;
    }

    public function preview(array $arguments): array
    {
        return [
            ['key' => 'work_order', 'value' => (string) ($arguments['title'] ?? $arguments['work_order_id'] ?? '')],
            ['key' => 'technician', 'value' => (string) ($arguments['technician_name'] ?? '')],
        ];
    }

    public function execute(array $arguments, AIToolContext $context): array
    {
        $resolver = app(RecordResolver::class);
        $order = $resolver->workOrder($arguments['work_order_id'] ?? null, $arguments['title'] ?? null);

        if (! $context->user->can('view', $order)) {
            return ['success' => false, 'data' => null, 'message' => 'That work order was not found in this organization.'];
        }

        $technician = $resolver->technicianByName($arguments['technician_name']);
        $order = app(WorkOrderService::class)->assign($order, $technician->id);

        return $this->ok([
            'id' => $order->id,
            'title' => $order->title,
            'technician' => $order->technician?->user?->name,
        ], 'work_orders');
    }
}
