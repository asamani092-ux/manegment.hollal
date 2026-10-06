<?php

namespace Tests\Feature;

use App\Livewire\Hr\ViolationCatalog;
use App\Livewire\Hr\ViolationsIndex;
use App\Livewire\Settings\ReferenceListsIndex;
use App\Models\ReferenceItem;
use App\Models\User;
use App\Models\Violation;
use App\Services\ReferenceListService;
use App\Services\ViolationCatalogService;
use App\Services\ViolationService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ReferenceListsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * محرر المخالفات والاستيراد والجزاء حسب الواقعة.
 * Time: O(1) لكل حالة | Space: O(1)
 */
class ViolationCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_item_can_be_selected_and_penalty_follows_occurrence(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(ReferenceListsSeeder::class);
        $hr = User::factory()->create();
        $hr->givePermissionTo(['hr.violations.view', 'hr.violations.manage', 'hr.violations.decide']);
        $employee = User::factory()->create(['name' => 'موظف الجزاء']);

        Livewire::actingAs($hr)
            ->test(ViolationCatalog::class)
            ->assertSee('لا توجد مخالفات بعد', false)
            ->set('nameAr', 'تأخر عن الدوام')
            ->set('category', 'مواعيد العمل')
            ->set('description', 'التأخر المتكرر')
            ->set('penalties', [
                ['type' => 'warning', 'value' => ''],
                ['type' => 'deduct_days', 'value' => '2'],
                ['type' => 'deduct_pct_daily', 'value' => '50'],
                ['type' => 'none', 'value' => ''],
            ])
            ->set('autoDetect', 'late')
            ->set('lateMinutes', '15')
            ->call('save')
            ->assertHasNoErrors();

        $item = app(ReferenceListService::class)->item('violations', 'V-001');
        $this->assertNotNull($item);
        $this->assertSame('active', $item->status);
        $this->assertSame('company', $item->attributes['origin']);
        $this->assertSame([], $item->attributes['ministry_baseline']);
        $this->assertSame('warning', $item->attributes['penalties'][0]['type']);

        Livewire::actingAs($hr)
            ->test(ViolationsIndex::class)
            ->assertSee('تأخر عن الدوام', false)
            ->set('manualEmployeeId', $employee->id)
            ->set('manualItemId', $item->id)
            ->set('manualFacts', 'وقائع أولى')
            ->set('manualOccurred', now()->toDateString())
            ->call('recordManualFromList')
            ->assertHasNoErrors();

        $first = Violation::query()->where('facts', 'وقائع أولى')->firstOrFail();
        $this->assertSame(1, (int) $first->occurrence_index);
        app(ViolationService::class)->submitStatement($first, $employee, 'أعتذر');
        $applied = app(ViolationService::class)->decide($first->fresh(), 'apply', $hr, 'تطبيق');
        $this->assertSame('warning', $applied->decided_penalty['type']);

        Livewire::actingAs($hr)
            ->test(ViolationsIndex::class)
            ->set('manualEmployeeId', $employee->id)
            ->set('manualItemId', $item->id)
            ->set('manualFacts', 'وقائع ثانية')
            ->set('manualOccurred', now()->toDateString())
            ->call('recordManualFromList')
            ->assertHasNoErrors();

        $second = Violation::query()->where('facts', 'وقائع ثانية')->firstOrFail();
        $this->assertSame(2, (int) $second->occurrence_index);
        app(ViolationService::class)->submitStatement($second, $employee, 'أعتذر مرة أخرى');
        $next = app(ViolationService::class)->decide($second->fresh(), 'apply', $hr, 'تطبيق');
        $this->assertSame('deduct_days', $next->decided_penalty['type']);
        $this->assertEquals(2, (float) $next->decided_penalty['value']);
    }

    public function test_excel_preview_marks_an_error_row_and_purge_keeps_referenced_samples(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(ReferenceListsSeeder::class);
        $path = storage_path('app/violation-preview-test.xlsx');
        $sheet = new Spreadsheet;
        $sheet->getActiveSheet()->fromArray([
            ViolationCatalogService::HEADERS,
            ['', 'تصنيف خاطئ', '', '', '', '', '', '', '', '', '', '', '', ''],
        ]);
        (new Xlsx($sheet))->save($path);

        $rows = app(ViolationCatalogService::class)->previewSheet($path);
        $this->assertSame('خطأ', $rows[0]['state']);
        $this->assertStringContainsString('الاسم مطلوب', $rows[0]['reason']);

        $service = app(ReferenceListService::class);
        $sample = $service->createDraft('violations', 'draft-sample', 'نموذج', ['category' => 'أخرى'], now()->toDateString());
        $catalog = app(ViolationCatalogService::class);
        $this->assertSame(1, $catalog->purgeSampleItems());
        $this->assertNull(ReferenceItem::query()->find($sample->id));

        $kept = $service->createDraft('violations', 'clarity-late', 'تأخر', ['category' => 'مواعيد العمل'], now()->toDateString());
        $kept = $service->publish($kept, null, 'اختبار');
        $employee = User::factory()->create();
        Violation::query()->create([
            'employee_id' => $employee->id,
            'reference_item_id' => $kept->id,
            'occurred_on' => now()->toDateString(),
            'discovered_on' => now()->toDateString(),
            'status' => 'suggested',
            'occurrence_index' => 1,
            'source' => 'manual',
        ]);
        $this->assertSame(0, $catalog->purgeSampleItems());
        $this->assertNotNull(ReferenceItem::query()->find($kept->id));
    }

    public function test_reference_form_renders_typed_fields_and_arabic_status(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(ReferenceListsSeeder::class);
        $admin = User::factory()->create();
        $admin->givePermissionTo(['settings.lists.view', 'settings.lists.manage']);

        Livewire::actingAs($admin)
            ->test(ReferenceListsIndex::class)
            ->call('selectList', 'leave_types')
            ->assertSee('ساري', false)
            ->assertSee('إضافة صف', false)
            ->assertSee('نعم', false)
            ->assertSee('رصيدها', false)
            ->assertDontSeeHtml('type="date"')
            ->assertSeeHtml('type="number"')
            ->call('selectList', 'violations')
            ->assertSee('جدول المخالفات', false)
            ->assertSee('لا توجد مخالفات بعد', false)
            ->assertDontSeeHtml('wire:model="code"');
    }
}
