<?php

use App\AI\Contracts\AIProviderInterface;
use App\AI\Data\AIResponse;
use App\Enums\OrganizationRole;
use App\Jobs\ProcessWhatsAppEvent;
use App\Models\Customer;
use App\Models\Integration;
use App\Models\Organization;
use App\Models\User;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppWebhookEvent;
use App\Support\OrganizationContext;
use App\WhatsApp\Contracts\WhatsAppProviderInterface;
use App\WhatsApp\WhatsAppMessenger;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;

const LIVE_PHONE_ID = '200000000000002';

/** An AI provider that fails the test if the assistant is asked anything. */
function forbidAssistant(): void
{
    app()->instance(AIProviderInterface::class, new class implements AIProviderInterface
    {
        public function complete(array $messages, array $tools): AIResponse
        {
            throw new RuntimeException('The assistant must not be called for this message.');
        }
    });
}

/** @return array{0: Organization, 1: Integration, 2: User} */
function liveIntegration(array $credentials = ['access_token' => 'saved-access-token'], array $metadata = []): array
{
    [$owner, $organization] = createMember();
    $organization->update(['country' => 'SA', 'timezone' => 'Asia/Riyadh', 'locale' => 'ar']);
    OrganizationContext::set($organization, OrganizationRole::Owner);
    $integration = Integration::query()->create([
        'provider' => 'fake',
        'type' => 'whatsapp',
        'status' => 'active',
        'credentials' => $credentials,
        'metadata' => ['phone_number_id' => LIVE_PHONE_ID, ...$metadata],
    ]);
    OrganizationContext::forget();

    return [$organization->fresh(), $integration, $owner];
}

function liveCustomer(Organization $organization, string $phone = '0550002001'): Customer
{
    OrganizationContext::set($organization, OrganizationRole::Owner);
    $customer = Customer::query()->create(['type' => 'individual', 'name' => 'Live Customer', 'phone' => $phone, 'country' => 'SA', 'status' => 'active', 'source' => 'manual']);
    OrganizationContext::forget();

    return $customer;
}

/**
 * A webhook body shaped like the ones Meta sends.
 *
 * @param  array<string, mixed>  $value
 */
function metaBody(array $value): string
{
    return json_encode([
        'object' => 'whatsapp_business_account',
        'entry' => [[
            'id' => '300000000000003',
            'changes' => [[
                'field' => 'messages',
                'value' => [
                    'messaging_product' => 'whatsapp',
                    'metadata' => ['display_phone_number' => '15550001111', 'phone_number_id' => LIVE_PHONE_ID],
                    ...$value,
                ],
            ]],
        ]],
    ], JSON_UNESCAPED_UNICODE);
}

/** @param  array<string, mixed>  $message */
function metaMessage(array $message, string $from = '966550002001'): string
{
    return metaBody([
        'contacts' => [['profile' => ['name' => 'Live Customer'], 'wa_id' => $from]],
        'messages' => [['from' => $from, 'timestamp' => (string) now()->timestamp, ...$message]],
    ]);
}

function metaStatus(string $id, string $status, array $extra = []): string
{
    return metaBody(['statuses' => [[
        'id' => $id,
        'status' => $status,
        'timestamp' => (string) now()->timestamp,
        'recipient_id' => '966550002001',
        'conversation' => ['id' => 'conv-1', 'origin' => ['type' => 'service']],
        'pricing' => ['billable' => true, 'pricing_model' => 'CBP', 'category' => 'service'],
        ...$extra,
    ]]]);
}

function signedPost(string $body, string $secret = 'app-secret'): TestResponse
{
    return test()->call('POST', '/webhooks/whatsapp', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, $secret),
    ], $body);
}

function liveMessages(Organization $organization)
{
    return WhatsAppMessage::query()->withoutGlobalScope('organization')->where('organization_id', $organization->id);
}

describe('webhook secrets', function () {
    it('accepts the verify token saved on the settings page', function () {
        config(['whatsapp.verify_token' => null]);
        liveIntegration(['access_token' => 'token', 'verify_token' => 'saved-verify-token']);

        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=saved-verify-token&hub.challenge=1158201444')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSeeText('1158201444');

        $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=guess&hub.challenge=1')->assertForbidden();
    });

    it('checks the signature with the app secret saved on the settings page', function () {
        config(['whatsapp.app_secret' => null]);
        liveIntegration(['access_token' => 'token', 'app_secret' => 'saved-app-secret']);
        $body = metaMessage(['id' => 'wamid.saved-secret', 'type' => 'text', 'text' => ['body' => 'hello']]);

        signedPost($body, 'saved-app-secret')->assertOk();
        signedPost(metaMessage(['id' => 'wamid.wrong', 'type' => 'text', 'text' => ['body' => 'hi']]), 'wrong-secret')->assertUnauthorized();

        expect(WhatsAppWebhookEvent::query()->pluck('external_event_id')->all())->toBe(['wamid.saved-secret']);
    });

    it('rejects a missing signature header', function () {
        liveIntegration();

        $this->call('POST', '/webhooks/whatsapp', [], [], [], ['CONTENT_TYPE' => 'application/json'], metaMessage(['id' => 'wamid.nosig', 'type' => 'text', 'text' => ['body' => 'hi']]))
            ->assertUnauthorized();
    });
});

describe('message types', function () {
    it('keeps an image without a caption for staff and does not ask the assistant', function () {
        [$organization] = liveIntegration();
        liveCustomer($organization);
        forbidAssistant();

        signedPost(metaMessage(['id' => 'wamid.text-ar', 'type' => 'reaction', 'reaction' => ['message_id' => 'x', 'emoji' => '👍']]))->assertOk();
        signedPost(metaMessage(['id' => 'wamid.img1', 'type' => 'image', 'image' => ['id' => 'media-1', 'mime_type' => 'image/jpeg', 'sha256' => 'abc']]))->assertOk();
        signedPost(metaMessage(['id' => 'wamid.img2', 'type' => 'image', 'image' => ['id' => 'media-2', 'mime_type' => 'image/jpeg']]))->assertOk();

        $image = liveMessages($organization)->where('external_message_id', 'wamid.img1')->first();
        expect($image->message_type)->toBe('image')
            ->and($image->text)->toBeNull()
            ->and($image->media)->toBe(['id' => 'media-1', 'mime_type' => 'image/jpeg'])
            ->and(liveMessages($organization)->where('direction', 'outbound')->pluck('text')->all())
            ->toBe(['حالياً أقدر أقرأ الرسائل النصية فقط. اكتب لي طلبك لو سمحت.'])
            ->and(WhatsAppWebhookEvent::query()->where('event_type', 'message')->pluck('status')->unique()->all())->toBe(['processed']);
    });

    it('stores audio and a location without errors', function (array $message, array $media) {
        [$organization] = liveIntegration();
        liveCustomer($organization);
        forbidAssistant();

        signedPost(metaMessage($message))->assertOk();

        $stored = liveMessages($organization)->where('direction', 'inbound')->first();
        expect($stored->message_type)->toBe($message['type'])
            ->and($stored->media)->toBe($media)
            ->and(WhatsAppWebhookEvent::query()->first()->status)->toBe('processed');
    })->with([
        'audio' => [['id' => 'wamid.audio', 'type' => 'audio', 'audio' => ['id' => 'media-a', 'mime_type' => 'audio/ogg; codecs=opus', 'voice' => true]], ['id' => 'media-a', 'mime_type' => 'audio/ogg; codecs=opus']],
        'location' => [['id' => 'wamid.loc', 'type' => 'location', 'location' => ['latitude' => 24.7136, 'longitude' => 46.6753, 'name' => 'Office']], ['latitude' => 24.7136, 'longitude' => 46.6753, 'name' => 'Office']],
        'unsupported' => [['id' => 'wamid.unsup', 'type' => 'unsupported', 'errors' => [['code' => 131051, 'title' => 'Message type unknown']]], ['error_code' => 131051]],
    ]);

    it('answers an English customer in English when they send media', function () {
        [$organization] = liveIntegration();
        liveCustomer($organization);

        signedPost(metaMessage(['id' => 'wamid.en-text', 'type' => 'text', 'text' => ['body' => 'How much revenue did the company make?']]))->assertOk();
        forbidAssistant();
        signedPost(metaMessage(['id' => 'wamid.sticker', 'type' => 'sticker', 'sticker' => ['id' => 'media-s', 'mime_type' => 'image/webp', 'animated' => false]]))->assertOk();

        expect(liveMessages($organization)->where('direction', 'outbound')->latest('id')->value('text'))
            ->toBe('I can only read text messages for now. Please type your request.');
    });

    it('reads an image caption and a button reply as text', function (array $message) {
        [$organization] = liveIntegration();
        liveCustomer($organization);

        signedPost(metaMessage($message))->assertOk();

        expect(liveMessages($organization)->where('direction', 'inbound')->value('text'))->toBe('The air conditioner is not cooling.')
            ->and(liveMessages($organization)->where('direction', 'outbound')->value('text'))->not->toContain('only read text');
    })->with([
        'caption' => [['id' => 'wamid.caption', 'type' => 'image', 'image' => ['id' => 'media-c', 'caption' => 'The air conditioner is not cooling.']]],
        'button' => [['id' => 'wamid.button', 'type' => 'interactive', 'interactive' => ['type' => 'button_reply', 'button_reply' => ['id' => 'b1', 'title' => 'The air conditioner is not cooling.']]]],
    ]);

    it('does not start onboarding when an unknown number sends media', function () {
        [$organization] = liveIntegration();
        forbidAssistant();

        signedPost(metaMessage(['id' => 'wamid.unknown-img', 'type' => 'image', 'image' => ['id' => 'm']], '966550009999'))->assertOk();

        $conversation = WhatsAppConversation::query()->withoutGlobalScope('organization')->first();
        expect($conversation->metadata)->toBeNull()
            ->and(liveMessages($organization)->where('direction', 'outbound')->count())->toBe(1);
    });
});

it('still runs the local simulator through the webhook', function () {
    [$organization, , $owner] = liveIntegration();
    liveCustomer($organization);

    actingInOrganization($owner, $organization)
        ->post('/whatsapp/simulate', ['from' => '966550002001', 'text' => 'How much revenue did the company make?'])
        ->assertRedirect();

    expect(liveMessages($organization)->where('direction', 'inbound')->count())->toBe(1)
        ->and(liveMessages($organization)->where('direction', 'outbound')->count())->toBe(1);
});

it('shows staff what a media message was and why a reply failed', function () {
    [$organization, , $owner] = liveIntegration();
    liveCustomer($organization);
    forbidAssistant();
    signedPost(metaMessage(['id' => 'wamid.voice', 'type' => 'audio', 'audio' => ['id' => 'media-v', 'mime_type' => 'audio/ogg']]))->assertOk();
    $notice = liveMessages($organization)->where('direction', 'outbound')->first();
    $notice->update(['status' => 'failed', 'metadata' => ['author' => 'system', 'reason' => 'recipient_not_allowed']]);

    actingInOrganization($owner, $organization)
        ->get('/whatsapp/'.$notice->whatsapp_conversation_id)
        ->assertInertia(fn ($page) => $page
            ->where('messages.0.type', 'audio')
            ->where('messages.0.media', ['mime_type' => 'audio/ogg'])
            ->where('messages.0.failure', null)
            ->where('messages.1.failure', 'recipient_not_allowed'));
});

describe('statuses and retries', function () {
    it('applies sent, delivered and read in any order, and late ones too', function () {
        [$organization, $integration] = liveIntegration();
        liveCustomer($organization);
        signedPost(metaMessage(['id' => 'wamid.in', 'type' => 'text', 'text' => ['body' => 'How much revenue did the company make?']]))->assertOk();
        $outbound = liveMessages($organization)->where('direction', 'outbound')->first();
        $id = $outbound->external_message_id;

        signedPost(metaStatus($id, 'read', ['timestamp' => (string) now()->subHours(3)->timestamp]))->assertOk();
        signedPost(metaStatus($id, 'delivered'))->assertOk();
        signedPost(metaStatus($id, 'sent'))->assertOk();

        expect($outbound->fresh()->status)->toBe('read')
            ->and(WhatsAppWebhookEvent::query()->where('event_type', 'status')->pluck('status')->unique()->all())->toBe(['processed']);
    });

    it('records the Meta error code of a failed delivery', function () {
        [$organization] = liveIntegration();
        liveCustomer($organization);
        signedPost(metaMessage(['id' => 'wamid.in2', 'type' => 'text', 'text' => ['body' => 'How much revenue did the company make?']]))->assertOk();
        $outbound = liveMessages($organization)->where('direction', 'outbound')->first();

        signedPost(metaStatus($outbound->external_message_id, 'failed', ['errors' => [[
            'code' => 131047,
            'title' => 'Re-engagement message',
            'error_data' => ['details' => 'Message failed to send because more than 24 hours have passed.'],
        ]]]))->assertOk();

        expect($outbound->fresh())
            ->status->toBe('failed')
            ->metadata->toMatchArray(['reason' => 'outside_24_hour_window', 'error_code' => 131047, 'error_title' => 'Re-engagement message']);
    });

    it('ignores statuses for messages it does not know', function () {
        liveIntegration();

        signedPost(metaStatus('wamid.not-ours', 'delivered'))->assertOk();

        expect(WhatsAppWebhookEvent::query()->first()->status)->toBe('processed');
    });

    it('treats a Meta retry of the same message as one message with one reply', function () {
        [$organization] = liveIntegration();
        liveCustomer($organization);
        $body = metaMessage(['id' => 'wamid.retry', 'type' => 'text', 'text' => ['body' => 'How much revenue did the company make?']]);

        signedPost($body)->assertOk();
        signedPost($body)->assertOk();
        signedPost($body)->assertOk();

        expect(WhatsAppWebhookEvent::query()->count())->toBe(1)
            ->and(liveMessages($organization)->where('direction', 'inbound')->count())->toBe(1)
            ->and(liveMessages($organization)->where('direction', 'outbound')->count())->toBe(1);
    });

    it('queues a retried event again when the first job never ran', function () {
        liveIntegration();
        Queue::fake();
        $body = metaMessage(['id' => 'wamid.lost-job', 'type' => 'text', 'text' => ['body' => 'hello']]);

        signedPost($body)->assertOk();
        signedPost($body)->assertOk();

        $event = WhatsAppWebhookEvent::query()->sole();
        Queue::assertPushed(ProcessWhatsAppEvent::class, fn (ProcessWhatsAppEvent $job) => $job->eventId === $event->id);
        Queue::assertPushed(ProcessWhatsAppEvent::class, 2);
    });

    it('keeps a stale message for the record without replying', function () {
        [$organization] = liveIntegration();
        liveCustomer($organization);

        signedPost(metaMessage(['id' => 'wamid.old', 'type' => 'text', 'text' => ['body' => 'hello'], 'timestamp' => (string) now()->subHours(2)->timestamp]))->assertOk();

        expect(WhatsAppWebhookEvent::query()->first())
            ->status->toBe('ignored')
            ->error->toBe('stale_message')
            ->and(liveMessages($organization)->count())->toBe(0);
    });

    it('acknowledges the sample from the Meta dashboard and payloads with missing parts', function (string $body) {
        liveIntegration();

        signedPost($body)->assertOk();
    })->with([
        'dashboard test' => [json_encode(['object' => 'whatsapp_business_account', 'entry' => [['id' => '0', 'changes' => [['field' => 'messages', 'value' => [
            'messaging_product' => 'whatsapp',
            'metadata' => ['display_phone_number' => '16505551111', 'phone_number_id' => '123456123'],
            'contacts' => [['profile' => ['name' => 'test user name'], 'wa_id' => '16315551181']],
            'messages' => [['from' => '16315551181', 'id' => 'ABGGFlA5Fpa', 'timestamp' => '1504902988', 'type' => 'text', 'text' => ['body' => 'this is a text message']]],
        ]]]]]])],
        'template status update' => [json_encode(['object' => 'whatsapp_business_account', 'entry' => [['id' => '1', 'changes' => [['field' => 'message_template_status_update', 'value' => ['event' => 'APPROVED']]]]]])],
        'no entry' => [json_encode(['object' => 'whatsapp_business_account'])],
        'entry not a list' => [json_encode(['entry' => 'oops'])],
        'message without id' => [metaBody(['messages' => [['from' => '966550002001', 'timestamp' => (string) time(), 'type' => 'text', 'text' => ['body' => 'x']]]])],
        'not json' => ['not json at all'],
    ]);

    it('marks a message without a sender as failed instead of crashing', function () {
        liveIntegration();

        signedPost(metaBody(['messages' => [['id' => 'wamid.nofrom', 'timestamp' => (string) time(), 'type' => 'text', 'text' => ['body' => 'x']]]]))->assertOk();

        expect(WhatsAppWebhookEvent::query()->first()->status)->toBe('failed');
    });

    it('marks an event failed, not stuck, when processing crashes', function () {
        [$organization] = liveIntegration();
        liveCustomer($organization);
        forbidAssistant();

        signedPost(metaMessage(['id' => 'wamid.crash', 'type' => 'text', 'text' => ['body' => 'hello there']]))->assertOk();

        expect(WhatsAppWebhookEvent::query()->first())
            ->status->toBe('failed')
            ->error->toBe('Unexpected error while processing the event.');
    });
});

describe('sending through Meta', function () {
    it('names the Meta error and logs it without the token or message', function (int $code, int $status, string $reason) {
        config(['whatsapp.provider' => 'meta']);
        app()->forgetInstance(WhatsAppProviderInterface::class);
        Log::spy();
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => [
            'message' => 'Detailed text mentioning saved-access-token',
            'type' => 'OAuthException',
            'code' => $code,
            'fbtrace_id' => 'trace-1',
        ]], $status)]);
        [$organization, $integration] = liveIntegration();
        liveCustomer($organization);
        OrganizationContext::set($organization, OrganizationRole::Owner);
        $conversation = WhatsAppConversation::query()->create(['integration_id' => $integration->id, 'contact_phone' => '966550002001', 'status' => 'open', 'unread_count' => 0]);

        $message = app(WhatsAppMessenger::class)->sendText($conversation, $integration, 'Hello', 'staff');

        expect($message->status)->toBe('failed')
            ->and($message->metadata['reason'])->toBe($reason);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer saved-access-token'));
        Log::shouldHaveReceived('warning')->withArgs(fn (string $event, array $context) => $event === 'whatsapp.meta.send'
            && $context['reason'] === $reason
            && $context['error_code'] === $code
            && $context['fbtrace_id'] === 'trace-1'
            && ! str_contains(json_encode($context), 'saved-access-token')
            && ! str_contains(json_encode($context), 'Detailed text'))->once();
    })->with([
        'outside the 24 hour window' => [131047, 400, 'outside_24_hour_window'],
        'test recipient not added' => [131030, 400, 'recipient_not_allowed'],
        'expired token' => [190, 401, 'access_token_invalid'],
        'rate limit' => [130429, 429, 'rate_limited'],
    ]);
});

describe('whatsapp:check', function () {
    it('lists what is missing without calling Meta or printing values', function () {
        Http::fake();
        config(['whatsapp.provider' => 'fake', 'whatsapp.verify_token' => null, 'whatsapp.app_secret' => null, 'whatsapp.access_token' => null]);

        $this->artisan('whatsapp:check')
            ->expectsOutputToContain('WHATSAPP_PROVIDER is meta')
            ->expectsOutputToContain('Callback URL path: /webhooks/whatsapp')
            ->expectsOutputToContain('Set WHATSAPP_PROVIDER=meta')
            ->expectsOutputToContain('Set WHATSAPP_VERIFY_TOKEN')
            ->expectsOutputToContain('Set WHATSAPP_APP_SECRET')
            ->expectsOutputToContain('Save the WhatsApp settings')
            ->expectsOutputToContain('Result: not ready')
            ->assertFailed();

        Http::assertNothingSent();
    });

    it('reports ready from saved settings and never prints a secret', function () {
        Http::fake();
        config(['whatsapp.provider' => 'meta', 'whatsapp.verify_token' => null, 'whatsapp.app_secret' => null, 'whatsapp.access_token' => null]);
        liveIntegration(['access_token' => 'EAAG-secret-token', 'app_secret' => 'secret-app-value', 'verify_token' => 'secret-verify-value'], ['business_account_id' => '300000000000003']);

        $this->artisan('whatsapp:check')
            ->expectsOutputToContain('Result: ready for a live test.')
            ->doesntExpectOutputToContain('EAAG-secret-token')
            ->doesntExpectOutputToContain('secret-app-value')
            ->doesntExpectOutputToContain('secret-verify-value')
            ->doesntExpectOutputToContain(LIVE_PHONE_ID)
            ->assertSuccessful();

        Http::assertNothingSent();
    });

    it('flags two organizations sharing one phone number ID', function () {
        config(['whatsapp.provider' => 'meta', 'whatsapp.verify_token' => 'v', 'whatsapp.app_secret' => 's']);
        liveIntegration();
        liveIntegration();

        $this->artisan('whatsapp:check')
            ->expectsOutputToContain('use the same phone number ID')
            ->assertFailed();
    });

    it('checks the phone number and the app subscription with --live', function () {
        config(['whatsapp.provider' => 'meta', 'whatsapp.access_token' => null]);
        liveIntegration(['access_token' => 'EAAG-secret-token', 'app_secret' => 's', 'verify_token' => 'v'], ['business_account_id' => '300000000000003']);
        Http::fake([
            'graph.facebook.com/*/'.LIVE_PHONE_ID => Http::response(['display_phone_number' => '15550001111', 'id' => LIVE_PHONE_ID]),
            'graph.facebook.com/*/300000000000003/subscribed_apps' => Http::response(['data' => []]),
            'graph.facebook.com/*/debug_token*' => Http::response(['data' => ['is_valid' => true, 'type' => 'SYSTEM_USER', 'expires_at' => 0]]),
        ]);

        $this->artisan('whatsapp:check --live')
            ->expectsOutputToContain('Phone number reachable with the access token')
            ->expectsOutputToContain('long-lived')
            ->expectsOutputToContain('is not subscribed to the business account')
            ->doesntExpectOutputToContain('EAAG-secret-token')
            ->doesntExpectOutputToContain('15550001111')
            ->assertFailed();

        // Phone number, token lifetime (debug_token), app subscription.
        Http::assertSentCount(3);
    });

    it('names an expired token in --live', function () {
        config(['whatsapp.provider' => 'meta']);
        liveIntegration(['access_token' => 'EAAG-expired', 'app_secret' => 's', 'verify_token' => 'v']);
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['code' => 190, 'message' => 'Session has expired EAAG-expired']], 401)]);

        $this->artisan('whatsapp:check --live')
            ->expectsOutputToContain('access_token_invalid')
            ->doesntExpectOutputToContain('EAAG-expired')
            ->assertFailed();
    });
});
