<?php

namespace Tests\Feature;

use App\Livewire\Finance\AssetsIndex;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Contract;
use App\Models\Document;
use App\Models\User;
use App\Notifications\ContractExpiring;
use App\Notifications\PolicyReviewDue;
use App\Support\RecordUrl;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Report round 2 — CRIT-1 assets UI + CRIT-3 notification deep links.
 */
class ReportRound2CritTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_livewire_create_asset_shows_name_amount_location_and_category(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo(['finance.assets.view', 'finance.assets.manage']);

        $category = AssetCategory::create([
            'name_ar' => 'أجهزة حاسب',
            'can_be_custody' => true,
            'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(AssetsIndex::class)
            ->call('openCreateModal')
            ->set('name_ar', 'شاشة عرض')
            ->set('description', 'شاشة اجتماعات')
            ->set('category_id', $category->id)
            ->set('purchase_amount', '4200.50')
            ->set('location', 'الطابق الثاني')
            ->set('condition', Asset::CONDITION_GOOD)
            ->call('saveAsset')
            ->assertHasNoErrors()
            ->assertSee('شاشة عرض', false)
            ->assertSee('أجهزة حاسب', false)
            ->assertSee('الطابق الثاني', false)
            ->assertSee('4,200.50', false);

        $this->assertDatabaseHas('assets', [
            'name_ar' => 'شاشة عرض',
            'category_id' => $category->id,
            'location' => 'الطابق الثاني',
        ]);
    }

    public function test_record_url_and_notification_deep_links(): void
    {
        $this->assertStringContainsString('open=7', RecordUrl::meeting(7));
        $this->assertStringContainsString('open=9', RecordUrl::payrollRun(9));
        $this->assertSame(route('payroll-runs.index'), RecordUrl::payrollRun());
        $this->assertStringContainsString('open=3', RecordUrl::contract(3));
        $this->assertStringContainsString('open=4', RecordUrl::document(4));
        $this->assertStringContainsString('open=5', RecordUrl::revenue(5));

        $employee = User::factory()->create(['must_change_password' => false]);
        $contract = Contract::create([
            'employee_id' => $employee->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(20)->toDateString(),
            'status' => 'active',
            'value' => 1000,
        ]);

        $payload = (new ContractExpiring($contract, 20))->toDatabase($employee);
        $this->assertSame(RecordUrl::contract($contract->id), $payload['url']);

        $document = Document::create([
            'title' => 'سياسة الموارد',
            'category' => 'سياسة',
            'confidentiality' => 'managers',
            'uploader_id' => $employee->id,
            'path' => 'documents/policy.pdf',
            'review_date' => now()->toDateString(),
        ]);

        $policyPayload = (new PolicyReviewDue($document))->toDatabase($employee);
        $this->assertSame(RecordUrl::document($document->id), $policyPayload['url']);
    }
}
