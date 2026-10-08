<?php

namespace App\Enums;

enum OrganizationRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Manager = 'manager';
    case Dispatcher = 'dispatcher';
    case Technician = 'technician';
    case Accountant = 'accountant';
    case CustomerService = 'customer_service';

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => ['*'],
            self::Admin => [
                'organization.view',
                'organization.update',
                'branches.view',
                'branches.manage',
                'users.view',
                'users.manage',
            ],
            self::Manager => [
                'organization.view',
                'branches.view',
                'branches.manage',
                'users.view',
            ],
            self::Dispatcher, self::Technician, self::Accountant, self::CustomerService => [
                'organization.view',
                'branches.view',
            ],
        };
    }

    public function allows(string $permission): bool
    {
        $permissions = $this->permissions();

        return in_array('*', $permissions, true) || in_array($permission, $permissions, true);
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Roles an actor may assign. Owners are the only role that can grant ownership.
     *
     * @return list<self>
     */
    public function assignableRoles(): array
    {
        return match ($this) {
            self::Owner => self::cases(),
            self::Admin => array_values(array_filter(
                self::cases(),
                fn (self $role) => $role !== self::Owner,
            )),
            default => [],
        };
    }
}
