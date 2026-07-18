<?php

namespace Tests\Feature;

use App\Livewire\Expenses\ExpensesIndex;
use App\Livewire\Settings\RolesIndex;
use App\Models\AuditLog;
use App\Models\ExpenseRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\ExpenseApprovalService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected User $finance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->finance = User::factory()->create(['must_change_password' => false]);
        $this->finance->assignRole('Finance');
    }

    public function test_expense_approve_writes_audit_log(): void
    {
        $requester = User::factory()->create();
        $expense = ExpenseRequest::factory()->create([
            'requester_id' => $requester->id,
            'status' => 'pending',
            'current_approval_stage' => ExpenseApprovalService::STAGE_FINANCE,
            'approval_stages' => [ExpenseApprovalService::STAGE_FINANCE],
            'paid_ready_at' => null,
        ]);

        app(ExpenseApprovalService::class)->approve($this->finance, $expense);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $this->finance->id,
            'action' => 'expense.approved',
            'target_type' => $expense->getMorphClass(),
            'target_id' => $expense->id,
        ]);
    }

    public function test_role_permission_change_writes_audit_log(): void
    {
        $admin = User::factory()->create(['must_change_password' => false]);
        $admin->assignRole('Super Admin');

        $role = Role::where('name', 'Employee')->first();

        Livewire::actingAs($admin)
            ->test(RolesIndex::class)
            ->call('openEditModal', $role->id)
            ->set('selectedPermissions', ['dashboard.view', 'esnad.tasks.view'])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'role.updated',
            'target_type' => $role->getMorphClass(),
            'target_id' => $role->id,
        ]);
    }

    public function test_file_download_writes_audit_log(): void
    {
        Storage::fake('local');

        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo(['esnad.tasks.view', 'esnad.tasks.create']);

        $task = \App\Models\Task::factory()->create([
            'assigned_by' => $user->id,
            'assigned_to' => $user->id,
            'attachment_path' => 'tasks/test.pdf',
        ]);

        Storage::disk('local')->put('tasks/test.pdf', 'content');

        $this->actingAs($user)
            ->get(route('tasks.files.download', ['task' => $task, 'type' => 'attachment']))
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'action' => 'file.download',
            'target_type' => $task->getMorphClass(),
            'target_id' => $task->id,
        ]);
    }

    public function test_login_failure_writes_audit_log(): void
    {
        $this->post(route('login'), [
            'phone' => '0509999999',
            'password' => 'wrong-password',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'auth.login_failure',
            'actor_id' => null,
        ]);
    }

    public function test_audit_log_has_no_updated_at(): void
    {
        $this->assertFalse((new AuditLog)->usesTimestamps());
    }
}
