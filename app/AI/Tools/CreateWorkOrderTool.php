<?php

namespace App\AI\Tools;

use App\AI\Data\AIToolContext;
use App\AI\Support\RecordResolver;
use App\Enums\Priority;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Operations\WorkOrderService;

class CreateWorkOrderTool extends ApplicationTool
{
    public function name(): string
    {
        return 'create_work_order';
    }

    public function description(): string
    {
        return 'Propose a work order for an existing service request. It is saved only after the user confirms.';
    }

    public function schema(): array
    {
        return $this->objectSchema([
            'customer_name' => ['type' => 'string'],
            'customer_id' => ['type' => 'string'],
            'service_request_id' => ['type' => 'string'],
            'request_subject' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'technician_name' => ['type' => 'string'],
            'priority' => ['type' => 'string', 'enum' => Priority::values()],
        ], ['title']);
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
            ['key' => 'title', 'value' => (string) ($arguments['title'] ?? '')],
            ['key' => 'customer', 'value' => (string) ($arguments['customer_name'] ?? '')],
            ['key' => 'technician', 'value' => (string) ($arguments['technician_name'] ?? '')],
            ['key' => 'priority', 'value' => (string) ($arguments['priority'] ?? Priority::Normal->value)],
        ];
    }

    public function execute(array $arguments, AIToolContext $context): array
    {
        $resolver = app(RecordResolver::class);
        $customer = $resolver->customer($arguments['customer_id'] ?? null, $arguments['customer_name'] ?? null);
        $request = $resolver->serviceRequest($arguments['service_request_id'] ?? null, $arguments['request_subject'] ?? null);
        $technician = null;

        if (is_string($arguments['technician_name'] ?? null) && $arguments['technician_name'] !== '') {
            $technician = $resolver->technicianByName($arguments['technician_name']);
        }

        $order = app(WorkOrderService::class)->create([
            'branch_id' => $context->scopedBranchId() ?? $request->branch_id,
            'customer_id' => $customer->id,
            'service_request_id' => $request->id,
            'technician_id' => $technician?->id,
            'title' => $arguments['title'],
            'priority' => $arguments['priority'] ?? Priority::Normal->value,
        ]);

        return $this->ok([
            'id' => $order->id,
            'title' => $order->title,
            'status' => $order->status->value,
        ], 'work_orders');
    }
}
