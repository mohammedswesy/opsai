<?php

use App\AI\Contracts\AIProviderInterface;
use App\AI\Data\AIToolContext;
use App\AI\Exceptions\AIException;
use App\AI\Tools\AIToolRegistry;
use App\AI\Tools\GetCustomerTool;
use App\AI\Tools\GetInvoiceTool;
use App\AI\Tools\GetWorkOrderTool;
use App\Enums\AiActionStatus;
use App\Enums\OrganizationRole;
use App\Enums\WorkOrderStatus;
use App\Models\Activity;
use App\Models\AiAction;
use App\Models\AiConversation;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\ServiceRequest;
use App\Models\Task;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\OrganizationContext;
use Illuminate\Support\Facades\Auth;

it('requires authentication for the assistant', function () {
    $this->postJson('/api/ai/chat', ['message' => 'Hello'])->assertUnauthorized();
});

it('answers open requests from application data', function () {
    [$owner, $organization] = aiContext();
    $customer = Customer::query()->create([
        'type' => 'individual',
        'name' => 'Open Customer',
        'phone' => '0550001772',
        'status' => 'active',
    ]);
    serviceRequest($customer, 'new');

    $response = aiChat($owner, $organization, 'How many open requests do we have?');

    $response->assertOk();
    expect($response->json('message.content'))->toBe('There are 1 open service requests.')
        ->and($response->json('tools_used'))->toContain('get_open_service_requests');
});

it('isolates customers, invoices, work orders, conversations, and actions', function () {
    [$owner, $organization] = aiContext();
    [$other, $otherOrganization] = createMember();
    OrganizationContext::set($otherOrganization, OrganizationRole::Owner);
    Auth::login($other);
    $customer = Customer::query()->create([
        'type' => 'individual',
        'name' => 'Hidden Harbor',
        'phone' => '0550001666',
        'status' => 'active',
    ]);
    $request = serviceRequest($customer, 'new');
    $order = WorkOrder::query()->create([
        'customer_id' => $customer->id,
        'service_request_id' => $request->id,
        'title' => 'Hidden order',
        'priority' => 'normal',
        'status' => WorkOrderStatus::Assigned,
    ]);
    $invoice = Invoice::query()->create([
        'customer_id' => $customer->id,
        'invoice_number' => 'INV-HIDDEN',
        'status' => 'issued',
        'issue_date' => now()->toDateString(),
        'currency' => 'AED',
        'subtotal' => 10,
        'discount' => 0,
        'tax' => 0,
        'total' => 10,
        'amount_paid' => 0,
        'amount_due' => 10,
    ]);
    $conversation = AiConversation::query()->create([
        'user_id' => $other->id,
        'title' => 'Secret chat',
        'status' => 'active',
    ]);
    $action = AiAction::query()->create([
        'user_id' => $other->id,
        'conversation_id' => $conversation->id,
        'tool_name' => 'create_task',
        'action_type' => 'write',
        'arguments' => ['title' => 'Secret'],
        'status' => AiActionStatus::Pending,
        'idempotency_key' => 'secret-key',
    ]);

    OrganizationContext::set($organization, OrganizationRole::Owner);
    $context = new AIToolContext($owner);
    $registry = app(AIToolRegistry::class);

    expect($registry->execute(app(GetCustomerTool::class), ['customer_id' => $customer->id, 'organization_id' => $otherOrganization->id], $context)['success'])->toBeFalse()
        ->and($registry->execute(app(GetInvoiceTool::class), ['invoice_id' => $invoice->id], $context)['success'])->toBeFalse()
        ->and($registry->execute(app(GetWorkOrderTool::class), ['work_order_id' => $order->id], $context)['success'])->toBeFalse();

    aiChat($owner, $organization, 'Hello', ['conversation_id' => $conversation->id])->assertNotFound();
    actingInOrganization($owner, $organization)
        ->withHeaders(['origin' => 'http://localhost', 'referer' => 'http://localhost'])
        ->postJson('/api/ai/actions/'.$action->id.'/confirm')
        ->assertNotFound();
    actingInOrganization($owner, $organization)->get('/ai/'.$conversation->id)->assertNotFound();
});

it('requires confirmation and does not duplicate a confirmed task', function () {
    [$owner, $organization] = aiContext();
    $technician = User::factory()->create(['name' => 'Riyadh Technician']);
    $organization->members()->attach($technician->id, ['role' => OrganizationRole::Technician->value]);
    $response = aiChat($owner, $organization, 'Create a task for the technician.');
    $response->assertOk();
    $actionId = $response->json('pending_action.id');
    expect($actionId)->not->toBeNull()
        ->and(Task::query()->count())->toBe(0);

    $confirm = actingInOrganization($owner, $organization)
        ->withHeaders(['origin' => 'http://localhost', 'referer' => 'http://localhost'])
        ->postJson('/api/ai/actions/'.$actionId.'/confirm');
    $confirm->assertOk()->assertJsonPath('action.status', 'executed');
    expect(Task::query()->where('title', 'Follow up for the technician')->count())->toBe(1)
        ->and(Activity::query()->where('action', 'task.created')->count())->toBe(1);

    actingInOrganization($owner, $organization)
        ->withHeaders(['origin' => 'http://localhost', 'referer' => 'http://localhost'])
        ->postJson('/api/ai/actions/'.$actionId.'/confirm')
        ->assertOk();
    expect(Task::query()->where('title', 'Follow up for the technician')->count())->toBe(1);
});

it('stops a technician from creating tasks through the assistant', function () {
    [$technician, $organization] = aiContext(OrganizationRole::Technician);
    $response = aiChat($technician, $organization, 'Create a task for the technician.');
    $response->assertOk();
    expect($response->json('pending_action'))->toBeNull()
        ->and($response->json('message.content'))->toBe('I could not retrieve that from the application.')
        ->and(Task::query()->count())->toBe(0);
});

it('records a failed action for malformed tool arguments', function () {
    [$owner, $organization] = aiContext();
    aiChat($owner, $organization, 'malformed task')->assertOk();
    expect(AiAction::query()->where('status', AiActionStatus::Failed)->exists())->toBeTrue()
        ->and(Task::query()->count())->toBe(0);
});

it('refuses invoice changes', function () {
    [$owner, $organization] = aiContext();
    $response = aiChat($owner, $organization, 'Create an invoice.');
    $response->assertOk();
    expect($response->json('message.content'))->toContain('cannot change invoices')
        ->and(Invoice::query()->count())->toBe(0);
});

it('returns a safe message when the provider fails', function () {
    [$owner, $organization] = aiContext();
    app()->instance(AIProviderInterface::class, new class implements AIProviderInterface
    {
        public function complete(array $messages, array $tools): never
        {
            throw new AIException('The assistant is unavailable right now.', 503, ['reason' => 'down']);
        }
    });

    aiChat($owner, $organization, 'How many open requests do we have?')
        ->assertStatus(503)
        ->assertJsonPath('message', 'The assistant is unavailable right now.');
});

it('rate limits assistant requests', function () {
    config(['ai.rate_limit_per_minute' => 1]);
    [$owner, $organization] = aiContext();
    aiChat($owner, $organization, 'Hello')->assertOk();
    aiChat($owner, $organization, 'Hello again')->assertStatus(429);
});

it('lists the registered tools', function () {
    $names = app(AIToolRegistry::class)->names();
    expect($names)->toContain('get_dashboard_metrics', 'get_overdue_invoices', 'get_customer_balance', 'create_task', 'create_work_order')
        ->and($names)->not->toContain('create_invoice', 'record_payment');
});

function aiContext(OrganizationRole $role = OrganizationRole::Owner): array
{
    [$user, $organization] = createMember($role);
    $organization->update(['timezone' => 'Asia/Riyadh', 'currency' => 'SAR']);
    OrganizationContext::set($organization->fresh(), $role);
    Auth::login($user);

    return [$user, $organization->fresh()];
}

function aiChat(User $user, $organization, string $message, array $extra = [])
{
    return actingInOrganization($user, $organization)
        ->withHeaders(['origin' => 'http://localhost', 'referer' => 'http://localhost'])
        ->postJson('/api/ai/chat', ['message' => $message, ...$extra]);
}

function serviceRequest(Customer $customer, string $status): ServiceRequest
{
    return ServiceRequest::query()->create([
        'customer_id' => $customer->id,
        'source' => 'manual',
        'subject' => 'Open visit',
        'description' => 'Needs a visit.',
        'priority' => 'normal',
        'status' => $status,
        'requested_at' => now(),
    ]);
}
