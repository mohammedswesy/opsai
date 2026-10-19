<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Technician;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Technician>
 */
class TechnicianFactory extends Factory
{
    protected $model = Technician::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'user_id' => User::factory(),
            'employee_code' => fake()->unique()->bothify('TEC-###'),
            'availability_status' => 'available',
            'hourly_rate' => 80,
            'status' => 'active',
        ];
    }
}
