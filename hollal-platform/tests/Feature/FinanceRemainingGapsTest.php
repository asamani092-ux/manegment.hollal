<?php

namespace Tests\Feature;

use App\Models\ApprovalRule;
use App\Models\AssetCategory;
use App\Models\ChartOfAccount;
use App\Models\ExpenseRequest;
use App\Models\JournalEntry;
use App\Models\RevenueCategory;
use App\Models\User;
use App\Services\AssetService;
use App\Services\ExpenseApprovalService;
use App\Services\RevenueService;
use App\Support\CoaCodes;
use Database\Seeders\ApprovalRulesSeeder;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * إغلاق الفجوات الأربع المتبقية في التبويب المالي.
 */
class FinanceRemainingGapsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
        $this->seed(ApprovalRulesSeeder::class);
    }

    public function test_depreciation_command_is_scheduled(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('finance:run-depreciation');
    }

    public function test_asset_purchase_debits_correct_account(): void
    {
        $category = AssetCategory::create([
            'name_ar' => 'أجهزة حاسب اختبار',
            'default_account_code' => '122',
            'can_be_custody' => true,
            'is_active' => true,
        ]);

        $asset = app(AssetService::class)->create('لابتوب اختبار', $category->id, [
            'purchase_amount' => 2500,
            'purchase_date' => now()->toDateString(),
        ]);

        $journal = JournalEntry::query()
            ->where('source_type', $asset->getMorphClass())
            ->where('source_id', $asset->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($journal);
        $debitLine = $journal->lines()->where('debit', '>', 0)->with('account:id,code')->first();
        $this->assertNotNull($debitLine);
        $this->assertSame('122', $debitLine->account->code);
        $this->assertSame(CoaCodes::COMPUTERS, $debitLine->account->code);
    }

    public function test_revenue_without_category_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('تصنيف الإيراد مطلوب');

        app(RevenueService::class)->recordManual(1000, null, now()->toDateString());
    }

    public function test_revenue_category_must_use_4xx_account(): void
    {
        $cashId = ChartOfAccount::where('code', CoaCodes::CASH)->value('id');
        $category = RevenueCategory::create([
            'name_ar' => 'تصنيف خاطئ',
            'account_id' => $cashId,
            'is_active' => true,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('حساب تصنيف الإيراد يجب أن يكون ضمن حسابات الإيرادات (4xx)');

        app(RevenueService::class)->recordManual(500, $category->id, now()->toDateString());
    }

    public function test_no_fallback_to_chain_mode(): void
    {
        ApprovalRule::where('transaction_type', ApprovalRule::TYPE_EXPENSE)->delete();

        $expense = ExpenseRequest::factory()->create([
            'amount' => 1500,
            'status' => 'draft',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('لا توجد قاعدة اعتماد مطابقة');

        app(ExpenseApprovalService::class)->initializeChain($expense);
    }
}
