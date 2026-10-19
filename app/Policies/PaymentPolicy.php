<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->can($user, 'payments.view');
    }

    public function view(User $user, Payment $payment): bool
    {
        return $this->inTenant($payment) && $this->can($user, 'payments.view');
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'payments.manage');
    }

    public function refund(User $user, Payment $payment): bool
    {
        return $this->inTenant($payment) && $this->can($user, 'payments.manage');
    }
}
