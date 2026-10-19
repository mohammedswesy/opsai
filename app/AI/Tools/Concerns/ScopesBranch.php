<?php

namespace App\AI\Tools\Concerns;

use App\AI\Data\AIToolContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

trait ScopesBranch
{
    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function scopeBranch(Builder $query, AIToolContext $context, string $column = 'branch_id'): Builder
    {
        $branchId = $context->scopedBranchId();

        if ($branchId !== null) {
            $query->where($column, $branchId);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    protected function metricsRequest(AIToolContext $context, array $query = []): Request
    {
        $branchId = $context->scopedBranchId();

        if ($branchId !== null) {
            $query['branch_id'] = $branchId;
        }

        return Request::create('/', 'GET', $query);
    }
}
