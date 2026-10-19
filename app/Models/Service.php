<?php

namespace App\Models;

use App\Enums\RecordStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use BelongsToOrganization, HasUlids, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'name',
        'description',
        'category',
        'base_price',
        'estimated_duration',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'estimated_duration' => 'integer',
            'status' => RecordStatus::class,
        ];
    }
}
