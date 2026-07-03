<?php

namespace Database\Factories;

use App\Models\Payroll;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payroll>
 */
class PayrollFactory extends Factory
{
    protected $model = Payroll::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $base = fake()->randomFloat(2, 3000, 15000);
        $additions = fake()->randomFloat(2, 0, 2000);
        $deductions = fake()->randomFloat(2, 0, 1000);

        return [
            'employee_id' => User::factory(),
            'month' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-01'),
            'base' => $base,
            'additions' => $additions,
            'deductions' => $deductions,
            'net' => Payroll::computeNet($base, $additions, $deductions),
            'transfer_status' => fake()->randomElement(['pending', 'transferred', 'failed']),
        ];
    }
}
