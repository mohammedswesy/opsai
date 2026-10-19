<?php

namespace App\Models;

use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Task extends Model
{
    use BelongsToOrganization, HasUlids;

    protected $fillable = [
        'organization_id',
        'branch_id',
        'created_by',
        'assigned_to',
        'title',
        'description',
        'priority',
        'status',
        'due_at',
        'completed_at',
        'related_type',
        'related_id',
    ];

    protected function casts(): array
    {
        return [
            'priority' => Priority::class,
            'status' => TaskStatus::class,
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'subject');
    }

    public function latestActivity(): MorphOne
    {
        return $this->morphOne(Activity::class, 'subject')->latestOfMany();
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }
}
