<?php

namespace App\AI\Tools;

use App\AI\Data\AIToolContext;
use App\AI\Support\RecordResolver;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class GetCustomerTool extends ApplicationTool
{
    public function name(): string
    {
        return 'get_customer';
    }

    public function description(): string
    {
        return 'Find one customer in the current organization by id or exact name.';
    }

    public function schema(): array
    {
        return $this->objectSchema([
            'customer_id' => ['type' => 'string'],
            'customer_name' => ['type' => 'string'],
        ]);
    }

    public function authorize(User $user): bool
    {
        return $user->can('viewAny', Customer::class);
    }

    public function execute(array $arguments, AIToolContext $context): array
    {
        try {
            $customer = app(RecordResolver::class)->customer($arguments['customer_id'] ?? null, $arguments['customer_name'] ?? null);
        } catch (ValidationException $exception) {
            throw $exception;
        }

        if (! $context->user->can('view', $customer)) {
            return ['success' => false, 'data' => null, 'message' => 'You are not allowed to do that.'];
        }

        return $this->ok([
            'id' => $customer->id,
            'name' => $customer->name,
            'type' => $customer->type->value,
            'phone' => $customer->phone,
            'city' => $customer->city,
            'status' => $customer->status->value,
        ], 'customers');
    }
}
