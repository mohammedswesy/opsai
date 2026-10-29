<?php

namespace App\WhatsApp;

use App\Models\Integration;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\Models\WhatsAppTemplate;
use App\WhatsApp\Contracts\WhatsAppProviderInterface;
use App\WhatsApp\Data\WhatsAppSendResult;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WhatsAppMessenger
{
    /** Failures worth sending again under the same idempotency key. */
    private const RETRYABLE = ['rate_limited', 'timeout', 'provider_failure', 'access_token_invalid', 'permission_denied'];

    public function __construct(
        private readonly WhatsAppProviderInterface $provider,
        private readonly ConnectionHealth $health,
    ) {}

    public function sendText(WhatsAppConversation $conversation, Integration $integration, string $text, string $author): WhatsAppMessage
    {
        $phoneNumberId = (string) ($integration->metadata['phone_number_id'] ?? '');
        $message = $conversation->messages()->create([
            'customer_id' => $conversation->customer_id,
            'integration_id' => $integration->id,
            'direction' => 'outbound',
            'from_number' => $phoneNumberId,
            'to_number' => $conversation->contact_phone,
            'message_type' => 'text',
            'text' => $text,
            'status' => 'queued',
            'metadata' => ['author' => $author],
        ]);

        return $this->deliver($message, $conversation, $integration, fn (?string $token) => $this->provider->sendTextMessage(
            $conversation->contact_phone,
            $text,
            $phoneNumberId,
            $token,
        ));
    }

    /**
     * Sends an approved template with its body parameters in mapping order. With an
     * idempotency key a second call returns the first message instead of sending again,
     * unless that one failed for a reason worth retrying (then the same row is reused).
     *
     * @param  list<string>  $values
     */
    public function sendTemplate(WhatsAppConversation $conversation, Integration $integration, WhatsAppTemplate $template, array $values, string $author, ?string $idempotencyKey = null): WhatsAppMessage
    {
        $phoneNumberId = (string) ($integration->metadata['phone_number_id'] ?? '');
        $metadata = [
            'author' => $author,
            'template' => $template->name,
            'language' => $template->language,
            'purpose' => $template->purpose,
        ];
        $attributes = [
            'customer_id' => $conversation->customer_id,
            'integration_id' => $integration->id,
            'direction' => 'outbound',
            'from_number' => $phoneNumberId,
            'to_number' => $conversation->contact_phone,
            'message_type' => 'template',
            'text' => implode(' · ', $values),
            'status' => 'queued',
            'failed_at' => null,
            'metadata' => $metadata,
        ];

        $message = $idempotencyKey === null
            ? $conversation->messages()->create($attributes)
            : $this->claim($conversation, $idempotencyKey, $attributes);

        if ($message->status !== 'queued') {
            return $message;
        }

        return $this->deliver($message, $conversation, $integration, fn (?string $token) => $this->provider->sendTemplateMessage(
            $conversation->contact_phone,
            $template->name,
            $template->language,
            $phoneNumberId,
            self::components($values),
            $token,
        ));
    }

    /**
     * Graph API components for positional body variables {{1}}, {{2}}, ...
     *
     * @param  list<string>  $values
     * @return list<array<string, mixed>>
     */
    public static function components(array $values): array
    {
        if ($values === []) {
            return [];
        }

        return [[
            'type' => 'body',
            'parameters' => array_map(fn (string $value) => ['type' => 'text', 'text' => $value], array_values($values)),
        ]];
    }

    /**
     * The message row for this key: a new queued one, the earlier one requeued when it
     * failed for a retryable reason, or the earlier one untouched (not queued: no send).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function claim(WhatsAppConversation $conversation, string $key, array $attributes): WhatsAppMessage
    {
        $existing = WhatsAppMessage::query()->where('idempotency_key', $key)->first();

        if ($existing === null) {
            try {
                return $conversation->messages()->create([...$attributes, 'idempotency_key' => $key]);
            } catch (QueryException $exception) {
                if (($exception->errorInfo[1] ?? null) !== 19 && ($exception->errorInfo[0] ?? null) !== '23000') {
                    throw $exception;
                }

                // Another worker claimed the key first.
                return WhatsAppMessage::query()->where('idempotency_key', $key)->firstOrFail();
            }
        }

        $reason = $existing->metadata['reason'] ?? null;

        if ($existing->status === 'failed' && in_array($reason, self::RETRYABLE, true)) {
            $existing->update($attributes);
        }

        return $existing;
    }

    /**
     * @param  Closure(?string): WhatsAppSendResult  $send
     */
    private function deliver(WhatsAppMessage $message, WhatsAppConversation $conversation, Integration $integration, Closure $send): WhatsAppMessage
    {
        $metadata = $message->metadata ?? [];

        if ($this->throttled($conversation->organization_id)) {
            $message->update([
                'status' => 'failed',
                'failed_at' => now(),
                'metadata' => [...$metadata, 'reason' => 'rate_limited'],
            ]);

            return $message;
        }

        try {
            $token = is_array($integration->credentials) ? ($integration->credentials['access_token'] ?? null) : null;
            $result = $send(is_string($token) ? $token : null);
            $message->update([
                'external_message_id' => $result->externalId,
                'status' => $result->status,
                'sent_at' => now(),
                'metadata' => array_diff_key($metadata, ['reason' => true]),
            ]);
            $this->health->succeeded($integration);
        } catch (WhatsAppException $exception) {
            Log::warning('whatsapp.send_failed', ['reason' => $exception->reason]);
            $message->update([
                'status' => 'failed',
                'failed_at' => now(),
                'metadata' => [...$metadata, 'reason' => $exception->reason],
            ]);
            $this->health->failed($integration, $exception->reason);
        }

        $conversation->update(['last_message_at' => now()]);

        return $message->fresh() ?? $message;
    }

    /** The per-organization outbound limit (WHATSAPP_OUTBOUND_PER_MINUTE), shared by every send. */
    private function throttled(string $organizationId): bool
    {
        $limit = max(1, (int) config('whatsapp.outbound_per_minute'));
        $key = 'whatsapp-outbound:'.$organizationId;

        if (! Cache::has($key)) {
            Cache::put($key, 0, now()->addMinute());
        }

        if ((int) Cache::increment($key) > $limit) {
            return true;
        }

        Cache::put($key, (int) Cache::get($key), now()->addMinute());

        return false;
    }
}
