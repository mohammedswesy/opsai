<?php

use App\Enums\OrganizationRole;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Integration;
use App\Models\Organization;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\Support\OrganizationContext;
use App\WhatsApp\AppointmentTemplateSender;
use App\WhatsApp\Providers\FakeWhatsAppProvider;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia;

const TPL_PHONE_ID = '300000000000077';
const TPL_WABA = '400000000000088';

beforeEach(function () {
    // 09:00 in Riyadh; the reminder window is (10:00 today, 09:00 tomorrow].
    $this->travelTo(Carbon::parse('2026-11-01 09:00', 'Asia/Riyadh'));
});

/** @return array{0: User, 1: Organization, 2: Integration} */
function tplOrganization(string $name = 'Riyadh Cool Co', array $integration = []): array
{
    [$owner, $organization] = createMember();
    $organization->update(['name' => $name, 'country' => 'SA', 'timezone' => 'Asia/Riyadh', 'locale' => 'en']);
    OrganizationContext::set($organization, OrganizationRole::Owner);
    $model = Integration::query()->create([
        'provider' => 'fake',
        'type' => 'whatsapp',
        'status' => 'active',
        'reminders_enabled' => true,
        'credentials' => ['access_token' => 'saved-token-value'],
        'metadata' => ['phone_number_id' => TPL_PHONE_ID, 'business_account_id' => TPL_WABA],
        ...$integration,
    ]);
    OrganizationContext::forget();

    return [$owner, $organization->fresh(), $model];
}

function tplTemplate(Organization $organization, string $purpose = 'appointment_reminder', string $status = 'approved', string $language = 'en'): WhatsAppTemplate
{
    OrganizationContext::set($organization, OrganizationRole::Owner);
    $template = WhatsAppTemplate::query()->create([
        'name' => 'opsai_'.$purpose,
        'language' => $language,
        'purpose' => $purpose,
        'status' => $status,
        'parameters' => ['customer_first_name', 'service_name', 'appointment_date', 'appointment_time', 'company_name'],
    ]);
    OrganizationContext::forget();

    return $template;
}

/** An appointment tomorrow at 08:30 Riyadh time (23.5 hours ahead) for an opted-in customer. */
function tplAppointment(Organization $organization, array $customer = [], string $start = '2026-11-02 08:30', string $status = 'scheduled'): Appointment
{
    OrganizationContext::set($organization, OrganizationRole::Owner);
    $model = Customer::query()->create([
        'type' => 'individual',
        'name' => 'Sara Al Qahtani',
        'phone' => '0550003001',
        'country' => 'SA',
        'status' => 'active',
        'whatsapp_opt_in_at' => now()->subWeek(),
        ...$customer,
    ]);
    $service = Service::query()->create(['name' => 'AC cleaning', 'status' => 'active']);
    $request = ServiceRequest::query()->create([
        'customer_id' => $model->id,
        'service_id' => $service->id,
        'source' => 'phone',
        'subject' => 'Clean the split units',
        'description' => 'Two units.',
        'priority' => 'normal',
        'status' => 'scheduled',
        'requested_at' => now(),
    ]);
    $appointment = Appointment::query()->create([
        'customer_id' => $model->id,
        'technician_id' => dispatchTechnician($organization)->id,
        'service_request_id' => $request->id,
        'scheduled_start' => Carbon::parse($start, 'Asia/Riyadh')->utc(),
        'scheduled_end' => Carbon::parse($start, 'Asia/Riyadh')->addHour()->utc(),
        'status' => $status,
    ]);
    OrganizationContext::forget();

    return $appointment;
}

/** A conversation whose last customer message was `$hoursAgo` hours ago. */
function tplConversation(Organization $organization, Integration $integration, Customer $customer, int $hoursAgo): WhatsAppConversation
{
    OrganizationContext::set($organization, OrganizationRole::Owner);
    $conversation = WhatsAppConversation::query()->create([
        'integration_id' => $integration->id,
        'customer_id' => $customer->id,
        'contact_phone' => '966550003001',
        'contact_name' => $customer->name,
        'status' => 'open',
        'unread_count' => 0,
    ]);
    $message = new WhatsAppMessage([
        'integration_id' => $integration->id,
        'whatsapp_conversation_id' => $conversation->id,
        'direction' => 'inbound',
        'from_number' => '966550003001',
        'to_number' => TPL_PHONE_ID,
        'message_type' => 'text',
        'text' => 'Hello',
        'status' => 'received',
    ]);
    $message->forceFill(['created_at' => now()->subHours($hoursAgo)])->save();
    OrganizationContext::forget();

    return $conversation;
}

function tplMessages(Organization $organization)
{
    return WhatsAppMessage::query()->withoutGlobalScope('organization')->where('organization_id', $organization->id)->where('direction', 'outbound');
}

function tplMeta(): void
{
    config(['whatsapp.provider' => 'meta']);
    $sent = 0;
    Http::fake(['graph.facebook.com/*/'.TPL_PHONE_ID.'/messages' => function () use (&$sent) {
        $sent++;

        return Http::response(['messages' => [['id' => 'wamid.template.'.$sent]]]);
    }]);
}

describe('template sending', function () {
    it('sends the template payload Meta expects, with Riyadh date and time in mapping order', function () {
        tplMeta();
        [, $organization] = tplOrganization();
        tplTemplate($organization);
        tplAppointment($organization);

        $this->artisan('whatsapp:send-reminders')->assertSuccessful();

        Http::assertSent(function (HttpRequest $request) {
            return $request->url() === 'https://graph.facebook.com/v21.0/'.TPL_PHONE_ID.'/messages'
                && $request->hasHeader('Authorization', 'Bearer saved-token-value')
                && $request->data() === [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => '966550003001',
                    'type' => 'template',
                    'template' => [
                        'name' => 'opsai_appointment_reminder',
                        'language' => ['code' => 'en'],
                        'components' => [[
                            'type' => 'body',
                            'parameters' => [
                                ['type' => 'text', 'text' => 'Sara'],
                                ['type' => 'text', 'text' => 'AC cleaning'],
                                ['type' => 'text', 'text' => 'Nov 2, 2026'],
                                ['type' => 'text', 'text' => '08:30'],
                                ['type' => 'text', 'text' => 'Riyadh Cool Co'],
                            ],
                        ]],
                    ],
                ];
        });

        $message = tplMessages($organization)->first();
        expect($message->message_type)->toBe('template')
            ->and($message->status)->toBe('sent')
            ->and($message->external_message_id)->toBe('wamid.template.1')
            ->and($message->metadata)->toMatchArray(['author' => 'reminder', 'template' => 'opsai_appointment_reminder', 'purpose' => 'appointment_reminder']);
    });

    it('formats Arabic parameters with Latin digits', function () {
        [, $organization] = tplOrganization();
        $template = tplTemplate($organization, language: 'ar');
        $appointment = tplAppointment($organization);
        OrganizationContext::set($organization, OrganizationRole::Owner);

        $values = app(AppointmentTemplateSender::class)->values($appointment->fresh(), $template);

        expect($values[2])->toContain('2026')->toContain('نوفمبر')
            ->and($values[3])->toBe('08:30');
    });

    it('sends each template once per appointment and purpose', function () {
        tplMeta();
        [, $organization] = tplOrganization();
        tplTemplate($organization);
        tplTemplate($organization, 'appointment_confirmation');
        $appointment = tplAppointment($organization);

        $this->artisan('whatsapp:send-reminders')->assertSuccessful();
        $this->artisan('whatsapp:send-reminders')->assertSuccessful();

        OrganizationContext::set($organization, OrganizationRole::Owner);
        $sender = app(AppointmentTemplateSender::class);
        $sender->send($appointment->fresh(), 'appointment_reminder', 'staff', requireOptIn: false);
        $sender->send($appointment->fresh(), 'appointment_confirmation', 'staff', requireOptIn: false);
        $sender->send($appointment->fresh(), 'appointment_confirmation', 'staff', requireOptIn: false);

        Http::assertSentCount(2);
        expect(tplMessages($organization)->count())->toBe(2)
            ->and(tplMessages($organization)->pluck('idempotency_key')->sort()->values()->all())->toBe([
                'appointment:'.$appointment->id.':appointment_confirmation',
                'appointment:'.$appointment->id.':appointment_reminder',
            ]);
    });

    it('retries a rate-limited template on the same message row', function () {
        config(['whatsapp.outbound_per_minute' => 1]);
        [, $organization] = tplOrganization();
        tplTemplate($organization);
        tplAppointment($organization);
        $second = tplAppointment($organization, ['name' => 'Omar Second', 'phone' => '0550003002']);

        $this->artisan('whatsapp:send-reminders')->assertSuccessful();
        $limited = tplMessages($organization)->where('status', 'failed')->first();
        expect($limited->metadata['reason'])->toBe('rate_limited');

        $this->travel(2)->minutes();
        $this->artisan('whatsapp:send-reminders')->assertSuccessful();

        expect(tplMessages($organization)->count())->toBe(2)
            ->and($limited->fresh()->status)->toBe('sent')
            ->and($limited->fresh()->metadata)->not->toHaveKey('reason');
    });
});

describe('whatsapp:send-reminders', function () {
    it('is scheduled hourly', function () {
        $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains((string) $event->command, 'whatsapp:send-reminders'));

        expect($event)->not->toBeNull()
            ->and($event->expression)->toBe('0 * * * *');
    });

    it('never sends to a customer who did not opt in', function () {
        Log::spy();
        [, $organization] = tplOrganization();
        tplTemplate($organization);
        $appointment = tplAppointment($organization, ['whatsapp_opt_in_at' => null]);

        $this->artisan('whatsapp:send-reminders')->assertSuccessful();

        expect(tplMessages($organization)->count())->toBe(0);
        Log::shouldHaveReceived('info')->with('whatsapp.reminder_skipped', ['appointment_id' => $appointment->id, 'reason' => 'no_opt_in']);
    });

    it('skips each case silently with a reason and no personal data', function (array $integration, ?string $templateStatus, array $customer, string $start, string $status, ?string $reason) {
        Log::spy();
        [, $organization, $model] = tplOrganization(integration: $integration);
        if ($templateStatus !== null) {
            tplTemplate($organization, status: $templateStatus);
        }
        $appointment = tplAppointment($organization, $customer, $start, $status);

        $this->artisan('whatsapp:send-reminders')->assertSuccessful();

        expect(tplMessages($organization)->count())->toBe(0);

        if ($reason === 'reminders_disabled') {
            Log::shouldHaveReceived('info')->with('whatsapp.reminders_skipped', ['integration_id' => $model->id, 'reason' => 'reminders_disabled']);
        } elseif ($reason !== null) {
            Log::shouldHaveReceived('info')->with('whatsapp.reminder_skipped', ['appointment_id' => $appointment->id, 'reason' => $reason]);
        }

    })->with([
        'feature off' => [['reminders_enabled' => false], 'approved', [], '2026-11-02 08:30', 'scheduled', 'reminders_disabled'],
        'feature never set' => [['reminders_enabled' => null], 'approved', [], '2026-11-02 08:30', 'scheduled', 'reminders_disabled'],
        'template pending' => [[], 'pending', [], '2026-11-02 08:30', 'scheduled', 'template_not_approved'],
        'template rejected' => [[], 'rejected', [], '2026-11-02 08:30', 'scheduled', 'template_not_approved'],
        'no template' => [[], null, [], '2026-11-02 08:30', 'scheduled', 'template_not_approved'],
        'no usable phone' => [[], 'approved', ['phone' => '12', 'whatsapp_number' => null], '2026-11-02 08:30', 'scheduled', 'no_phone'],
        'more than a day ahead' => [[], 'approved', [], '2026-11-02 10:30', 'scheduled', null],
        'starting within the hour' => [[], 'approved', [], '2026-11-01 09:30', 'scheduled', null],
        'cancelled' => [[], 'approved', [], '2026-11-02 08:30', 'cancelled', null],
        'completed' => [[], 'approved', [], '2026-11-02 08:30', 'completed', null],
    ]);

    it('sends for confirmed appointments too', function () {
        [, $organization] = tplOrganization();
        tplTemplate($organization);
        tplAppointment($organization, status: 'confirmed');

        $this->artisan('whatsapp:send-reminders')->expectsOutputToContain('1 sent')->assertSuccessful();

        expect(tplMessages($organization)->where('status', 'sent')->count())->toBe(1);
    });

    it('keeps each organization to its own templates and customers', function () {
        [, $north] = tplOrganization('North Co');
        [, $south] = tplOrganization('South Co', ['reminders_enabled' => false]);
        tplTemplate($north);
        tplTemplate($south);
        tplAppointment($north);
        tplAppointment($south);

        $this->artisan('whatsapp:send-reminders')->assertSuccessful();

        $sent = tplMessages($north)->first();
        expect(tplMessages($north)->count())->toBe(1)
            ->and(tplMessages($south)->count())->toBe(0)
            ->and($sent->text)->toContain('North Co')
            ->and(WhatsAppConversation::query()->withoutGlobalScope('organization')->where('organization_id', $south->id)->count())->toBe(0);
    });
});

describe('inbox composer and the 24-hour window', function () {
    it('keeps the free-text composer while the window is open', function () {
        [$owner, $organization, $integration] = tplOrganization();
        tplTemplate($organization);
        $appointment = tplAppointment($organization);
        $conversation = tplConversation($organization, $integration, $appointment->customer()->withoutGlobalScope('organization')->first(), 2);

        actingInOrganization($owner, $organization)->get('/whatsapp/'.$conversation->id)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('WhatsApp/Show')
                ->where('window.open', true)
                ->where('templates', [])
                ->where('appointments', []));

        actingInOrganization($owner, $organization)->post('/whatsapp/'.$conversation->id.'/reply', ['message' => 'On our way'])
            ->assertSessionHasNoErrors();
        expect(tplMessages($organization)->where('message_type', 'text')->value('status'))->toBe('sent');
    });

    it('offers approved templates and refuses free text once the window has closed', function () {
        [$owner, $organization, $integration] = tplOrganization();
        $approved = tplTemplate($organization);
        tplTemplate($organization, 'appointment_confirmation', 'pending');
        $appointment = tplAppointment($organization);
        $conversation = tplConversation($organization, $integration, $appointment->customer()->withoutGlobalScope('organization')->first(), 25);

        actingInOrganization($owner, $organization)->get('/whatsapp/'.$conversation->id)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('window.open', false)
                ->has('templates', 1)
                ->where('templates.0.id', $approved->id)
                ->has('appointments', 1)
                ->where('appointments.0.id', $appointment->id));

        actingInOrganization($owner, $organization)->post('/whatsapp/'.$conversation->id.'/reply', ['message' => 'Hello again'])
            ->assertSessionHasErrors(['message' => 'window_closed']);
        expect(tplMessages($organization)->count())->toBe(0);

        actingInOrganization($owner, $organization)->post('/whatsapp/'.$conversation->id.'/template', ['template_id' => $approved->id, 'appointment_id' => $appointment->id])
            ->assertSessionHasNoErrors();
        expect(tplMessages($organization)->where('message_type', 'template')->value('status'))->toBe('sent');
    });

    it('treats a conversation the customer never wrote in as closed', function () {
        [$owner, $organization, $integration] = tplOrganization();
        tplTemplate($organization);
        tplAppointment($organization);
        $this->artisan('whatsapp:send-reminders');
        $conversation = WhatsAppConversation::query()->withoutGlobalScope('organization')->where('organization_id', $organization->id)->first();

        actingInOrganization($owner, $organization)->get('/whatsapp/'.$conversation->id)
            ->assertInertia(fn (AssertableInertia $page) => $page->where('window.open', false)->where('messages.0.template.purpose', 'appointment_reminder'));
    });

    it('cannot send another organization\'s template or appointment', function () {
        [$owner, $organization, $integration] = tplOrganization('North Co');
        [, $other] = tplOrganization('South Co');
        $foreignTemplate = tplTemplate($other);
        $foreignAppointment = tplAppointment($other);
        tplTemplate($organization);
        $appointment = tplAppointment($organization);
        $conversation = tplConversation($organization, $integration, $appointment->customer()->withoutGlobalScope('organization')->first(), 30);

        actingInOrganization($owner, $organization)->post('/whatsapp/'.$conversation->id.'/template', ['template_id' => $foreignTemplate->id, 'appointment_id' => $appointment->id])
            ->assertSessionHasErrors('template_id');
        actingInOrganization($owner, $organization)->post('/whatsapp/'.$conversation->id.'/template', ['template_id' => WhatsAppTemplate::query()->withoutGlobalScope('organization')->where('organization_id', $organization->id)->value('id'), 'appointment_id' => $foreignAppointment->id])
            ->assertSessionHasErrors('appointment_id');

        expect(WhatsAppMessage::query()->withoutGlobalScope('organization')->where('direction', 'outbound')->count())->toBe(0);
    });
});

describe('roles', function () {
    it('lets only settings managers enable reminders or sync templates', function (OrganizationRole $role, int $status) {
        [, $organization, $integration] = tplOrganization(integration: ['reminders_enabled' => false]);
        [$member] = createMember($role, $organization);

        actingInOrganization($member, $organization)->put('/settings/integrations/whatsapp/reminders', ['enabled' => true])->assertStatus($status);
        actingInOrganization($member, $organization)->post('/settings/integrations/whatsapp/templates/sync')->assertStatus($status);

        expect($integration->fresh()->reminders_enabled)->toBe($status === 302);
    })->with([
        'owner' => [OrganizationRole::Owner, 302],
        'admin' => [OrganizationRole::Admin, 302],
        'manager' => [OrganizationRole::Manager, 403],
        'dispatcher' => [OrganizationRole::Dispatcher, 403],
        'customer service' => [OrganizationRole::CustomerService, 403],
        'technician' => [OrganizationRole::Technician, 403],
    ]);

    it('lets inbox staff send an approved template, and no one else', function (OrganizationRole $role, bool $allowed) {
        [, $organization, $integration] = tplOrganization();
        $template = tplTemplate($organization);
        $appointment = tplAppointment($organization);
        $conversation = tplConversation($organization, $integration, $appointment->customer()->withoutGlobalScope('organization')->first(), 30);
        [$member] = createMember($role, $organization);

        $response = actingInOrganization($member, $organization)->post('/whatsapp/'.$conversation->id.'/template', ['template_id' => $template->id, 'appointment_id' => $appointment->id]);

        $allowed ? $response->assertSessionHasNoErrors()->assertRedirect() : $response->assertForbidden();
        expect(tplMessages($organization)->count())->toBe($allowed ? 1 : 0);
    })->with([
        'customer service' => [OrganizationRole::CustomerService, true],
        'dispatcher' => [OrganizationRole::Dispatcher, true],
        'manager' => [OrganizationRole::Manager, true],
        'technician' => [OrganizationRole::Technician, false],
        'accountant' => [OrganizationRole::Accountant, false],
    ]);
});

describe('template registry', function () {
    it('lists the registry without calling Meta', function () {
        Http::fake();
        [, $organization] = tplOrganization();
        tplTemplate($organization);

        $this->artisan('whatsapp:templates:sync')
            ->expectsOutputToContain('opsai_appointment_reminder')
            ->doesntExpectOutputToContain('saved-token-value')
            ->assertSuccessful();

        Http::assertNothingSent();
    });

    it('reads names and statuses from Meta with --live', function () {
        config(['whatsapp.provider' => 'meta']);
        Http::fake(['graph.facebook.com/*/'.TPL_WABA.'/message_templates*' => Http::response(['data' => [
            ['name' => 'opsai_appointment_reminder', 'language' => 'ar', 'status' => 'APPROVED', 'category' => 'UTILITY'],
            ['name' => 'opsai_appointment_reminder', 'language' => 'en', 'status' => 'PENDING', 'category' => 'UTILITY'],
            ['name' => 'opsai_appointment_confirmation', 'language' => 'ar', 'status' => 'REJECTED', 'category' => 'UTILITY'],
            ['name' => 'spring_offer', 'language' => 'en', 'status' => 'APPROVED', 'category' => 'MARKETING'],
        ]])]);
        [, $organization] = tplOrganization();

        $this->artisan('whatsapp:templates:sync --live')
            ->expectsOutputToContain('Not found in Meta: opsai_appointment_confirmation (en)')
            ->expectsOutputToContain('In Meta but not used by OpsAI: spring_offer (en)')
            ->doesntExpectOutputToContain('saved-token-value')
            ->assertSuccessful();

        OrganizationContext::set($organization, OrganizationRole::Owner);
        $statuses = WhatsAppTemplate::query()->get()->mapWithKeys(fn ($template) => [$template->name.'|'.$template->language => $template->status])->all();
        expect($statuses)->toEqual([
            'opsai_appointment_reminder|ar' => 'approved',
            'opsai_appointment_reminder|en' => 'pending',
            'opsai_appointment_confirmation|ar' => 'rejected',
            'opsai_appointment_confirmation|en' => 'unknown',
        ]);
        Http::assertSent(fn (HttpRequest $request) => $request->hasHeader('Authorization', 'Bearer saved-token-value'));
    });

    it('shows the registry read-only on the settings page', function () {
        [$owner, $organization] = tplOrganization();
        tplTemplate($organization);

        actingInOrganization($owner, $organization)->get('/settings/integrations/whatsapp')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('WhatsApp/Settings')
                ->where('remindersEnabled', true)
                ->where('templates.0.status', 'approved')
                ->where('templates.0.parameters', ['customer_first_name', 'service_name', 'appointment_date', 'appointment_time', 'company_name']))
            ->assertDontSee('saved-token-value');
    });
});

describe('connection health', function () {
    it('flags the connection on Meta error 190 and clears it after the next successful send', function () {
        config(['whatsapp.provider' => 'meta']);
        Http::fakeSequence('graph.facebook.com/*')
            ->push(['error' => ['code' => 190, 'error_subcode' => 463, 'message' => 'Error validating access token: Session has expired saved-token-value']], 401)
            ->push(['messages' => [['id' => 'wamid.after']]]);
        [$owner, $organization, $integration] = tplOrganization();
        $appointment = tplAppointment($organization);
        $conversation = tplConversation($organization, $integration, $appointment->customer()->withoutGlobalScope('organization')->first(), 1);

        actingInOrganization($owner, $organization)->post('/whatsapp/'.$conversation->id.'/reply', ['message' => 'First']);

        $integration->refresh();
        expect($integration->attention_reason)->toBe('access_token_invalid')
            ->and($integration->attention_at)->not->toBeNull()
            ->and(json_encode($integration->getAttributes()))->not->toContain('Session has expired');

        foreach (['/whatsapp', '/whatsapp/'.$conversation->id, '/settings/integrations/whatsapp'] as $path) {
            actingInOrganization($owner, $organization)->get($path)
                ->assertInertia(fn (AssertableInertia $page) => $page->where('health.needs_attention', true)->where('health.reason', 'access_token_invalid'))
                ->assertDontSee('Session has expired');
        }

        actingInOrganization($owner, $organization)->post('/whatsapp/'.$conversation->id.'/reply', ['message' => 'Second']);

        expect($integration->fresh()->attention_reason)->toBeNull()
            ->and($integration->fresh()->attention_at)->toBeNull();
    });

    it('flags the connection when the template sync is refused', function () {
        config(['whatsapp.provider' => 'meta']);
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['code' => 190]], 401)]);
        [, $organization, $integration] = tplOrganization();

        $this->artisan('whatsapp:templates:sync --live')->expectsOutputToContain('access_token_invalid')->assertFailed();

        expect($integration->fresh()->attention_reason)->toBe('access_token_invalid');
    });

    it('is not flagged by a failure unrelated to the token', function () {
        config(['whatsapp.provider' => 'meta']);
        Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['code' => 131047]], 400)]);
        [$owner, $organization, $integration] = tplOrganization();
        $appointment = tplAppointment($organization);
        $conversation = tplConversation($organization, $integration, $appointment->customer()->withoutGlobalScope('organization')->first(), 1);

        actingInOrganization($owner, $organization)->post('/whatsapp/'.$conversation->id.'/reply', ['message' => 'Hi']);

        expect($integration->fresh()->attention_reason)->toBeNull();
    });

    it('clears the flag after a successful whatsapp:check --live and reports an expiring token', function () {
        config(['whatsapp.provider' => 'meta']);
        [, , $integration] = tplOrganization(integration: ['attention_reason' => 'access_token_invalid', 'attention_at' => now()]);
        Http::fake([
            'graph.facebook.com/*/'.TPL_PHONE_ID => Http::response(['display_phone_number' => '15550001111']),
            'graph.facebook.com/*/debug_token*' => Http::response(['data' => ['is_valid' => true, 'type' => 'USER', 'expires_at' => now()->addHours(5)->timestamp]]),
            'graph.facebook.com/*/subscribed_apps' => Http::response(['data' => [['id' => '1']]]),
        ]);

        $this->artisan('whatsapp:check --live')
            ->expectsOutputToContain('expiring')
            ->doesntExpectOutputToContain('saved-token-value');

        expect($integration->fresh()->attention_reason)->toBeNull();
    });

    it('says when the token lifetime cannot be determined', function () {
        config(['whatsapp.provider' => 'meta']);
        tplOrganization();
        Http::fake([
            'graph.facebook.com/*/'.TPL_PHONE_ID => Http::response(['display_phone_number' => '15550001111']),
            'graph.facebook.com/*/debug_token*' => Http::response(['error' => ['code' => 100]], 400),
            'graph.facebook.com/*/subscribed_apps' => Http::response(['data' => [['id' => '1']]]),
        ]);

        $this->artisan('whatsapp:check --live')->expectsOutputToContain('could not be determined');
    });

    it('sets and clears the flag from the settings verify button with the fake provider', function () {
        [$owner, $organization, $integration] = tplOrganization();
        $integration->update(['credentials' => ['access_token' => FakeWhatsAppProvider::INVALID_TOKEN]]);

        actingInOrganization($owner, $organization)->post('/settings/integrations/whatsapp/verify')->assertSessionHasErrors('whatsapp');
        expect($integration->fresh()->attention_reason)->toBe('access_token_invalid');

        $integration->update(['credentials' => ['access_token' => 'renewed-token']]);
        actingInOrganization($owner, $organization)->post('/settings/integrations/whatsapp/verify')->assertSessionHasNoErrors();
        expect($integration->fresh()->attention_reason)->toBeNull();
    });
});

describe('customer opt-in', function () {
    it('stores consent as a timestamp from the customer form and clears it when unticked', function () {
        [$owner, $organization] = createMember();
        $payload = ['type' => 'individual', 'name' => 'Opt In', 'phone' => '0550009001', 'status' => 'active'];

        actingInOrganization($owner, $organization)->post('/customers', [...$payload, 'whatsapp_opt_in' => true])->assertSessionHasNoErrors();
        OrganizationContext::set($organization, OrganizationRole::Owner);
        $customer = Customer::query()->where('name', 'Opt In')->first();
        $consentedAt = $customer->whatsapp_opt_in_at;
        expect($consentedAt)->not->toBeNull();
        OrganizationContext::forget();

        $this->travel(1)->day();
        actingInOrganization($owner, $organization)->put('/customers/'.$customer->id, [...$payload, 'whatsapp_opt_in' => true]);
        expect($customer->fresh()->whatsapp_opt_in_at->equalTo($consentedAt))->toBeTrue();

        actingInOrganization($owner, $organization)->put('/customers/'.$customer->id, [...$payload, 'whatsapp_opt_in' => false]);
        expect($customer->fresh()->whatsapp_opt_in_at)->toBeNull();
    });
});
