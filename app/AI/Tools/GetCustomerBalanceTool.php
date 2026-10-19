<?php

namespace App\AI\Tools;

use App\AI\Data\AIToolContext;
use App\AI\Support\RecordResolver;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Finance\CustomerBalanceService;

class GetCustomerBalanceTool extends ApplicationTool
{
    public function name(): string
    {
        return 'get_customer_balance';
    }

    public function description(): string
    {
        return 'Customer invoiced, paid, outstanding, and overdue amounts from the finance balance service.';
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
        return $user->can('viewAny', Customer::class) && $user->can('viewAny', Invoice::class);
    }

    public function execute(array $arguments, AIToolContext $context): array
    {
        $customer = app(RecordResolver::class)->customer($arguments['customer_id'] ?? null, $arguments['customer_name'] ?? null);

        if (! $context->user->can('view', $customer)) {
            return ['success' => false, 'data' => null, 'message' => 'You are not allowed to do that.'];
        }

        $summary = app(CustomerBalanceService::class)->summary($customer);

        return $this->ok([
            'customer' => $customer->name,
            'currency' => $summary['currency'],
            'invoiced' => $summary['invoiced'],
            'paid' => $summary['paid'],
            'outstanding' => $summary['outstanding'],
            'overdue' => $summary['overdue'],
        ], 'customer_balance');
    }
}
