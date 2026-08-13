<?php

namespace Tests\Feature;

use App\Console\Commands\GenerateWeeklyReport;
use App\Livewire\Programs\PlanTemplateEditor;
use App\Livewire\Projects\ProjectShow;
use App\Livewire\Projects\ProjectsIndex;
use App\Livewire\Reports\AuditLogIndex;
use App\Livewire\Reports\ReportsCenter;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\PlanTemplate;
use App\Models\Project;
use App\Models\User;
use App\Models\WeeklyReport;
use App\Support\DownloadHeaders;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlanTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Report round 2 — Batch 5: projects, documents/reports, struct smoke, suite green.
 */
class ReportRound2Batch5Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        Storage::fake('local');
    }

    public function test_revenue_download_requires_permission_middleware(): void
    {
        Storage::disk('local')->put('revenues/t.pdf', '%PDF');
        $user = User::factory()->create(['must_change_password' => false]);
        $revenue = \App\Models\Revenue::create([
            'source_type' => \App\Models\Revenue::SOURCE_MANUAL,
            'amount' => 100,
            'received_at' => now()->toDateString(),
            'status' => \App\Models\Revenue::STATUS_RECORDED,
            'external_document_path' => 'revenues/t.pdf',
        ]);

        $this->actingAs($user)
            ->get(route('revenues.files.download', $revenue))
            ->assertForbidden();

        $user->givePermissionTo('finance.revenues.view');
        $this->actingAs($user)
            ->get(route('revenues.files.download', $revenue))
            ->assertOk();
    }

    public function test_project_show_execution_entry_and_files_source_text(): void
    {
        $manager = User::factory()->create(['must_change_password' => false]);
        $manager->givePermissionTo(['projects.view', 'documents.create', 'documents.view']);
        $project = Project::factory()->create(['manager_id' => $manager->id]);

        Livewire::actingAs($manager)
            ->test(ProjectShow::class, ['project' => $project])
            ->assertSee('فتح مساحة التنفيذ')
            ->assertSee('القياس القبلي')
            ->set('activeTab', 'files')
            ->assertSee('مستودع المستندات المرتبطة بهذا المشروع')
            ->set('docTitle', 'مرفق دفعة 5')
            ->set('docCategory', 'عقود')
            ->set('docFile', UploadedFile::fake()->create('file.pdf', 15, 'application/pdf'))
            ->call('uploadProjectDocument')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('documents', [
            'title' => 'مرفق دفعة 5',
            'project_id' => $project->id,
        ]);
    }

    public function test_partnership_form_accepts_contract_file_upload(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo(['projects.view', 'partnerships.create', 'partnerships.update']);

        Livewire::actingAs($user)
            ->test(ProjectsIndex::class)
            ->call('openPartnershipCreate')
            ->set('entity_name', 'جهة رفع عقد')
            ->set('contractFile', UploadedFile::fake()->create('عقد.pdf', 20, 'application/pdf'))
            ->call('savePartnership')
            ->assertHasNoErrors();

        $partnership = \App\Models\Partnership::query()->where('entity_name', 'جهة رفع عقد')->first();
        $this->assertNotNull($partnership);
        $this->assertNotEmpty($partnership->contract_pdf);
        $this->assertStringNotContainsString('http', (string) $partnership->contract_pdf);
        Storage::disk('local')->assertExists($partnership->contract_pdf);
    }

    public function test_plan_template_preview_still_works(): void
    {
        $this->seed(PlanTemplateSeeder::class);
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo(['projects.templates.manage']);
        $template = PlanTemplate::query()->orderBy('id')->firstOrFail();

        Livewire::actingAs($user)
            ->test(PlanTemplateEditor::class)
            ->call('selectTemplate', $template->id)
            ->call('togglePreview')
            ->assertSet('showPreview', true)
            ->assertSee('معاينة الخطة');
    }

    public function test_weekly_report_generate_archives_document_cumulatively(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        User::factory()->create(['manager_id' => $user->id]);

        Artisan::call(GenerateWeeklyReport::class);
        Artisan::call(GenerateWeeklyReport::class);

        $this->assertSame(2, WeeklyReport::count());
        $this->assertSame(2, Document::query()->where('category', 'تقرير')->where('source_type', 'weekly_report')->count());
        $doc = Document::query()->where('category', 'تقرير')->first();
        $this->assertStringContainsString('تقرير أسبوعي', $doc->title);
        $this->assertSame($user->id, $doc->uploader_id);
        Storage::disk('local')->assertExists($doc->path);
    }

    public function test_reports_center_export_creates_document_in_repo(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo(['reports.view', 'reports.monthly.view', 'reports.export', 'documents.view']);

        Livewire::actingAs($user)
            ->test(ReportsCenter::class)
            ->assertSee('مستودع المستندات')
            ->call('exportCsv');

        $this->assertDatabaseHas('documents', [
            'category' => 'تقرير',
            'source_type' => 'report_export',
            'uploader_id' => $user->id,
        ]);
        $this->assertSame(1, Document::query()->where('category', 'تقرير')->count());

        Livewire::actingAs($user)->test(ReportsCenter::class)->call('exportCsv');
        $this->assertSame(2, Document::query()->where('category', 'تقرير')->count());
    }

    public function test_document_download_uses_arabic_filename_headers(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo(['documents.view', 'documents.create']);
        Storage::disk('local')->put('documents/demo.txt', 'محتوى');
        $document = Document::create([
            'title' => 'تقرير شهري تجريبي',
            'category' => 'تقرير',
            'confidentiality' => 'team',
            'uploader_id' => $user->id,
            'path' => 'documents/demo.txt',
        ]);

        $header = DownloadHeaders::contentDisposition('تقرير شهري تجريبي.txt');
        $this->assertStringContainsString("filename*=UTF-8''", $header);
        $this->assertStringContainsString(rawurlencode('تقرير شهري تجريبي.txt'), $header);

        $response = $this->actingAs($user)->get(route('documents.files.download', $document));
        $response->assertOk();
        $disposition = $response->headers->get('Content-Disposition');
        $this->assertNotNull($disposition);
        $this->assertStringContainsString("filename*=UTF-8''", $disposition);
    }

    public function test_audit_log_arabic_labels_status_column_and_cached_actions(): void
    {
        $this->assertSame('صرف مدفوع', AuditLog::labelFor('expense.paid'));
        $this->assertSame('تسجيل دخول ناجح', AuditLog::labelFor('auth.login_success'));

        $actor = User::factory()->create(['must_change_password' => false]);
        $actor->givePermissionTo(['reports.audit-log.view', 'reports.export']);
        AuditLog::create([
            'actor_id' => $actor->id,
            'action' => 'expense.approved',
            'metadata' => ['stage' => 'finance', 'final' => true],
            'created_at' => now(),
        ]);

        Livewire::actingAs($actor)
            ->test(AuditLogIndex::class)
            ->assertSee('اعتماد صرف')
            ->assertSee('نهائي')
            ->assertSeeHtml('الحالة');
    }
}
