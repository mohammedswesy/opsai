<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->can($user, 'customers.view');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $this->inTenant($customer) && $this->can($user, 'customers.view');
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'customers.manage');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->inTenant($customer) && $this->can($user, 'customers.manage');
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $this->update($user, $customer);
    }
}
