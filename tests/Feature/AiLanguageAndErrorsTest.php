<?php

use App\AI\Contracts\AIProviderInterface;
use App\AI\Data\AIMessage;
use App\AI\Data\AIResponse;
use App\AI\Data\AIToolCall;
use App\AI\Providers\FakeAIProvider;
use App\AI\Support\ReplyLanguage;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use App\Support\OrganizationContext;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;

/**
 * FakeAIProvider that records every transcript it receives. `$script` can replace a
 * reply (for example with a tool call) so the confirmation flow runs for any language.
 */
class RecordingFakeProvider extends FakeAIProvider
{
    /** @var list<list<AIMessage>> */
    public array $transcripts = [];

    public ?Closure $script = null;

    public function complete(array $messages, array $tools): AIResponse
    {
        $this->transcripts[] = $messages;

        return ($this->script ? ($this->script)($messages) : null) ?? parent::complete($messages, $tools);
    }

    /** System text of the latest request. */
    public function systemText(): string
    {
        return collect(end($this->transcripts) ?: [])
            ->filter(fn (AIMessage $message) => $message->role === 'system')
            ->map(fn (AIMessage $message) => (string) $message->content)
            ->implode("\n\n");
    }
}

/** @return array{0: User, 1: Organization, 2: RecordingFakeProvider} */
function languageContext(string $organizationLocale = 'en'): array
{
    config(['ai.provider' => 'fake']);
    [$user, $organization] = createMember();
    $organization->update(['locale' => $organizationLocale, 'timezone' => 'Asia/Riyadh', 'currency' => 'SAR']);
    OrganizationContext::set($organization->fresh(), OrganizationRole::Owner);
    $fake = new RecordingFakeProvider;
    app()->instance(AIProviderInterface::class, $fake);

    return [$user, $organization->fresh(), $fake];
}

function languageChat(User $user, Organization $organization, string $message, ?string $sessionLocale = null, array $extra = []): TestResponse
{
    return test()->actingAs($user)
        ->withSession(array_filter(['current_organization_id' => $organization->id, 'locale' => $sessionLocale]))
        ->withHeaders(['origin' => 'http://localhost', 'referer' => 'http://localhost'])
        ->postJson('/api/ai/chat', ['message' => $message, ...$extra]);
}

describe('reply language', function () {
    it('answers an Arabic message in Arabic even when the interface is English', function () {
        [$user, $organization, $fake] = languageContext();

        languageChat($user, $organization, 'كم طلب صيانة مفتوح عندنا؟', 'en')->assertOk();

        expect($fake->systemText())
            ->toContain('Reply language: Arabic.')
            ->toContain("The user's latest message is in Arabic.");
    });

    it('answers an English message in English even when the interface is Arabic', function () {
        [$user, $organization, $fake] = languageContext('ar');

        languageChat($user, $organization, 'How many open requests do we have?', 'ar')->assertOk();

        expect($fake->systemText())->toContain('Reply language: English.');
    });

    it('falls back to the interface locale when the message has no letters', function (string $locale, string $language) {
        [$user, $organization, $fake] = languageContext();

        languageChat($user, $organization, '2026-000123 ؟', $locale)->assertOk();

        expect($fake->systemText())
            ->toContain("Reply language: {$language}.")
            ->toContain("so use their interface language, {$language}.");
    })->with([['ar', 'Arabic'], ['en', 'English']]);

    it('uses the organization locale when there is no session locale', function () {
        [$user, $organization, $fake] = languageContext('ar');

        languageChat($user, $organization, '42')->assertOk();

        expect($fake->systemText())->toContain('Reply language: Arabic.');
    });

    it('follows the user when they switch language in the same conversation', function () {
        [$user, $organization, $fake] = languageContext();

        $first = languageChat($user, $organization, 'كم طلب مفتوح؟', 'en')->assertOk();
        expect($fake->systemText())->toContain('Reply language: Arabic.');

        languageChat($user, $organization, 'And in English please: how many open requests?', 'en', [
            'conversation_id' => $first->json('conversation_id'),
        ])->assertOk();
        expect($fake->systemText())->toContain('Reply language: English.');
    });

    it('keeps the rule in one system message, separate from the prompts', function () {
        [$user, $organization, $fake] = languageContext();

        languageChat($user, $organization, 'How many open requests do we have?', 'en')->assertOk();

        $system = collect(end($fake->transcripts))->filter(fn (AIMessage $message) => $message->role === 'system');
        expect($system)->toHaveCount(2)
            ->and($system->filter(fn (AIMessage $message) => str_contains((string) $message->content, 'Reply language:')))->toHaveCount(1)
            ->and($system->first()->content)->not->toContain("Answer in the user's language")
            ->and($system->first()->content)->not->toContain('"locale"');
    });

    it('asks for confirmation in Arabic and still saves nothing until confirmed', function () {
        [$user, $organization, $fake] = languageContext();
        $technician = User::factory()->create(['name' => 'Riyadh Technician']);
        $organization->members()->attach($technician->id, ['role' => OrganizationRole::Technician->value]);
        $fake->script = fn (array $messages) => collect($messages)->last()->role === 'user'
            ? new AIResponse(null, [new AIToolCall('call_1', 'create_task', [
                'title' => 'متابعة مع الفني',
                'assignee_name' => 'Riyadh Technician',
                'priority' => 'normal',
            ])], [], 'fake')
            : null;

        $response = languageChat($user, $organization, 'أنشئ مهمة متابعة للفني', 'en')->assertOk();

        expect($response->json('message.content'))->toBe('يرجى تأكيد هذا الإجراء قبل أن أحفظه.')
            ->and($response->json('pending_action.tool_name'))->toBe('create_task')
            ->and(Task::query()->count())->toBe(0);
    });

    it('keeps the English confirmation text for English messages', function () {
        [$user, $organization] = languageContext('ar');
        $technician = User::factory()->create(['name' => 'Riyadh Technician']);
        $organization->members()->attach($technician->id, ['role' => OrganizationRole::Technician->value]);

        languageChat($user, $organization, 'Create a task for the technician.', 'ar')
            ->assertOk()
            ->assertJsonPath('message.content', 'Please confirm this action before I save it.');
    });

    it('detects the language from the letters in mixed text', function (string $text, string $code) {
        expect(ReplyLanguage::resolve($text, 'en')->code)->toBe($code);
    })->with([
        'arabic with a reference' => ['اعرض أمر العمل WO-12', 'ar'],
        'english with an arabic name' => ['Show the customer محمد and his visits', 'en'],
        'emoji only' => ['👍', 'en'],
        'arabic punctuation and digits only' => ['؟ ١٢٣', 'en'],
    ]);
});

describe('provider errors', function () {
    beforeEach(function () {
        config(['ai.provider' => 'openai', 'ai.api_key' => 'sk-test-secret']);
        app()->forgetInstance(AIProviderInterface::class);
    });

    it('maps each OpenAI failure to its own message without leaking the provider body', function (int $status, array $error, int $expectedStatus, string $reason, string $message) {
        Log::spy();
        Http::fake([
            'api.openai.com/*' => Http::response(['error' => [...$error, 'message' => 'provider detail for sk-test-secret']], $status, ['x-request-id' => 'req_123']),
        ]);
        [$user, $organization] = languageContext();
        config(['ai.provider' => 'openai']);
        app()->forgetInstance(AIProviderInterface::class);

        languageChat($user, $organization, 'How many open requests do we have?', 'en')
            ->assertStatus($expectedStatus)
            ->assertExactJson(['message' => $message, 'reason' => $reason])
            ->assertDontSee('sk-test-secret')
            ->assertDontSee('provider detail');

        Log::shouldHaveReceived('warning')->withArgs(function (string $event, array $context) use ($reason, $status) {
            return $event === 'ai.openai.http'
                && $context['reason'] === $reason
                && $context['status'] === $status
                && $context['request_id'] === 'req_123'
                && ! str_contains(json_encode($context), 'sk-test-secret')
                && ! str_contains(json_encode($context), 'provider detail');
        })->once();
    })->with([
        'insufficient quota' => [429, ['type' => 'insufficient_quota', 'code' => 'insufficient_quota'], 503, 'insufficient_quota', 'The AI service account has run out of credit. Ask an administrator to check the billing.'],
        'rate limit' => [429, ['type' => 'requests', 'code' => 'rate_limit_exceeded'], 429, 'rate_limited', 'The AI service is getting too many requests. Wait a minute and try again.'],
        'invalid key' => [401, ['type' => 'invalid_request_error', 'code' => 'invalid_api_key'], 503, 'invalid_api_key', 'The assistant cannot sign in to the AI service. Ask an administrator to check the AI API key.'],
        'gateway timeout' => [504, ['type' => 'server_error'], 504, 'timeout', 'The assistant took too long to answer. Try again, or ask a shorter question.'],
        'server error' => [500, ['type' => 'server_error'], 503, 'unavailable', 'The AI service is unavailable right now. Try again later.'],
    ]);

    it('tells a timeout apart from an unreachable service', function (string $exception, int $status, string $reason) {
        Http::fake(fn () => throw new ConnectionException($exception));
        [$user, $organization] = languageContext();
        config(['ai.provider' => 'openai']);
        app()->forgetInstance(AIProviderInterface::class);

        languageChat($user, $organization, 'How many open requests do we have?', 'en')
            ->assertStatus($status)
            ->assertJsonPath('reason', $reason)
            ->assertDontSee('sk-test-secret');
    })->with([
        'timeout' => ['cURL error 28: Operation timed out after 20001 milliseconds (sk-test-secret)', 504, 'timeout'],
        'dns' => ['cURL error 6: Could not resolve host: api.openai.com', 503, 'unreachable'],
    ]);

    it('shows the error in Arabic for an Arabic interface', function () {
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['code' => 'insufficient_quota']], 429)]);
        [$user, $organization] = languageContext();
        config(['ai.provider' => 'openai']);
        app()->forgetInstance(AIProviderInterface::class);

        languageChat($user, $organization, 'كم طلب مفتوح؟', 'ar')
            ->assertStatus(503)
            ->assertJsonPath('reason', 'insufficient_quota')
            ->assertJsonPath('message', 'نفد رصيد حساب خدمة الذكاء الاصطناعي. اطلب من المسؤول مراجعة الفوترة.');
    });

    it('says when the user sends messages too quickly, in their language', function () {
        config(['ai.provider' => 'fake', 'ai.rate_limit_per_minute' => 1]);
        [$user, $organization] = languageContext();

        languageChat($user, $organization, 'Hello', 'ar')->assertOk();
        languageChat($user, $organization, 'Hello again', 'ar')
            ->assertStatus(429)
            ->assertJsonPath('reason', 'too_many_messages')
            ->assertJsonPath('message', 'أنت ترسل الرسائل بسرعة كبيرة. انتظر دقيقة ثم حاول مرة أخرى.');
    });

    it('prints the failure reason in the health check without the key', function () {
        config(['ai.model' => 'gpt-4.1-mini']);
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['code' => 'insufficient_quota', 'message' => 'quota for sk-test-secret']], 429)]);

        $this->artisan('ai:health-check')
            ->expectsOutputToContain('Reason: insufficient_quota')
            ->expectsOutputToContain('run out of credit')
            ->doesntExpectOutputToContain('sk-test-secret')
            ->assertFailed();
    });
});
