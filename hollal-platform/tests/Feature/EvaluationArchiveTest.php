<?php

namespace Tests\Feature;

use App\Livewire\Hr\EvaluationsIndex;
use App\Livewire\Users\EmployeeProfileShow;
use App\Models\PeriodicEvaluation;
use App\Models\User;
use App\Services\EvaluationService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Evaluation archive appears on employee profile.
 * Time: O(1) | Space: O(1)
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

        $employee = User::factory()->create(['must_change_password' => false, 'is_active' => true]);

        $evaluation = app(EvaluationService::class)->create($employee, '2026-Q3', $admin);

        Livewire::actingAs($admin)
            ->test(EvaluationsIndex::class)
            ->call('archive', $evaluation->id)
            ->assertHasNoErrors();

        $this->assertSame(PeriodicEvaluation::STATUS_ARCHIVED, $evaluation->fresh()->status);

        Livewire::actingAs($admin)
            ->test(EmployeeProfileShow::class, ['user' => $employee])
            ->set('activeTab', 'evaluations')
            ->assertViewHas('archivedEvaluations', fn ($rows) => $rows->contains('id', $evaluation->id))
            ->assertSee('الأرشيف', false)
            ->assertSee('2026-Q3', false);
    }
}
