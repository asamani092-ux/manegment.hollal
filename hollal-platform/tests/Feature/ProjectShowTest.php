<?php

namespace Tests\Feature;

use App\Livewire\Projects\ProjectShow;
use App\Models\Document;
use App\Models\ExpenseRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectShowTest extends TestCase
{
    use RefreshDatabase;

    protected User $manager;

    protected User $teamMember;

    protected User $outsider;

    protected Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);

        $this->manager = User::factory()->create([
            'phone' => '0504444444',
            'must_change_password' => false,
        ]);
        $this->manager->givePermissionTo('projects.view');

        $this->teamMember = User::factory()->create([
            'phone' => '0505555555',
            'must_change_password' => false,
        ]);
        $this->teamMember->givePermissionTo('projects.view');

        $this->outsider = User::factory()->create([
            'phone' => '0503333333',
            'must_change_password' => false,
        ]);
        $this->outsider->givePermissionTo('projects.view');

        $this->project = Project::factory()->create([
            'manager_id' => $this->manager->id,
            'name' => 'مشروع تجريبي',
            'budget' => 10000,
        ]);

        $this->project->team()->attach($this->teamMember->id);
    }

    public function test_authorized_user_can_view_project_show(): void
    {
        $this->actingAs($this->manager)
            ->get(route('projects.show', $this->project))
            ->assertOk()
            ->assertSee('مشروع تجريبي');
    }

    public function test_unauthorized_user_gets_403(): void
    {
        Livewire::actingAs($this->outsider)
            ->test(ProjectShow::class, ['project' => $this->project])
            ->assertForbidden();
    }

    public function test_project_manager_can_submit_weekly_update(): void
    {
        Livewire::actingAs($this->manager)
            ->test(ProjectShow::class, ['project' => $this->project])
            ->set('activeTab', 'updates')
            ->set('done', 'أنجزنا المرحلة الأولى')
            ->set('next', 'نبدأ المرحلة الثانية')
            ->set('blockers', 'لا يوجد')
            ->set('decision_needed', 'موافقة على الميزانية')
            ->set('update_date', '2026-07-01')
            ->call('submitWeeklyUpdate')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('project_updates', [
            'project_id' => $this->project->id,
            'author_id' => $this->manager->id,
            'done' => 'أنجزنا المرحلة الأولى',
            'next' => 'نبدأ المرحلة الثانية',
        ]);
    }

    public function test_non_manager_cannot_submit_update(): void
    {
        Livewire::actingAs($this->teamMember)
            ->test(ProjectShow::class, ['project' => $this->project])
            ->set('activeTab', 'updates')
            ->set('done', 'محاولة تحديث')
            ->set('next', 'خطوة تالية')
            ->call('submitWeeklyUpdate')
            ->assertForbidden();

        $this->assertDatabaseCount('project_updates', 0);
    }

    public function test_tabs_load_project_tasks_documents_expenses_scoped_to_project(): void
    {
        $otherProject = Project::factory()->create(['manager_id' => $this->manager->id]);

        $projectTask = Task::factory()->create([
            'project_id' => $this->project->id,
            'title' => 'مهمة المشروع الحالي',
            'assigned_by' => $this->manager->id,
            'assigned_to' => $this->teamMember->id,
        ]);

        Task::factory()->create([
            'project_id' => $otherProject->id,
            'title' => 'مهمة مشروع آخر',
            'assigned_by' => $this->manager->id,
            'assigned_to' => $this->teamMember->id,
        ]);

        Document::factory()->forProject($this->project)->create([
            'title' => 'مستند المشروع',
            'uploader_id' => $this->manager->id,
        ]);

        Document::factory()->forProject($otherProject)->create([
            'title' => 'مستند مشروع آخر',
            'uploader_id' => $this->manager->id,
        ]);

        ExpenseRequest::factory()->approved()->create([
            'project_id' => $this->project->id,
            'requester_id' => $this->manager->id,
            'reason' => 'صرف المشروع الحالي',
            'amount' => 1500,
        ]);

        ExpenseRequest::factory()->approved()->create([
            'project_id' => $otherProject->id,
            'requester_id' => $this->manager->id,
            'reason' => 'صرف مشروع آخر',
            'amount' => 2500,
        ]);

        Livewire::actingAs($this->manager)
            ->test(ProjectShow::class, ['project' => $this->project])
            ->set('activeTab', 'tasks')
            ->assertSee('مهمة المشروع الحالي')
            ->assertDontSee('مهمة مشروع آخر')
            ->set('activeTab', 'files')
            ->assertSee('مستند المشروع')
            ->assertDontSee('مستند مشروع آخر')
            ->set('activeTab', 'finance')
            ->assertSee('صرف المشروع الحالي')
            ->assertDontSee('صرف مشروع آخر')
            ->assertSee('1,500.00');
    }
}
