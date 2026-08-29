<?php

namespace Tests\Feature;

use App\Livewire\Hr\EvaluationsIndex;
use App\Livewire\Users\EmployeeProfileShow;
use App\Models\EmployeeEvaluation;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Services\QuarterlyEvaluationService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Approved/archived quarterly evaluations appear on employee profile archive.
 */
class EvaluationArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_archive_moves_evaluation_to_profile_archive_section(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $admin = User::factory()->create(['must_change_password' => false, 'is_active' => true]);
        $admin->assignRole('Super Admin');

        $employee = User::factory()->create([
            'must_change_password' => false,
            'is_active' => true,
            'employment_status' => User::STATUS_ACTIVE,
            'manager_id' => $admin->id,
        ]);
        EmployeeProfile::create([
            'user_id' => $employee->id,
            'hire_date' => '2025-01-01',
            'employment_type' => 'دوام_كامل',
        ]);

        $service = app(QuarterlyEvaluationService::class);
        $template = $service->createTemplate('قالب أرشيف', [
            ['section' => 'مدير', 'question_text' => 'أ', 'weight' => 60, 'sort_order' => 1],
            ['section' => 'موارد', 'question_text' => 'ب', 'weight' => 40, 'sort_order' => 2],
        ]);
        $cycle = $service->createCycle(2026, 3, $template, '2026-07-01', '2026-09-30');
        $service->openCycle($cycle);
        $service->bulkOpen($cycle->fresh());

        $evaluation = EmployeeEvaluation::query()
            ->where('evaluation_cycle_id', $cycle->id)
            ->where('employee_id', $employee->id)
            ->firstOrFail();

        Livewire::actingAs($admin)
            ->test(EvaluationsIndex::class)
            ->call('closeCycle', $cycle->id)
            ->assertHasNoErrors();

        $this->assertSame(EmployeeEvaluation::STATUS_ARCHIVED, $evaluation->fresh()->status);

        Livewire::actingAs($admin)
            ->test(EmployeeProfileShow::class, ['user' => $employee])
            ->set('activeTab', 'log')
            ->assertViewHas('quarterlyEvaluations', fn ($rows) => $rows->contains('id', $evaluation->id))
            ->assertSee('الربع 3 / 2026', false)
            ->assertSee('مؤرشف', false);
    }
}
