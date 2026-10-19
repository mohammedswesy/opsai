<?php

namespace App\Policies;

use App\Models\AiConversation;
use App\Models\User;

class AiConversationPolicy extends TenantPolicy
{
    public function view(User $user, AiConversation $conversation): bool
    {
        return $this->inTenant($conversation) && $conversation->user_id === $user->id;
    }
}
