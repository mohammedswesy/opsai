<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWhatsAppEvent;
use App\Models\Integration;
use App\Models\WhatsAppWebhookEvent;
use App\WhatsApp\Contracts\WhatsAppProviderInterface;
use App\WhatsApp\WebhookSecrets;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request, WhatsAppProviderInterface $provider, WebhookSecrets $secrets): Response
    {
        // PHP turns "hub.mode" into "hub_mode"; read both spellings.
        $mode = $request->string('hub_mode')->toString() ?: $request->string('hub.mode')->toString();
        $token = $request->string('hub_verify_token')->toString() ?: $request->string('hub.verify_token')->toString();
        $challenge = $request->string('hub_challenge')->toString() ?: $request->string('hub.challenge')->toString();

        foreach ($secrets->verifyTokens() as $expected) {
            $answer = $provider->verifyWebhook($mode, $token, $challenge, $expected);

            if ($answer !== null) {
                return response($answer, 200)->header('Content-Type', 'text/plain');
            }
        }

        Log::warning('whatsapp.webhook.verify_failed', ['mode' => $mode]);

        return response('Forbidden', 403);
    }

    public function store(Request $request, WhatsAppProviderInterface $provider, WebhookSecrets $secrets): Response
    {
        $raw = $request->getContent();
        $signature = $request->header('X-Hub-Signature-256');
        $valid = collect($secrets->appSecrets())->contains(fn (string $secret) => $provider->verifySignature($raw, $signature, $secret));

        if (! $valid) {
            Log::warning('whatsapp.webhook.signature_failed', ['has_signature' => $signature !== null]);

            return response('Unauthorized', 401);
        }

        $payload = $request->json()->all();

        foreach ($provider->parseIncomingMessage($payload) as $message) {
            $this->remember('message', (string) $message['external_message_id'], $message);
        }

        foreach ($provider->parseDeliveryStatus($payload) as $status) {
            if ($status['external_message_id'] === '' || $status['status'] === '') {
                continue;
            }

            $this->remember('status', 'status:'.$status['external_message_id'].':'.$status['status'], $status);
        }

        // Anything else (account updates, template reviews, Meta's dashboard test) is acknowledged and ignored.
        return response('OK', 200);
    }

    /**
     * Stores the event once (Meta retries until it gets a 200) and queues it. A retry of an
     * event that is still waiting is queued again; the processor claims each event once.
     *
     * @param  array<string, mixed>  $item
     */
    private function remember(string $type, string $externalId, array $item): void
    {
        if ($externalId === '') {
            return;
        }

        $integration = Integration::query()
            ->withoutGlobalScope('organization')
            ->where('type', 'whatsapp')
            ->where('status', 'active')
            ->where('metadata->phone_number_id', (string) ($item['phone_number_id'] ?? ''))
            ->first();

        // Status updates are safe to apply late. A message delivered long after it was sent
        // (or replayed) is kept for the record but does not get an automatic reply.
        $stale = $type === 'message' && $this->stale((int) ($item['timestamp'] ?? 0));
        $status = match (true) {
            $integration === null => 'ignored',
            $stale => 'ignored',
            default => 'received',
        };

        try {
            $event = WhatsAppWebhookEvent::query()->create([
                'organization_id' => $integration?->organization_id,
                'provider' => (string) config('whatsapp.provider'),
                'external_event_id' => $externalId,
                'event_type' => $type,
                'payload' => [...$item, 'integration_id' => $integration?->id],
                'signature_valid' => true,
                'status' => $status,
                'error' => match (true) {
                    $integration === null => 'unknown_phone_number_id',
                    $stale => 'stale_message',
                    default => null,
                },
            ]);
        } catch (UniqueConstraintViolationException) {
            $this->requeueIfWaiting($externalId);

            return;
        }

        if ($status === 'ignored') {
            Log::info('whatsapp.webhook.ignored', ['type' => $type, 'reason' => $event->error]);

            return;
        }

        ProcessWhatsAppEvent::dispatch($event->id);
    }

    private function stale(int $timestamp): bool
    {
        return $timestamp === 0 || abs(now()->timestamp - $timestamp) > (int) config('whatsapp.replay_window_seconds');
    }

    private function requeueIfWaiting(string $externalId): void
    {
        $waiting = WhatsAppWebhookEvent::query()
            ->withoutGlobalScope('organization')
            ->where('provider', (string) config('whatsapp.provider'))
            ->where('external_event_id', $externalId)
            ->where('status', 'received')
            ->value('id');

        if (is_string($waiting)) {
            ProcessWhatsAppEvent::dispatch($waiting);
        }
    }
}
