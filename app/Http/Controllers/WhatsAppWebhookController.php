<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessWhatsAppEvent;
use App\Models\Integration;
use App\Models\WhatsAppWebhookEvent;
use App\WhatsApp\Contracts\WhatsAppProviderInterface;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request, WhatsAppProviderInterface $provider): Response
    {
        $challenge = $provider->verifyWebhook(
            $request->string('hub_mode')->toString() ?: $request->string('hub.mode')->toString(),
            $request->string('hub_verify_token')->toString() ?: $request->string('hub.verify_token')->toString(),
            $request->string('hub_challenge')->toString() ?: $request->string('hub.challenge')->toString(),
        );

        if ($challenge === null) {
            return response('Forbidden', 403);
        }

        return response($challenge, 200);
    }

    public function store(Request $request, WhatsAppProviderInterface $provider): Response
    {
        $raw = $request->getContent();

        if (! $provider->verifySignature($raw, $request->header('X-Hub-Signature-256'))) {
            return response('Unauthorized', 401);
        }

        $payload = $request->json()->all();
        $messages = $provider->parseIncomingMessage($payload);
        $statuses = $provider->parseDeliveryStatus($payload);

        foreach ($messages as $message) {
            $this->remember('message', (string) $message['external_message_id'], $message, $provider);
        }

        foreach ($statuses as $status) {
            $id = 'status:'.$status['external_message_id'].':'.$status['status'];
            $this->remember('status', $id, $status, $provider);
        }

        return response('OK', 200);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function remember(string $type, string $externalId, array $item, WhatsAppProviderInterface $provider): void
    {
        if ($externalId === '' || $externalId === 'status::') {
            return;
        }

        $timestamp = (int) ($item['timestamp'] ?? 0);
        $window = (int) config('whatsapp.replay_window_seconds');

        if ($timestamp === 0 || abs(now()->timestamp - $timestamp) > $window) {
            return;
        }

        $integration = Integration::query()
            ->withoutGlobalScope('organization')
            ->where('type', 'whatsapp')
            ->where('status', 'active')
            ->where('metadata->phone_number_id', (string) ($item['phone_number_id'] ?? ''))
            ->first();

        try {
            $event = WhatsAppWebhookEvent::query()->create([
                'organization_id' => $integration?->organization_id,
                'provider' => (string) config('whatsapp.provider'),
                'external_event_id' => $externalId,
                'event_type' => $type,
                'payload' => [...$item, 'integration_id' => $integration?->id],
                'signature_valid' => true,
                'status' => $integration === null ? 'ignored' : 'received',
            ]);
        } catch (QueryException) {
            return;
        }

        if ($integration !== null) {
            ProcessWhatsAppEvent::dispatch($event->id);
        }
    }
}
