<?php

namespace Tests\Feature;

use App\Livewire\DashboardIndex;
use App\Models\OfficialDutiesDocument;
use App\Models\PeriodicEvaluation;
use App\Models\Responsibility;
use App\Models\Task;
use App\Models\User;
use App\Services\EvaluationService;
use App\Services\OffboardingService;
use App\Services\OnboardingService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 01-B5 — responsibilities, evaluations (1–5, employee read-only), onboarding
 * tasks, offboarding account disable, duties file dashboard link.
 */
class HrLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_evaluation_score_must_be_between_1_and_5(): void
    {
        $employee = User::factory()->create();
        $responsibility = Responsibility::create(['employee_id' => $employee->id, 'body' => 'إدارة الملفات']);
        $evaluation = PeriodicEvaluation::create(['employee_id' => $employee->id, 'period' => '2026-Q3']);
        $service = app(EvaluationService::class);

        $service->recordScore($evaluation, $responsibility, 4);
        $this->assertDatabaseHas('evaluation_scores', ['score' => 4, 'responsibility_id' => $responsibility->id]);

        $this->expectException(\InvalidArgumentException::class);
        $service->recordScore($evaluation, $responsibility, 6);
    }

    public function test_published_evaluation_scores_are_read_only_and_employee_can_comment(): void
    {
        $employee = User::factory()->create();
        $responsibility = Responsibility::create(['employee_id' => $employee->id, 'body' => 'المتابعة']);
        $evaluation = PeriodicEvaluation::create(['employee_id' => $employee->id, 'period' => '2026-Q3']);
        $service = app(EvaluationService::class);

        $service->recordScore($evaluation, $responsibility, 5);
        $service->publish($evaluation);

        // Employee may add a single comment.
        $service->addEmployeeComment($evaluation, 'أشكر التقييم وسأعمل على التطوير');
        $this->assertNotNull($evaluation->fresh()->employee_comment);

        // Scores are locked after publication.
        $this->expectException(\RuntimeException::class);
        $service->recordScore($evaluation, $responsibility, 3);
    }

    public function test_onboarding_generates_tasks(): void
    {
        $creator = User::factory()->create();
        $employee = User::factory()->create();

        $tasks = app(OnboardingService::class)->generateTasks($employee, $creator);

        $this->assertGreaterThanOrEqual(4, count($tasks));
        $this->assertSame(count($tasks), Task::where('assigned_by', $creator->id)->count());
    }

    public function test_offboarding_disables_account(): void
    {
        $actor = User::factory()->create();
        $employee = User::factory()->create(['employment_status' => 'نشط', 'is_active' => true]);

        app(OffboardingService::class)->offboard($employee, $actor);

        $this->assertSame('منتهية_علاقته', $employee->fresh()->employment_status);
        $this->assertFalse((bool) $employee->fresh()->is_active);
        $this->assertDatabaseHas('tasks', ['assigned_by' => $actor->id, 'priority' => 'high']);
    }

    public function test_published_duties_file_surfaces_on_dashboard(): void
    {
        OfficialDutiesDocument::create([
            'version' => 1,
            'file_path' => 'duties/v1.pdf',
            'published_at' => now(),
        ]);

        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo('dashboard.view');

        Livewire::actingAs($user)
            ->test(DashboardIndex::class)
            ->assertSee('ملف المهام الرسمي');
    }
}
