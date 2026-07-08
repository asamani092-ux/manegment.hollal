<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileDownloadRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);

        Storage::fake('local');

        $this->user = User::factory()->create(['must_change_password' => false]);
        $this->user->givePermissionTo(['tasks.view', 'tasks.create']);

        $this->task = Task::factory()->create([
            'assigned_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'attachment_path' => 'tasks/rate.pdf',
        ]);

        Storage::disk('local')->put('tasks/rate.pdf', 'content');

        RateLimiter::clear('files:'.$this->user->id);
    }

    public function test_files_routes_are_rate_limited(): void
    {
        $url = route('tasks.files.download', ['task' => $this->task, 'type' => 'attachment']);

        for ($i = 0; $i < 30; $i++) {
            $this->actingAs($this->user)->get($url)->assertOk();
        }

        $this->actingAs($this->user)->get($url)->assertStatus(429);
    }
}
