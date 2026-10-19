<?php

namespace App\Models;

use App\Enums\Priority;
use App\Enums\RequestSource;
use App\Enums\ServiceRequestStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ServiceRequest extends Model
{
    use BelongsToOrganization, HasUlids;

    protected $fillable = [
        'organization_id',
        'branch_id',
        'customer_id',
        'service_id',
        'source',
        'subject',
        'description',
        'priority',
        'status',
        'requested_at',
        'preferred_date',
        'preferred_time',
        'location',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'source' => RequestSource::class,
            'priority' => Priority::class,
            'status' => ServiceRequestStatus::class,
            'requested_at' => 'datetime',
            'preferred_date' => 'date',
            'metadata' => 'array',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function workOrder(): HasOne
    {
        return $this->hasOne(WorkOrder::class);
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }
}
