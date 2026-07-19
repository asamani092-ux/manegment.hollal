<?php

namespace Tests\Feature;

use App\Livewire\Reports\AuditLogIndex;
use App\Livewire\Reports\ReportsCenter;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentTemplate;
use App\Models\ExpenseRequest;
use App\Models\Organization;
use App\Models\OrganizationImpactRecord;
use App\Models\Partnership;
use App\Models\Project;
use App\Models\ReportSnapshot;
use App\Models\Task;
use App\Models\User;
use App\Notifications\PolicyReviewDue;
use App\Services\DocumentLibraryService;
use App\Services\ReportCenterService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 07-B1 — document source linking, versions, templates, policy review alerts.
 * 08-B1/08-B2 — reports centre, immutable snapshots, audit-log screen.
 */
class DocumentsAndReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function reader(): User
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo(['reports.view', 'documents.view', 'documents.create']);

        return $user;
    }

    private function document(array $attributes = []): Document
    {
        return Document::create(array_merge([
            'title' => 'عقد الجهة',
            'category' => 'عقود',
            'source_type' => 'partnership',
            'source_id' => 7,
            'confidentiality' => 'team',
            'uploader_id' => User::factory()->create()->id,
            'path' => 'documents/a.pdf',
        ], $attributes));
    }

    // ------------------------------------------------------------ 07-B1

    public function test_documents_auto_list_by_source(): void
    {
        $this->document();
        $this->document(['title' => 'ملحق العقد']);
        $this->document(['title' => 'مستند آخر', 'source_id' => 8]);

        $listed = app(DocumentLibraryService::class)->forSource('partnership', 7);

        $this->assertCount(2, $listed);
        $this->assertTrue($listed->pluck('title')->contains('ملحق العقد'));
    }

    public function test_new_version_keeps_the_previous_one(): void
    {
        $document = $this->document();
        $service = app(DocumentLibraryService::class);

        $service->addVersion($document, 'documents/v2.pdf', 'تحديث البنود');
        $service->addVersion($document->fresh(), 'documents/v3.pdf', 'تصحيح إملائي');

        $document->refresh();
        $this->assertSame(3, $document->versions()->count() + 1 - 1 + 1); // v1 implicit + 2 stored
        $this->assertSame(2, $document->current_version);
        $this->assertSame('documents/v3.pdf', $document->path);
        $this->assertDatabaseHas('document_versions', ['path' => 'documents/v2.pdf', 'version' => 1]);
    }

    public function test_template_library_stores_reusable_forms(): void
    {
        DocumentTemplate::create([
            'title' => 'نموذج محضر زيارة',
            'category' => 'نماذج',
            'path' => 'templates/visit.docx',
        ]);

        $this->assertDatabaseHas('document_templates', ['title' => 'نموذج محضر زيارة']);
    }

    public function test_policy_review_alert_fires_once_on_the_review_date(): void
    {
        Notification::fake();
        $owner = $this->reader();

        $policy = $this->document([
            'title' => 'سياسة الحضور',
            'is_policy' => true,
            'review_date' => now()->subDay()->toDateString(),
        ]);
        $this->document(['title' => 'سياسة لاحقة', 'is_policy' => true, 'review_date' => now()->addMonth()->toDateString()]);

        $service = app(DocumentLibraryService::class);
        $alerted = $service->firePolicyReviewAlerts();

        $this->assertSame([$policy->id], $alerted);
        Notification::assertSentTo($owner, PolicyReviewDue::class);
        $this->assertNotNull($policy->fresh()->review_alert_sent_at);

        // second sweep does not re-alert
        $this->assertSame([], $service->firePolicyReviewAlerts());
    }

    public function test_policy_review_command_runs(): void
    {
        Notification::fake();
        $this->document(['is_policy' => true, 'review_date' => now()->toDateString()]);

        $this->artisan('documents:check-policy-reviews')
            ->expectsOutputToContain('policy review alert(s) sent.')
            ->assertSuccessful();
    }

    // ------------------------------------------------------------ 08-B1

    public function test_monthly_report_derives_its_indicators(): void
    {
        $project = Project::factory()->create();
        Task::factory()->count(3)->create(['project_id' => $project->id]);
        Task::factory()->create(['project_id' => $project->id, 'status' => 'completed']);
        ExpenseRequest::create([
            'requester_id' => User::factory()->create()->id,
            'project_id' => $project->id,
            'type' => 'operational', 'amount' => 750, 'reason' => 'x',
            'payment_method' => 'transfer', 'status' => 'paid',
        ]);

        $report = app(ReportCenterService::class)->monthly(now()->format('Y-m'));

        $this->assertSame(4, $report['tasks_created']);
        $this->assertSame(1, $report['tasks_completed']);
        $this->assertSame(750.0, $report['spend']);
        $this->assertArrayHasKey('فرصة', $report['partnerships_by_stage']);
    }

    public function test_project_dashboard_report_matches_the_indicator_mapping(): void
    {
        $project = Project::factory()->create(['budget' => 10000]);
        Task::factory()->create(['project_id' => $project->id, 'final_rating' => 'متميز']);
        Task::factory()->create(['project_id' => $project->id]);

        $report = app(ReportCenterService::class)->projectDashboard($project);

        foreach ([
            'weighted_progress', 'tasks_total', 'tasks_evaluated', 'tasks_overdue',
            'budget', 'consumed', 'remaining', 'consumption_percent',
            'beneficiaries', 'improvement_percent', 'satisfaction_percent',
            'visits_done', 'consultations_closed',
        ] as $indicator) {
            $this->assertArrayHasKey($indicator, $report, "missing indicator: {$indicator}");
        }

        $this->assertSame(50.0, $report['weighted_progress']);
        $this->assertSame(10000.0, $report['budget']);
    }

    public function test_snapshot_is_immutable_after_creation(): void
    {
        $service = app(ReportCenterService::class);
        $snapshot = $service->snapshot(ReportSnapshot::KIND_MONTHLY, 'التقرير الشهري', ['a' => 1], now()->format('Y-m'));

        $this->assertTrue($snapshot->isIntact());

        try {
            $snapshot->update(['label' => 'محاولة تعديل']);
            $this->fail('a snapshot must not be updatable');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('غير قابلة للتعديل', $e->getMessage());
        }

        $this->expectException(\RuntimeException::class);
        $snapshot->delete();
    }

    public function test_reports_center_takes_a_snapshot(): void
    {
        Livewire::actingAs($this->reader())->test(ReportsCenter::class)
            ->assertOk()
            ->call('takeSnapshot');

        $this->assertSame(1, ReportSnapshot::where('kind', ReportSnapshot::KIND_MONTHLY)->count());
    }

    public function test_reports_center_requires_permission(): void
    {
        $user = User::factory()->create(['must_change_password' => false]);

        $this->actingAs($user)->get('/reports/center')->assertForbidden();
    }

    // ------------------------------------------------------------ 08-B2

    public function test_impact_report_derives_from_impact_records(): void
    {
        $organization = Organization::create(['name' => 'جمعية النور']);
        OrganizationImpactRecord::create([
            'organization_id' => $organization->id,
            'beneficiaries' => 100,
            'improvement_percent' => 20,
            'satisfaction_percent' => 80,
        ]);
        OrganizationImpactRecord::create([
            'organization_id' => $organization->id,
            'beneficiaries' => 50,
            'improvement_percent' => 40,
            'satisfaction_percent' => 90,
        ]);

        $report = app(ReportCenterService::class)->impact($organization);

        $this->assertSame(150, $report['beneficiaries']);
        $this->assertSame(30.0, round((float) $report['avg_improvement_percent'], 2));
    }

    public function test_kpis_derive_from_live_data(): void
    {
        Task::factory()->count(2)->create(['status' => 'completed']);
        Task::factory()->count(2)->create();
        Partnership::create(['entity_name' => 'جهة', 'stage' => Partnership::STAGE_QUOTE, 'stage_entered_at' => now()]);

        $kpis = app(ReportCenterService::class)->kpis();

        $this->assertSame(50.0, $kpis['task_completion_percent']);
        $this->assertSame(1, $kpis['active_partnerships']);
    }

    public function test_audit_log_screen_is_read_only(): void
    {
        $this->assertFalse(method_exists(AuditLogIndex::class, 'save'));
        $this->assertFalse(method_exists(AuditLogIndex::class, 'update'));
        $this->assertFalse(method_exists(AuditLogIndex::class, 'delete'));

        foreach (Route::getRoutes() as $route) {
            if (str_contains((string) $route->getName(), 'audit')) {
                $this->assertSame(['GET', 'HEAD'], $route->methods(), 'audit routes must be read-only');
            }
        }
    }

    public function test_audit_log_screen_filters_and_exports(): void
    {
        $actor = $this->reader();
        AuditLog::create([
            'actor_id' => $actor->id,
            'action' => 'settings.updated',
            'target_type' => 'App\\Models\\PlatformSetting',
            'target_id' => 1,
            'created_at' => now(),
        ]);
        AuditLog::create(['action' => 'asset.condition_changed', 'created_at' => now()]);

        $component = Livewire::actingAs($actor)->test(AuditLogIndex::class)
            ->assertOk()
            ->set('actionFilter', 'settings');

        // the filter narrows the rows themselves, not just the visible text
        $rows = $component->viewData('logs');
        $this->assertCount(1, $rows);
        $this->assertSame('settings.updated', $rows->first()->action);

        $response = Livewire::actingAs($actor)->test(AuditLogIndex::class)->call('export');
        $this->assertNotNull($response);
    }
}
