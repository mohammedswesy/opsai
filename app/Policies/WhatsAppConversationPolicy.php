<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WhatsAppConversation;

class WhatsAppConversationPolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->can($user, 'whatsapp.view');
    }

    public function view(User $user, WhatsAppConversation $conversation): bool
    {
        return $this->inTenant($conversation) && $this->can($user, 'whatsapp.view');
    }

    public function reply(User $user, WhatsAppConversation $conversation): bool
    {
        return $this->inTenant($conversation) && $this->can($user, 'whatsapp.manage');
    }

    /** Anyone who may reply in the inbox may send an approved template. */
    public function sendTemplate(User $user, WhatsAppConversation $conversation): bool
    {
        return $this->reply($user, $conversation);
    }
}
