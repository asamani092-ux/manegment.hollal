<?php

namespace Tests\Feature;

use App\Livewire\Documents\DocumentsIndex;
use App\Livewire\Expenses\ExpensesIndex;
use App\Livewire\Meetings\MeetingsIndex;
use App\Livewire\Payroll\PayrollIndex;
use App\Livewire\Tasks\TasksIndex;
use App\Models\Document;
use App\Models\ExpenseRequest;
use App\Models\Meeting;
use App\Models\Payroll;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * IDOR audit — Employee role attempting cross-user resource access.
 */
class SecurityIdorAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $employee;

    protected User $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        $this->employee = User::factory()->create(['phone' => '0501000001', 'must_change_password' => false]);
        $this->employee->assignRole('Employee');

        $this->other = User::factory()->create(['phone' => '0501000002', 'must_change_password' => false]);
    }

    public function test_employee_cannot_edit_others_task(): void
    {
        $task = Task::factory()->create([
            'assigned_by' => $this->other->id,
            'assigned_to' => $this->other->id,
        ]);

        Livewire::actingAs($this->employee)
            ->test(TasksIndex::class)
            ->call('openTaskEdit', $task->id)
            ->assertForbidden();
    }

    public function test_employee_cannot_edit_others_expense(): void
    {
        $expense = ExpenseRequest::factory()->create([
            'requester_id' => $this->other->id,
            'status' => 'draft',
        ]);

        Livewire::actingAs($this->employee)
            ->test(ExpensesIndex::class)
            ->call('openExpenseEdit', $expense->id)
            ->assertForbidden();
    }

    public function test_employee_cannot_approve_others_expense(): void
    {
        $expense = ExpenseRequest::factory()->create([
            'requester_id' => $this->other->id,
            'status' => 'pending',
            'current_approval_stage' => 'executive',
        ]);

        Livewire::actingAs($this->employee)
            ->test(ExpensesIndex::class)
            ->call('approveExpense', $expense->id)
            ->assertForbidden();
    }

    public function test_employee_cannot_edit_meeting_they_do_not_attend(): void
    {
        $meeting = Meeting::factory()->create([
            'chair_id' => $this->other->id,
        ]);

        Livewire::actingAs($this->employee)
            ->test(MeetingsIndex::class)
            ->call('openEdit', $meeting->id)
            ->assertForbidden();
    }

    public function test_employee_cannot_delete_others_document(): void
    {
        $document = Document::factory()->create([
            'uploader_id' => $this->other->id,
            'confidentiality' => 'managers',
        ]);

        Livewire::actingAs($this->employee)
            ->test(DocumentsIndex::class)
            ->call('delete', $document->id)
            ->assertForbidden();
    }

    public function test_employee_cannot_access_payroll_index(): void
    {
        $this->actingAs($this->employee)
            ->get(route('payroll.index'))
            ->assertForbidden();
    }

    public function test_employee_cannot_edit_payroll_record_via_livewire(): void
    {
        $this->employee->givePermissionTo('salaries.view');

        $payroll = Payroll::factory()->create();

        Livewire::actingAs($this->employee)
            ->test(PayrollIndex::class)
            ->call('openEdit', $payroll->id)
            ->assertForbidden();
    }
}
