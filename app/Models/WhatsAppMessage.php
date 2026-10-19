<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMessage extends Model
{
    use BelongsToOrganization, HasUlids;

    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'organization_id',
        'customer_id',
        'integration_id',
        'whatsapp_conversation_id',
        'external_message_id',
        'direction',
        'from_number',
        'to_number',
        'message_type',
        'text',
        'media',
        'status',
        'sent_at',
        'delivered_at',
        'read_at',
        'failed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'media' => 'array',
            'metadata' => 'array',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WhatsAppConversation::class, 'whatsapp_conversation_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
