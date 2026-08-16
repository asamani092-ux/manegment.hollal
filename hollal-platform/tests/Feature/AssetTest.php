<?php

namespace Tests\Feature;

use App\Livewire\Finance\AssetsIndex;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Project;
use App\Models\User;
use App\Services\AssetService;
use App\Services\BudgetService;
use App\Services\OffboardingService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 04-B5 / Wave D-deep — asset movements + handover PDF, condition audit,
 * offboarding hold, independent register (useful life + book value,
 * active/all filtering — never feeds project budgets).
 */
class AssetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_handover_creates_movement_and_pdf(): void
    {
        $service = app(AssetService::class);
        $holder = User::factory()->create();
        $asset = $service->create('جهاز حاسب', null);

        $movement = $service->handover($asset, $holder, 'تسليم للموظف');

        $this->assertDatabaseHas('asset_movements', [
            'asset_id' => $asset->id,
            'to_holder_id' => $holder->id,
            'movement_type' => 'تسليم',
        ]);
        $this->assertNotNull($movement->handover_document_path);
        Storage::disk('local')->assertExists($movement->handover_document_path);
        $this->assertSame($holder->id, $asset->fresh()->current_holder_id);
    }

    public function test_code_is_auto_generated(): void
    {
        $service = app(AssetService::class);
        $a = $service->create('أصل 1', null);
        $b = $service->create('أصل 2', null);

        $this->assertNotSame($a->code, $b->code);
        $this->assertStringStartsWith('AST-', $a->code);
    }

    public function test_can_be_custody_inherited_from_category(): void
    {
        $category = AssetCategory::create(['name_ar' => 'أجهزة', 'can_be_custody' => true]);
        $asset = app(AssetService::class)->create('لابتوب', $category->id);

        $this->assertTrue($asset->can_be_custody);
    }

    public function test_condition_update_is_logged(): void
    {
        $asset = app(AssetService::class)->create('طابعة', null);

        app(AssetService::class)->updateCondition($asset, Asset::CONDITION_MAINTENANCE);

        $this->assertSame('صيانة', $asset->fresh()->condition);
        $this->assertDatabaseHas('audit_logs', ['action' => 'asset.condition_changed']);
    }

    public function test_offboarding_blocked_by_held_asset(): void
    {
        $service = app(AssetService::class);
        $holder = User::factory()->create();
        $asset = $service->create('عهدة', null);
        $service->handover($asset, $holder);

        $this->assertNotEmpty(app(OffboardingService::class)->holds($holder));

        $actor = User::factory()->create();
        app(OffboardingService::class)->offboard($holder, $actor);

        $this->expectException(\RuntimeException::class);
        app(OffboardingService::class)->complete($holder, $actor);
    }

    public function test_book_value_depreciates_straight_line_over_useful_life(): void
    {
        $asset = Asset::create([
            'code' => 'AST-TEST-1',
            'name_ar' => 'حاسب محمول',
            'condition' => Asset::CONDITION_GOOD,
            'purchase_amount' => 10000,
            'useful_life_years' => 5,
            'purchase_date' => now()->subYears(2),
        ]);

        // 2 of 5 years elapsed → ~60% of purchase value remains.
        $this->assertEqualsWithDelta(6000.0, $asset->bookValue(), 25.0);
    }

    public function test_book_value_never_goes_below_zero_past_useful_life(): void
    {
        $asset = Asset::create([
            'code' => 'AST-TEST-2',
            'name_ar' => 'طابعة قديمة',
            'condition' => Asset::CONDITION_GOOD,
            'purchase_amount' => 1000,
            'useful_life_years' => 2,
            'purchase_date' => now()->subYears(10),
        ]);

        $this->assertSame(0.0, $asset->bookValue());
    }

    public function test_book_value_falls_back_to_purchase_amount_without_useful_life(): void
    {
        $asset = Asset::create([
            'code' => 'AST-TEST-3',
            'name_ar' => 'كرسي',
            'condition' => Asset::CONDITION_GOOD,
            'purchase_amount' => 500,
        ]);

        $this->assertSame(500.0, $asset->bookValue());
    }

    public function test_useful_life_is_set_at_creation_via_the_screen(): void
    {
        $this->seed(PermissionSeeder::class);
        $user = User::factory()->create();
        $user->givePermissionTo(['finance.assets.view', 'finance.assets.manage']);

        Livewire::actingAs($user)->test(AssetsIndex::class)
            ->call('openCreateModal')
            ->set('name_ar', 'خزانة ملفات')
            ->set('purchase_amount', '2000')
            ->set('useful_life_years', '4')
            ->call('saveAsset')
            ->assertHasNoErrors();

        $asset = Asset::where('name_ar', 'خزانة ملفات')->firstOrFail();
        $this->assertSame(4, $asset->useful_life_years);
    }

    public function test_is_active_excludes_damaged_and_retired(): void
    {
        $good = app(AssetService::class)->create('أصل سليم', null);
        $damaged = app(AssetService::class)->create('أصل تالف', null, ['condition' => Asset::CONDITION_DAMAGED]);
        $retired = app(AssetService::class)->create('أصل مستبعد', null, ['condition' => Asset::CONDITION_RETIRED]);

        $this->assertTrue($good->isActive());
        $this->assertFalse($damaged->isActive());
        $this->assertFalse($retired->isActive());
    }

    public function test_default_screen_view_excludes_damaged_and_retired_assets(): void
    {
        $this->seed(PermissionSeeder::class);
        $user = User::factory()->create();
        $user->givePermissionTo('finance.assets.view');

        app(AssetService::class)->create('أصل نشط', null);
        app(AssetService::class)->create('أصل تالف للاستبعاد', null, ['condition' => Asset::CONDITION_DAMAGED]);

        Livewire::actingAs($user)->test(AssetsIndex::class)
            ->assertSee('أصل نشط')
            ->assertDontSee('أصل تالف للاستبعاد')
            ->call('setStatusTab', 'all')
            ->assertSee('أصل تالف للاستبعاد');
    }

    public function test_assets_never_appear_in_the_project_budget_formula(): void
    {
        $project = Project::factory()->create(['budget' => 10000]);
        app(AssetService::class)->create('أصل غالي', null, ['purchase_amount' => 9000]);

        $consumption = app(BudgetService::class)->consumption($project);

        $this->assertSame(0.0, $consumption['actual_spend']);
        $this->assertSame(0.0, $consumption['consumed']);
    }

    public function test_assets_excel_and_pdf_export_respect_filters(): void
    {
        $this->seed(PermissionSeeder::class);
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo(['finance.assets.view', 'finance.assets.manage']);

        app(AssetService::class)->create('أصل تصدير', null, ['purchase_amount' => 100, 'useful_life_years' => 5]);

        $excel = $this->actingAs($user)
            ->get(route('assets.excel', ['statusTab' => 'active']))
            ->assertOk();
        $excel->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('أصل تصدير', $excel->streamedContent());

        $pdf = $this->actingAs($user)
            ->get(route('assets.pdf', ['statusTab' => 'active', 'print' => 1]))
            ->assertOk();
        $pdf->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString(
            'inline',
            (string) $pdf->headers->get('Content-Disposition')
        );
        $this->assertGreaterThan(100, strlen($pdf->getContent()));
    }

    public function test_assets_index_shows_print_and_excel_actions(): void
    {
        $this->seed(PermissionSeeder::class);
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo(['finance.assets.view', 'finance.assets.manage']);

        Livewire::actingAs($user)
            ->test(AssetsIndex::class)
            ->assertOk()
            ->assertSee('طباعة تقرير الأصول')
            ->assertSee('تصدير Excel')
            ->assertSee('طباعة / PDF');
    }
}
