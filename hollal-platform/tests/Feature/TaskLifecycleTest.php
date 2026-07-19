<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskLifecycleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 02-B1 — triple-evaluation lifecycle: evidence gate, PM→final ordering,
 * revision loop, status-log history.
 */
class TaskLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private TaskLifecycleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TaskLifecycleService::class);
    }

    public function test_cannot_submit_for_review_without_required_evidence(): void
    {
        $assignee = User::factory()->create();
        $task = Task::factory()->create([
            'assigned_to' => $assignee->id,
            'status' => 'in_progress',
            'required_evidence' => 'أرفق تقرير الإنجاز',
            'submitted_file' => null,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->service->submitForReview($task, $assignee, 'متميز');
    }

    public function test_submit_with_evidence_moves_to_review_and_logs(): void
    {
        $assignee = User::factory()->create();
        $task = Task::factory()->create([
            'assigned_to' => $assignee->id,
            'status' => 'in_progress',
            'required_evidence' => 'أرفق تقرير الإنجاز',
            'submitted_file' => 'tasks/evidence.pdf',
        ]);

        $this->service->submitForReview($task, $assignee, 'متميز', 'تم الإنجاز');

        $this->assertSame('pending_review', $task->fresh()->status);
        $this->assertSame('متميز', $task->fresh()->self_rating);
        $this->assertDatabaseHas('task_status_logs', [
            'task_id' => $task->id,
            'to_status' => 'pending_review',
        ]);
    }

    public function test_final_rating_requires_pm_rating_for_project_tasks(): void
    {
        $assigner = User::factory()->create();
        $pm = User::factory()->create();
        $project = Project::factory()->create(['manager_id' => $pm->id]);
        $task = Task::factory()->create([
            'assigned_by' => $assigner->id,
            'project_id' => $project->id,
            'status' => 'pending_review',
        ]);

        // Final rating blocked before the PM rates.
        try {
            $this->service->recordFinalRating($task, $assigner, 'متوسط');
            $this->fail('Expected the PM-rating gate to block the final rating.');
        } catch (\RuntimeException) {
            // expected
        }

        $this->service->recordPmRating($task, $pm, 'متميز');
        $this->service->recordFinalRating($task, $assigner, 'متوسط', 'ملاحظة ختامية');

        $this->assertSame('completed', $task->fresh()->status);
        $this->assertSame('متوسط', $task->fresh()->final_rating);
        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_assignee_cannot_set_final_rating(): void
    {
        $assigner = User::factory()->create();
        $assignee = User::factory()->create();
        $task = Task::factory()->create([
            'assigned_by' => $assigner->id,
            'assigned_to' => $assignee->id,
            'status' => 'pending_review',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->service->recordFinalRating($task, $assignee, 'متميز');
    }

    public function test_revision_returns_to_in_progress_and_preserves_history(): void
    {
        $assigner = User::factory()->create();
        $assignee = User::factory()->create();
        $task = Task::factory()->create([
            'assigned_by' => $assigner->id,
            'assigned_to' => $assignee->id,
            'status' => 'in_progress',
            'submitted_file' => 'tasks/e.pdf',
        ]);

        $this->service->submitForReview($task, $assignee, 'مقبول');
        $this->service->requestRevision($task, $assigner, 'يرجى تحسين التقرير');

        $this->assertSame('in_progress', $task->fresh()->status);
        $this->assertGreaterThanOrEqual(2, $task->statusLogs()->count());
    }

    public function test_invalid_rating_rejected(): void
    {
        $assignee = User::factory()->create();
        $task = Task::factory()->create(['assigned_to' => $assignee->id, 'status' => 'in_progress']);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->submitForReview($task, $assignee, 'ممتاز-جدا');
    }
}
