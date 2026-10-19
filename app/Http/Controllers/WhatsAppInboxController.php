<?php

namespace App\Http\Controllers;

use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use App\WhatsApp\WhatsAppMessenger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WhatsAppInboxController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('viewAny', WhatsAppConversation::class), 403);

        $conversations = WhatsAppConversation::query()
            ->with('customer:id,name')
            ->latest('last_message_at')
            ->paginate(20)
            ->through(fn (WhatsAppConversation $conversation) => [
                'id' => $conversation->id,
                'customer' => $conversation->customer?->name ?? $conversation->contact_name,
                'phone' => $conversation->contact_phone,
                'status' => $conversation->status,
                'unread_count' => $conversation->unread_count,
                'last_message_at' => $conversation->last_message_at?->toIso8601String(),
                'preview' => $conversation->messages()->latest('id')->value('text'),
            ]);

        return Inertia::render('WhatsApp/Index', [
            'conversations' => $conversations,
            'simulation' => $this->simulationEnabled(),
        ]);
    }

    public function show(Request $request, WhatsAppConversation $conversation): Response
    {
        abort_unless($request->user()->can('view', $conversation), 403);
        $conversation->update(['unread_count' => 0]);

        return Inertia::render('WhatsApp/Show', [
            'conversation' => [
                'id' => $conversation->id,
                'customer' => $conversation->customer?->name ?? $conversation->contact_name,
                'phone' => $conversation->contact_phone,
                'status' => $conversation->status,
            ],
            'messages' => $conversation->messages()->orderBy('id')->get()->map(fn (WhatsAppMessage $message) => [
                'id' => $message->id,
                'direction' => $message->direction,
                'author' => $message->metadata['author'] ?? ($message->direction === 'inbound' ? 'customer' : 'system'),
                'text' => $message->text,
                'status' => $message->status,
                'created_at' => $message->created_at?->toIso8601String(),
            ]),
            'canReply' => $request->user()->can('reply', $conversation),
            'simulation' => $this->simulationEnabled(),
        ]);
    }

    public function reply(Request $request, WhatsAppConversation $conversation, WhatsAppMessenger $messenger): RedirectResponse
    {
        abort_unless($request->user()->can('reply', $conversation), 403);
        $data = $request->validate(['message' => ['required', 'string', 'max:2000']]);
        $integration = $conversation->integration;

        if ($integration === null) {
            abort(404);
        }

        $messenger->sendText($conversation, $integration, $data['message'], 'staff');

        return back();
    }

    public function resume(Request $request, WhatsAppConversation $conversation): RedirectResponse
    {
        abort_unless($request->user()->can('reply', $conversation), 403);
        $conversation->update(['status' => 'open']);

        return back();
    }

    private function simulationEnabled(): bool
    {
        return config('whatsapp.provider') === 'fake' && ! app()->environment('production');
    }
}
