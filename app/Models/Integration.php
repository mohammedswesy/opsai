<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Integration extends Model
{
    use BelongsToOrganization, HasUlids;

    protected $fillable = [
        'organization_id',
        'provider',
        'type',
        'status',
        'reminders_enabled',
        'attention_reason',
        'attention_at',
        'credentials',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'metadata' => 'array',
            'reminders_enabled' => 'boolean',
            'attention_at' => 'datetime',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function safeMetadata(): array
    {
        $metadata = $this->metadata ?? [];
        $credentials = is_array($this->credentials) ? $this->credentials : [];

        return [
            'provider' => $this->provider,
            'status' => $this->status,
            'phone_number_id' => $metadata['phone_number_id'] ?? null,
            'display_phone' => $metadata['display_phone'] ?? null,
            'business_account_id' => $metadata['business_account_id'] ?? null,
            'token_hint' => $this->hint($credentials['access_token'] ?? null),
            'secret_hint' => $this->hint($credentials['app_secret'] ?? null),
            'verify_token_set' => is_string($credentials['verify_token'] ?? null) && $credentials['verify_token'] !== '',
        ];
    }

    public function needsAttention(): bool
    {
        return $this->attention_reason !== null;
    }

    /**
     * Connection health for the WhatsApp pages: a reason code and when it was seen,
     * never Meta's own error text.
     *
     * @return array{needs_attention: bool, reason: string|null, since: string|null}
     */
    public function health(): array
    {
        return [
            'needs_attention' => $this->needsAttention(),
            'reason' => $this->attention_reason,
            'since' => $this->attention_at?->toIso8601String(),
        ];
    }

    private function hint(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return '••••'.(strlen($value) >= 8 ? substr($value, -4) : '');
    }
}
