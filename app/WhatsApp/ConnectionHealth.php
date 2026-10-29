<?php

namespace App\WhatsApp;

use App\Models\Integration;
use Illuminate\Support\Facades\Log;

/**
 * Flags an organization's WhatsApp connection when Meta rejects its access token
 * (error 190, HTTP 401) or its permissions, and clears the flag after the next
 * successful send or live check. Only a reason code and a timestamp are kept.
 */
class ConnectionHealth
{
    public const AUTH_REASONS = ['access_token_invalid', 'permission_denied'];

    public function failed(Integration $integration, string $reason): void
    {
        if (! in_array($reason, self::AUTH_REASONS, true)) {
            return;
        }

        if ($integration->attention_reason === $reason) {
            return;
        }

        $integration->forceFill(['attention_reason' => $reason, 'attention_at' => now()])->save();
        Log::warning('whatsapp.needs_attention', ['integration_id' => $integration->id, 'reason' => $reason]);
    }

    public function succeeded(Integration $integration): void
    {
        if ($integration->attention_reason === null) {
            return;
        }

        $integration->forceFill(['attention_reason' => null, 'attention_at' => null])->save();
        Log::info('whatsapp.attention_cleared', ['integration_id' => $integration->id]);
    }
}
