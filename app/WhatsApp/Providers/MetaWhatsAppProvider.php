<?php

namespace App\WhatsApp\Providers;

use App\WhatsApp\Contracts\WhatsAppProviderInterface;
use App\WhatsApp\Data\WhatsAppSendResult;
use App\WhatsApp\MetaErrorCodes;
use App\WhatsApp\WhatsAppException;
use App\WhatsApp\WhatsAppPayload;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
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

        $body = [
            'name' => $template,
            'language' => ['code' => $language],
        ];

        if ($components !== []) {
            $body['components'] = $components;
        }

        return $this->send($phoneNumberId, $accessToken, [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'template',
            'template' => $body,
        ]);
    }

    public function listTemplates(string $businessAccountId, ?string $accessToken = null): array
    {
        $token = $accessToken ?: (string) config('whatsapp.access_token');

        if ($token === '' || $businessAccountId === '') {
            throw new WhatsAppException('The WhatsApp Business Account is not configured.', 'missing_credentials');
        }

        $templates = [];
        $url = $this->graph($businessAccountId.'/message_templates');
        $query = ['fields' => 'name,language,status,category', 'limit' => 100];

        // Follow the cursor, but stop after a few pages so a loop in paging cannot hang the command.
        for ($page = 0; $page < 20 && $url !== null; $page++) {
            try {
                $response = Http::withToken($token)->acceptJson()->timeout(20)->get($url, $query);
            } catch (ConnectionException) {
                throw new WhatsAppException('The WhatsApp provider took too long to respond.', 'timeout');
            }

            if ($response->failed()) {
                throw new WhatsAppException('The WhatsApp templates could not be read.', $this->failureReason($response, 'whatsapp.meta.templates'));
            }

            foreach ((array) $response->json('data', []) as $row) {
                if (is_array($row) && is_string($row['name'] ?? null) && is_string($row['language'] ?? null)) {
                    $templates[] = [
                        'name' => $row['name'],
                        'language' => $row['language'],
                        'status' => is_string($row['status'] ?? null) ? $row['status'] : 'UNKNOWN',
                        'category' => is_string($row['category'] ?? null) ? $row['category'] : null,
                    ];
                }
            }

            $next = $response->json('paging.next');
            $url = is_string($next) && str_starts_with($next, 'https://graph.facebook.com/') ? $next : null;
            $query = [];
        }

        return $templates;
    }

    /**
     * What Meta says about the token itself (type, validity, expiry), read with the same
     * token. Null when Meta does not answer it for this token. The token is never returned.
     *
     * @return array{valid: bool, type: string|null, expires_at: int|null, data_access_expires_at: int|null}|null
     */
    public function inspectToken(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        try {
            $response = Http::acceptJson()->timeout(15)->get($this->graph('debug_token'), [
                'input_token' => $token,
                'access_token' => $token,
            ]);
        } catch (ConnectionException) {
            return null;
        }

        $data = $response->json('data');

        if ($response->failed() || ! is_array($data)) {
            return null;
        }

        return [
            'valid' => ($data['is_valid'] ?? false) === true,
            'type' => is_string($data['type'] ?? null) ? $data['type'] : null,
            'expires_at' => is_numeric($data['expires_at'] ?? null) ? (int) $data['expires_at'] : null,
            'data_access_expires_at' => is_numeric($data['data_access_expires_at'] ?? null) ? (int) $data['data_access_expires_at'] : null,
        ];
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

    public function verifyWebhook(string $mode, string $token, string $challenge, ?string $expected = null): ?string
    {
        $expected ??= (string) config('whatsapp.verify_token');

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

    public function verifySignature(string $payload, ?string $signature, ?string $secret = null): bool
    {
        $secret ??= (string) config('whatsapp.app_secret');

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
            $reason = $this->failureReason($response, 'whatsapp.meta.verify');

            throw new WhatsAppException('The WhatsApp connection could not be verified.', $reason === 'http_error' ? 'verify_failed' : $reason);
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
            $reason = $this->failureReason($response, 'whatsapp.meta.send');

            throw new WhatsAppException('The WhatsApp provider could not send the message.', $reason);
        }

        $id = $response->json('messages.0.id');

        if (! is_string($id) || $id === '') {
            throw new WhatsAppException('The WhatsApp provider returned a response I could not use.', 'malformed_response');
        }

        return new WhatsAppSendResult($id, 'sent');
    }

    /**
     * Names the Graph API errors a first live test runs into and logs what Meta support
     * needs (status, code, subcode, type, fbtrace_id). The error message is not logged.
     */
    private function failureReason(Response $response, string $event): string
    {
        $code = $response->json('error.code');
        $code = is_numeric($code) ? (int) $code : null;

        $reason = MetaErrorCodes::reason($code, $response->status());

        Log::warning($event, [
            'reason' => $reason,
            'status' => $response->status(),
            'error_code' => $code,
            'error_subcode' => is_numeric($response->json('error.error_subcode')) ? (int) $response->json('error.error_subcode') : null,
            'error_type' => is_string($response->json('error.type')) ? $response->json('error.type') : null,
            'fbtrace_id' => is_string($response->json('error.fbtrace_id')) ? $response->json('error.fbtrace_id') : null,
        ]);

        return $reason;
    }

    private function endpoint(string $phoneNumberId): string
    {
        return $this->graph($phoneNumberId);
    }

    private function graph(string $path): string
    {
        $version = trim((string) config('whatsapp.api_version'), '/');

        return 'https://graph.facebook.com/'.$version.'/'.$path;
    }
}
