<?php

namespace App\Models;

use App\Enums\CustomerType;
use App\Enums\RecordStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use BelongsToOrganization, HasUlids, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'branch_id',
        'type',
        'name',
        'company_name',
        'email',
        'phone',
        'whatsapp_number',
        'address',
        'city',
        'country',
        'notes',
        'source',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'type' => CustomerType::class,
            'status' => RecordStatus::class,
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }
}
