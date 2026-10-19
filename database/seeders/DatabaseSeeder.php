<?php

namespace Database\Seeders;

use App\Enums\BranchStatus;
use App\Enums\OrganizationRole;
use App\Enums\OrganizationStatus;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $riyadh = $this->organization('Riyadh Cooling Services', 'riyadh-cooling-services', 'SA', 'Asia/Riyadh', 'SAR', 'ar', '15');
        $jeddah = $this->organization('Jeddah Maintenance Pro', 'jeddah-maintenance-pro', 'SA', 'Asia/Riyadh', 'AED', 'en', '5');

        $riyadhMain = $this->branch($riyadh, 'Riyadh Main', 'RYD-MAIN', 'Riyadh');
        $riyadhNorth = $this->branch($riyadh, 'North Riyadh', 'RYD-NORTH', 'Riyadh');
        $jeddahBranch = $this->branch($jeddah, 'Jeddah Branch', 'JED-MAIN', 'Jeddah');

        $owner = $this->member($riyadh, 'Riyadh Owner', 'owner@riyadh-cooling.test', OrganizationRole::Owner);
        $manager = $this->member($riyadh, 'Riyadh Manager', 'manager@riyadh-cooling.test', OrganizationRole::Manager);
        $dispatcher = $this->member($riyadh, 'Riyadh Dispatcher', 'dispatcher@riyadh-cooling.test', OrganizationRole::Dispatcher);
        $technician = $this->member($riyadh, 'Riyadh Technician', 'technician@riyadh-cooling.test', OrganizationRole::Technician);
        $accountant = $this->member($riyadh, 'Riyadh Accountant', 'accountant@riyadh-cooling.test', OrganizationRole::Accountant);
        $support = $this->member($riyadh, 'Riyadh Support', 'support@riyadh-cooling.test', OrganizationRole::CustomerService);
        $jeddahOwner = $this->member($jeddah, 'Jeddah Owner', 'owner@jeddah-maintenance.test', OrganizationRole::Owner);

        $riyadhMain->users()->attach([$owner->id, $manager->id, $technician->id]);
        $riyadhNorth->users()->attach([$dispatcher->id, $accountant->id, $support->id]);
        $jeddahBranch->users()->attach($jeddahOwner->id);

        $this->call(OperationsSeeder::class);
        $this->call(FinanceSeeder::class);
    }

    private function organization(string $name, string $slug, string $country, string $timezone, string $currency, string $locale, string $taxRate): Organization
    {
        return Organization::query()->create([
            'name' => $name,
            'slug' => $slug,
            'legal_name' => $name,
            'country' => $country,
            'timezone' => $timezone,
            'currency' => $currency,
            'locale' => $locale,
            'status' => OrganizationStatus::Active,
            'email' => 'hello@'.str_replace('-', '', $slug).'.test',
            'phone' => '0110000000',
            'settings' => ['tax_rate' => $taxRate],
        ]);
    }

    private function branch(Organization $organization, string $name, string $code, string $city): Branch
    {
        return Branch::withoutGlobalScope('organization')->create([
            'organization_id' => $organization->id,
            'name' => $name,
            'code' => $code,
            'city' => $city,
            'country' => $organization->country,
            'timezone' => $organization->timezone,
            'status' => BranchStatus::Active,
        ]);
    }

    private function member(Organization $organization, string $name, string $email, OrganizationRole $role): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => $email,
        ]);

        $organization->members()->attach($user->id, [
            'role' => $role->value,
        ]);

        return $user;
    }
}
