<?php

namespace App\Models\Concerns;

use App\Models\Organization;
use App\Support\OrganizationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToOrganization
{
    public static function bootBelongsToOrganization(): void
    {
        static::addGlobalScope('organization', function (Builder $builder): void {
            $organizationId = OrganizationContext::id();

            if ($organizationId === null) {
                $builder->whereRaw('0 = 1');

                return;
            }

            $builder->where($builder->qualifyColumn('organization_id'), $organizationId);
        });

        static::creating(function (Model $model): void {
            if (! $model->getAttribute('organization_id') && $organizationId = OrganizationContext::id()) {
                $model->setAttribute('organization_id', $organizationId);
            }
        });
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
