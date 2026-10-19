<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WhatsAppConversation extends Model
{
    use BelongsToOrganization, HasUlids;

    protected $table = 'whatsapp_conversations';

    protected $fillable = [
        'organization_id',
        'integration_id',
        'customer_id',
        'ai_conversation_id',
        'contact_phone',
        'contact_name',
        'status',
        'unread_count',
        'last_message_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'last_message_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(Integration::class);
    }

    public function aiConversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class, 'whatsapp_conversation_id');
    }
}
