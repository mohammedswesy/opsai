<?php

namespace App\WhatsApp;

use App\AI\Data\AIToolContext;
use App\AI\Exceptions\AIException;
use App\AI\Services\AIActionService;
use App\AI\Services\AIConversationService;
use App\Enums\AiActionStatus;
use App\Enums\AiConversationStatus;
use App\Enums\OrganizationRole;
use App\Models\AiAction;
use App\Models\AiConversation;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Integration;
use App\Models\Organization;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppWebhookEvent;
use App\Services\Operations\ActivityLogger;
use App\Services\Operations\CustomerService;
use App\Support\OrganizationContext;
use App\Support\PhoneNormalizer;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

class WhatsAppInboundProcessor
{
    public function __construct(
        private readonly PhoneNormalizer $phones,
        private readonly AIConversationService $ai,
        private readonly AIActionService $actions,
        private readonly CustomerService $customers,
        private readonly WhatsAppMessenger $messenger,
        private readonly ActivityLogger $activities,
    ) {}

    public function handle(WhatsAppWebhookEvent $event): void
    {
        $claimed = WhatsAppWebhookEvent::query()
            ->whereKey($event->id)
            ->where('status', 'received')
            ->update(['status' => 'processing']);

        if ($claimed === 0) {
            return;
        }

        $event->refresh();

        try {
            if ($event->event_type === 'status') {
                $this->status($event);
            } else {
                $this->message($event);
            }

            $event->update(['status' => 'processed', 'processed_at' => now(), 'error' => null]);
        } catch (AIException $exception) {
            Log::warning('whatsapp.ai_failed', ['reason' => $exception->context['reason'] ?? 'provider_error']);
            $event->update(['status' => 'failed', 'error' => $exception->publicMessage]);
        } catch (WhatsAppException $exception) {
            Log::warning('whatsapp.processing_failed', ['reason' => $exception->reason]);
            $event->update(['status' => 'failed', 'error' => $exception->publicMessage]);
        }
    }

    private function status(WhatsAppWebhookEvent $event): void
    {
        $payload = $event->payload;
        $externalId = (string) ($payload['external_message_id'] ?? '');
        $status = (string) ($payload['status'] ?? '');
        $message = WhatsAppMessage::query()
            ->withoutGlobalScope('organization')
            ->where('external_message_id', $externalId)
            ->first();

        if ($message === null || ! $this->mayAdvance($message->status, $status)) {
            return;
        }

        $message->status = $status;
        $message->sent_at = $status === 'sent' ? now() : $message->sent_at;
        $message->delivered_at = $status === 'delivered' ? now() : $message->delivered_at;
        $message->read_at = $status === 'read' ? now() : $message->read_at;

        if ($status === 'failed') {
            $message->failed_at = now();
            $message->metadata = [...($message->metadata ?? []), 'reason' => $payload['error'] ?? 'failed'];
        }

        $message->save();
    }

    private function message(WhatsAppWebhookEvent $event): void
    {
        $organization = $event->organization_id
            ? Organization::query()->find($event->organization_id)
            : null;
        $integration = Integration::query()
            ->withoutGlobalScope('organization')
            ->find($event->payload['integration_id'] ?? null);

        if ($organization === null || $integration === null) {
            throw new WhatsAppException('The WhatsApp integration could not be resolved.', 'unknown_integration');
        }

        OrganizationContext::set($organization, OrganizationRole::Technician);
        $phone = $this->phones->normalize((string) ($event->payload['from'] ?? ''), $organization->country);

        if ($phone === null) {
            throw new WhatsAppException('The sender phone number could not be read.', 'invalid_phone');
        }

        $conversation = $this->conversation($integration, $phone, $event->payload['profile_name'] ?? null);
        $customer = $this->matchCustomer($phone, $organization->country);

        if ($customer !== null) {
            $conversation->update(['customer_id' => $customer->id, 'contact_name' => $conversation->contact_name ?: $customer->name]);
        }

        $text = (string) ($event->payload['text'] ?? '');
        $stored = $this->storeInbound($conversation, $integration, $customer, $phone, $event->payload, $text);

        if ($stored === null) {
            return;
        }

        if ($conversation->status === 'human_required') {
            return;
        }

        if ($this->humanRequested($text)) {
            $conversation->update(['status' => 'human_required']);
            $this->activities->log($conversation, 'whatsapp.human_required', $conversation->contact_phone);
            $reply = preg_match('/\p{Arabic}/u', $text) === 1
                ? 'تمام، بحوّل المحادثة لأحد زملائنا ويكمل معك.'
                : 'A teammate will continue this conversation.';
            $this->messenger->sendText($conversation, $integration, $reply, 'system');

            return;
        }

        if ($this->confirmPending($conversation, $text, $integration)) {
            return;
        }

        if ($customer === null) {
            $this->unknownContact($conversation, $integration, $text);

            return;
        }

        $aiConversation = $this->aiConversation($conversation, $customer);
        $result = $this->ai->accept($aiConversation, $text, new AIToolContext(null, $customer->branch_id, false, 'customer', $customer));
        $this->messenger->sendText($conversation, $integration, (string) $result['message']['content'], 'ai');
    }

    private function conversation(Integration $integration, string $phone, mixed $name): WhatsAppConversation
    {
        return WhatsAppConversation::query()->firstOrCreate(
            [
                'organization_id' => $integration->organization_id,
                'contact_phone' => $phone,
            ],
            [
                'integration_id' => $integration->id,
                'contact_name' => is_string($name) ? $name : null,
                'status' => 'open',
                'unread_count' => 0,
            ],
        );
    }

    private function matchCustomer(string $phone, ?string $country): ?Customer
    {
        $matches = Customer::query()->get(['id', 'name', 'phone', 'whatsapp_number', 'branch_id'])
            ->filter(function (Customer $customer) use ($phone, $country) {
                return $this->phones->normalize($customer->phone, $country) === $phone
                    || $this->phones->normalize($customer->whatsapp_number, $country) === $phone;
            })
            ->values();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function storeInbound(WhatsAppConversation $conversation, Integration $integration, ?Customer $customer, string $phone, array $payload, string $text): ?WhatsAppMessage
    {
        try {
            $message = $conversation->messages()->create([
                'customer_id' => $customer?->id,
                'integration_id' => $integration->id,
                'external_message_id' => $payload['external_message_id'] ?? null,
                'direction' => 'inbound',
                'from_number' => $phone,
                'to_number' => (string) ($integration->metadata['phone_number_id'] ?? ''),
                'message_type' => (string) ($payload['type'] ?? 'text'),
                'text' => $text,
                'status' => 'received',
                'metadata' => ['author' => 'customer'],
            ]);
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) !== 19 && ($exception->errorInfo[0] ?? null) !== '23000') {
                throw $exception;
            }

            return null;
        }

        $conversation->increment('unread_count');
        $conversation->update(['last_message_at' => now()]);

        return $message;
    }

    private function confirmPending(WhatsAppConversation $conversation, string $text, Integration $integration): bool
    {
        if (! $this->isConfirmation($text) || $conversation->ai_conversation_id === null) {
            return false;
        }

        $action = AiAction::query()
            ->where('conversation_id', $conversation->ai_conversation_id)
            ->where('status', AiActionStatus::Pending->value)
            ->latest('id')
            ->first();

        if ($action === null) {
            return false;
        }

        $confirmed = $this->actions->confirmForCustomer($action);
        $reply = AiConversation::query()->find($conversation->ai_conversation_id)
            ?->messages()
            ->where('role', 'assistant')
            ->latest('id')
            ->value('content');

        $this->messenger->sendText($conversation, $integration, is_string($reply) ? $reply : 'The action was saved.', 'ai');

        return $confirmed->status === AiActionStatus::Executed || $confirmed->status === AiActionStatus::Failed;
    }

    private function unknownContact(WhatsAppConversation $conversation, Integration $integration, string $text): void
    {
        $metadata = $conversation->metadata ?? [];

        if (($metadata['onboarding'] ?? null) === 'awaiting_name' && $this->looksLikeName($text)) {
            $branchId = Branch::query()->orderBy('id')->value('id');
            $customer = $this->customers->create([
                'branch_id' => $branchId,
                'type' => 'individual',
                'name' => trim($text),
                'phone' => $conversation->contact_phone,
                'whatsapp_number' => $conversation->contact_phone,
                'country' => OrganizationContext::current()->organization->country,
                'status' => 'active',
                'source' => 'whatsapp',
            ]);
            $conversation->update([
                'customer_id' => $customer->id,
                'contact_name' => $customer->name,
                'metadata' => ['onboarding' => 'complete'],
            ]);
            $this->messenger->sendText($conversation, $integration, 'شكراً '.$customer->name.'. كيف يمكننا مساعدتك؟', 'system');

            return;
        }

        $conversation->update(['metadata' => ['onboarding' => 'awaiting_name', 'pending_problem' => $text]]);

        if (str_contains($text, 'السلام عليكم')) {
            $this->messenger->sendText($conversation, $integration, 'وعليكم السلام 👋 كيف يمكننا مساعدتك؟', 'system');

            return;
        }

        $reply = preg_match('/\p{Arabic}/u', $text) === 1
            ? 'أهلاً بك. ما اسمك حتى نتابع طلبك؟'
            : 'Welcome. What is your name so we can continue?';
        $this->messenger->sendText($conversation, $integration, $reply, 'system');
    }

    private function aiConversation(WhatsAppConversation $conversation, Customer $customer): AiConversation
    {
        if ($conversation->ai_conversation_id !== null) {
            $existing = AiConversation::query()->find($conversation->ai_conversation_id);

            if ($existing !== null) {
                $existing->update(['customer_id' => $customer->id]);

                return $existing;
            }
        }

        $ai = AiConversation::query()->create([
            'customer_id' => $customer->id,
            'channel' => 'customer',
            'title' => $customer->name,
            'status' => AiConversationStatus::Active,
        ]);
        $conversation->update(['ai_conversation_id' => $ai->id]);

        return $ai;
    }

    private function humanRequested(string $text): bool
    {
        $lower = mb_strtolower($text);
        $phrases = [
            'أريد التحدث مع موظف',
            'أريد التحدث مع شخص',
            'أبغى موظف',
            'ابغى موظف',
            'أبغى أكلم المسؤول',
            'ابغى اكلم المسؤول',
            'talk to a person',
            'talk to someone',
            'speak to a person',
            'human support',
        ];

        foreach ($phrases as $phrase) {
            if (str_contains($text, $phrase) || str_contains($lower, mb_strtolower($phrase))) {
                return true;
            }
        }

        return false;
    }

    private function isConfirmation(string $text): bool
    {
        return in_array(mb_strtolower(trim($text)), ['yes', 'confirm', 'ok', 'نعم', 'أجل', 'موافق'], true);
    }

    private function looksLikeName(string $text): bool
    {
        $trimmed = trim($text);

        return $trimmed !== '' && mb_strlen($trimmed) <= 80 && ! str_contains($trimmed, '?') && ! str_contains($trimmed, '؟');
    }

    private function mayAdvance(string $current, string $next): bool
    {
        $rank = ['queued' => 0, 'sent' => 1, 'delivered' => 2, 'read' => 3, 'failed' => 4];

        if ($next === 'failed') {
            return $current !== 'read';
        }

        return ($rank[$next] ?? -1) > ($rank[$current] ?? -1);
    }
}
