<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\User;
use App\Services\AssetService;
use App\Services\OffboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * 04-B5 — asset movements + handover PDF, condition audit, offboarding hold.
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

        $this->expectException(\RuntimeException::class);
        app(OffboardingService::class)->offboard($holder, User::factory()->create());
    }
}
