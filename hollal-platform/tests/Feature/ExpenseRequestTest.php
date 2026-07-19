<?php

namespace Tests\Feature;

use App\Livewire\Expenses\ExpensesIndex;
use App\Models\ExpenseRequest;
use App\Models\ExpenseSetting;
use App\Models\User;
use App\Notifications\ExpenseAwaitingApproval;
use App\Notifications\ExpensePaidReady;
use App\Notifications\ExpenseRejected;
use App\Services\ExpenseApprovalService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class ExpenseRequestTest extends TestCase
{
    use RefreshDatabase;

    protected User $requester;

    protected User $executive;

    protected User $finance;

    protected User $viewer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);

        ExpenseSetting::current()->update(['chain_mode' => 'short']);

        $this->requester = User::factory()->create(['phone' => '0503333333', 'must_change_password' => false]);
        $this->requester->assignRole('Employee');

        $this->executive = User::factory()->create(['phone' => '0504444444', 'must_change_password' => false]);
        $this->executive->assignRole('Executive Manager');

        $this->finance = User::factory()->create(['phone' => '0504444445', 'must_change_password' => false]);
        $this->finance->assignRole('Finance');

        $this->viewer = User::factory()->create(['phone' => '0505555555', 'must_change_password' => false]);
        $this->viewer->givePermissionTo(['finance.expenses.view']);
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
            ->set('payment_method', 'transfer')
            ->set('priority', 'normal')
            ->set('category_id', \App\Models\ExpenseCategory::create(['name_ar' => 'عام'])->id)
            ->set('project_id', \App\Models\Project::factory()->create()->id)
            ->call('saveExpense', true)
            ->assertHasNoErrors();

        $expense = ExpenseRequest::first();

        $this->assertNotNull($expense);
        $this->assertSame('pending', $expense->status);
        $this->assertSame(ExpenseApprovalService::STAGE_EXECUTIVE, $expense->current_approval_stage);
        $this->assertSame($this->requester->id, $expense->requester_id);

        Notification::assertSentTo($this->executive, ExpenseAwaitingApproval::class);
    }

    public function test_finance_approver_completes_chain_and_notifies_requester(): void
    {
        Notification::fake();

        $expense = ExpenseRequest::factory()->pending()->create([
            'requester_id' => $this->requester->id,
            'amount' => 2000,
            'current_approval_stage' => ExpenseApprovalService::STAGE_FINANCE,
            'approval_stages' => [
                ExpenseApprovalService::STAGE_EXECUTIVE,
                ExpenseApprovalService::STAGE_FINANCE,
            ],
        ]);

        Livewire::actingAs($this->finance)
            ->test(ExpensesIndex::class)
            ->call('approveExpense', $expense->id)
            ->assertHasNoErrors();

        $expense->refresh();

        $this->assertSame('approved', $expense->status);
        $this->assertSame($this->finance->id, $expense->approver_id);
        $this->assertNotNull($expense->approved_at);
        $this->assertNotNull($expense->paid_ready_at);

        Notification::assertSentTo($this->requester, ExpensePaidReady::class);
    }

    public function test_executive_can_reject_expense_with_reason_and_notify_requester(): void
    {
        Notification::fake();

        $expense = ExpenseRequest::factory()->pending()->create([
            'requester_id' => $this->requester->id,
            'current_approval_stage' => ExpenseApprovalService::STAGE_EXECUTIVE,
            'approval_stages' => [
                ExpenseApprovalService::STAGE_EXECUTIVE,
                ExpenseApprovalService::STAGE_FINANCE,
            ],
        ]);

        Livewire::actingAs($this->executive)
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

    public function test_user_without_stage_permission_cannot_approve(): void
    {
        $expense = ExpenseRequest::factory()->pending()->create([
            'requester_id' => $this->requester->id,
            'current_approval_stage' => ExpenseApprovalService::STAGE_EXECUTIVE,
        ]);

        Livewire::actingAs($this->viewer)
            ->test(ExpensesIndex::class)
            ->call('approveExpense', $expense->id)
            ->assertForbidden();

        $this->assertSame('pending', $expense->fresh()->status);
    }

    public function test_project_actual_spend_includes_approved_and_paid_expenses(): void
    {
        $project = \App\Models\Project::factory()->create(['budget' => 10000]);

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
