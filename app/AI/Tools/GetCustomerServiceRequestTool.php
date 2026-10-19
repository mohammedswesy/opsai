<?php

namespace App\AI\Tools;

use App\AI\Data\AIToolContext;
use App\Models\ServiceRequest;
use App\Models\User;

class GetCustomerServiceRequestTool extends ApplicationTool
{
    public function name(): string
    {
        return 'get_customer_service_request';
    }

    public function description(): string
    {
        return 'List only this identified customer’s service requests and their stored status.';
    }

    public function schema(): array
    {
        return $this->objectSchema([]);
    }

    public function forStaff(): bool
    {
        return false;
    }

    public function forCustomer(): bool
    {
        return true;
    }

    public function authorize(User $user): bool
    {
        return false;
    }

    public function authorizeCustomer(AIToolContext $context): bool
    {
        return $context->customer !== null;
    }

    public function execute(array $arguments, AIToolContext $context): array
    {
        $requests = ServiceRequest::query()
            ->where('customer_id', $context->customer?->id)
            ->latest('requested_at')
            ->limit(5)
            ->get(['id', 'subject', 'status']);

        return $this->ok([
            'count' => $requests->count(),
            'requests' => $requests->map(fn (ServiceRequest $request) => [
                'subject' => $request->subject,
                'status' => $request->status->value,
            ])->all(),
        ], 'service_requests');
    }
}
