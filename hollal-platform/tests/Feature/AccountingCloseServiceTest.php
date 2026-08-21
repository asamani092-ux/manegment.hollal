<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use App\Services\AccountingCloseService;
use App\Services\JournalService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingCloseServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
    }

    public function test_sync_opening_reconcile_and_close(): void
    {
        Department::create(['name' => 'إدارة تجريبية']);
        $user = User::factory()->create(['must_change_password' => false]);
        $service = app(AccountingCloseService::class);

        $this->assertGreaterThan(0, $service->syncCostCentersFromStructure());

        $opening = $service->postOpeningBalance(5000, $user);
        $this->assertTrue($opening->isBalanced());

        $bankId = \App\Models\ChartOfAccount::where('code', '1200')->value('id');
        $rec = $service->reconcileBank($bankId, now()->startOfMonth()->toDateString(), now()->toDateString(), 0, $user);
        $this->assertSame('مكتمل', $rec->status);

        $cash = \App\Models\ChartOfAccount::where('code', '1100')->firstOrFail();
        $rev = \App\Models\ChartOfAccount::where('code', '4100')->firstOrFail();
        app(JournalService::class)->postManual('إيراد سنة', now()->toDateString(), [
            ['account_id' => $cash->id, 'debit' => 200, 'credit' => 0],
            ['account_id' => $rev->id, 'debit' => 0, 'credit' => 200],
        ], $user);

        $close = $service->closeFiscalYear((int) now()->year, $user);
        $this->assertSame((int) now()->year, $close->year);
    }
}
