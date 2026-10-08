<?php

namespace Database\Factories;

use App\Enums\OrganizationStatus;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Organization::uniqueSlug($name),
            'legal_name' => $name,
            'country' => 'SA',
            'timezone' => 'Asia/Riyadh',
            'currency' => 'SAR',
            'locale' => 'en',
            'status' => OrganizationStatus::Active,
        ];
    }
}
