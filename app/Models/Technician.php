<?php

namespace App\Models;

use App\Enums\AvailabilityStatus;
use App\Enums\RecordStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Technician extends Model
{
    use BelongsToOrganization, HasUlids, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'branch_id',
        'user_id',
        'employee_code',
        'availability_status',
        'current_latitude',
        'current_longitude',
        'hourly_rate',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'availability_status' => AvailabilityStatus::class,
            'status' => RecordStatus::class,
            'current_latitude' => 'decimal:7',
            'current_longitude' => 'decimal:7',
            'hourly_rate' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function skills(): HasMany
    {
        return $this->hasMany(TechnicianSkill::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }
}
