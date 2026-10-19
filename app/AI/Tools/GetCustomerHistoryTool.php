<?php

namespace App\AI\Tools;

use App\AI\Data\AIToolContext;
use App\AI\Support\RecordResolver;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Finance\CustomerBalanceService;

class GetCustomerHistoryTool extends ApplicationTool
{
    public function name(): string
    {
        return 'get_customer_history';
    }

    public function description(): string
    {
        return 'Recent requests, work orders, and the customer balance when the user can view finance.';
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
        $customer = app(RecordResolver::class)->customer($arguments['customer_id'] ?? null, $arguments['customer_name'] ?? null);

        if (! $context->user->can('view', $customer)) {
            return ['success' => false, 'data' => null, 'message' => 'You are not allowed to do that.'];
        }

        $balance = $context->user->can('viewAny', Invoice::class)
            ? app(CustomerBalanceService::class)->summary($customer)
            : null;

        return $this->ok([
            'customer' => ['id' => $customer->id, 'name' => $customer->name],
            'requests' => $customer->serviceRequests()->latest('requested_at')->limit(5)->get(['id', 'subject', 'status'])->map(fn ($row) => [
                'id' => $row->id,
                'subject' => $row->subject,
                'status' => $row->status->value,
            ])->all(),
            'work_orders' => $customer->workOrders()->latest()->limit(5)->get(['id', 'title', 'status'])->map(fn ($row) => [
                'id' => $row->id,
                'title' => $row->title,
                'status' => $row->status->value,
            ])->all(),
            'balance' => $balance === null ? null : [
                'currency' => $balance['currency'],
                'invoiced' => $balance['invoiced'],
                'paid' => $balance['paid'],
                'outstanding' => $balance['outstanding'],
                'overdue' => $balance['overdue'],
            ],
        ], 'customer_history');
    }
}
