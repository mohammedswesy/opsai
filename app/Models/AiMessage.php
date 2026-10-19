<?php

namespace App\Models;

use App\Enums\AiMessageRole;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMessage extends Model
{
    use HasUlids;

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'tool_calls',
        'tool_results',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'role' => AiMessageRole::class,
            'tool_calls' => 'array',
            'tool_results' => 'array',
            'metadata' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }
}
