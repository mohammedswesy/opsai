<?php

namespace App\Services\Finance;

use App\Models\InvoiceSequence;
use App\Support\OrganizationContext;
use Illuminate\Database\UniqueConstraintViolationException;

class InvoiceNumberService
{
    public function next(): string
    {
        $organizationId = OrganizationContext::id();
        $year = (int) now(OrganizationContext::current()->organization->timezone)->year;

        $sequence = InvoiceSequence::query()
            ->where('organization_id', $organizationId)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        if ($sequence === null) {
            try {
                $sequence = InvoiceSequence::query()->create([
                    'organization_id' => $organizationId,
                    'year' => $year,
                    'last_number' => 0,
                ]);
            } catch (UniqueConstraintViolationException) {
                $sequence = InvoiceSequence::query()
                    ->where('organization_id', $organizationId)
                    ->where('year', $year)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $sequence = InvoiceSequence::query()->whereKey($sequence->id)->lockForUpdate()->firstOrFail();
        }

        $next = $sequence->last_number + 1;
        $sequence->update(['last_number' => $next]);

        return sprintf('INV-%d-%06d', $year, $next);
    }
}
