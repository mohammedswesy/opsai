<?php

namespace App\Policies;

use App\Models\Technician;
use App\Models\User;

class TechnicianPolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->can($user, 'technicians.view');
    }

    public function view(User $user, Technician $technician): bool
    {
        return $this->inTenant($technician) && $this->can($user, 'technicians.view');
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'technicians.manage');
    }

    public function update(User $user, Technician $technician): bool
    {
        return $this->inTenant($technician) && $this->can($user, 'technicians.manage');
    }

    public function delete(User $user, Technician $technician): bool
    {
        return $this->update($user, $technician);
    }
}
