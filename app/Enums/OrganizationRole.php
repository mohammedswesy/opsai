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
                'customers.view',
                'customers.manage',
                'services.view',
                'services.manage',
                'technicians.view',
                'technicians.manage',
                'requests.view',
                'requests.manage',
                'appointments.view',
                'appointments.manage',
                'work_orders.view',
                'work_orders.manage',
                'tasks.view',
                'tasks.manage',
                'invoices.view',
                'invoices.manage',
                'payments.view',
                'payments.manage',
                'whatsapp.view',
                'whatsapp.manage',
            ],
            self::Manager => [
                'organization.view',
                'branches.view',
                'branches.manage',
                'users.view',
                'customers.view',
                'customers.manage',
                'services.view',
                'services.manage',
                'technicians.view',
                'technicians.manage',
                'requests.view',
                'requests.manage',
                'appointments.view',
                'appointments.manage',
                'work_orders.view',
                'work_orders.manage',
                'tasks.view',
                'tasks.manage',
                'invoices.view',
                'invoices.manage',
                'payments.view',
                'payments.manage',
                'whatsapp.view',
                'whatsapp.manage',
            ],
            self::Dispatcher => [
                'organization.view',
                'branches.view',
                'customers.view',
                'customers.manage',
                'services.view',
                'technicians.view',
                'requests.view',
                'requests.manage',
                'appointments.view',
                'appointments.manage',
                'work_orders.view',
                'work_orders.manage',
                'tasks.view',
                'tasks.manage',
                'invoices.view',
                'payments.view',
                'whatsapp.view',
                'whatsapp.manage',
            ],
            self::Technician => [
                'organization.view',
                'branches.view',
                'customers.view',
                'services.view',
                'requests.view',
                'appointments.view',
                'work_orders.view',
                'work_orders.progress',
                'tasks.view',
            ],
            self::Accountant => [
                'organization.view',
                'branches.view',
                'customers.view',
                'requests.view',
                'work_orders.view',
                'tasks.view',
                'invoices.view',
                'invoices.manage',
                'payments.view',
                'payments.manage',
            ],
            self::CustomerService => [
                'organization.view',
                'branches.view',
                'customers.view',
                'customers.manage',
                'services.view',
                'requests.view',
                'requests.manage',
                'appointments.view',
                'tasks.view',
                'tasks.manage',
                'invoices.view',
                'payments.view',
                'whatsapp.view',
                'whatsapp.manage',
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
