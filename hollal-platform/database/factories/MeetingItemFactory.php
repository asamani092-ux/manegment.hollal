<?php

namespace Database\Factories;

use App\Models\Meeting;
use App\Models\MeetingItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MeetingItem> */
class MeetingItemFactory extends Factory
{
    protected $model = MeetingItem::class;

    public function definition(): array
    {
        return [
            'meeting_id' => Meeting::factory(),
            'topic' => fake()->sentence(4),
            'discussion_summary' => fake()->optional()->paragraph(),
            'decision' => fake()->optional()->sentence(),
            'responsible_id' => User::factory(),
            'due_date' => fake()->optional()->dateTimeBetween('now', '+1 month'),
            'status' => 'open',
        ];
    }
}
