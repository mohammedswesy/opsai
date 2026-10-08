<?php

namespace App\Models;

use App\Enums\BranchStatus;
use App\Models\Concerns\BelongsToOrganization;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use BelongsToOrganization, HasFactory, HasUlids, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'name',
        'code',
        'address',
        'city',
        'country',
        'latitude',
        'longitude',
        'phone',
        'timezone',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BranchStatus::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }
}
