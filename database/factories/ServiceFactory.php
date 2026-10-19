<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->randomElement(['AC Maintenance', 'Electrical Repair', 'Plumbing Visit', 'Deep Cleaning']),
            'category' => 'HVAC',
            'base_price' => 250,
            'estimated_duration' => 90,
            'status' => 'active',
        ];
    }
}
