<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy extends TenantPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->can($user, 'invoices.view');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $this->inTenant($invoice) && $this->can($user, 'invoices.view');
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'invoices.manage');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $this->inTenant($invoice) && $this->can($user, 'invoices.manage') && $invoice->status->canEdit();
    }

    public function issue(User $user, Invoice $invoice): bool
    {
        return $this->inTenant($invoice) && $this->can($user, 'invoices.manage');
    }

    public function cancel(User $user, Invoice $invoice): bool
    {
        return $this->inTenant($invoice) && $this->can($user, 'invoices.manage');
    }
}
