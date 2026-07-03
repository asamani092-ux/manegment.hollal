<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TaskFileDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected Task $task;

    protected User $assigner;

    protected User $assignee;

    protected User $outsider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);

        Storage::fake('local');

        $this->assigner = User::factory()->create(['phone' => '0501111111']);
        $this->assignee = User::factory()->create(['phone' => '0502222222']);
        $this->outsider = User::factory()->create(['phone' => '0503333333']);

        Storage::disk('local')->put('tasks/sample.pdf', 'task-file-content');

        $this->task = Task::factory()->create([
            'assigned_by' => $this->assigner->id,
            'assigned_to' => $this->assignee->id,
            'attachment_path' => 'tasks/sample.pdf',
        ]);
    }

    public function test_guest_is_redirected_to_login_when_downloading_task_file(): void
    {
        $response = $this->get(route('tasks.files.download', [
            'task' => $this->task,
            'type' => 'attachment',
        ]));

        $response->assertRedirect(route('login'));
    }

    public function test_unrelated_user_receives_forbidden_when_downloading_task_file(): void
    {
        $response = $this->actingAs($this->outsider)->get(route('tasks.files.download', [
            'task' => $this->task,
            'type' => 'attachment',
        ]));

        $response->assertForbidden();
    }

    public function test_assignee_can_download_task_attachment(): void
    {
        $response = $this->actingAs($this->assignee)->get(route('tasks.files.download', [
            'task' => $this->task,
            'type' => 'attachment',
        ]));

        $response->assertOk();
        $response->assertDownload('sample.pdf');
    }

    public function test_assigner_can_download_task_attachment(): void
    {
        $response = $this->actingAs($this->assigner)->get(route('tasks.files.download', [
            'task' => $this->task,
            'type' => 'attachment',
        ]));

        $response->assertOk();
        $response->assertDownload('sample.pdf');
    }

    public function test_user_with_tasks_view_permission_can_download_task_attachment(): void
    {
        Permission::findByName('tasks.view', 'web');
        $this->outsider->givePermissionTo('tasks.view');

        $response = $this->actingAs($this->outsider)->get(route('tasks.files.download', [
            'task' => $this->task,
            'type' => 'attachment',
        ]));

        $response->assertOk();
        $response->assertDownload('sample.pdf');
    }
}
