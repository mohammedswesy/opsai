<?php

namespace App\WhatsApp;

use App\Models\WhatsAppConversation;
use Illuminate\Support\Carbon;

/**
 * Meta's customer service window: free-form messages are allowed for 24 hours after
 * the customer's last message. Outside it only an approved template can be sent.
 */
class ConversationWindow
{
    public const HOURS = 24;

    public function closesAt(WhatsAppConversation $conversation): ?Carbon
    {
        $last = $conversation->messages()
            ->where('direction', 'inbound')
            ->latest('created_at')
            ->value('created_at');

        return $last === null ? null : Carbon::parse($last)->addHours(self::HOURS);
    }

    public function isOpen(WhatsAppConversation $conversation): bool
    {
        $closesAt = $this->closesAt($conversation);

        return $closesAt !== null && $closesAt->isFuture();
    }
}
