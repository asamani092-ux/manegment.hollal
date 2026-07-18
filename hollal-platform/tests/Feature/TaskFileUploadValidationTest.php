<?php

namespace Tests\Feature;

use App\Livewire\Tasks\TasksIndex;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class TaskFileUploadValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $assigner;

    protected User $assignee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        Storage::fake('local');

        $this->assigner = User::factory()->create(['phone' => '0501111111']);
        $this->assigner->givePermissionTo(['esnad.tasks.view', 'esnad.tasks.create']);

        $this->assignee = User::factory()->create(['phone' => '0502222222']);
    }

    public function test_save_task_rejects_disallowed_attachment_mime_type(): void
    {
        Livewire::actingAs($this->assigner)
            ->test(TasksIndex::class)
            ->call('openTaskCreate')
            ->set('title', 'مهمة بملف غير مسموح')
            ->set('assigned_to', $this->assignee->id)
            ->set('priority', 'medium')
            ->set('status', 'new')
            ->set('attachment', UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload'))
            ->call('saveTask')
            ->assertHasErrors(['attachment']);

        $this->assertDatabaseCount('tasks', 0);
    }
}
