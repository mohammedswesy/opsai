<?php

namespace App\Policies;

use App\Models\User;

class DispatchBoardPolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->can($user, 'appointments.view') || $this->can($user, 'work_orders.view');
    }

    public function viewAppointments(User $user): bool
    {
        return $this->can($user, 'appointments.view');
    }

    public function viewWorkOrders(User $user): bool
    {
        return $this->can($user, 'work_orders.view');
    }

    public function viewRequests(User $user): bool
    {
        return $this->can($user, 'requests.view');
    }
}
