<?php

namespace App\WhatsApp\Providers;

use App\WhatsApp\Contracts\WhatsAppProviderInterface;
use App\WhatsApp\Data\WhatsAppSendResult;
use App\WhatsApp\WhatsAppException;
use App\WhatsApp\WhatsAppPayload;
use Illuminate\Support\Str;

class FakeWhatsAppProvider implements WhatsAppProviderInterface
{
    public function __construct(private readonly WhatsAppPayload $payload) {}

    public function sendTextMessage(string $to, string $body, string $phoneNumberId, ?string $accessToken = null): WhatsAppSendResult
    {
        $this->guardFailure();

        return new WhatsAppSendResult('wamid.fake.'.Str::ulid(), 'sent');
    }

    public function sendTemplateMessage(string $to, string $template, string $language, string $phoneNumberId, array $components = [], ?string $accessToken = null): WhatsAppSendResult
    {
        if ($template === '' || ! config('whatsapp.templates_enabled')) {
            throw new WhatsAppException('WhatsApp templates are not configured.', 'template_not_configured');
        }

        return $this->sendTextMessage($to, $template, $phoneNumberId, $accessToken);
    }

    public function sendMediaMessage(string $to, string $mediaId, string $phoneNumberId, ?string $accessToken = null): WhatsAppSendResult
    {
        return $this->sendTextMessage($to, $mediaId, $phoneNumberId, $accessToken);
    }

    public function verifyWebhook(string $mode, string $token, string $challenge): ?string
    {
        $expected = (string) config('whatsapp.verify_token');

        if ($mode === 'subscribe' && $expected !== '' && hash_equals($expected, $token)) {
            return $challenge;
        }

        return null;
    }

    public function parseIncomingMessage(array $payload): array
    {
        return $this->payload->messages($payload);
    }

    public function parseDeliveryStatus(array $payload): array
    {
        return $this->payload->statuses($payload);
    }

    public function verifySignature(string $payload, ?string $signature): bool
    {
        $secret = (string) config('whatsapp.app_secret');

        if ($secret === '' || $signature === null || ! str_starts_with($signature, 'sha256=')) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }

    public function verifyConnection(array $credentials, array $metadata): array
    {
        $phoneNumberId = (string) ($metadata['phone_number_id'] ?? '');

        if ($phoneNumberId === '') {
            throw new WhatsAppException('The WhatsApp phone number is not configured.', 'missing_phone');
        }

        return [
            'connected' => true,
            'phone' => is_string($metadata['display_phone'] ?? null) ? $metadata['display_phone'] : $phoneNumberId,
        ];
    }

    private function guardFailure(): void
    {
        if (config('whatsapp.fake_fail') === true) {
            throw new WhatsAppException('The WhatsApp provider could not send the message.', 'provider_failure');
        }
    }
}
