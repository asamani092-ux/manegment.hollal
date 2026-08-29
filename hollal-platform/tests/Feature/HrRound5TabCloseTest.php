<?php

namespace Tests\Feature;

use App\Livewire\Users\EmployeeProfileShow;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * HR Round 5 batch A — profile tab consolidation redirects + visible tabs.
 */
class HrRound5TabCloseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_legacy_tabs_redirect_to_consolidated_keys(): void
    {
        $viewer = User::factory()->create(['must_change_password' => false]);
        $viewer->givePermissionTo(['hr.employees.view', 'hr.salaries.view']);
        $target = User::factory()->create(['must_change_password' => false]);

        Livewire::actingAs($viewer)
            ->test(EmployeeProfileShow::class, ['user' => $target])
            ->call('setTab', 'contracts')
            ->assertSet('activeTab', 'contracts_documents')
            ->call('setTab', 'documents')
            ->assertSet('activeTab', 'contracts_documents')
            ->call('setTab', 'salary')
            ->assertSet('activeTab', 'job')
            ->call('setTab', 'evaluations')
            ->assertSet('activeTab', 'log');
    }

    public function test_query_tab_redirects_on_mount(): void
    {
        $viewer = User::factory()->create(['must_change_password' => false]);
        $viewer->givePermissionTo('hr.employees.view');
        $target = User::factory()->create(['must_change_password' => false]);

        $this->actingAs($viewer)
            ->get(route('users.profile', $target).'?tab=documents')
            ->assertOk();

        Livewire::actingAs($viewer)
            ->withQueryParams(['tab' => 'documents'])
            ->test(EmployeeProfileShow::class, ['user' => $target])
            ->assertSet('activeTab', 'contracts_documents');

        Livewire::actingAs($viewer)
            ->withQueryParams(['tab' => 'evaluations'])
            ->test(EmployeeProfileShow::class, ['user' => $target])
            ->assertSet('activeTab', 'log');

        Livewire::actingAs($viewer)
            ->withQueryParams(['tab' => 'salary'])
            ->test(EmployeeProfileShow::class, ['user' => $target])
            ->assertSet('activeTab', 'job');
    }

    public function test_tab_bar_shows_consolidated_labels_only(): void
    {
        $viewer = User::factory()->create(['must_change_password' => false]);
        $viewer->givePermissionTo(['hr.employees.view', 'hr.salaries.view']);
        $target = User::factory()->create(['must_change_password' => false]);

        Livewire::actingAs($viewer)
            ->test(EmployeeProfileShow::class, ['user' => $target])
            ->assertSee('البيانات', false)
            ->assertSee('الوظيفة', false)
            ->assertSee('العقود والمستندات', false)
            ->assertSee('المهام', false)
            ->assertSee('الإجازات', false)
            ->assertSee('السجل', false)
            ->assertDontSeeHtml("setTab('contracts')")
            ->assertDontSeeHtml("setTab('documents')")
            ->assertDontSeeHtml("setTab('salary')")
            ->assertDontSeeHtml("setTab('evaluations')");
    }

    public function test_salary_section_hidden_without_permission_inside_job_tab(): void
    {
        $viewer = User::factory()->create(['must_change_password' => false]);
        $viewer->givePermissionTo('hr.employees.view');
        $target = User::factory()->create(['must_change_password' => false]);

        Livewire::actingAs($viewer)
            ->test(EmployeeProfileShow::class, ['user' => $target])
            ->call('setTab', 'salary')
            ->assertSet('activeTab', 'job')
            ->assertDontSee('بيانات الراتب', false)
            ->assertDontSee('مكوّنات الراتب', false);

        $this->assertDatabaseCount('profile_access_logs', 0);
    }

    public function test_salary_legacy_tab_still_logs_when_permitted(): void
    {
        $viewer = User::factory()->create(['must_change_password' => false]);
        $viewer->givePermissionTo(['hr.employees.view', 'hr.salaries.view']);
        $target = User::factory()->create(['must_change_password' => false]);

        Livewire::actingAs($viewer)
            ->test(EmployeeProfileShow::class, ['user' => $target])
            ->call('setTab', 'salary')
            ->assertSet('activeTab', 'job')
            ->assertSee('بيانات الراتب', false);

        $this->assertDatabaseHas('profile_access_logs', [
            'user_id' => $viewer->id,
            'target_user_id' => $target->id,
            'tab_accessed' => 'salary',
        ]);
    }
}
