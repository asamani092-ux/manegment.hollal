<?php

namespace Tests\Feature;

use App\Models\ApprovalRule;
use App\Models\Delegation;
use App\Models\ExpenseRequest;
use App\Models\User;
use App\Services\DelegationService;
use App\Services\ExpenseApprovalService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
    }

    public function test_named_employee_without_finance_permission_can_approve_expense(): void
    {
        $requester = User::factory()->create();
        $approver = User::factory()->create();
        $approver->assignRole('Employee');

        ApprovalRule::query()->create([
            'transaction_type' => ApprovalRule::TYPE_EXPENSE,
            'metric' => 'amount',
            'min_amount' => 0,
            'max_amount' => null,
            'approval_steps' => [
                ['type' => 'user', 'user_id' => $approver->id, 'required' => true, 'mode' => 'any', 'label_ar' => 'معتمد'],
            ],
            'is_active' => true,
        ]);

        $expense = ExpenseRequest::factory()->create([
            'requester_id' => $requester->id,
            'amount' => 400,
            'status' => 'draft',
        ]);

        app(ExpenseApprovalService::class)->initializeChain($expense);
        $expense->refresh();

        $this->assertTrue(app(ExpenseApprovalService::class)->canApprove($approver, $expense));
        app(ExpenseApprovalService::class)->approve($approver, $expense);
        $this->assertSame('approved', $expense->fresh()->status);
    }

    public function test_delegate_can_act_and_record_returns_to_delegator_after_end(): void
    {
        $manager = User::factory()->create();
        $manager->givePermissionTo('finance.expenses.approve');
        $employee = User::factory()->create(['manager_id' => $manager->id]);
        $junior = User::factory()->create();
        $junior->assignRole('Employee');

        ApprovalRule::query()->create([
            'transaction_type' => ApprovalRule::TYPE_EXPENSE,
            'min_amount' => 0,
            'max_amount' => null,
            'approval_steps' => [
                ['type' => 'direct_manager', 'required' => true, 'mode' => 'any', 'label_ar' => 'المدير'],
            ],
            'is_active' => true,
        ]);

        $expense = ExpenseRequest::factory()->create([
            'requester_id' => $employee->id,
            'amount' => 200,
            'status' => 'draft',
        ]);
        app(ExpenseApprovalService::class)->initializeChain($expense);

        $delegation = Delegation::query()->create([
            'delegator_id' => $manager->id,
            'delegate_id' => $junior->id,
            'starts_on' => today()->toDateString(),
            'ends_on' => today()->addDay()->toDateString(),
            'status' => Delegation::STATUS_ACTIVE,
            'reason' => 'إنابة',
        ]);

        $this->assertTrue(app(ExpenseApprovalService::class)->canApprove($junior->fresh(), $expense->fresh()));
        $this->assertSame($manager->id, app(DelegationService::class)->behalfOf($junior, $expense->fresh()));

        app(DelegationService::class)->end($delegation);
        $this->assertFalse(app(ExpenseApprovalService::class)->canApprove($junior->fresh(), $expense->fresh()));
        $this->assertTrue(app(ExpenseApprovalService::class)->canApprove($manager->fresh(), $expense->fresh()));
    }

    public function test_unknown_role_is_not_dropped(): void
    {
        $requester = User::factory()->create();
        $partner = User::factory()->create();
        $partner->assignRole('Partnerships Manager');

        ApprovalRule::query()->create([
            'transaction_type' => ApprovalRule::TYPE_EXPENSE,
            'min_amount' => 0,
            'max_amount' => null,
            'approval_steps' => [
                ['type' => 'role', 'role' => 'Partnerships Manager', 'required' => true, 'mode' => 'any', 'label_ar' => 'شراكات'],
            ],
            'is_active' => true,
        ]);

        $expense = ExpenseRequest::factory()->create([
            'requester_id' => $requester->id,
            'amount' => 50,
            'status' => 'draft',
        ]);
        app(ExpenseApprovalService::class)->initializeChain($expense);
        $expense->refresh();

        $this->assertSame('role:Partnerships Manager', $expense->current_approval_stage);
        $this->assertTrue(app(ExpenseApprovalService::class)->canApprove($partner, $expense));
    }

    public function test_requester_cannot_approve_own_request(): void
    {
        $user = User::factory()->create();
        ApprovalRule::query()->create([
            'transaction_type' => ApprovalRule::TYPE_EXPENSE,
            'min_amount' => 0,
            'max_amount' => null,
            'approval_steps' => [
                ['type' => 'user', 'user_id' => $user->id, 'required' => true, 'mode' => 'any', 'label_ar' => 'نفسه'],
            ],
            'is_active' => true,
        ]);
        $expense = ExpenseRequest::factory()->create([
            'requester_id' => $user->id,
            'amount' => 10,
            'status' => 'draft',
        ]);
        app(ExpenseApprovalService::class)->initializeChain($expense);

        $this->assertFalse(app(ExpenseApprovalService::class)->canApprove($user, $expense->fresh()));
    }

    public function test_extend_and_cut_delegation_dates(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        $row = Delegation::query()->create([
            'delegator_id' => $a->id,
            'delegate_id' => $b->id,
            'starts_on' => today()->toDateString(),
            'ends_on' => today()->addDays(3)->toDateString(),
            'status' => Delegation::STATUS_ACTIVE,
        ]);
        $service = app(DelegationService::class);
        $this->assertSame(today()->addDays(6)->toDateString(), $service->extend($row, today()->addDays(6)->toDateString())->ends_on->toDateString());
        $this->assertSame(Delegation::STATUS_ENDED, $service->cut($row, today()->toDateString())->status);
    }

    public function test_any_of_users_step_and_behalf_is_stored(): void
    {
        $requester = User::factory()->create();
        $manager = User::factory()->create();
        $a = User::factory()->create();
        $b = User::factory()->create();
        $a->assignRole('Employee');
        $b->assignRole('Employee');

        ApprovalRule::query()->create([
            'transaction_type' => ApprovalRule::TYPE_EXPENSE,
            'min_amount' => 0,
            'max_amount' => null,
            'approval_steps' => [[
                'type' => 'any_of_users',
                'user_ids' => [$a->id, $b->id],
                'required' => true,
                'mode' => 'any',
                'label_ar' => 'أحد المعتمدين',
            ]],
            'is_active' => true,
        ]);

        $expense = ExpenseRequest::factory()->create([
            'requester_id' => $requester->id,
            'amount' => 80,
            'status' => 'draft',
        ]);
        app(ExpenseApprovalService::class)->initializeChain($expense);
        $this->assertTrue(app(ExpenseApprovalService::class)->canApprove($b, $expense->fresh()));

        $managerExpense = ExpenseRequest::factory()->create([
            'requester_id' => User::factory()->create(['manager_id' => $manager->id])->id,
            'amount' => 90,
            'status' => 'draft',
        ]);
        ApprovalRule::query()->where('transaction_type', ApprovalRule::TYPE_EXPENSE)->delete();
        ApprovalRule::query()->create([
            'transaction_type' => ApprovalRule::TYPE_EXPENSE,
            'min_amount' => 0,
            'max_amount' => null,
            'approval_steps' => [['type' => 'direct_manager', 'required' => true, 'mode' => 'any', 'label_ar' => 'مدير']],
            'is_active' => true,
        ]);
        app(ExpenseApprovalService::class)->initializeChain($managerExpense);
        $junior = User::factory()->create();
        Delegation::query()->create([
            'delegator_id' => $manager->id,
            'delegate_id' => $junior->id,
            'starts_on' => today()->toDateString(),
            'ends_on' => today()->toDateString(),
            'status' => Delegation::STATUS_ACTIVE,
        ]);
        app(ExpenseApprovalService::class)->approve($junior, $managerExpense->fresh());
        $step = \App\Models\ApprovalRequestStep::query()->where('acted_by', $junior->id)->first();
        $this->assertNotNull($step);
        $this->assertSame($manager->id, (int) $step->acted_on_behalf_of);
    }

    public function test_delegation_stays_unscheduled_until_hr_and_notifies(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $manager = User::factory()->create();
        $employee = User::factory()->create(['manager_id' => $manager->id]);
        $delegate = User::factory()->create();
        $hr = User::factory()->create();
        $hr->givePermissionTo('hr.delegations.manage');

        $service = app(DelegationService::class);
        $row = $service->propose($employee, $delegate, today()->toDateString(), today()->addDay()->toDateString(), 'سفر');
        $this->assertSame('pending_delegate', $row->status);
        $row = $service->acceptByDelegate($row, $delegate);
        $row = $service->approveByManager($row, $manager);
        $this->assertSame('pending_hr', $row->status);
        $row = $service->approveByHr($row, $hr);
        $this->assertSame(Delegation::STATUS_SCHEDULED, $row->status);
        \Illuminate\Support\Facades\Notification::assertSentTo($employee, \App\Notifications\DelegationApprovedSummary::class);
        $preview = $service->preview($hr);
        $this->assertArrayHasKey('sensitive', $preview);
    }

    public function test_delegate_inherits_permission_only_while_active(): void
    {
        $this->seed(\Database\Seeders\ReferenceListsSeeder::class);
        $delegator = User::factory()->create(['must_change_password' => false]);
        $delegator->givePermissionTo('settings.lists.view');
        $delegate = User::factory()->create(['must_change_password' => false]);
        $delegate->assignRole('Employee');

        $row = Delegation::query()->create([
            'delegator_id' => $delegator->id,
            'delegate_id' => $delegate->id,
            'starts_on' => today()->toDateString(),
            'ends_on' => today()->toDateString(),
            'status' => Delegation::STATUS_ACTIVE,
        ]);

        $this->actingAs($delegate)->get(route('settings.lists'))->assertOk();
        app(DelegationService::class)->end($row);
        $this->actingAs($delegate)->get(route('settings.lists'))->assertForbidden();
    }

    public function test_inflight_expense_stage_remains_approvable(): void
    {
        $manager = User::factory()->create();
        $manager->givePermissionTo('finance.expenses.approve');
        $requester = User::factory()->create(['manager_id' => $manager->id]);
        $expense = ExpenseRequest::factory()->create([
            'requester_id' => $requester->id,
            'amount' => 100,
            'status' => 'pending',
            'approval_stages' => [ExpenseApprovalService::STAGE_DEPARTMENT_MANAGER],
            'current_approval_stage' => ExpenseApprovalService::STAGE_DEPARTMENT_MANAGER,
        ]);

        $this->assertTrue(app(ExpenseApprovalService::class)->canApprove($manager, $expense));
    }

    public function test_map_role_to_stage_symbol_is_gone(): void
    {
        $source = file_get_contents(app_path('Services/ApprovalChainService.php'));
        $this->assertStringNotContainsString('mapRoleToStage', $source);
    }
}
