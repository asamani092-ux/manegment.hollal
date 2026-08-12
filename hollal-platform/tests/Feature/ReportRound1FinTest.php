<?php

namespace Tests\Feature;

use App\Livewire\Finance\AssetsIndex;
use App\Livewire\Finance\BudgetsBoard;
use App\Livewire\Finance\TaxInvoicesIndex;
use App\Models\Asset;
use App\Models\Custody;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Services\AssetService;
use App\Services\BudgetService;
use App\Services\CustodyService;
use App\Services\FinancialReportService;
use App\Support\PdfArabic;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Report round 1 — FIN-2..6.
 */
class ReportRound1FinTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_custody_reject_requires_reason_and_blocks_disburse(): void
    {
        $employee = User::factory()->create();
        $executive = User::factory()->create();
        $service = app(CustodyService::class);
        $custody = $service->request($employee, 500, 'عهدة', null, null, null, $employee);

        $this->expectException(\InvalidArgumentException::class);
        $service->reject($custody, $executive, '  ');
    }

    public function test_custody_reject_stores_reason(): void
    {
        $employee = User::factory()->create();
        $executive = User::factory()->create();
        $service = app(CustodyService::class);
        $custody = $service->request($employee, 500, 'عهدة', null, null, null, $employee);
        $service->reject($custody, $executive, 'الغرض غير واضح');

        $this->assertSame(Custody::STATUS_REJECTED, $custody->fresh()->status);
        $this->assertSame('الغرض غير واضح', $custody->fresh()->rejection_reason);

        $this->expectException(\RuntimeException::class);
        $service->disburse($custody->fresh());
    }

    public function test_asset_create_stores_extended_fields(): void
    {
        $hr = User::factory()->create(['must_change_password' => false]);
        $hr->givePermissionTo(['finance.assets.view', 'finance.assets.manage']);
        $holder = User::factory()->create(['is_active' => true]);

        Livewire::actingAs($hr)
            ->test(AssetsIndex::class)
            ->call('openCreateModal')
            ->set('name_ar', 'لابتوب')
            ->set('description', 'جهاز عمل')
            ->set('purchase_amount', '3500')
            ->set('location', 'الرياض')
            ->set('condition', Asset::CONDITION_GOOD)
            ->set('create_holder_id', $holder->id)
            ->call('saveAsset')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('assets', [
            'name_ar' => 'لابتوب',
            'description' => 'جهاز عمل',
            'location' => 'الرياض',
            'current_holder_id' => $holder->id,
        ]);
    }

    public function test_budget_addition_applies_only_after_ceo_approval(): void
    {
        $project = Project::factory()->create(['budget' => 1000]);
        $finance = User::factory()->create();
        $ceo = User::factory()->create();
        $ceo->givePermissionTo('finance.budgets.manage');

        $addition = app(BudgetService::class)->requestAddition($project, 250, $finance, 'إيراد منح');
        $this->assertSame(1000.0, (float) $project->fresh()->budget);

        app(BudgetService::class)->approveAddition($addition, $ceo);
        $this->assertSame(1250.0, (float) $project->fresh()->budget);
        $this->assertSame(\App\Models\BudgetAddition::STATUS_APPROVED, $addition->fresh()->status);
    }

    public function test_tax_invoice_org_dropdown_fills_buyer(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo(['finance.tax_invoices.view', 'finance.tax_invoices.issue']);
        $org = Organization::create(['name' => 'جهة الفاتورة', 'tax_number' => '310099988877766']);

        Livewire::actingAs($user)
            ->test(TaxInvoicesIndex::class)
            ->call('openIssueModal')
            ->set('buyerSource', 'جهة')
            ->set('organizationId', $org->id)
            ->assertSet('buyerName', 'جهة الفاتورة')
            ->assertSet('buyerVatNumber', '310099988877766');
    }

    public function test_financial_pdf_uses_arabic_table_chrome(): void
    {
        $pdf = app(FinancialReportService::class)->exportMonthlyPdf('2026-07');
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertTrue(is_file(resource_path('fonts/Amiri-Regular.ttf')));
        $this->assertSame('Amiri', PdfArabic::defaultFont());
    }

    public function test_budgets_board_shows_source_copy(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo(['finance.budgets.view', 'finance.budgets.manage']);
        Project::factory()->create(['budget' => 1000, 'name' => 'مشروع المصدر']);

        Livewire::actingAs($user)
            ->test(BudgetsBoard::class)
            ->assertSee('مصدر الموازنة', false)
            ->assertSee('إضافة للموازنة', false);
    }
}
