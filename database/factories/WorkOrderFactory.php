<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Organization;
use App\Models\ServiceRequest;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkOrder>
 */
class WorkOrderFactory extends Factory
{
    protected $model = WorkOrder::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'customer_id' => Customer::factory(),
            'service_request_id' => ServiceRequest::factory(),
            'title' => 'Inspect the indoor unit',
            'priority' => 'normal',
            'status' => 'assigned',
        ];
    }
}
