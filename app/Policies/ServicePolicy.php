<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;

class ServicePolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->can($user, 'services.view');
    }

    public function view(User $user, Service $service): bool
    {
        return $this->inTenant($service) && $this->can($user, 'services.view');
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'services.manage');
    }

    public function update(User $user, Service $service): bool
    {
        return $this->inTenant($service) && $this->can($user, 'services.manage');
    }

    public function delete(User $user, Service $service): bool
    {
        return $this->update($user, $service);
    }
}
