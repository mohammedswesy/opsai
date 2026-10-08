<?php

namespace App\Support;

use App\Enums\OrganizationRole;
use App\Models\Organization;

final class OrganizationContext
{
    public function __construct(
        public readonly Organization $organization,
        public readonly OrganizationRole $role,
    ) {}

    public static function set(Organization $organization, OrganizationRole $role): void
    {
        app()->instance(self::class, new self($organization, $role));
    }

    public static function current(): ?self
    {
        if (! app()->bound(self::class)) {
            return null;
        }

        $context = app(self::class);

        return $context instanceof self ? $context : null;
    }

    public static function id(): ?string
    {
        return self::current()?->organization->id;
    }

    public static function forget(): void
    {
        if (app()->bound(self::class)) {
            app()->forgetInstance(self::class);
        }
    }
}
