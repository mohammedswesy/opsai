<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    protected $model = Task::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'created_by' => User::factory(),
            'title' => 'Call the site contact',
            'priority' => 'normal',
            'status' => 'todo',
        ];
    }
}
