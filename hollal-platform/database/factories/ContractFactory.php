<?php

namespace Database\Factories;

use App\Models\Contract;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contract>
 */
class ContractFactory extends Factory
{
    protected $model = Contract::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-1 year', 'now');

        return [
            'employee_id' => User::factory(),
            'start_date' => $start,
            'end_date' => fake()->dateTimeBetween($start, '+2 years'),
            'value' => fake()->randomFloat(2, 5000, 50000),
            'status' => 'active',
        ];
    }
}
