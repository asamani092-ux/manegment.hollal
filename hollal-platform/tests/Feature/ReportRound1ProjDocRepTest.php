<?php

namespace Tests\Feature;

use App\Livewire\Meetings\MeetingsArchiveIndex;
use App\Livewire\Programs\PlanTemplateEditor;
use App\Livewire\Projects\ProjectShow;
use App\Livewire\Reports\AuditLogIndex;
use App\Livewire\Reports\ReportsIndex;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\Meeting;
use App\Models\MeetingAmendment;
use App\Models\PlanTemplate;
use App\Models\Project;
use App\Models\User;
use App\Models\WeeklyReport;
use App\Services\MeetingService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PlanTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Report round 1 — PROJ-2..4 + DOC-2 + REP-1..3.
 */
class ReportRound1ProjDocRepTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        Storage::fake('local');
    }

    public function test_project_page_uploads_document_linked_to_project(): void
    {
        $manager = User::factory()->create(['must_change_password' => false]);
        $manager->givePermissionTo(['projects.view', 'documents.create', 'documents.view']);
        $project = Project::factory()->create(['manager_id' => $manager->id]);

        Livewire::actingAs($manager)
            ->test(ProjectShow::class, ['project' => $project])
            ->set('activeTab', 'files')
            ->set('docTitle', 'مرفق المشروع')
            ->set('docCategory', 'تقارير')
            ->set('docFile', UploadedFile::fake()->create('note.pdf', 20, 'application/pdf'))
            ->call('uploadProjectDocument')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('documents', [
            'title' => 'مرفق المشروع',
            'project_id' => $project->id,
            'uploader_id' => $manager->id,
        ]);
    }

    public function test_project_page_links_to_execution_and_shows_measurement_labels(): void
    {
        $manager = User::factory()->create(['must_change_password' => false]);
        $manager->givePermissionTo(['projects.view']);
        $project = Project::factory()->create(['manager_id' => $manager->id, 'name' => 'مشروع القياس']);

        Livewire::actingAs($manager)
            ->test(ProjectShow::class, ['project' => $project])
            ->assertSee('التنفيذ')
            ->assertSee('الزيارات')
            ->assertSee('القياس')
            ->assertSee('قبلي')
            ->assertSee('بعدي');
    }

    public function test_plan_template_preview_toggles(): void
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

    public function test_minutes_amendment_request_then_approval_tags_version(): void
    {
        $requester = User::factory()->create(['must_change_password' => false]);
        $approver = User::factory()->create(['must_change_password' => false]);
        $requester->givePermissionTo(['meetings.view', 'meetings.update', 'documents.view']);
        $approver->givePermissionTo(['meetings.view', 'meetings.update', 'documents.view']);

        $meeting = Meeting::factory()->create([
            'chair_id' => $approver->id,
            'approval_status' => 'مسودة',
            'version' => 1,
        ]);
        $service = app(MeetingService::class);
        $service->approveMinutes($meeting, $approver);

        Livewire::actingAs($requester)
            ->test(MeetingsArchiveIndex::class)
            ->assertSee('طلب ← موافقة ← تعديل البنود ← اعتماد التغيير', false)
            ->call('openAmendRequest', $meeting->id)
            ->set('amendNote', 'تصحيح تاريخ')
            ->call('submitAmendRequest')
            ->assertHasNoErrors();

        $this->assertSame(1, $meeting->fresh()->version);
        $amendment = MeetingAmendment::query()->where('meeting_id', $meeting->id)->where('status', 'معلق')->first();
        $this->assertNotNull($amendment);

        Livewire::actingAs($approver)
            ->test(MeetingsArchiveIndex::class)
            ->call('approveAmendment', $amendment->id);

        $this->assertSame(1, $meeting->fresh()->version);
        $this->assertSame(MeetingAmendment::STATUS_EDITING, $amendment->fresh()->status);

        Livewire::actingAs($approver)
            ->test(MeetingsArchiveIndex::class)
            ->call('finalizeAmendment', $amendment->id);

        $this->assertSame(2, $meeting->fresh()->version);
        $this->assertSame(MeetingAmendment::STATUS_APPROVED, $amendment->fresh()->status);
    }

    public function test_audit_log_displays_arabic_action_labels_without_changing_keys(): void
    {
        $this->assertSame('تحديث إعدادات', AuditLog::labelFor('settings.updated'));

        $actor = User::factory()->create(['must_change_password' => false]);
        $actor->givePermissionTo(['reports.audit-log.view']);
        AuditLog::create([
            'actor_id' => $actor->id,
            'action' => 'settings.updated',
            'created_at' => now(),
        ]);

        Livewire::actingAs($actor)
            ->test(AuditLogIndex::class)
            ->assertSee('تحديث إعدادات');

        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.updated']);
    }

    public function test_weekly_report_has_print_and_document_repo_link(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo(['reports.view']);
        $report = WeeklyReport::create([
            'week_start' => now()->startOfWeek()->toDateString(),
            'week_end' => now()->endOfWeek()->toDateString(),
            'generated_at' => now(),
            'week_spend' => 0,
            'done' => [],
            'overdue' => [],
            'project_status' => [],
            'open_decisions' => [],
        ]);

        Livewire::actingAs($user)
            ->test(ReportsIndex::class)
            ->assertSee('مركز التقارير')
            ->assertSee('مستودع المستندات')
            ->call('openReport', $report->id)
            ->assertSee('طباعة');
    }
}
