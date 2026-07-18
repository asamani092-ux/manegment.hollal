<?php

namespace Tests\Feature;

use App\Livewire\DashboardIndex;
use App\Models\ExpenseRequest;
use App\Models\Meeting;
use App\Models\MeetingItem;
use App\Models\Partnership;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
    }

    public function test_employee_sees_my_workspace_without_finance_metric(): void
    {
        $employee = User::factory()->create(['must_change_password' => false]);
        $employee->givePermissionTo(['dashboard.view', 'esnad.tasks.view']);

        Task::factory()->create([
            'title' => 'مهمة اليوم',
            'assigned_to' => $employee->id,
            'due_date' => now(),
            'status' => 'new',
        ]);

        Task::factory()->create([
            'title' => 'مهمة مفتوحة',
            'assigned_to' => $employee->id,
            'due_date' => now()->addDay(),
            'status' => 'in_progress',
        ]);

        ExpenseRequest::factory()->approved()->create([
            'amount' => 9999,
            'approved_at' => now(),
        ]);

        Livewire::actingAs($employee)
            ->test(DashboardIndex::class)
            ->assertSee('مساحة عملي')
            ->assertSee('مهمة اليوم')
            ->assertSee('مهمة مفتوحة')
            ->assertDontSee('مصروفات الشهر');
    }

    public function test_finance_user_sees_month_spend_metric(): void
    {
        $financeUser = User::factory()->create(['must_change_password' => false]);
        $financeUser->givePermissionTo(['dashboard.view', 'finance.expenses.view']);

        ExpenseRequest::factory()->approved()->create([
            'amount' => 1500,
            'approved_at' => now(),
        ]);

        Livewire::actingAs($financeUser)
            ->test(DashboardIndex::class)
            ->assertSee('مصروفات الشهر')
            ->assertSee('1,500.00');
    }

    public function test_manager_sees_subordinate_overdue_task_in_action_section(): void
    {
        $manager = User::factory()->create(['must_change_password' => false]);
        $manager->givePermissionTo(['dashboard.view', 'esnad.tasks.view']);

        $report = User::factory()->create([
            'manager_id' => $manager->id,
            'must_change_password' => false,
        ]);

        Task::factory()->create([
            'title' => 'مهمة متأخرة للفريق',
            'assigned_to' => $report->id,
            'assigned_by' => $manager->id,
            'due_date' => now()->subDay(),
            'status' => 'overdue',
        ]);

        Livewire::actingAs($manager)
            ->test(DashboardIndex::class)
            ->assertSee('يحتاج إجراءك')
            ->assertSee('مهمة متأخرة: مهمة متأخرة للفريق');
    }

    public function test_employee_does_not_see_subordinate_overdue_tasks(): void
    {
        $manager = User::factory()->create(['must_change_password' => false]);
        $employee = User::factory()->create([
            'manager_id' => $manager->id,
            'must_change_password' => false,
        ]);
        $employee->givePermissionTo(['dashboard.view', 'esnad.tasks.view']);

        $peer = User::factory()->create(['manager_id' => $manager->id]);

        Task::factory()->create([
            'title' => 'مهمة زميل متأخرة',
            'assigned_to' => $peer->id,
            'due_date' => now()->subDays(2),
            'status' => 'overdue',
        ]);

        Livewire::actingAs($employee)
            ->test(DashboardIndex::class)
            ->assertDontSee('يحتاج إجراءك')
            ->assertDontSee('مهمة زميل متأخرة');
    }

    public function test_approver_sees_pending_expense_in_action_section(): void
    {
        $approver = User::factory()->create(['must_change_password' => false]);
        $approver->givePermissionTo(['dashboard.view', 'finance.expenses.approve']);

        ExpenseRequest::factory()->pending()->create([
            'type' => 'تشغيلي',
            'amount' => 800,
        ]);

        Livewire::actingAs($approver)
            ->test(DashboardIndex::class)
            ->assertSee('يحتاج إجراءك')
            ->assertSee('مصروف بانتظار الموافقة: تشغيلي');
    }

    public function test_dashboard_route_renders_livewire_component(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo('dashboard.view');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSeeLivewire(DashboardIndex::class);
    }

    public function test_active_projects_metric_uses_task_completion(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo(['dashboard.view', 'projects.view']);

        $project = Project::factory()->create(['status' => 'active']);

        Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'completed',
        ]);
        Task::factory()->create([
            'project_id' => $project->id,
            'status' => 'new',
        ]);

        Livewire::actingAs($user)
            ->test(DashboardIndex::class)
            ->assertSee('المشاريع النشطة')
            ->assertSee('1')
            ->assertSee('نسبة الإنجاز 50%');
    }

    public function test_user_sees_past_due_meeting_decision_when_involved(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo(['dashboard.view', 'meetings.view']);

        $meeting = Meeting::factory()->create([
            'chair_id' => $user->id,
            'secretary_id' => $user->id,
        ]);

        MeetingItem::factory()->create([
            'meeting_id' => $meeting->id,
            'topic' => 'متابعة العقد',
            'decision' => 'إعداد التقرير',
            'responsible_id' => $user->id,
            'due_date' => now()->subDay(),
            'status' => 'open',
        ]);

        Livewire::actingAs($user)
            ->test(DashboardIndex::class)
            ->assertSee('قرار متأخر: متابعة العقد');
    }

    public function test_partnerships_viewer_sees_expiring_partnership(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo(['dashboard.view', 'partnerships.view']);

        Partnership::query()->create([
            'entity_name' => 'شريك تجريبي',
            'status' => 'active',
            'token_expires_at' => now()->addDays(10),
        ]);

        Livewire::actingAs($user)
            ->test(DashboardIndex::class)
            ->assertSee('شراكة تنتهي قريباً: شريك تجريبي');
    }
}
