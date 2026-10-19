<?php

namespace App\Policies;

use App\Models\ServiceRequest;
use App\Models\User;

class ServiceRequestPolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->can($user, 'requests.view');
    }

    public function view(User $user, ServiceRequest $serviceRequest): bool
    {
        return $this->inTenant($serviceRequest) && $this->can($user, 'requests.view');
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'requests.manage');
    }

    public function update(User $user, ServiceRequest $serviceRequest): bool
    {
        return $this->inTenant($serviceRequest) && $this->can($user, 'requests.manage');
    }
}
