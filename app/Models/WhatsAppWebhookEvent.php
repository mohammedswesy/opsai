<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class WhatsAppWebhookEvent extends Model
{
    use HasUlids;

    protected $table = 'whatsapp_webhook_events';

    protected $fillable = [
        'organization_id',
        'provider',
        'external_event_id',
        'event_type',
        'payload',
        'signature_valid',
        'processed_at',
        'status',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'signature_valid' => 'boolean',
            'processed_at' => 'datetime',
        ];
    }
}
