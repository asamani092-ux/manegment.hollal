<?php

namespace Tests\Feature;

use App\Livewire\Finance\BudgetsBoard;
use App\Livewire\Finance\FinancialReportsIndex;
use App\Models\ExpenseCategory;
use App\Models\ExpenseRequest;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\Project;
use App\Models\Revenue;
use App\Models\User;
use App\Notifications\BudgetThresholdAlert;
use App\Services\BudgetService;
use App\Services\FinancialReportService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 04-B6 — budget consumption + threshold alerts (80% / 100%) and strictly
 * derived financial reports that reconcile to their source ledgers.
 */
class BudgetAndFinancialReportTest extends TestCase
{
    use RefreshDatabase;

    private function spend(Project $project, float $amount, string $status = 'paid', ?int $categoryId = null): ExpenseRequest
    {
        return ExpenseRequest::create([
            'requester_id' => User::factory()->create()->id,
            'project_id' => $project->id,
            'category_id' => $categoryId,
            'type' => 'operational',
            'amount' => $amount,
            'reason' => 'اختبار',
            'payment_method' => 'transfer',
            'status' => $status,
        ]);
    }

    public function test_consumption_is_derived_from_approved_and_paid_expenses(): void
    {
        $project = Project::factory()->create(['budget' => 10000]);
        $this->spend($project, 4000, 'paid');
        $this->spend($project, 1000, 'approved');
        $this->spend($project, 9000, 'draft'); // never counted

        $consumption = app(BudgetService::class)->consumption($project);

        $this->assertSame(4000.0, $consumption['actual_spend']);
        $this->assertSame(1000.0, $consumption['committed']);
        $this->assertSame(5000.0, $consumption['consumed']);
        $this->assertSame(5000.0, $consumption['remaining']);
        $this->assertSame(50, $consumption['percent']);
    }

    public function test_alert_fires_at_80_percent(): void
    {
        Notification::fake();
        $project = Project::factory()->create(['budget' => 10000]);
        $this->spend($project, 8000, 'paid');

        $alerted = app(BudgetService::class)->fireThresholdAlerts();

        $this->assertSame([['project_id' => $project->id, 'tier' => 80, 'percent' => 80]], $alerted);
        Notification::assertSentTimes(BudgetThresholdAlert::class, 1);
    }

    public function test_alert_fires_at_100_percent_and_reaches_finance_and_manager(): void
    {
        Notification::fake();
        $this->seed(PermissionSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);

        $finance = User::factory()->create();
        $finance->assignRole('Finance');
        $manager = User::factory()->create();
        $project = Project::factory()->create(['budget' => 10000, 'manager_id' => $manager->id]);
        $this->spend($project, 10000, 'paid');

        $alerted = app(BudgetService::class)->fireThresholdAlerts();

        $this->assertSame(100, $alerted[0]['tier']);
        $this->assertSame(100, $alerted[0]['percent']);
        Notification::assertSentTo($finance, BudgetThresholdAlert::class);
        Notification::assertSentTo($manager, BudgetThresholdAlert::class);
    }

    public function test_no_alert_below_the_threshold(): void
    {
        Notification::fake();
        $project = Project::factory()->create(['budget' => 10000]);
        $this->spend($project, 7900, 'paid');

        $this->assertSame([], app(BudgetService::class)->fireThresholdAlerts());
        Notification::assertNothingSent();
    }

    public function test_console_command_sweeps_thresholds(): void
    {
        Notification::fake();
        $project = Project::factory()->create(['budget' => 1000]);
        $this->spend($project, 1000, 'paid');

        $this->artisan('budgets:check-thresholds')
            ->expectsOutputToContain('1 budget threshold alert(s) sent.')
            ->assertSuccessful();
    }

    public function test_report_totals_reconcile_to_source_ledgers(): void
    {
        $month = now()->format('Y-m');
        $project = Project::factory()->create(['budget' => 100000]);
        $catA = ExpenseCategory::create(['name_ar' => 'تشغيلي']);
        $catB = ExpenseCategory::create(['name_ar' => 'إداري']);

        $this->spend($project, 1500, 'paid', $catA->id);
        $this->spend($project, 500, 'approved', $catB->id);
        $this->spend($project, 999, 'rejected', $catB->id); // excluded

        Revenue::create([
            'source_type' => Revenue::SOURCE_MANUAL,
            'amount' => 5000,
            'status' => Revenue::STATUS_CONFIRMED,
            'confirmed_at' => now(),
        ]);
        Revenue::create([
            'source_type' => Revenue::SOURCE_MANUAL,
            'amount' => 777,
            'status' => Revenue::STATUS_RECORDED, // unconfirmed, excluded
        ]);

        $run = PayrollRun::create(['month' => $month, 'status' => PayrollRun::STATUS_EXECUTED]);
        $item = new PayrollRunItem(['employee_id' => User::factory()->create()->id, 'base' => 900, 'net' => 900]);
        $item->payroll_run_id = $run->id;
        $item->save();

        $service = app(FinancialReportService::class);
        $report = $service->monthly($month);

        $this->assertSame(2000.0, $report['expenses_total']);
        $this->assertSame(5000.0, $report['revenues_total']);
        $this->assertSame(900.0, $report['payroll_total']);
        $this->assertSame(2100.0, $report['net']);
        $this->assertTrue($service->reconciles($report));

        // line items tie back to the header totals
        $this->assertSame(2000.0, collect($report['expenses_by_category'])->sum('total'));
        $this->assertSame(5000.0, collect($report['revenues_by_category'])->sum('total'));
    }

    public function test_reconciliation_fails_when_a_total_is_tampered_with(): void
    {
        $service = app(FinancialReportService::class);
        $report = $service->monthly(now()->format('Y-m'));
        $report['expenses_total'] = 12345.67;

        $this->assertFalse($service->reconciles($report));
    }

    public function test_budgets_board_screen_renders_for_permitted_user(): void
    {
        $this->seed(PermissionSeeder::class);
        $user = User::factory()->create();
        $user->givePermissionTo('finance.budgets.view');
        $project = Project::factory()->create(['budget' => 1000, 'name' => 'مشروع الموازنة']);
        $this->spend($project, 900, 'paid');

        Livewire::actingAs($user)->test(BudgetsBoard::class)
            ->assertOk()
            ->assertSee('مشروع الموازنة')
            ->assertSee('90%');
    }

    public function test_budgets_board_is_read_only(): void
    {
        $this->assertFalse(method_exists(BudgetsBoard::class, 'save'));
        $this->assertFalse(method_exists(BudgetsBoard::class, 'update'));
        $this->assertFalse(method_exists(FinancialReportsIndex::class, 'save'));
    }

    public function test_financial_reports_screen_requires_permission(): void
    {
        $this->seed(PermissionSeeder::class);
        $user = User::factory()->create(['must_change_password' => false]);

        $this->actingAs($user)->get('/financial-reports')->assertForbidden();
        $this->actingAs($user)->get('/budgets')->assertForbidden();
    }

    public function test_financial_report_pdf_is_downloadable(): void
    {
        $this->seed(PermissionSeeder::class);
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo('finance.reports.view');

        $this->actingAs($user)
            ->get(route('financial-reports.pdf', ['month' => now()->format('Y-m')]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }
}
