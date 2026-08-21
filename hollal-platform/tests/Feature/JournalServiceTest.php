<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\ChartOfAccount;
use App\Models\ExpenseCategory;
use App\Models\ExpenseRequest;
use App\Models\JournalEntry;
use App\Models\Revenue;
use App\Models\RevenueCategory;
use App\Models\User;
use App\Services\AssetService;
use App\Services\JournalService;
use App\Services\RevenueService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * FIN-ACC-2 — balanced journals and automatic posting.
 */
class JournalServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
    }

    public function test_manual_entry_must_balance(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $cash = ChartOfAccount::where('code', '1100')->firstOrFail();
        $expense = ChartOfAccount::where('code', '5100')->firstOrFail();

        $this->expectException(\InvalidArgumentException::class);
        app(JournalService::class)->postManual('غير متوازن', now()->toDateString(), [
            ['account_id' => $expense->id, 'debit' => 100, 'credit' => 0],
            ['account_id' => $cash->id, 'debit' => 0, 'credit' => 50],
        ], $user);
    }

    public function test_manual_balanced_entry_posts(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $cash = ChartOfAccount::where('code', '1100')->firstOrFail();
        $expense = ChartOfAccount::where('code', '5100')->firstOrFail();

        $entry = app(JournalService::class)->postManual('تسوية', now()->toDateString(), [
            ['account_id' => $expense->id, 'debit' => 100, 'credit' => 0],
            ['account_id' => $cash->id, 'debit' => 0, 'credit' => 100],
        ], $user);

        $this->assertTrue($entry->isBalanced());
        $this->assertSame(100.0, $entry->debitTotal());
        $this->assertFalse($entry->is_automatic);
    }

    public function test_expense_paid_posts_once(): void
    {
        $category = ExpenseCategory::create([
            'name_ar' => 'تشغيل',
            'account_id' => ChartOfAccount::where('code', '5100')->value('id'),
            'is_active' => true,
        ]);
        $expense = ExpenseRequest::create([
            'requester_id' => User::factory()->create()->id,
            'amount' => 250,
            'type' => 'operational',
            'status' => 'paid',
            'category_id' => $category->id,
            'payment_method' => 'transfer',
            'reason' => 'اختبار قيد صرف',
            'description' => 'اختبار',
        ]);

        $first = app(JournalService::class)->postExpensePaid($expense->fresh(['category.account']));
        $second = app(JournalService::class)->postExpensePaid($expense->fresh(['category.account']));

        $this->assertNotNull($first);
        $this->assertTrue($first->isBalanced());
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, JournalEntry::query()->where('source_type', $expense->getMorphClass())->count());
    }

    public function test_revenue_and_asset_post_journals(): void
    {
        $revCat = RevenueCategory::create([
            'name_ar' => 'منح',
            'account_id' => ChartOfAccount::where('code', '4100')->value('id'),
            'is_active' => true,
        ]);

        $revenue = app(RevenueService::class)->recordManual(500, $revCat->id, now()->toDateString());
        $this->assertDatabaseHas('journal_entries', [
            'source_type' => Revenue::class,
            'source_id' => $revenue->id,
            'is_automatic' => true,
        ]);

        $asset = app(AssetService::class)->create('جهاز', null, [
            'purchase_amount' => 1200,
            'purchase_date' => now()->toDateString(),
        ]);
        $this->assertInstanceOf(Asset::class, $asset);
        $this->assertDatabaseHas('journal_entries', [
            'source_type' => Asset::class,
            'source_id' => $asset->id,
        ]);
    }

    public function test_journal_screen_opens(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo('finance.accounting.manage');

        $this->actingAs($user)->get(route('journal-entries.index'))
            ->assertOk()
            ->assertSee('القيود اليومية');
    }
}
