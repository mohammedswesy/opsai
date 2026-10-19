<?php

namespace App\AI\Data;

use App\Models\Customer;
use App\Models\User;

final class AIToolContext
{
    public function __construct(
        public readonly ?User $user,
        public readonly ?string $branchId = null,
        public readonly bool $allBranches = false,
        public readonly string $channel = 'staff',
        public readonly ?Customer $customer = null,
    ) {}

    public function withAllBranches(bool $allBranches): self
    {
        return new self($this->user, $this->branchId, $allBranches, $this->channel, $this->customer);
    }

    public function scopedBranchId(): ?string
    {
        if ($this->allBranches || $this->branchId === null || $this->branchId === '') {
            return null;
        }

        return $this->branchId;
    }
}
