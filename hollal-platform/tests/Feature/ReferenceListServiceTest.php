<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ReferenceItem;
use App\Models\User;
use App\Services\ReferenceListService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ReferenceListsSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ReferenceListServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReferenceListService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
        $this->seed(ReferenceListsSeeder::class);
        $this->service = app(ReferenceListService::class);

        Schema::create('reference_pins', function ($table) {
            $table->id();
            $table->unsignedBigInteger('reference_item_id');
        });
        config([
            'reference_lists.references.exclusion_reasons' => [
                ['table' => 'reference_pins', 'column' => 'reference_item_id'],
            ],
        ]);
    }

    public function test_revising_referenced_item_keeps_version_one_pointer(): void
    {
        $v1 = $this->service->item('exclusion_reasons', 'other');
        $this->assertNotNull($v1);
        DB::table('reference_pins')->insert(['reference_item_id' => $v1->id]);

        $v2 = $this->service->revise($v1, ['name_ar' => 'أخرى محدّثة'], 'تحديث النص', now()->toDateString());

        $this->assertSame(2, $v2->version);
        $this->assertSame($v1->id, $v2->supersedes_id);
        $pin = DB::table('reference_pins')->first();
        $still = ReferenceItem::find($pin->reference_item_id);
        $this->assertSame(1, $still->version);
        $this->assertSame('أخرى', $still->name_ar);
        $this->assertNotSame('أخرى محدّثة', $still->name_ar);
    }

    public function test_deleting_referenced_item_is_rejected_in_arabic(): void
    {
        $item = $this->service->item('exclusion_reasons', 'other');
        DB::table('reference_pins')->insert(['reference_item_id' => $item->id]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('لا يمكن حذف عنصر مُشار إليه');
        $this->service->delete($item, 'محاولة حذف');
    }

    public function test_active_items_on_a_past_date_return_that_version(): void
    {
        $v1 = $this->service->item('exclusion_reasons', 'other');
        DB::table('reference_pins')->insert(['reference_item_id' => $v1->id]);
        $this->service->revise($v1->fresh(), ['name_ar' => 'نسخة لاحقة'], 'تحديث', now()->addDay()->toDateString());

        $past = $this->service->item('exclusion_reasons', 'other', now()->toDateString());
        $future = $this->service->item('exclusion_reasons', 'other', now()->addDay()->toDateString());

        $this->assertSame('أخرى', $past->name_ar);
        $this->assertSame(1, $past->version);
        $this->assertSame('نسخة لاحقة', $future->name_ar);
        $this->assertSame(2, $future->version);
    }

    public function test_import_dry_run_then_commit_creates_version_with_reason(): void
    {
        $path = storage_path('app/reference-import-test.xlsx');
        $sheet = new Spreadsheet;
        $sheet->getActiveSheet()->fromArray([
            ['code', 'name_ar', 'attributes_json'],
            ['other', 'أخرى من الملف', '{}'],
            ['new_reason', 'سبب جديد', '{}'],
        ]);
        (new Xlsx($sheet))->save($path);

        $preview = $this->service->previewImport('exclusion_reasons', $path, now()->addDays(2)->toDateString());
        $this->assertCount(1, $preview['adds']);
        $this->assertCount(1, $preview['changes']);
        $this->assertSame('new_reason', $preview['adds'][0]['code']);

        $this->service->commitImport('exclusion_reasons', $path, now()->addDays(2)->toDateString(), 'استيراد مع سبب');

        $this->assertNotNull($this->service->item('exclusion_reasons', 'new_reason', now()->addDays(2)->toDateString()));
        $this->assertTrue(
            AuditLog::query()->where('action', 'reference_item.revised')->where('metadata->reason', 'استيراد مع سبب')->exists()
            || AuditLog::query()->where('metadata->reason', 'استيراد مع سبب')->exists()
        );
    }

    public function test_help_button_renders_topic_and_hides_without_permission(): void
    {
        $admin = User::factory()->create(['must_change_password' => false]);
        $admin->assignRole('Super Admin');
        $html = $this->actingAs($admin)->blade('<x-help-button screen="settings.lists" />');
        $this->assertStringContainsString('القوائم المرجعية', $html);
        $this->assertStringContainsString('شرح', $html);

        $employee = User::factory()->create(['must_change_password' => false]);
        $hidden = $this->actingAs($employee)->blade('<x-help-button screen="screen.without.topic" />');
        $this->assertStringNotContainsString('أضف شرحًا', $hidden);
        $this->assertStringNotContainsString('شرح', $hidden);
    }

    public function test_suspend_requires_reason_and_writes_audit(): void
    {
        $item = $this->service->item('exclusion_reasons', 'other');
        $this->service->suspend($item, 'إيقاف مؤقت');
        $this->assertSame(ReferenceItem::STATUS_SUSPENDED, $item->fresh()->status);
        $this->assertTrue(AuditLog::query()->where('action', 'reference_item.suspended')->exists());
    }
}
