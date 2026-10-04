<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedIfEmptyTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_users_skip_the_demo_seed(): void
    {
        User::factory()->create(['name' => 'تعديل المالك']);

        $this->artisan('db:seed-if-empty')
            ->expectsOutputToContain('skipping demo seed')
            ->assertSuccessful();

        $this->assertSame(1, User::query()->count());
        $this->assertSame('تعديل المالك', User::query()->value('name'));
    }
}
