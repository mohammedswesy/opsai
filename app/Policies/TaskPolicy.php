<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->can($user, 'tasks.view');
    }

    public function view(User $user, Task $task): bool
    {
        return $this->inTenant($task) && $this->can($user, 'tasks.view');
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'tasks.manage');
    }

    public function update(User $user, Task $task): bool
    {
        return $this->inTenant($task) && $this->can($user, 'tasks.manage');
    }
}
