<?php

use App\AI\Contracts\AIProviderInterface;
use App\AI\Exceptions\AIException;
use App\Enums\OrganizationRole;
use App\Models\AiAction;
use App\Models\Customer;
use App\Models\ServiceRequest;
use App\Models\Task;
use App\Support\OrganizationContext;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

it('reports a missing api key without calling openai', function () {
    Http::fake();
    config(['ai.provider' => 'openai', 'ai.api_key' => '']);

    [$owner, $organization] = openaiContext();

    actingInOrganization($owner, $organization)
        ->withHeaders(['origin' => 'http://localhost', 'referer' => 'http://localhost'])
        ->postJson('/api/ai/chat', ['message' => 'How many open service requests do we have?'])
        ->assertStatus(503)
        ->assertJsonPath('message', 'The assistant is not set up yet. Ask an administrator to add the AI API key.')
        ->assertJsonPath('reason', 'not_configured');

    Http::assertNothingSent();
});

it('reports an invalid api key without returning the provider body', function () {
    config(['ai.provider' => 'openai', 'ai.api_key' => 'sk-test-secret']);
    Http::fake([
        'api.openai.com/*' => Http::response(['error' => ['message' => 'bad key sk-test-secret']], 401),
    ]);

    [$owner, $organization] = openaiContext();

    actingInOrganization($owner, $organization)
        ->withHeaders(['origin' => 'http://localhost', 'referer' => 'http://localhost'])
        ->postJson('/api/ai/chat', ['message' => 'How many open service requests do we have?'])
        ->assertStatus(503)
        ->assertJsonPath('message', 'The assistant cannot sign in to the AI service. Ask an administrator to check the AI API key.')
        ->assertJsonPath('reason', 'invalid_api_key')
        ->assertDontSee('sk-test-secret');
});

it('reports a provider timeout', function () {
    config(['ai.provider' => 'openai', 'ai.api_key' => 'sk-test-secret']);
    Http::fake(fn () => throw new ConnectionException('timed out talking to sk-test-secret'));

    [$owner, $organization] = openaiContext();

    actingInOrganization($owner, $organization)
        ->withHeaders(['origin' => 'http://localhost', 'referer' => 'http://localhost'])
        ->postJson('/api/ai/chat', ['message' => 'How many open service requests do we have?'])
        ->assertStatus(504)
        ->assertJsonPath('message', 'The assistant took too long to answer. Try again, or ask a shorter question.')
        ->assertJsonPath('reason', 'timeout')
        ->assertDontSee('sk-test-secret');
});

it('reports a malformed model response', function () {
    config(['ai.provider' => 'openai', 'ai.api_key' => 'sk-test-secret']);
    Http::fake([
        'api.openai.com/v1/responses' => Http::response(['output' => 'not-an-array']),
    ]);

    [$owner, $organization] = openaiContext();

    actingInOrganization($owner, $organization)
        ->withHeaders(['origin' => 'http://localhost', 'referer' => 'http://localhost'])
        ->postJson('/api/ai/chat', ['message' => 'How many open service requests do we have?'])
        ->assertStatus(502)
        ->assertJsonPath('message', 'The assistant sent an answer it could not read. Try again.')
        ->assertJsonPath('reason', 'malformed_response');
});

it('records invalid tool arguments from the model', function () {
    config(['ai.provider' => 'openai', 'ai.api_key' => 'sk-test-secret']);
    Http::fake([
        'api.openai.com/v1/responses' => Http::sequence()
            ->push(openaiTool('create_task', []))
            ->push(openaiText('I could not save that task.')),
    ]);

    [$owner, $organization] = openaiContext();

    actingInOrganization($owner, $organization)
        ->withHeaders(['origin' => 'http://localhost', 'referer' => 'http://localhost'])
        ->postJson('/api/ai/chat', ['message' => 'Create a task for the technician.'])
        ->assertOk()
        ->assertJsonPath('pending_action', null)
        ->assertJsonPath('message.content', 'I could not save that task.');

    expect(AiAction::query()->where('status', 'failed')->exists())->toBeTrue()
        ->and(Task::query()->count())->toBe(0);
});

it('rejects an unauthorized tool call from the model', function () {
    config(['ai.provider' => 'openai', 'ai.api_key' => 'sk-test-secret']);
    Http::fake([
        'api.openai.com/v1/responses' => Http::sequence()
            ->push(openaiTool('create_task', ['title' => 'Secret task']))
            ->push(openaiText('I could not retrieve that from the application.')),
    ]);

    [$technician, $organization] = openaiContext(OrganizationRole::Technician);

    actingInOrganization($technician, $organization)
        ->withHeaders(['origin' => 'http://localhost', 'referer' => 'http://localhost'])
        ->postJson('/api/ai/chat', ['message' => 'Create a task for the technician.'])
        ->assertOk()
        ->assertJsonPath('pending_action', null);

    expect(Task::query()->count())->toBe(0);
});

it('stops after the configured tool call limit', function () {
    config(['ai.provider' => 'openai', 'ai.api_key' => 'sk-test-secret', 'ai.max_tool_calls' => 1]);
    Http::fake([
        'api.openai.com/v1/responses' => Http::response(openaiTool('get_open_service_requests', [])),
    ]);

    [$owner, $organization] = openaiContext();

    actingInOrganization($owner, $organization)
        ->withHeaders(['origin' => 'http://localhost', 'referer' => 'http://localhost'])
        ->postJson('/api/ai/chat', ['message' => 'How many open service requests do we have?'])
        ->assertOk()
        ->assertJsonPath('message.content', 'I stopped because that request needed too many lookups.');
});

it('sends tool results to the responses api and ignores model organization ids', function () {
    config(['ai.provider' => 'openai', 'ai.api_key' => 'sk-test-secret', 'ai.model' => 'gpt-4.1-mini']);
    Http::fake([
        'api.openai.com/v1/responses' => Http::sequence()
            ->push(openaiTool('get_open_service_requests', ['organization_id' => 'org-from-the-model']))
            ->push(openaiText('There is 1 open service request.')),
    ]);

    [$owner, $organization] = openaiContext();
    $customer = Customer::query()->create([
        'type' => 'individual',
        'name' => 'Open Customer',
        'phone' => '0550001772',
        'status' => 'active',
    ]);
    ServiceRequest::query()->create([
        'customer_id' => $customer->id,
        'source' => 'manual',
        'subject' => 'Open visit',
        'description' => 'Needs a visit.',
        'priority' => 'normal',
        'status' => 'new',
        'requested_at' => now(),
    ]);

    actingInOrganization($owner, $organization)
        ->withHeaders(['origin' => 'http://localhost', 'referer' => 'http://localhost'])
        ->postJson('/api/ai/chat', ['message' => 'How many open service requests do we have?'])
        ->assertOk()
        ->assertJsonPath('message.content', 'There is 1 open service request.');

    $requests = Http::recorded();
    expect($requests)->toHaveCount(2);

    [$request] = $requests[0];
    expect($request->url())->toBe('https://api.openai.com/v1/responses')
        ->and($request->data()['store'])->toBeFalse()
        ->and($request->data()['model'])->toBe('gpt-4.1-mini')
        ->and(collect($request->data()['tools'])->pluck('name'))->toContain('get_open_service_requests')
        ->and(json_encode($request->data()))->not->toContain('sk-test-secret');

    [$followUp] = $requests[1];
    $output = collect($followUp->data()['input'])->firstWhere('type', 'function_call_output');
    expect($output['output'])->toContain('"count":1')
        ->and($output['output'])->not->toContain('org-from-the-model');
});

it('runs the health check against a mocked response', function () {
    config(['ai.provider' => 'openai', 'ai.api_key' => 'sk-test-secret', 'ai.model' => 'gpt-4.1-mini']);
    Http::fake([
        'api.openai.com/v1/responses' => Http::response([
            'model' => 'gpt-4.1-mini',
            'output' => [[
                'type' => 'message',
                'content' => [['type' => 'output_text', 'text' => 'ok']],
            ]],
        ]),
    ]);

    $this->artisan('ai:health-check')
        ->expectsOutputToContain('Provider: openai')
        ->expectsOutputToContain('Model: gpt-4.1-mini')
        ->expectsOutputToContain('Latency:')
        ->doesntExpectOutputToContain('sk-test-secret')
        ->assertSuccessful();
});

it('refuses the health check outside local development', function () {
    app()['env'] = 'production';

    $this->artisan('ai:health-check')
        ->expectsOutputToContain('only in local development')
        ->assertFailed();
});

it('fails the health check when the api key is missing', function () {
    config(['ai.provider' => 'openai', 'ai.api_key' => null]);

    $this->artisan('ai:health-check')
        ->expectsOutputToContain('AI_API_KEY is missing.')
        ->assertFailed();
});

it('does not answer with the fake provider in production', function () {
    app()['env'] = 'production';
    config(['ai.provider' => 'fake']);
    app()->forgetInstance(AIProviderInterface::class);

    expect(fn () => app(AIProviderInterface::class)->complete([], []))
        ->toThrow(AIException::class, 'The assistant is not set up yet. Ask an administrator to add the AI API key.');
});

function openaiContext(OrganizationRole $role = OrganizationRole::Owner): array
{
    [$user, $organization] = createMember($role);
    OrganizationContext::set($organization, $role);

    return [$user, $organization];
}

/**
 * @param  array<string, mixed>  $arguments
 * @return array<string, mixed>
 */
function openaiTool(string $name, array $arguments): array
{
    return [
        'model' => 'gpt-4.1-mini',
        'output' => [[
            'type' => 'function_call',
            'call_id' => 'call_'.$name,
            'name' => $name,
            'arguments' => json_encode($arguments),
        ]],
    ];
}

/**
 * @return array<string, mixed>
 */
function openaiText(string $text): array
{
    return [
        'model' => 'gpt-4.1-mini',
        'output' => [[
            'type' => 'message',
            'content' => [['type' => 'output_text', 'text' => $text]],
        ]],
    ];
}
