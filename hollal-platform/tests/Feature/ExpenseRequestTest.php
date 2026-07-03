<?php

namespace Tests\Feature;

use App\Livewire\Expenses\ExpensesIndex;
use App\Models\ExpenseRequest;
use App\Models\Project;
use App\Models\User;
use App\Notifications\ExpenseApproved;
use App\Notifications\ExpenseAwaitingApproval;
use App\Notifications\ExpenseRejected;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class ExpenseRequestTest extends TestCase
{
    use RefreshDatabase;

    protected User $requester;

    protected User $approver;

    protected User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);

        $this->requester = User::factory()->create(['phone' => '0503333333']);
        $this->requester->givePermissionTo(['expenses.create']);

        $this->approver = User::factory()->create(['phone' => '0504444444']);
        $this->approver->givePermissionTo(['expenses.view', 'expenses.approve']);

        $this->viewer = User::factory()->create(['phone' => '0505555555']);
        $this->viewer->givePermissionTo(['expenses.view']);
    }

    public function test_user_can_create_and_submit_expense_to_pending(): void
    {
        Notification::fake();

        Livewire::actingAs($this->requester)
            ->test(ExpensesIndex::class)
            ->call('openExpenseCreate')
            ->set('type', 'operational')
            ->set('amount', '1500.50')
            ->set('reason', 'شراء مستلزمات')
            ->set('payment_method', 'bank_transfer')
            ->call('saveExpense', true)
            ->assertHasNoErrors();

        $expense = ExpenseRequest::first();

        $this->assertNotNull($expense);
        $this->assertSame('pending', $expense->status);
        $this->assertSame($this->requester->id, $expense->requester_id);

        Notification::assertSentTo($this->approver, ExpenseAwaitingApproval::class);
    }

    public function test_approver_can_approve_expense_and_notify_requester(): void
    {
        Notification::fake();

        $expense = ExpenseRequest::factory()->pending()->create([
            'requester_id' => $this->requester->id,
            'amount' => 2000,
        ]);

        Livewire::actingAs($this->approver)
            ->test(ExpensesIndex::class)
            ->call('approveExpense', $expense->id)
            ->assertHasNoErrors();

        $expense->refresh();

        $this->assertSame('approved', $expense->status);
        $this->assertSame($this->approver->id, $expense->approver_id);
        $this->assertNotNull($expense->approved_at);

        Notification::assertSentTo($this->requester, ExpenseApproved::class);
    }

    public function test_approver_can_reject_expense_with_reason_and_notify_requester(): void
    {
        Notification::fake();

        $expense = ExpenseRequest::factory()->pending()->create([
            'requester_id' => $this->requester->id,
        ]);

        Livewire::actingAs($this->approver)
            ->test(ExpensesIndex::class)
            ->call('openRejectModal', $expense->id)
            ->set('rejectionReason', 'المبلغ يتجاوز الميزانية')
            ->call('confirmRejectExpense')
            ->assertHasNoErrors();

        $expense->refresh();

        $this->assertSame('rejected', $expense->status);
        $this->assertSame('المبلغ يتجاوز الميزانية', $expense->rejection_reason);

        Notification::assertSentTo($this->requester, ExpenseRejected::class);
    }

    public function test_user_without_approve_permission_cannot_approve(): void
    {
        $expense = ExpenseRequest::factory()->pending()->create([
            'requester_id' => $this->requester->id,
        ]);

        Livewire::actingAs($this->viewer)
            ->test(ExpensesIndex::class)
            ->call('approveExpense', $expense->id)
            ->assertForbidden();

        $this->assertSame('pending', $expense->fresh()->status);
    }

    public function test_project_actual_spend_includes_approved_and_paid_expenses(): void
    {
        $project = Project::factory()->create(['budget' => 10000]);

        ExpenseRequest::factory()->create([
            'project_id' => $project->id,
            'requester_id' => $this->requester->id,
            'amount' => 1500,
            'status' => 'approved',
        ]);

        ExpenseRequest::factory()->create([
            'project_id' => $project->id,
            'requester_id' => $this->requester->id,
            'amount' => 500,
            'status' => 'paid',
        ]);

        ExpenseRequest::factory()->create([
            'project_id' => $project->id,
            'requester_id' => $this->requester->id,
            'amount' => 300,
            'status' => 'pending',
        ]);

        $this->assertSame(2000.0, $project->fresh()->actualSpend());
        $this->assertSame(8000.0, $project->fresh()->remainingBudget());
    }
}
