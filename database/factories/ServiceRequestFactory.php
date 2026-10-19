<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Organization;
use App\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceRequest>
 */
class ServiceRequestFactory extends Factory
{
    protected $model = ServiceRequest::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'customer_id' => Customer::factory(),
            'source' => 'manual',
            'subject' => 'AC not cooling',
            'description' => 'The indoor unit is running but the room stays warm.',
            'priority' => 'normal',
            'status' => 'new',
            'requested_at' => now(),
        ];
    }
}
