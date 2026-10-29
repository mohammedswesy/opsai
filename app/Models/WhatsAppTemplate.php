<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class WhatsAppTemplate extends Model
{
    use BelongsToOrganization, HasUlids;

    public const PURPOSES = ['appointment_reminder', 'appointment_confirmation'];

    public const STATUSES = ['approved', 'pending', 'rejected', 'unknown'];

    /** Fields a parameter mapping may name, all read from existing appointment data. */
    public const FIELDS = ['customer_first_name', 'appointment_date', 'appointment_time', 'service_name', 'company_name'];

    protected $table = 'whatsapp_templates';

    protected $fillable = [
        'organization_id',
        'name',
        'language',
        'purpose',
        'status',
        'provider_status',
        'category',
        'parameters',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /** @return list<string> */
    public function parameterFields(): array
    {
        return array_values(array_filter(
            is_array($this->parameters) ? $this->parameters : [],
            fn ($field) => is_string($field) && in_array($field, self::FIELDS, true),
        ));
    }
}
