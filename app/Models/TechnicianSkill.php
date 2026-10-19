<?php

namespace App\Models;

use App\Enums\TechnicianSkillName;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnicianSkill extends Model
{
    use HasUlids;

    protected $fillable = [
        'technician_id',
        'service_id',
        'skill',
    ];

    protected function casts(): array
    {
        return [
            'skill' => TechnicianSkillName::class,
        ];
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(Technician::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
