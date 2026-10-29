<?php

namespace App\WhatsApp;

use App\Models\Integration;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Log;

/**
 * The verify tokens and app secrets the webhook accepts: the ones in config (.env) and
 * the ones saved, encrypted, on the WhatsApp settings page. One Meta app signs every
 * webhook with its app secret, so the secret is checked before the payload is trusted.
 */
class WebhookSecrets
{
    /** @return list<string> */
    public function verifyTokens(): array
    {
        return $this->collect('verify_token', config('whatsapp.verify_token'));
    }

    /** @return list<string> */
    public function appSecrets(): array
    {
        return $this->collect('app_secret', config('whatsapp.app_secret'));
    }

    /** @return list<string> */
    private function collect(string $field, mixed $configured): array
    {
        $values = is_string($configured) && $configured !== '' ? [$configured] : [];

        foreach ($this->savedCredentials() as $credentials) {
            $value = $credentials[$field] ?? null;

            if (is_string($value) && $value !== '') {
                $values[] = $value;
            }
        }

        return array_values(array_unique($values));
    }

    /** @return list<array<string, mixed>> */
    private function savedCredentials(): array
    {
        $saved = [];

        $integrations = Integration::query()
            ->withoutGlobalScope('organization')
            ->where('type', 'whatsapp')
            ->get(['id', 'credentials']);

        foreach ($integrations as $integration) {
            try {
                $credentials = $integration->credentials;
            } catch (DecryptException) {
                // Saved under a different APP_KEY; whatsapp:check reports this.
                Log::warning('whatsapp.credentials_unreadable', ['integration_id' => $integration->id]);

                continue;
            }

            if (is_array($credentials)) {
                $saved[] = $credentials;
            }
        }

        return $saved;
    }
}
