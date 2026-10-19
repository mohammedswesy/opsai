<?php

namespace App\Http\Controllers;

use App\Models\Integration;
use App\Models\WhatsAppConversation;
use App\WhatsApp\Contracts\WhatsAppProviderInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WhatsAppSimulationController extends Controller
{
    public function store(Request $request, WhatsAppProviderInterface $provider): RedirectResponse
    {
        abort_unless($request->user()->can('viewAny', WhatsAppConversation::class), 403);
        abort_unless(config('whatsapp.provider') === 'fake' && ! app()->environment('production'), 404);

        $data = $request->validate([
            'from' => ['required', 'string', 'max:32'],
            'text' => ['required', 'string', 'max:2000'],
        ]);

        $integration = Integration::query()->where('type', 'whatsapp')->where('status', 'active')->first();
        abort_if($integration === null, 404);

        $payload = [
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'metadata' => ['phone_number_id' => $integration->metadata['phone_number_id'] ?? ''],
                        'messages' => [[
                            'from' => $data['from'],
                            'id' => 'wamid.sim.'.bin2hex(random_bytes(8)),
                            'timestamp' => (string) now()->timestamp,
                            'type' => 'text',
                            'text' => ['body' => $data['text']],
                        ]],
                    ],
                ]],
            ]],
        ];

        $body = json_encode($payload) ?: '{}';
        $signature = 'sha256='.hash_hmac('sha256', $body, (string) config('whatsapp.app_secret'));
        $forward = Request::create('/webhooks/whatsapp', 'POST', content: $body);
        $forward->headers->set('Content-Type', 'application/json');
        $forward->headers->set('X-Hub-Signature-256', $signature);
        app(WhatsAppWebhookController::class)->store($forward, $provider);

        return back();
    }
}
