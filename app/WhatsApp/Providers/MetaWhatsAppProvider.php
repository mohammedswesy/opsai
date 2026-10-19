<?php

namespace App\WhatsApp\Providers;

use App\WhatsApp\Contracts\WhatsAppProviderInterface;
use App\WhatsApp\Data\WhatsAppSendResult;
use App\WhatsApp\WhatsAppException;
use App\WhatsApp\WhatsAppPayload;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaWhatsAppProvider implements WhatsAppProviderInterface
{
    public function __construct(private readonly WhatsAppPayload $payload) {}

    public function sendTextMessage(string $to, string $body, string $phoneNumberId, ?string $accessToken = null): WhatsAppSendResult
    {
        return $this->send($phoneNumberId, $accessToken, [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => $body],
        ]);
    }

    public function sendTemplateMessage(string $to, string $template, string $language, string $phoneNumberId, array $components = [], ?string $accessToken = null): WhatsAppSendResult
    {
        if ($template === '' || $language === '') {
            throw new WhatsAppException('WhatsApp templates are not configured.', 'template_not_configured');
        }

        return $this->send($phoneNumberId, $accessToken, [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $template,
                'language' => ['code' => $language],
                'components' => $components,
            ],
        ]);
    }

    public function sendMediaMessage(string $to, string $mediaId, string $phoneNumberId, ?string $accessToken = null): WhatsAppSendResult
    {
        return $this->send($phoneNumberId, $accessToken, [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'image',
            'image' => ['id' => $mediaId],
        ]);
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

        return hash_equals('sha256='.hash_hmac('sha256', $payload, $secret), $signature);
    }

    public function verifyConnection(array $credentials, array $metadata): array
    {
        $token = is_string($credentials['access_token'] ?? null) ? $credentials['access_token'] : '';
        $phoneNumberId = (string) ($metadata['phone_number_id'] ?? '');

        if ($token === '' || $phoneNumberId === '') {
            throw new WhatsAppException('The WhatsApp connection is not configured.', 'missing_credentials');
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(15)
                ->get($this->endpoint($phoneNumberId));
        } catch (ConnectionException) {
            throw new WhatsAppException('The WhatsApp provider took too long to respond.', 'timeout');
        }

        if ($response->failed()) {
            Log::warning('whatsapp.meta.verify', ['status' => $response->status()]);

            throw new WhatsAppException('The WhatsApp connection could not be verified.', 'verify_failed');
        }

        $display = $response->json('display_phone_number');

        return [
            'connected' => true,
            'phone' => is_string($display) ? $display : $phoneNumberId,
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function send(string $phoneNumberId, ?string $accessToken, array $body): WhatsAppSendResult
    {
        $token = $accessToken ?: (string) config('whatsapp.access_token');

        if ($token === '' || $phoneNumberId === '') {
            throw new WhatsAppException('The WhatsApp connection is not configured.', 'missing_credentials');
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(20)
                ->post($this->endpoint($phoneNumberId).'/messages', $body);
        } catch (ConnectionException) {
            Log::warning('whatsapp.meta.timeout', ['reason' => 'timeout']);

            throw new WhatsAppException('The WhatsApp provider took too long to respond.', 'timeout');
        }

        if ($response->failed()) {
            Log::warning('whatsapp.meta.http', ['status' => $response->status()]);

            throw new WhatsAppException('The WhatsApp provider could not send the message.', 'http_error');
        }

        $id = $response->json('messages.0.id');

        if (! is_string($id) || $id === '') {
            throw new WhatsAppException('The WhatsApp provider returned a response I could not use.', 'malformed_response');
        }

        return new WhatsAppSendResult($id, 'sent');
    }

    private function endpoint(string $phoneNumberId): string
    {
        $version = trim((string) config('whatsapp.api_version'), '/');

        return 'https://graph.facebook.com/'.$version.'/'.$phoneNumberId;
    }
}
