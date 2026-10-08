<?php

namespace App\Actions;

use App\Enums\OrganizationRole;
use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterOrganization
{
    /**
     * @param  array{name: string, email: string, password: string, organization_name: string, country: string, timezone: string, currency: string, locale: string}  $data
     * @return array{user: User, organization: Organization}
     */
    public function handle(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $organization = Organization::query()->create([
                'name' => $data['organization_name'],
                'slug' => Organization::uniqueSlug($data['organization_name']),
                'email' => $data['email'],
                'country' => strtoupper($data['country']),
                'timezone' => $data['timezone'],
                'currency' => strtoupper($data['currency']),
                'locale' => $data['locale'],
                'status' => OrganizationStatus::Active,
            ]);

            $organization->members()->attach($user->id, [
                'role' => OrganizationRole::Owner->value,
            ]);

            return ['user' => $user, 'organization' => $organization];
        });
    }
}
