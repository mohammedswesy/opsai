<?php

namespace App\WhatsApp;

use App\Models\Integration;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\WhatsApp\Contracts\WhatsAppProviderInterface;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WhatsAppMessenger
{
    public function __construct(private readonly WhatsAppProviderInterface $provider) {}

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

        $limit = max(1, (int) config('whatsapp.outbound_per_minute'));
        $key = 'whatsapp-outbound:'.$conversation->organization_id;

        if (! Cache::has($key)) {
            Cache::put($key, 0, now()->addMinute());
        }

        if ((int) Cache::increment($key) > $limit) {
            $message->update([
                'status' => 'failed',
                'failed_at' => now(),
                'metadata' => ['author' => $author, 'reason' => 'rate_limited'],
            ]);

            return $message;
        }

        Cache::put($key, (int) Cache::get($key), now()->addMinute());

        try {
            $token = is_array($integration->credentials) ? ($integration->credentials['access_token'] ?? null) : null;
            $result = $this->provider->sendTextMessage(
                $conversation->contact_phone,
                $text,
                $phoneNumberId,
                is_string($token) ? $token : null,
            );
            $message->update([
                'external_message_id' => $result->externalId,
                'status' => $result->status,
                'sent_at' => now(),
            ]);
        } catch (WhatsAppException $exception) {
            Log::warning('whatsapp.send_failed', ['reason' => $exception->reason]);
            $message->update([
                'status' => 'failed',
                'failed_at' => now(),
                'metadata' => ['author' => $author, 'reason' => $exception->reason],
            ]);
        }

        $conversation->update(['last_message_at' => now()]);

        return $message->fresh() ?? $message;
    }
}
