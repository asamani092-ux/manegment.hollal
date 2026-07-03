<?php

namespace Database\Factories;

use App\Models\ExpenseRequest;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExpenseRequest>
 */
class ExpenseRequestFactory extends Factory
{
    protected $model = ExpenseRequest::class;

    public function definition(): array
    {
        return [
            'requester_id' => User::factory(),
            'project_id' => Project::factory(),
            'type' => 'operational',
            'amount' => fake()->randomFloat(2, 100, 5000),
            'reason' => fake()->sentence(),
            'payment_method' => 'bank_transfer',
            'status' => 'draft',
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => 'pending']);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => 'approved',
            'approved_at' => now(),
        ]);
    }
}
