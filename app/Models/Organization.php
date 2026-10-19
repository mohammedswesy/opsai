<?php

namespace App\Models;

use App\Enums\OrganizationStatus;
use App\Support\Money;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'legal_name',
        'tax_number',
        'phone',
        'email',
        'country',
        'timezone',
        'currency',
        'locale',
        'logo',
        'status',
        'settings',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrganizationStatus::class,
            'settings' => 'array',
        ];
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Branch, $this>
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function taxRate(): string
    {
        $rate = $this->settings['tax_rate'] ?? '0';

        return is_numeric($rate) ? Money::normalize((string) $rate) : '0.00';
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $base = $base !== '' ? $base : 'organization';
        $slug = $base;
        $suffix = 2;

        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
