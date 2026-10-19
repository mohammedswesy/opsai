<?php

use App\AI\Contracts\AIProviderInterface;
use App\AI\Data\AIToolContext;
use App\AI\Exceptions\AIException;
use App\AI\Tools\AIToolRegistry;
use App\AI\Tools\GetDashboardMetricsTool;
use App\Enums\OrganizationRole;
use App\Jobs\ProcessWhatsAppEvent;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Integration;
use App\Models\ServiceRequest;
use App\Models\Technician;
use App\Models\User;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppWebhookEvent;
use App\Support\OrganizationContext;
use App\Support\PhoneNormalizer;
use App\WhatsApp\Contracts\WhatsAppProviderInterface;
use App\WhatsApp\WhatsAppException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

it('verifies the webhook challenge', function () {
    $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=verify-token&hub.challenge=challenge-token')
        ->assertOk()
        ->assertSee('challenge-token');

    $this->get('/webhooks/whatsapp?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=challenge-token')
        ->assertForbidden();
});

it('rejects an invalid webhook signature', function () {
    whatsappIntegration();

    $this->call('POST', '/webhooks/whatsapp', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256=bad',
    ], json_encode(whatsappPayload('966550001001', 'hello', 'wamid.invalid')))
        ->assertUnauthorized();

    expect(WhatsAppWebhookEvent::query()->count())->toBe(0);
});

it('stores an inbound message once and resolves the organization from the phone number', function () {
    [$owner, $organization] = whatsappIntegration();
    $customer = whatsappCustomer($organization, 'North Customer', '0550001001');
    $body = json_encode(whatsappPayload('966550001001', 'The air conditioner is not cooling.', 'wamid.once'));

    whatsappPost($body)->assertOk();
    whatsappPost($body)->assertOk();

    expect(WhatsAppWebhookEvent::query()->count())->toBe(1)
        ->and(WhatsAppMessage::query()->where('direction', 'inbound')->count())->toBe(1)
        ->and(WhatsAppConversation::query()->first()->organization_id)->toBe($organization->id)
        ->and(WhatsAppConversation::query()->first()->customer_id)->toBe($customer->id);

    OrganizationContext::set($organization, OrganizationRole::Owner);
    whatsappPost($body);
    $second = json_encode(whatsappPayload('966550001001', 'yes', 'wamid.yes'));
    whatsappPost($second)->assertOk();

    expect(ServiceRequest::query()->where('subject', 'Air conditioner is not cooling')->count())->toBe(1)
        ->and(WhatsAppMessage::query()->where('direction', 'outbound')->count())->toBeGreaterThan(0);
});

it('does not create a customer for an unknown greeting', function () {
    [$owner, $organization] = whatsappIntegration();
    OrganizationContext::set($organization, OrganizationRole::Owner);
    $before = Customer::query()->count();
    OrganizationContext::forget();
    whatsappPost(json_encode(whatsappPayload('966500000111', 'السلام عليكم، المكيف عندي لا يعمل.', 'wamid.unknown')))->assertOk();

    OrganizationContext::set($organization, OrganizationRole::Owner);
    expect(Customer::query()->count())->toBe($before)
        ->and(WhatsAppMessage::query()->where('direction', 'outbound')->value('text'))->toBe('وعليكم السلام 👋 كيف يمكننا مساعدتك؟');
});

it('updates delivery status idempotently', function () {
    [$owner, $organization] = whatsappIntegration();
    whatsappCustomer($organization, 'Status Customer', '0550001222');
    whatsappPost(json_encode(whatsappPayload('966550001222', 'The air conditioner is not cooling.', 'wamid.status')))->assertOk();
    $outbound = WhatsAppMessage::query()->where('direction', 'outbound')->first();
    $status = json_encode(whatsappStatus($outbound->external_message_id, 'delivered'));

    whatsappPost($status)->assertOk();
    whatsappPost($status)->assertOk();

    expect($outbound->fresh()->status)->toBe('delivered')
        ->and(WhatsAppWebhookEvent::query()->where('event_type', 'status')->count())->toBe(1);
});

it('keeps whatsapp conversations inside the organization', function () {
    [$owner, $organization] = whatsappIntegration();
    whatsappCustomer($organization, 'Isolated Customer', '0550001333');
    whatsappPost(json_encode(whatsappPayload('966550001333', 'hello there', 'wamid.isolate')))->assertOk();
    $conversation = WhatsAppConversation::query()->first();

    [$other, $otherOrganization] = createMember();
    OrganizationContext::set($otherOrganization, OrganizationRole::Owner);
    Integration::query()->create([
        'provider' => 'fake',
        'type' => 'whatsapp',
        'status' => 'active',
        'credentials' => ['access_token' => 'other-token'],
        'metadata' => ['phone_number_id' => '200000000000002'],
    ]);
    OrganizationContext::forget();

    actingInOrganization($other, $otherOrganization)
        ->get('/whatsapp')
        ->assertOk()
        ->assertDontSee('Isolated Customer');

    actingInOrganization($other, $otherOrganization)
        ->get('/whatsapp/'.$conversation->id)
        ->assertNotFound();
});

it('encrypts integration credentials and hides them from the settings page', function () {
    [$owner, $organization] = whatsappIntegration('plaintext-token-value');

    expect(DB::table('integrations')->value('credentials'))->not->toContain('plaintext-token-value');

    actingInOrganization($owner, $organization)
        ->get('/settings/integrations/whatsapp')
        ->assertOk()
        ->assertDontSee('plaintext-token-value');
});

it('refuses internal financial questions from a whatsapp customer', function () {
    [$owner, $organization] = whatsappIntegration();
    whatsappCustomer($organization, 'Finance Customer', '0550001444');
    whatsappPost(json_encode(whatsappPayload('966550001444', 'How much revenue did the company make?', 'wamid.finance')))->assertOk();

    $text = WhatsAppMessage::query()->where('direction', 'outbound')->value('text');
    expect($text)->toBe('I can only help with your own service request or appointment.')
        ->and($text)->not->toContain('SAR');
});

it('stops automatic replies after a human handoff', function () {
    [$owner, $organization] = whatsappIntegration();
    whatsappCustomer($organization, 'Handoff Customer', '0550001555');
    whatsappPost(json_encode(whatsappPayload('966550001555', 'I want to talk to a person.', 'wamid.human')))->assertOk();

    expect(WhatsAppConversation::query()->first()->status)->toBe('human_required');

    whatsappPost(json_encode(whatsappPayload('966550001555', 'How much revenue did the company make?', 'wamid.human-2')))->assertOk();
    expect(WhatsAppMessage::query()->where('text', 'I can only help with your own service request or appointment.')->count())->toBe(0);
});

it('does not book a conflicting appointment', function () {
    [$owner, $organization] = whatsappIntegration();
    $customer = whatsappCustomer($organization, 'Booking Customer', '0550001666');
    $technicianUser = User::factory()->create(['name' => 'Only Technician']);
    $organization->members()->attach($technicianUser->id, ['role' => OrganizationRole::Technician->value]);
    OrganizationContext::set($organization, OrganizationRole::Owner);
    $technician = Technician::query()->create([
        'user_id' => $technicianUser->id,
        'employee_code' => 'ONLY',
        'availability_status' => 'available',
        'status' => 'active',
    ]);
    $start = Carbon::parse(now()->addDay()->setTime(16, 0)->format('Y-m-d H:i'), 'Asia/Riyadh');
    Appointment::query()->create([
        'branch_id' => $customer->branch_id,
        'customer_id' => $customer->id,
        'technician_id' => $technician->id,
        'scheduled_start' => $start->copy()->utc(),
        'scheduled_end' => $start->copy()->addHour()->utc(),
        'status' => 'scheduled',
    ]);
    OrganizationContext::forget();

    whatsappPost(json_encode(whatsappPayload('966550001666', 'I need a technician tomorrow at 4.', 'wamid.book')))->assertOk();

    OrganizationContext::set($organization, OrganizationRole::Owner);
    expect(Appointment::query()->count())->toBe(1)
        ->and(WhatsAppMessage::query()->where('direction', 'outbound')->latest('id')->value('text'))->toBe('That time is not available.');
});

it('does not run the same webhook job twice', function () {
    [$owner, $organization] = whatsappIntegration();
    whatsappCustomer($organization, 'Idempotent Customer', '0550001777');
    whatsappPost(json_encode(whatsappPayload('966550001777', 'How much revenue did the company make?', 'wamid.job')))->assertOk();
    $event = WhatsAppWebhookEvent::query()->first();
    $count = WhatsAppMessage::query()->count();

    ProcessWhatsAppEvent::dispatchSync($event->id);

    expect(WhatsAppMessage::query()->count())->toBe($count)
        ->and($event->fresh()->status)->toBe('processed');
});

it('keeps the inbound message when the assistant fails', function () {
    [$owner, $organization] = whatsappIntegration();
    whatsappCustomer($organization, 'AI Failure', '0550001888');
    app()->instance(AIProviderInterface::class, new class implements AIProviderInterface
    {
        public function complete(array $messages, array $tools): never
        {
            throw new AIException('The assistant is unavailable right now.', 503, ['reason' => 'down']);
        }
    });

    whatsappPost(json_encode(whatsappPayload('966550001888', 'The air conditioner is not cooling.', 'wamid.ai-fail')))->assertOk();

    expect(WhatsAppMessage::query()->where('direction', 'inbound')->count())->toBe(1)
        ->and(WhatsAppWebhookEvent::query()->first()->status)->toBe('failed')
        ->and(WhatsAppWebhookEvent::query()->first()->error)->toBe('The assistant is unavailable right now.');
});

it('records a failed outbound message when the provider fails', function () {
    [$owner, $organization] = whatsappIntegration();
    whatsappCustomer($organization, 'Provider Failure', '0550001999');
    config(['whatsapp.fake_fail' => true]);

    whatsappPost(json_encode(whatsappPayload('966550001999', 'How much revenue did the company make?', 'wamid.provider')))->assertOk();

    expect(WhatsAppMessage::query()->where('direction', 'inbound')->count())->toBe(1)
        ->and(WhatsAppMessage::query()->where('direction', 'outbound')->value('status'))->toBe('failed');
});

it('does not expose staff tools to a whatsapp customer', function () {
    [$owner, $organization] = whatsappIntegration();
    $customer = whatsappCustomer($organization, 'Tool Customer', '0550001010');
    OrganizationContext::set($organization, OrganizationRole::Owner);
    $names = collect(app(AIToolRegistry::class)->definitionsFor(new AIToolContext(null, null, false, 'customer', $customer)))
        ->pluck('function.name');

    expect($names->all())->toContain('create_service_request', 'get_customer_service_request', 'request_appointment')
        ->and($names->all())->not->toContain('get_dashboard_metrics', 'get_overdue_invoices', 'get_technician_workload', 'create_task');
});

it('asks one arabic question for a vague cooling problem', function () {
    [$owner, $organization] = whatsappIntegration();
    whatsappCustomer($organization, 'Cooling Customer', '0550002111');

    whatsappPost(json_encode(whatsappPayload('966550002111', 'المكيف ما يبرد', 'wamid.cool')))->assertOk();

    OrganizationContext::set($organization, OrganizationRole::Owner);
    expect(ServiceRequest::query()->count())->toBe(0)
        ->and(WhatsAppMessage::query()->where('direction', 'outbound')->value('text'))
        ->toBe('وعليكم السلام 👋 أكيد نساعدك. هل التبريد ضعيف أم المكيف يطلع هواء حار تمامًا؟');
});

it('confirms an arabic yes before saving the service request', function () {
    [$owner, $organization] = whatsappIntegration();
    whatsappCustomer($organization, 'Arabic Confirm', '0550002112');

    whatsappPost(json_encode(whatsappPayload('966550002112', 'The air conditioner is not cooling.', 'wamid.ar-problem')))->assertOk();
    whatsappPost(json_encode(whatsappPayload('966550002112', 'نعم', 'wamid.ar-yes')))->assertOk();

    OrganizationContext::set($organization, OrganizationRole::Owner);
    expect(ServiceRequest::query()->where('subject', 'Air conditioner is not cooling')->count())->toBe(1);
});

it('offers only schedule times the availability tool marks open', function () {
    [$owner, $organization] = whatsappIntegration();
    whatsappCustomer($organization, 'Slot Customer', '0550002113');
    whatsappTechnician($organization);

    whatsappPost(json_encode(whatsappPayload('966550002113', 'أبغى فني', 'wamid.slots')))->assertOk();

    OrganizationContext::set($organization, OrganizationRole::Owner);
    $reply = (string) WhatsAppMessage::query()->where('direction', 'outbound')->latest('id')->value('text');
    $morning = now()->addDay()->setTime(10, 0)->format('Y-m-d H:i');
    $afternoon = now()->addDay()->setTime(14, 0)->format('Y-m-d H:i');

    expect(Appointment::query()->count())->toBe(0)
        ->and($reply)->toContain($morning)
        ->and($reply)->toContain($afternoon);

    OrganizationContext::forget();
    whatsappPost(json_encode(whatsappPayload('966550002113', '10', 'wamid.pick')))->assertOk();
    OrganizationContext::set($organization, OrganizationRole::Owner);
    expect(Appointment::query()->count())->toBe(0)
        ->and(WhatsAppMessage::query()->where('direction', 'outbound')->latest('id')->value('text'))
        ->toBe('Please confirm this action before I save it.');

    OrganizationContext::forget();
    whatsappPost(json_encode(whatsappPayload('966550002113', 'نعم', 'wamid.pick-yes')))->assertOk();
    OrganizationContext::set($organization, OrganizationRole::Owner);
    expect(Appointment::query()->count())->toBe(1);
});

it('reads the customer appointment instead of inventing one', function () {
    [$owner, $organization] = whatsappIntegration();
    $customer = whatsappCustomer($organization, 'Visit Customer', '0550002114');
    $technician = whatsappTechnician($organization);
    $start = now()->addDays(2)->utc();
    Appointment::query()->create([
        'customer_id' => $customer->id,
        'technician_id' => $technician->id,
        'scheduled_start' => $start,
        'scheduled_end' => $start->copy()->addHour(),
        'status' => 'scheduled',
    ]);
    OrganizationContext::forget();

    whatsappPost(json_encode(whatsappPayload('966550002114', 'متى موعدي؟', 'wamid.when')))->assertOk();

    OrganizationContext::set($organization, OrganizationRole::Owner);
    expect(WhatsAppMessage::query()->where('direction', 'outbound')->latest('id')->value('text'))
        ->toContain($start->toIso8601String());
});

it('reads the customer service request status', function () {
    [$owner, $organization] = whatsappIntegration();
    $customer = whatsappCustomer($organization, 'Status Owner', '0550002115');
    ServiceRequest::query()->create([
        'customer_id' => $customer->id,
        'source' => 'manual',
        'subject' => 'Split unit inspection',
        'description' => 'Demo status lookup.',
        'priority' => 'normal',
        'status' => 'scheduled',
        'requested_at' => now(),
    ]);
    OrganizationContext::forget();

    whatsappPost(json_encode(whatsappPayload('966550002115', 'وين وصل طلبي؟', 'wamid.where')))->assertOk();

    OrganizationContext::set($organization, OrganizationRole::Owner);
    $text = (string) WhatsAppMessage::query()->where('direction', 'outbound')->latest('id')->value('text');
    expect($text)->toContain('Split unit inspection')->and($text)->toContain('scheduled');
});

it('stops automatic replies for an arabic request to reach a person', function () {
    [$owner, $organization] = whatsappIntegration();
    whatsappCustomer($organization, 'Arabic Handoff', '0550002116');

    whatsappPost(json_encode(whatsappPayload('966550002116', 'أبغى موظف', 'wamid.person')))->assertOk();

    expect(WhatsAppConversation::query()->first()->status)->toBe('human_required')
        ->and(WhatsAppMessage::query()->where('direction', 'outbound')->value('text'))
        ->toBe('تمام، بحوّل المحادثة لأحد زملائنا ويكمل معك.');

    whatsappPost(json_encode(whatsappPayload('966550002116', 'The air conditioner is not cooling.', 'wamid.person-2')))->assertOk();
    OrganizationContext::set($organization, OrganizationRole::Owner);
    expect(ServiceRequest::query()->count())->toBe(0);
});

it('apologizes for a complaint without promising compensation', function () {
    [$owner, $organization] = whatsappIntegration();
    whatsappCustomer($organization, 'Complaint Customer', '0550002117');

    whatsappPost(json_encode(whatsappPayload('966550002117', 'الفني تأخر عن الموعد', 'wamid.complaint')))->assertOk();

    $text = (string) WhatsAppMessage::query()->where('direction', 'outbound')->value('text');
    expect($text)->toContain('نعتذر')
        ->and($text)->not->toContain('SAR')
        ->and(WhatsAppConversation::query()->first()->status)->toBe('open');
});

it('does not show another customer appointment in the same organization', function () {
    [$owner, $organization] = whatsappIntegration();
    whatsappCustomer($organization, 'Asking Customer', '0550002118');
    $other = whatsappCustomer($organization, 'Other Customer', '0550002119');
    $technician = whatsappTechnician($organization);
    $start = now()->addDays(3)->utc();
    Appointment::query()->create([
        'customer_id' => $other->id,
        'technician_id' => $technician->id,
        'scheduled_start' => $start,
        'scheduled_end' => $start->copy()->addHour(),
        'status' => 'scheduled',
    ]);
    OrganizationContext::forget();

    whatsappPost(json_encode(whatsappPayload('966550002118', 'متى موعدي؟', 'wamid.other-appt')))->assertOk();

    $text = (string) WhatsAppMessage::query()->where('direction', 'outbound')->latest('id')->value('text');
    expect($text)->toBe('ما لقيت موعد قادم باسمك.')
        ->and($text)->not->toContain($start->toIso8601String());
});

it('refuses a staff tool when the actor is a customer', function () {
    [$owner, $organization] = whatsappIntegration();
    $customer = whatsappCustomer($organization, 'Blocked Customer', '0550002120');
    OrganizationContext::set($organization, OrganizationRole::Owner);

    $result = app(AIToolRegistry::class)->execute(
        app(GetDashboardMetricsTool::class),
        [],
        new AIToolContext(null, null, false, 'customer', $customer),
    );

    expect($result['success'])->toBeFalse();
});

it('answers an english service problem through confirmation', function () {
    [$owner, $organization] = whatsappIntegration();
    whatsappCustomer($organization, 'English Customer', '0550002121');

    whatsappPost(json_encode(whatsappPayload('966550002121', 'The air conditioner is not cooling.', 'wamid.english')))->assertOk();

    expect(WhatsAppMessage::query()->where('direction', 'outbound')->value('text'))
        ->toBe('Please confirm this action before I save it.');
    OrganizationContext::set($organization, OrganizationRole::Owner);
    expect(ServiceRequest::query()->count())->toBe(0);
});

it('does not pretend a template was sent', function () {
    app(WhatsAppProviderInterface::class)->sendTemplateMessage('966550001001', '', 'en', '100', []);
})->throws(WhatsAppException::class, 'WhatsApp templates are not configured.');

it('normalizes gcc phone numbers with the organization country', function () {
    $phones = app(PhoneNormalizer::class);

    expect($phones->normalize('0550001001', 'SA'))->toBe('966550001001')
        ->and($phones->normalize('+971501112233', 'SA'))->toBe('971501112233')
        ->and($phones->normalize('0501112233', null))->toBeNull();
});

function whatsappIntegration(string $token = 'token-value'): array
{
    [$user, $organization] = createMember();
    $organization->update(['country' => 'SA', 'timezone' => 'Asia/Riyadh']);
    OrganizationContext::set($organization, OrganizationRole::Owner);
    Integration::query()->create([
        'provider' => 'fake',
        'type' => 'whatsapp',
        'status' => 'active',
        'credentials' => ['access_token' => $token],
        'metadata' => ['phone_number_id' => '100000000000001', 'display_phone' => '+966110000000'],
    ]);
    OrganizationContext::forget();

    return [$user, $organization->fresh()];
}

function whatsappTechnician($organization): Technician
{
    $user = User::factory()->create();
    $organization->members()->attach($user->id, ['role' => OrganizationRole::Technician->value]);
    OrganizationContext::set($organization, OrganizationRole::Owner);

    return Technician::query()->create([
        'user_id' => $user->id,
        'employee_code' => 'WA-'.str()->lower(str()->random(4)),
        'availability_status' => 'available',
        'status' => 'active',
    ]);
}

function whatsappCustomer($organization, string $name, string $phone): Customer
{
    OrganizationContext::set($organization, OrganizationRole::Owner);

    return Customer::query()->create([
        'type' => 'individual',
        'name' => $name,
        'phone' => $phone,
        'country' => 'SA',
        'status' => 'active',
        'source' => 'manual',
    ]);
}

function whatsappPayload(string $from, string $text, string $id): array
{
    return [
        'entry' => [[
            'changes' => [[
                'value' => [
                    'metadata' => ['phone_number_id' => '100000000000001'],
                    'messages' => [[
                        'from' => $from,
                        'id' => $id,
                        'timestamp' => (string) now()->timestamp,
                        'type' => 'text',
                        'text' => ['body' => $text],
                    ]],
                ],
            ]],
        ]],
    ];
}

function whatsappStatus(string $id, string $status): array
{
    return [
        'entry' => [[
            'changes' => [[
                'value' => [
                    'metadata' => ['phone_number_id' => '100000000000001'],
                    'statuses' => [[
                        'id' => $id,
                        'status' => $status,
                        'timestamp' => (string) now()->timestamp,
                        'recipient_id' => '966550001222',
                    ]],
                ],
            ]],
        ]],
    ];
}

function whatsappPost(string $body)
{
    $signature = 'sha256='.hash_hmac('sha256', $body, 'app-secret');

    return test()->call('POST', '/webhooks/whatsapp', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_HUB_SIGNATURE_256' => $signature,
    ], $body);
}
