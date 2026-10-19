<?php

namespace Database\Factories;

use App\Models\Technician;
use App\Models\TechnicianSkill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TechnicianSkill>
 */
class TechnicianSkillFactory extends Factory
{
    protected $model = TechnicianSkill::class;

    public function definition(): array
    {
        return [
            'technician_id' => Technician::factory(),
            'skill' => 'hvac',
        ];
    }
}
