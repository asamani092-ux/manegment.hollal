<?php

namespace Tests\Feature;

use App\Livewire\Departments\DepartmentsIndex;
use App\Livewire\Documents\DocumentsIndex;
use App\Livewire\Expenses\ExpensesIndex;
use App\Livewire\Meetings\MeetingMinutes;
use App\Livewire\Meetings\MeetingsIndex;
use App\Livewire\Payroll\PayrollIndex;
use App\Livewire\Settings\RolesIndex;
use App\Livewire\Users\UsersIndex;
use App\Models\Department;
use App\Models\Document;
use App\Models\ExpenseRequest;
use App\Models\Meeting;
use App\Models\Payroll;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LivewireIdorTest extends TestCase
{
    use RefreshDatabase;

    protected User $employee;

    protected User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);

        $this->employee = User::factory()->create(['phone' => '0501000001', 'must_change_password' => false]);
        $this->employee->givePermissionTo([
            'esnad.tasks.view', 'finance.expenses.create', 'meetings.view', 'hr.employees.view',
            'structure.departments.view', 'roles.view', 'hr.salaries.view', 'documents.view',
        ]);

        $this->otherUser = User::factory()->create(['phone' => '0501000002', 'must_change_password' => false]);
    }

    public function test_employee_cannot_edit_another_users_expense_draft(): void
    {
        $expense = ExpenseRequest::factory()->create([
            'requester_id' => $this->otherUser->id,
            'status' => 'draft',
        ]);

        Livewire::actingAs($this->employee)
            ->test(ExpensesIndex::class)
            ->call('openExpenseEdit', $expense->id)
            ->assertForbidden();
    }

    public function test_employee_cannot_delete_another_users_expense(): void
    {
        $expense = ExpenseRequest::factory()->create([
            'requester_id' => $this->otherUser->id,
            'status' => 'draft',
        ]);

        Livewire::actingAs($this->employee)
            ->test(ExpensesIndex::class)
            ->call('deleteExpense', $expense->id)
            ->assertForbidden();
    }

    public function test_employee_cannot_edit_meeting_they_do_not_attend(): void
    {
        $meeting = Meeting::factory()->create();

        Livewire::actingAs($this->employee)
            ->test(MeetingsIndex::class)
            ->call('openEdit', $meeting->id)
            ->assertForbidden();
    }

    public function test_employee_cannot_access_minutes_of_unrelated_meeting(): void
    {
        $meeting = Meeting::factory()->create();

        Livewire::actingAs($this->employee)
            ->test(MeetingMinutes::class, ['meeting' => $meeting])
            ->assertForbidden();
    }

    public function test_employee_cannot_update_another_user_via_users_index(): void
    {
        Livewire::actingAs($this->employee)
            ->test(UsersIndex::class)
            ->call('openEditModal', $this->otherUser->id)
            ->assertForbidden();
    }

    public function test_employee_cannot_delete_another_user(): void
    {
        Livewire::actingAs($this->employee)
            ->test(UsersIndex::class)
            ->call('delete', $this->otherUser->id)
            ->assertForbidden();
    }

    public function test_employee_cannot_update_department_without_permission(): void
    {
        $department = Department::create(['name' => 'قسم اختبار']);

        Livewire::actingAs($this->employee)
            ->test(DepartmentsIndex::class)
            ->call('openEdit', $department->id)
            ->assertForbidden();
    }

    public function test_employee_cannot_update_role_without_permission(): void
    {
        $role = Role::create(['name' => 'test-role', 'guard_name' => 'web']);

        Livewire::actingAs($this->employee)
            ->test(RolesIndex::class)
            ->call('openEditModal', $role->id)
            ->assertForbidden();
    }

    public function test_employee_cannot_edit_payroll_without_manage_permission(): void
    {
        $payroll = Payroll::factory()->create(['employee_id' => $this->otherUser->id]);

        Livewire::actingAs($this->employee)
            ->test(PayrollIndex::class)
            ->call('openEdit', $payroll->id)
            ->assertForbidden();
    }

    public function test_employee_cannot_delete_document_they_cannot_access(): void
    {
        $document = Document::factory()->create([
            'uploader_id' => $this->otherUser->id,
            'confidentiality' => 'managers',
        ]);

        Livewire::actingAs($this->employee)
            ->test(DocumentsIndex::class)
            ->call('delete', $document->id)
            ->assertForbidden();
    }
}
