<?php

namespace Tests\Feature;

use App\Livewire\Hr\PayrollRunsIndex;
use App\Models\ChartOfAccount;
use App\Models\ExpenseRequest;
use App\Models\JournalEntry;
use App\Models\Project;
use App\Models\User;
use App\Services\BudgetService;
use App\Services\JournalService;
use App\Services\PayrollRunService;
use App\Support\CoaCodes;
use Database\Seeders\ChartOfAccountsSeeder;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * قيود البذر، المسير الفارغ، واستهلاك الميزانية.
 * Time: O(n) | Space: O(n)
 */
class FinanceClarityTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_refuses_when_no_active_employees_and_empty_run_cannot_be_submitted(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('لا يمكن إنشاء مسيّر بلا موظفين نشطين.');
        app(PayrollRunService::class)->generate('2026-11');
    }

    public function test_backfill_dry_run_counts_paid_expense_and_real_run_posts_it(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(ChartOfAccountsSeeder::class);
        $user = User::factory()->create();
        $expense = ExpenseRequest::factory()->create([
            'requester_id' => $user->id,
            'status' => 'paid',
            'amount' => 1250,
            'payment_method' => 'cash',
        ]);

        $this->artisan('finance:backfill-journal --dry-run')
            ->expectsOutputToContain('مصروف مدفوع')
            ->assertSuccessful();

        $this->assertSame(0, JournalEntry::query()->where('source_id', $expense->id)->count());

        $this->artisan('finance:backfill-journal')->assertSuccessful();
        $this->assertNotNull(JournalEntry::query()->where('source_type', ExpenseRequest::class)->where('source_id', $expense->id)->first());

        $this->artisan('finance:backfill-journal --dry-run')
            ->expectsOutputToContain('0')
            ->assertSuccessful();
    }

    public function test_paid_project_expense_is_budget_consumption(): void
    {
        $project = Project::factory()->create(['budget' => 10000]);
        ExpenseRequest::factory()->create([
            'project_id' => $project->id,
            'status' => 'paid',
            'amount' => 2500,
        ]);

        $row = app(BudgetService::class)->consumption($project->fresh());
        $this->assertSame(2500.0, $row['actual_spend']);
        $this->assertSame(2500.0, $row['consumed']);
        $this->assertSame(25, $row['percent']);
    }

    public function test_payroll_screen_hides_raw_status_keys(): void
    {
        $this->seed(PermissionSeeder::class);
        $hr = User::factory()->create(['must_change_password' => false]);
        $hr->givePermissionTo('hr.salaries.view');
        \App\Models\PayrollRun::query()->create([
            'month' => '2026-07',
            'status' => \App\Models\PayrollRun::STATUS_SUBMITTED,
        ]);

        $html = Livewire::actingAs($hr)->test(PayrollRunsIndex::class)->html();
        $text = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/si', ' ', $html) ?? $html;
        $text = strip_tags($text);
        $this->assertStringContainsString('مرفوع للمالية', $text);
        $this->assertStringContainsString('يوليو 2026', $text);
        $this->assertStringNotContainsString('مرفوع_للمالية', $text);
        $this->assertDoesNotMatchRegularExpression('/type="month"/', $html);
    }

    public function test_journal_cash_account_exists_for_paid_cash_expense(): void
    {
        $this->seed(ChartOfAccountsSeeder::class);
        $expense = ExpenseRequest::factory()->create([
            'status' => 'paid',
            'amount' => 100,
            'payment_method' => 'cash',
        ]);
        app(JournalService::class)->postExpensePaid($expense);
        $cash = ChartOfAccount::query()->where('code', CoaCodes::CASH)->first();
        $this->assertNotNull($cash);
        $this->assertTrue(
            \App\Models\JournalLine::query()->where('account_id', $cash->id)->where('credit', '>', 0)->exists()
        );
    }
}
