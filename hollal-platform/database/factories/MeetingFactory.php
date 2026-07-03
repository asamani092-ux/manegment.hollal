<?php

namespace Database\Factories;

use App\Models\Meeting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Meeting> */
class MeetingFactory extends Factory
{
    protected $model = Meeting::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'scheduled_at' => fake()->dateTimeBetween('-1 month', '+1 month'),
            'location' => fake()->optional()->address(),
            'link' => fake()->optional()->url(),
            'chair_id' => User::factory(),
            'secretary_id' => User::factory(),
            'status' => 'scheduled',
        ];
    }
}
