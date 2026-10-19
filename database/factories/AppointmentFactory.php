<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\Technician;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    protected $model = Appointment::class;

    public function definition(): array
    {
        $start = now()->addDay()->setTime(9, 0);

        return [
            'organization_id' => Organization::factory(),
            'customer_id' => Customer::factory(),
            'technician_id' => Technician::factory(),
            'scheduled_start' => $start,
            'scheduled_end' => $start->copy()->addHours(2),
            'status' => 'scheduled',
        ];
    }
}
