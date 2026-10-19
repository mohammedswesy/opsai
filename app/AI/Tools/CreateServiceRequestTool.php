<?php

namespace App\AI\Tools;

use App\AI\Data\AIToolContext;
use App\AI\Support\RecordResolver;
use App\Enums\Priority;
use App\Enums\RequestSource;
use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\Operations\ServiceRequestService;

class CreateServiceRequestTool extends ApplicationTool
{
    public function name(): string
    {
        return 'create_service_request';
    }

    public function description(): string
    {
        return 'Propose a service request. It is saved only after the user confirms.';
    }

    public function schema(): array
    {
        return $this->objectSchema([
            'customer_name' => ['type' => 'string'],
            'customer_id' => ['type' => 'string'],
            'subject' => ['type' => 'string'],
            'description' => ['type' => 'string'],
            'priority' => ['type' => 'string', 'enum' => Priority::values()],
            'service_name' => ['type' => 'string'],
        ], ['subject', 'description']);
    }

    public function forCustomer(): bool
    {
        return true;
    }

    public function authorize(User $user): bool
    {
        return $user->can('create', ServiceRequest::class);
    }

    public function authorizeCustomer(AIToolContext $context): bool
    {
        return $context->customer !== null;
    }

    public function requiresConfirmation(): bool
    {
        return true;
    }

    public function preview(array $arguments): array
    {
        return [
            ['key' => 'customer', 'value' => (string) ($arguments['customer_name'] ?? $arguments['customer_id'] ?? '')],
            ['key' => 'subject', 'value' => (string) ($arguments['subject'] ?? '')],
            ['key' => 'priority', 'value' => (string) ($arguments['priority'] ?? Priority::Normal->value)],
        ];
    }

    public function execute(array $arguments, AIToolContext $context): array
    {
        $customer = $context->channel === 'customer'
            ? $context->customer
            : app(RecordResolver::class)->customer($arguments['customer_id'] ?? null, $arguments['customer_name'] ?? null);

        if ($customer === null) {
            return ['success' => false, 'data' => null, 'message' => 'The customer could not be identified.'];
        }

        $service = app(RecordResolver::class)->service(null, $arguments['service_name'] ?? null);

        $request = app(ServiceRequestService::class)->create([
            'branch_id' => $context->scopedBranchId() ?? $customer->branch_id,
            'customer_id' => $customer->id,
            'service_id' => $service?->id,
            'source' => $context->channel === 'customer' ? RequestSource::Whatsapp->value : RequestSource::Ai->value,
            'subject' => $arguments['subject'],
            'description' => $arguments['description'],
            'priority' => $arguments['priority'] ?? Priority::Normal->value,
            'status' => ServiceRequestStatus::New->value,
            'requested_at' => now(),
        ]);

        return $this->ok([
            'id' => $request->id,
            'subject' => $request->subject,
            'customer' => $customer->name,
        ], 'service_requests');
    }
}
