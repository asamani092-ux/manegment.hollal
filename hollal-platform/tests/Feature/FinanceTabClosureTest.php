<?php

namespace Tests\Feature;

use App\Models\ApprovalRule;
use App\Models\Asset;
use App\Models\ChartOfAccount;
use App\Models\Custody;
use App\Models\ExpenseCategory;
use App\Models\ExpenseRequest;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\User;
use App\Services\ApprovalChainService;
use App\Services\AccountingReportService;
use App\Services\CustodyService;
use App\Services\DepreciationService;
use App\Services\ExpenseApprovalService;
use App\Services\JournalService;
use App\Support\CoaCodes;
use Database\Seeders\ApprovalRulesSeeder;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * إغلاق التبويب المالي — 7 اختبارات رئيسية.
 */
class FinanceTabClosureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(ApprovalRulesSeeder::class);
    }

    public function test_chart_is_three_digit_tree(): void
    {
        $this->assertGreaterThan(10, ChartOfAccount::count());
        $this->assertSame(5, ChartOfAccount::whereRaw('LENGTH(code) = 1')->count());
        $this->assertGreaterThan(5, ChartOfAccount::whereRaw('LENGTH(code) = 2')->count());
        $this->assertGreaterThan(15, ChartOfAccount::whereRaw('LENGTH(code) = 3')->count());

        $cash = ChartOfAccount::where('code', CoaCodes::CASH)->firstOrFail();
        $this->assertSame('11', $cash->parent()->value('code'));
        $this->assertTrue((bool) $cash->is_postable);
        $this->assertFalse((bool) ChartOfAccount::where('code', '11')->value('is_postable'));
        $this->assertSame(2, ChartOfAccount::whereIn('code', [CoaCodes::BANK_RAJHI, CoaCodes::BANK_INMA])->count());
    }

    public function test_custody_multi_invoice_vat_posts_compound_journal(): void
    {
        $employee = User::factory()->create();
        $catBags = ExpenseCategory::create([
            'name_ar' => 'حقائب',
            'is_active' => true,
            'account_id' => ChartOfAccount::where('code', CoaCodes::EXP_BAGS)->value('id'),
        ]);
        $catTrain = ExpenseCategory::create([
            'name_ar' => 'تدريب',
            'is_active' => true,
            'account_id' => ChartOfAccount::where('code', CoaCodes::EXP_TRAINING)->value('id'),
        ]);
        $catMisc = ExpenseCategory::create([
            'name_ar' => 'متنوع',
            'is_active' => true,
            'account_id' => ChartOfAccount::where('code', CoaCodes::EXP_MISC)->value('id'),
        ]);

        $service = app(CustodyService::class);
        $custody = $service->request($employee, 3000, 'عهدة اختبار', null, null, null, $employee);
        $service->approve($custody, User::factory()->create());
        $service->disburse($custody, 'custodies/disbursements/proof.pdf');

        $service->addSettlementItem($custody->fresh(), [
            'description' => 'فندق',
            'amount' => 1500,
            'vat_rate' => 0.15,
            'category_id' => $catBags->id,
            'vendor_name' => 'فندق',
            'invoice_number' => 'F-001',
        ]);
        $service->addSettlementItem($custody->fresh(), [
            'description' => 'نقل',
            'amount' => 800,
            'vat_rate' => 0.15,
            'category_id' => $catTrain->id,
            'vendor_name' => 'نقل',
            'invoice_number' => 'F-002',
        ]);
        $service->addSettlementItem($custody->fresh(), [
            'description' => 'طعام',
            'amount' => 400,
            'vat_rate' => 0.15,
            'category_id' => $catMisc->id,
            'vendor_name' => 'طعام',
            'invoice_number' => 'F-003',
        ]);

        $service->close($custody->fresh());
        $this->assertSame(Custody::STATUS_CLOSED, $custody->fresh()->status);

        $entry = JournalEntry::query()
            ->where('source_type', Custody::class)
            ->where('source_id', $custody->id)
            ->where('number', 'like', '%S')
            ->first();
        $this->assertNotNull($entry);

        $lines = JournalLine::where('journal_entry_id', $entry->id)->get();
        $debit = round((float) $lines->sum('debit'), 2);
        $credit = round((float) $lines->sum('credit'), 2);
        $this->assertEqualsWithDelta($debit, $credit, 0.005);

        $vatId = ChartOfAccount::where('code', CoaCodes::VAT_PAYABLE)->value('id');
        $this->assertEqualsWithDelta(405.0, (float) $lines->where('account_id', $vatId)->sum('debit'), 0.01);

        $advId = ChartOfAccount::where('code', CoaCodes::EMPLOYEE_ADVANCES)->value('id');
        $this->assertEqualsWithDelta(3000.0, (float) $lines->where('account_id', $advId)->sum('credit'), 0.01);
    }

    public function test_approval_chain_steps_by_amount(): void
    {
        $chain = app(ApprovalChainService::class);
        $this->assertCount(1, $chain->stepsFor('expense', 500));
        $this->assertCount(2, $chain->stepsFor('expense', 5000));
        $this->assertCount(3, $chain->stepsFor('expense', 15000));

        $this->assertGreaterThanOrEqual(3, ApprovalRule::where('transaction_type', 'expense')->count());

        $manager = User::factory()->create();
        $requester = User::factory()->create(['manager_id' => $manager->id]);
        $expense = ExpenseRequest::factory()->create([
            'requester_id' => $requester->id,
            'amount' => 500,
            'status' => 'draft',
        ]);
        app(ExpenseApprovalService::class)->initializeChain($expense);
        $stages = $expense->fresh()->approval_stages ?? [];
        $this->assertSame([ExpenseApprovalService::STAGE_DEPARTMENT_MANAGER], $stages);
    }

    public function test_income_statement_splits_restricted(): void
    {
        $user = User::factory()->create();
        $cash = ChartOfAccount::where('code', CoaCodes::CASH)->firstOrFail();
        $unrest = ChartOfAccount::where('code', CoaCodes::UNRESTRICTED_PARTNERSHIP_REVENUE)->firstOrFail();
        $rest = ChartOfAccount::where('code', CoaCodes::RESTRICTED_GRANT_REVENUE)->firstOrFail();
        $exp = ChartOfAccount::where('code', CoaCodes::EXP_MISC)->firstOrFail();

        $js = app(JournalService::class);
        $js->postManual('إيراد حر', now()->toDateString(), [
            ['account_id' => $cash->id, 'debit' => 700, 'credit' => 0],
            ['account_id' => $unrest->id, 'debit' => 0, 'credit' => 700],
        ], $user);
        $js->postManual('إيراد مقيد', now()->toDateString(), [
            ['account_id' => $cash->id, 'debit' => 300, 'credit' => 0],
            ['account_id' => $rest->id, 'debit' => 0, 'credit' => 300],
        ], $user);
        $js->postManual('مصروف', now()->toDateString(), [
            ['account_id' => $exp->id, 'debit' => 200, 'credit' => 0],
            ['account_id' => $cash->id, 'debit' => 0, 'credit' => 200],
        ], $user);

        $income = app(AccountingReportService::class)->incomeStatement();
        $this->assertEqualsWithDelta(700.0, $income['unrestricted_revenue'], 0.01);
        $this->assertEqualsWithDelta(300.0, $income['restricted_revenue'], 0.01);
        $this->assertEqualsWithDelta(1000.0, $income['total_revenue'], 0.01);
        $this->assertEqualsWithDelta(200.0, $income['expenses'], 0.01);
        $this->assertEqualsWithDelta(800.0, $income['change_in_net_assets'], 0.01);
    }

    public function test_balance_sheet_is_balanced(): void
    {
        $user = User::factory()->create();
        app(\App\Services\AccountingCloseService::class)->postOpeningBalance(1000, $user);

        $balance = app(AccountingReportService::class)->balanceSheet();
        $this->assertTrue($balance['balanced']);
        $this->assertArrayHasKey('unrestricted_net_assets', $balance);
        $this->assertArrayHasKey('restricted_net_assets', $balance);
        $this->assertArrayHasKey('total_net_assets', $balance);
    }

    public function test_cash_flow_opening_plus_net_equals_closing(): void
    {
        $user = User::factory()->create();
        $cash = ChartOfAccount::where('code', CoaCodes::CASH)->firstOrFail();
        $rev = ChartOfAccount::where('code', CoaCodes::UNRESTRICTED_PARTNERSHIP_REVENUE)->firstOrFail();

        app(JournalService::class)->postManual('إيراد', now()->toDateString(), [
            ['account_id' => $cash->id, 'debit' => 500, 'credit' => 0],
            ['account_id' => $rev->id, 'debit' => 0, 'credit' => 500],
        ], $user);

        $cf = app(AccountingReportService::class)->cashFlowStatement(
            now()->startOfYear()->toDateString(),
            now()->toDateString(),
        );

        $this->assertEqualsWithDelta(
            round($cf['opening_cash'] + $cf['net_change'], 2),
            $cf['closing_cash'],
            0.01
        );
    }

    public function test_monthly_depreciation_amount(): void
    {
        $asset = Asset::create([
            'code' => 'AST-DEP-1',
            'name_ar' => 'حاسب',
            'purchase_amount' => 12000,
            'salvage_value' => 0,
            'useful_life_months' => 24,
            'purchase_date' => now()->subMonths(1)->toDateString(),
            'condition' => Asset::CONDITION_GOOD,
        ]);

        $monthly = app(DepreciationService::class)->monthlyDepreciation($asset);
        $this->assertEqualsWithDelta(500.0, $monthly, 0.01);
    }
}
