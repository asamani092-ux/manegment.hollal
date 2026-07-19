<?php

namespace Tests\Feature;

use App\Models\RecurringTaskTemplate;
use App\Models\Task;
use App\Models\User;
use App\Services\RecurringTaskService;
use App\Services\WorkloadService;
use App\Support\Setting;
use Database\Seeders\PlatformSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 02-B3 — recurring generation, completion-triggered next instance, workload
 * counts.
 */
class RecurringTaskTest extends TestCase
{
    use RefreshDatabase;

    private function weeklyTemplate(User $assignee): RecurringTaskTemplate
    {
        return RecurringTaskTemplate::create([
            'title' => 'تقرير أسبوعي',
            'assigned_to_id' => $assignee->id,
            'created_by' => $assignee->id,
            'pattern' => RecurringTaskTemplate::PATTERN_WEEKLY,
            'day_of_week' => now()->dayOfWeek,
            'is_active' => true,
        ]);
    }

    public function test_generates_instance_when_due(): void
    {
        $assignee = User::factory()->create();
        $this->weeklyTemplate($assignee);

        $created = app(RecurringTaskService::class)->generateDue();

        $this->assertCount(1, $created);
        $this->assertDatabaseHas('tasks', ['title' => 'تقرير أسبوعي', 'assigned_to' => $assignee->id]);
    }

    public function test_does_not_duplicate_on_same_day(): void
    {
        $assignee = User::factory()->create();
        $this->weeklyTemplate($assignee);
        $service = app(RecurringTaskService::class);

        $service->generateDue();
        $secondRun = $service->generateDue();

        $this->assertCount(0, $secondRun);
        $this->assertSame(1, Task::where('title', 'تقرير أسبوعي')->count());
    }

    public function test_not_generated_when_not_due(): void
    {
        $assignee = User::factory()->create();
        RecurringTaskTemplate::create([
            'title' => 'مهمة شهرية',
            'assigned_to_id' => $assignee->id,
            'pattern' => RecurringTaskTemplate::PATTERN_MONTHLY,
            'day_of_month' => now()->day === 28 ? 15 : 28, // a day that is not today
            'is_active' => true,
        ]);

        $created = app(RecurringTaskService::class)->generateDue();

        $this->assertCount(0, $created);
    }

    public function test_completion_triggers_next_instance(): void
    {
        $assignee = User::factory()->create();
        $template = $this->weeklyTemplate($assignee);
        $task = Task::factory()->create([
            'recurring_template_id' => $template->id,
            'assigned_to' => $assignee->id,
        ]);

        $next = app(RecurringTaskService::class)->onInstanceCompleted($task);

        $this->assertNotNull($next);
        $this->assertSame($template->id, $next->recurring_template_id);
        $this->assertSame(2, Task::where('recurring_template_id', $template->id)->count());
    }

    public function test_workload_open_count_and_overload(): void
    {
        $this->seed(PlatformSettingsSeeder::class);
        Setting::set('attendance.workload_threshold', 2);

        $member = User::factory()->create();
        Task::factory()->count(3)->create(['assigned_to' => $member->id, 'status' => 'in_progress']);
        Task::factory()->create(['assigned_to' => $member->id, 'status' => 'completed']);

        $service = app(WorkloadService::class);

        $this->assertSame(3, $service->openCount($member->id));
        $this->assertTrue($service->isOverloaded($member->id));
    }

    public function test_command_generates_recurring(): void
    {
        $assignee = User::factory()->create();
        $this->weeklyTemplate($assignee);

        $this->artisan('tasks:generate-recurring')->assertSuccessful();

        $this->assertSame(1, Task::where('title', 'تقرير أسبوعي')->count());
    }
}
