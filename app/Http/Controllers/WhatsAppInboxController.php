<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Integration;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\WhatsApp\AppointmentTemplateSender;
use App\WhatsApp\ConversationWindow;
use App\WhatsApp\WhatsAppMessenger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppInboxController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('viewAny', WhatsAppConversation::class), 403);

        $conversations = WhatsAppConversation::query()
            ->with('customer:id,name')
            ->latest('last_message_at')
            ->paginate(20)
            ->through(fn (WhatsAppConversation $conversation) => [
                'id' => $conversation->id,
                'customer' => $conversation->customer?->name ?? $conversation->contact_name,
                'phone' => $conversation->contact_phone,
                'status' => $conversation->status,
                'unread_count' => $conversation->unread_count,
                'last_message_at' => $conversation->last_message_at?->toIso8601String(),
                'preview' => $conversation->messages()->latest('id')->value('text'),
            ]);

        return Inertia::render('WhatsApp/Index', [
            'conversations' => $conversations,
            'simulation' => $this->simulationEnabled(),
            'health' => $this->health(),
            'canManageSettings' => $this->canManageSettings($request),
        ]);
    }

    public function show(Request $request, WhatsAppConversation $conversation, ConversationWindow $window): Response
    {
        abort_unless($request->user()->can('view', $conversation), 403);
        $conversation->update(['unread_count' => 0]);
        $canReply = $request->user()->can('reply', $conversation);
        $closesAt = $window->closesAt($conversation);
        $windowOpen = $closesAt !== null && $closesAt->isFuture();

        return Inertia::render('WhatsApp/Show', [
            'conversation' => [
                'id' => $conversation->id,
                'customer' => $conversation->customer?->name ?? $conversation->contact_name,
                'phone' => $conversation->contact_phone,
                'status' => $conversation->status,
            ],
            'messages' => $conversation->messages()->orderBy('id')->get()->map(fn (WhatsAppMessage $message) => [
                'id' => $message->id,
                'direction' => $message->direction,
                'author' => $message->metadata['author'] ?? ($message->direction === 'inbound' ? 'customer' : 'system'),
                'text' => $message->text,
                'type' => $message->message_type,
                // Only what staff need to recognise a non-text message; the file is not downloaded.
                'media' => is_array($message->media)
                    ? array_intersect_key($message->media, array_flip(['mime_type', 'filename', 'name', 'address', 'latitude', 'longitude', 'emoji']))
                    : null,
                'status' => $message->status,
                'template' => $message->message_type === 'template' ? [
                    'name' => $message->metadata['template'] ?? null,
                    'language' => $message->metadata['language'] ?? null,
                    'purpose' => $message->metadata['purpose'] ?? null,
                ] : null,
                'failure' => $message->status === 'failed' && is_string($message->metadata['reason'] ?? null) ? $message->metadata['reason'] : null,
                'created_at' => $message->created_at?->toIso8601String(),
            ]),
            'canReply' => $canReply,
            'simulation' => $this->simulationEnabled(),
            'health' => $this->health(),
            'canManageSettings' => $this->canManageSettings($request),
            'window' => [
                'open' => $windowOpen,
                'closes_at' => $closesAt?->toIso8601String(),
            ],
            // Only needed, and only sent, when free text is not allowed.
            'templates' => $canReply && ! $windowOpen ? $this->approvedTemplates() : [],
            'appointments' => $canReply && ! $windowOpen ? $this->openAppointments($conversation) : [],
        ]);
    }

    public function reply(Request $request, WhatsAppConversation $conversation, WhatsAppMessenger $messenger, ConversationWindow $window): RedirectResponse
    {
        abort_unless($request->user()->can('reply', $conversation), 403);
        $data = $request->validate(['message' => ['required', 'string', 'max:2000']]);
        $integration = $conversation->integration;

        if ($integration === null) {
            abort(404);
        }

        // Meta refuses free text after 24 hours (131047); say so before trying.
        if (! $window->isOpen($conversation)) {
            return back()->withErrors(['message' => 'window_closed']);
        }

        $messenger->sendText($conversation, $integration, $data['message'], 'staff');

        return back();
    }

    /** Sends an approved template for one of the customer's open appointments. */
    public function template(Request $request, WhatsAppConversation $conversation, AppointmentTemplateSender $sender): RedirectResponse
    {
        abort_unless($request->user()->can('sendTemplate', $conversation), 403);
        $data = $request->validate([
            'template_id' => ['required', 'string'],
            'appointment_id' => ['required', 'string'],
        ]);

        $template = WhatsAppTemplate::query()->where('status', 'approved')->find($data['template_id']);
        $appointment = $conversation->customer_id === null ? null : Appointment::query()
            ->with(['customer', 'organization', 'serviceRequest.service', 'workOrder.serviceRequest.service'])
            ->where('customer_id', $conversation->customer_id)
            ->find($data['appointment_id']);

        if ($template === null) {
            return back()->withErrors(['template_id' => 'template_not_approved']);
        }

        if ($appointment === null) {
            return back()->withErrors(['appointment_id' => 'appointment_not_found']);
        }

        // Staff answer a customer who wrote to them before, so no separate opt-in is required here.
        $outcome = $sender->send($appointment, $template->purpose, 'staff', $template, requireOptIn: false);

        if ($outcome->skipped !== null) {
            return back()->withErrors(['template_id' => $outcome->skipped]);
        }

        return back();
    }

    public function resume(Request $request, WhatsAppConversation $conversation): RedirectResponse
    {
        abort_unless($request->user()->can('reply', $conversation), 403);
        $conversation->update(['status' => 'open']);

        return back();
    }

    /** @return array{needs_attention: bool, reason: string|null, since: string|null}|null */
    private function health(): ?array
    {
        return Integration::query()->where('type', 'whatsapp')->first()?->health();
    }

    private function canManageSettings(Request $request): bool
    {
        $organization = $request->user()->organizations()->find(session('current_organization_id'));

        return $organization !== null && $request->user()->can('update', $organization);
    }

    /** @return list<array{id: string, name: string, language: string, purpose: string}> */
    private function approvedTemplates(): array
    {
        return WhatsAppTemplate::query()->where('status', 'approved')->orderBy('purpose')->orderBy('language')->get()
            ->map(fn (WhatsAppTemplate $template) => [
                'id' => $template->id,
                'name' => $template->name,
                'language' => $template->language,
                'purpose' => $template->purpose,
            ])->all();
    }

    /** @return list<array{id: string, scheduled_start: string|null, service: string|null}> */
    private function openAppointments(WhatsAppConversation $conversation): array
    {
        if ($conversation->customer_id === null) {
            return [];
        }

        return Appointment::query()
            ->with('serviceRequest.service')
            ->where('customer_id', $conversation->customer_id)
            ->whereIn('status', array_map(fn ($status) => $status->value, AppointmentTemplateSender::OPEN_STATUSES))
            ->where('scheduled_start', '>', now())
            ->orderBy('scheduled_start')
            ->limit(10)
            ->get()
            ->map(fn (Appointment $appointment) => [
                'id' => $appointment->id,
                'scheduled_start' => $appointment->scheduled_start?->toIso8601String(),
                'service' => $appointment->serviceRequest?->service?->name ?? $appointment->serviceRequest?->subject,
            ])->all();
    }

    private function simulationEnabled(): bool
    {
        return config('whatsapp.provider') === 'fake' && ! app()->environment('production');
    }
}
