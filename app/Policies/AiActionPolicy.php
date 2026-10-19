<?php

namespace App\Policies;

use App\Models\AiAction;
use App\Models\User;

class AiActionPolicy extends TenantPolicy
{
    public function view(User $user, AiAction $action): bool
    {
        return $this->inTenant($action) && $action->user_id === $user->id;
    }

    public function confirm(User $user, AiAction $action): bool
    {
        return $this->view($user, $action);
    }
}
