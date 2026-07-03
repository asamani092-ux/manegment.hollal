<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectUpdate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectUpdate>
 */
class ProjectUpdateFactory extends Factory
{
    protected $model = ProjectUpdate::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'author_id' => User::factory(),
            'done' => fake()->paragraph(),
            'next' => fake()->paragraph(),
            'blockers' => fake()->optional()->sentence(),
            'decision_needed' => fake()->optional()->sentence(),
            'date' => fake()->date(),
        ];
    }
}
