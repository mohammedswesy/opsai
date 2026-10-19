<?php

namespace App\Models;

use App\Enums\AiActionStatus;
use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiAction extends Model
{
    use BelongsToOrganization, HasUlids;

    protected $fillable = [
        'organization_id',
        'user_id',
        'conversation_id',
        'tool_name',
        'action_type',
        'arguments',
        'status',
        'result',
        'idempotency_key',
        'approved_at',
        'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'arguments' => 'array',
            'result' => 'array',
            'status' => AiActionStatus::class,
            'approved_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }
}
