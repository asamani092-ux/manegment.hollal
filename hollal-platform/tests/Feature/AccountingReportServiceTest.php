<?php

namespace Tests\Feature;

use App\Models\ChartOfAccount;
use App\Models\User;
use App\Services\AccountingReportService;
use App\Services\JournalService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingReportServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
    }

    public function test_trial_balance_and_income_match_journals(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $cash = ChartOfAccount::where('code', '1100')->firstOrFail();
        $revenue = ChartOfAccount::where('code', '4100')->firstOrFail();
        $expense = ChartOfAccount::where('code', '5100')->firstOrFail();

        app(JournalService::class)->postManual('إيراد', now()->toDateString(), [
            ['account_id' => $cash->id, 'debit' => 1000, 'credit' => 0],
            ['account_id' => $revenue->id, 'debit' => 0, 'credit' => 1000],
        ], $user);

        app(JournalService::class)->postManual('مصروف', now()->toDateString(), [
            ['account_id' => $expense->id, 'debit' => 300, 'credit' => 0],
            ['account_id' => $cash->id, 'debit' => 0, 'credit' => 300],
        ], $user);

        $service = app(AccountingReportService::class);
        $trial = $service->trialBalance();
        $this->assertTrue($trial['balanced']);
        $this->assertSame(1000.0, $trial['total_debit']);
        $this->assertSame(1000.0, $trial['total_credit']);

        $income = $service->incomeStatement();
        $this->assertSame(1000.0, $income['revenues']);
        $this->assertSame(300.0, $income['expenses']);
        $this->assertSame(700.0, $income['surplus']);

        $balance = $service->balanceSheet();
        $this->assertTrue($balance['balanced']);
    }

    public function test_accounting_reports_screen_opens(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo('finance.accounting.manage');

        $this->actingAs($user)->get(route('accounting-reports.index'))
            ->assertOk()
            ->assertSee('ميزان المراجعة');
    }
}
