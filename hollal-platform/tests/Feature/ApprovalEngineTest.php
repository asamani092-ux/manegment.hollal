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

    public function test_map_role_to_stage_symbol_is_gone(): void
    {
        $source = file_get_contents(app_path('Services/ApprovalChainService.php'));
        $this->assertStringNotContainsString('mapRoleToStage', $source);
    }
}
