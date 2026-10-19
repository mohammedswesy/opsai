<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkOrder;

class WorkOrderPolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->can($user, 'work_orders.view');
    }

    public function view(User $user, WorkOrder $workOrder): bool
    {
        if (! $this->inTenant($workOrder) || ! $this->can($user, 'work_orders.view')) {
            return false;
        }

        if ($this->can($user, 'work_orders.manage')) {
            return true;
        }

        return $workOrder->technician?->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'work_orders.manage');
    }

    public function assign(User $user, WorkOrder $workOrder): bool
    {
        return $this->inTenant($workOrder) && $this->can($user, 'work_orders.manage');
    }

    public function transition(User $user, WorkOrder $workOrder): bool
    {
        if (! $this->inTenant($workOrder)) {
            return false;
        }

        if ($this->can($user, 'work_orders.manage')) {
            return true;
        }

        return $this->can($user, 'work_orders.progress') && $workOrder->technician?->user_id === $user->id;
    }
}
