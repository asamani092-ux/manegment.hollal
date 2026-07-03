<?php

namespace Database\Factories;

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
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'type' => 'single',
            'assigned_by' => User::factory(),
            'assigned_to' => User::factory(),
            'priority' => 'medium',
            'status' => 'new',
        ];
    }
}
