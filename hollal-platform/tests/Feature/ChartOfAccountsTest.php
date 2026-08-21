<?php

namespace Tests\Feature;

use App\Livewire\Finance\ChartOfAccountsIndex;
use App\Models\ChartOfAccount;
use App\Models\ExpenseCategory;
use App\Models\RevenueCategory;
use App\Models\User;
use App\Services\ChartOfAccountsService;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * FIN-ACC-1 — chart of accounts seed, bridge, and tree UI.
 */
class ChartOfAccountsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function accountant(): User
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo('finance.accounting.manage');

        return $user;
    }

    public function test_seeder_plants_chart_and_bridges_categories(): void
    {
        $expense = ExpenseCategory::create(['name_ar' => 'ضيافة', 'is_active' => true]);
        $revenue = RevenueCategory::create(['name_ar' => 'منح', 'is_active' => true]);

        $this->seed(ChartOfAccountsSeeder::class);

        $this->assertDatabaseHas('chart_of_accounts', ['code' => '1100', 'name_ar' => 'الصندوق']);
        $this->assertDatabaseHas('chart_of_accounts', ['code' => '5100', 'name_ar' => 'مصروفات تشغيلية']);
        $this->assertNotNull($expense->fresh()->account_id);
        $this->assertNotNull($revenue->fresh()->account_id);
    }

    public function test_tree_screen_opens_for_accountant(): void
    {
        $this->seed(ChartOfAccountsSeeder::class);

        $this->actingAs($this->accountant())
            ->get(route('chart-of-accounts.index'))
            ->assertOk()
            ->assertSee('دليل الحسابات')
            ->assertSee('الصندوق');
    }

    public function test_stranger_cannot_open_chart(): void
    {
        $stranger = User::factory()->create(['must_change_password' => false]);

        $this->actingAs($stranger)->get(route('chart-of-accounts.index'))->assertForbidden();
    }

    public function test_create_and_delete_account_via_livewire(): void
    {
        Livewire::actingAs($this->accountant())
            ->test(ChartOfAccountsIndex::class)
            ->call('openCreate')
            ->set('code', '1999')
            ->set('name_ar', 'حساب تجريبي')
            ->set('type', ChartOfAccount::TYPE_ASSETS)
            ->set('nature', ChartOfAccount::NATURE_DEBIT)
            ->call('save')
            ->assertHasNoErrors();

        $account = ChartOfAccount::where('code', '1999')->firstOrFail();
        $this->assertSame('حساب تجريبي', $account->name_ar);

        Livewire::actingAs($this->accountant())
            ->test(ChartOfAccountsIndex::class)
            ->call('deleteAccount', $account->id)
            ->assertHasNoErrors();

        $this->assertSoftDeleted('chart_of_accounts', ['id' => $account->id]);
    }

    public function test_cannot_delete_account_linked_to_expense_category(): void
    {
        $account = app(ChartOfAccountsService::class)->create([
            'code' => '5110',
            'name_ar' => 'ضيافة',
            'type' => ChartOfAccount::TYPE_EXPENSE,
            'nature' => ChartOfAccount::NATURE_DEBIT,
        ]);
        ExpenseCategory::create(['name_ar' => 'ضيافة', 'account_id' => $account->id, 'is_active' => true]);

        $this->expectException(\RuntimeException::class);
        app(ChartOfAccountsService::class)->delete($account);
    }
}
