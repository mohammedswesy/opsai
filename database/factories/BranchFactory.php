<?php

namespace Database\Factories;

use App\Enums\BranchStatus;
use App\Models\Branch;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => fake()->city().' Branch',
            'code' => strtoupper(fake()->unique()->bothify('BR##')),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'country' => 'SA',
            'timezone' => 'Asia/Riyadh',
            'phone' => '0500000000',
            'status' => BranchStatus::Active,
        ];
    }
}
