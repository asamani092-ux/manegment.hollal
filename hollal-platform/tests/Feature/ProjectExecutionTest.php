<?php

namespace Tests\Feature;

use App\Livewire\Projects\ProjectExecution;
use App\Models\BeneficiaryGroup;
use App\Models\Consultation;
use App\Models\MeasurementForm;
use App\Models\MeasurementQuestion;
use App\Models\MeasurementResponse;
use App\Models\Organization;
use App\Models\OrganizationImpactRecord;
use App\Models\Partnership;
use App\Models\PlanTemplate;
use App\Models\Program;
use App\Models\ProgramPrice;
use App\Models\Project;
use App\Models\ProjectGenerationRequest;
use App\Models\ProjectVisit;
use App\Models\Quote;
use App\Models\Task;
use App\Models\TemplateItem;
use App\Models\User;
use App\Services\MeasurementService;
use App\Services\PlanTemplateService;
use App\Services\ProjectClosureService;
use App\Services\ProjectGenerationService;
use App\Services\ProjectProgressService;
use App\Services\VisitService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * 06B-B1..06B-B5 — generation engine, project page, visits, measurement,
 * closure and renewal.
 */
class ProjectExecutionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed(PermissionSeeder::class);
    }

    private function manager(): User
    {
        $user = User::factory()->create(['must_change_password' => false]);
        $user->givePermissionTo([
            'projects.view', 'projects.update', 'projects.close',
            'projects.visits.view', 'projects.visits.manage',
            'projects.measurement.view', 'projects.measurement.manage',
        ]);

        return $user;
    }

    /** Two reviewed templates with a small, deterministic structure. */
    private function templates(): void
    {
        $service = app(PlanTemplateService::class);
        $reviewer = User::factory()->create();

        $hollal = $service->create('خطة حلل', PlanTemplate::KIND_HOLLAL);
        $version = $hollal->currentVersion;
        $phase = $service->addItem($version, [
            'title' => 'التهيئة',
            'role' => 'مدير مشروع حلل',
            'start_offset_days' => 0,
            'duration_days' => 3,
            'evidence_required' => 'محضر',
        ]);
        $service->addItem($version, [
            'title' => 'إجراء إلزامي',
            'role' => 'مشرف علمي',
            'start_offset_days' => 2,
            'duration_days' => 2,
        ], $phase);
        $service->addItem($version, [
            'title' => 'تنفيذ زيارة ميدانية',
            'role' => 'مشرف علمي',
            'item_kind' => TemplateItem::KIND_SERVICE,
            'service_type' => 'زيارة',
        ], $phase);
        $service->markReviewed($hollal, $reviewer);

        $entity = $service->create('خطة الجهة', PlanTemplate::KIND_ENTITY);
        $service->addItem($entity->currentVersion, [
            'title' => 'تهيئة القاعات',
            'role' => 'مدير جهة',
            'start_offset_days' => 1,
            'duration_days' => 1,
        ]);
        $service->markReviewed($entity, $reviewer);
    }

    private function generationRequest(array $services = ['تدريب']): ProjectGenerationRequest
    {
        $organization = Organization::create(['name' => 'جمعية النور']);
        $partnership = Partnership::create([
            'organization_id' => $organization->id,
            'entity_name' => 'جمعية النور',
            'stage' => Partnership::STAGE_CONTRACTED,
            'stage_entered_at' => now(),
        ]);
        $program = Program::create(['name' => 'برنامج القراءة', 'stage' => Program::STAGE_ACTIVE]);

        return ProjectGenerationRequest::create([
            'partnership_id' => $partnership->id,
            'program_id' => $program->id,
            'included_services' => $services,
            'launch_date' => now()->toDateString(),
            'status' => ProjectGenerationRequest::STATUS_PENDING,
        ]);
    }

    // ------------------------------------------------------------ 06B-B1

    public function test_generation_copies_mandatory_items_and_respects_hierarchy(): void
    {
        $this->templates();
        $project = app(ProjectGenerationService::class)->generate($this->generationRequest());

        $tasks = $project->tasks()->get();

        $this->assertSame(3, $tasks->count()); // 2 hollal mandatory + 1 entity
        $this->assertTrue($tasks->contains('title', 'التهيئة'));
        $this->assertFalse($tasks->contains('title', 'تنفيذ زيارة ميدانية')); // unsold service
        $root = $tasks->firstWhere('title', 'التهيئة');
        $child = $tasks->firstWhere('title', 'إجراء إلزامي');
        $this->assertSame($root->id, $child->parent_task_id);
    }

    public function test_service_items_are_generated_when_the_service_was_sold(): void
    {
        $this->templates();
        $project = app(ProjectGenerationService::class)->generate($this->generationRequest(['زيارة']));

        $this->assertTrue($project->tasks()->where('title', 'تنفيذ زيارة ميدانية')->exists());
    }

    public function test_generation_computes_dates_from_the_launch_offset(): void
    {
        $this->templates();
        $request = $this->generationRequest();
        $project = app(ProjectGenerationService::class)->generate($request);

        $task = $project->tasks()->where('title', 'إجراء إلزامي')->firstOrFail();

        // offset 2 + duration 2 from the launch date
        $this->assertSame(
            $request->launch_date->copy()->addDays(4)->toDateString(),
            $task->due_date->toDateString(),
        );
    }

    public function test_generation_does_not_mutate_the_template(): void
    {
        $this->templates();
        $version = PlanTemplate::where('kind', PlanTemplate::KIND_HOLLAL)->firstOrFail()->currentVersion;
        $before = $version->items()->get()->map->only(['id', 'title', 'parent_id', 'level'])->toArray();

        $project = app(ProjectGenerationService::class)->generate($this->generationRequest());

        $this->assertSame($before, $version->fresh()->items()->get()->map->only(['id', 'title', 'parent_id', 'level'])->toArray());

        // editing the project copy leaves the template alone
        $task = $project->tasks()->firstOrFail();
        $task->update(['title' => 'عنوان معدّل في المشروع']);
        $this->assertDatabaseHas('template_items', ['id' => $task->template_item_id, 'title' => 'التهيئة']);
    }

    public function test_generation_is_blocked_while_a_template_awaits_review(): void
    {
        $service = app(PlanTemplateService::class);
        $service->create('خطة حلل', PlanTemplate::KIND_HOLLAL);
        $service->create('خطة الجهة', PlanTemplate::KIND_ENTITY);

        $this->expectException(\RuntimeException::class);
        app(ProjectGenerationService::class)->generate($this->generationRequest());
    }

    public function test_entity_role_tasks_are_flagged_for_the_portal_and_have_no_assignee(): void
    {
        $this->templates();
        $project = app(ProjectGenerationService::class)->generate($this->generationRequest());

        $entityTask = $project->tasks()->where('title', 'تهيئة القاعات')->firstOrFail();

        $this->assertTrue((bool) $entityTask->entity_visible);
        $this->assertNull($entityTask->assigned_to);
        $this->assertSame('مدير جهة', $entityTask->role_label);
        $this->assertSame(0, $project->tasks()->where('entity_visible', false)->where('title', 'تهيئة القاعات')->count());
    }

    public function test_console_command_consumes_pending_requests(): void
    {
        $this->templates();
        $request = $this->generationRequest();

        $this->artisan('projects:generate-pending')
            ->expectsOutputToContain('1 project(s) generated.')
            ->assertSuccessful();

        $this->assertSame(ProjectGenerationRequest::STATUS_GENERATED, $request->fresh()->status);
        $this->assertNotNull($request->fresh()->project_id);
    }

    // ------------------------------------------------------------ 06B-B2

    public function test_weighted_progress_uses_final_ratings_only(): void
    {
        $project = Project::factory()->create();
        Task::factory()->create(['project_id' => $project->id, 'final_rating' => 'متميز']);
        Task::factory()->create(['project_id' => $project->id, 'final_rating' => 'مقبول']);
        Task::factory()->create(['project_id' => $project->id, 'self_rating' => 'متميز', 'pm_rating' => 'متميز']);
        Task::factory()->create(['project_id' => $project->id]);

        $summary = app(ProjectProgressService::class)->summary($project);

        // (1.0 + 0.5) / 4 tasks
        $this->assertSame(37.5, $summary['weighted_percent']);
        $this->assertSame(2, $summary['evaluated']);
        $this->assertSame(4, $summary['total']);
    }

    public function test_plan_tree_is_built_from_parent_task_id(): void
    {
        $this->templates();
        $project = app(ProjectGenerationService::class)->generate($this->generationRequest());

        $tree = app(ProjectProgressService::class)->planTree($project);

        $this->assertCount(2, $tree); // hollal root + entity root
        $this->assertCount(1, $tree->firstWhere('title', 'التهيئة')->children);
    }

    public function test_execution_screen_renders_the_plan_and_the_team(): void
    {
        $this->templates();
        $project = app(ProjectGenerationService::class)->generate($this->generationRequest());
        $project->entityMembers()->create(['name' => 'أ. سعيد', 'role_label' => 'مدير جهة']);

        Livewire::actingAs($this->manager())->test(ProjectExecution::class, ['project' => $project])
            ->assertOk()
            ->assertSee('التهيئة')
            ->assertSee('أ. سعيد');
    }

    // ------------------------------------------------------------ 06B-B3

    public function test_visit_quota_counts_only_completed_visits(): void
    {
        $project = $this->contractedProject();
        $visits = app(VisitService::class);

        $scheduled = $visits->schedule($project, now()->toDateString());
        $this->assertSame(0, $visits->quotas($project)['زيارة']['consumed']);

        $visits->report($scheduled, 'ملاحظات', null, null);

        $quota = $visits->quotas($project)['زيارة'];
        $this->assertSame(2, $quota['contracted']);
        $this->assertSame(1, $quota['consumed']);
        $this->assertSame(1, $quota['remaining']);
    }

    public function test_recommendation_becomes_a_corrective_task_linked_to_the_visit(): void
    {
        $project = Project::factory()->create();
        $visits = app(VisitService::class);
        $visit = $visits->schedule($project, now()->toDateString());
        $visits->report($visit, null, null, null, ['تصحيح جدول الحصص']);

        $task = $visits->createCorrectiveTask($visit->fresh(), 'تصحيح جدول الحصص');

        $this->assertSame($project->id, $task->project_id);
        $this->assertStringContainsString('مهمة تصحيحية', $task->title);
        $this->assertStringContainsString((string) $visit->id, $task->description);
    }

    public function test_consultation_lifecycle_and_quota(): void
    {
        $project = $this->contractedProject();
        $visits = app(VisitService::class);
        $specialist = $this->manager();

        $consultation = $visits->openConsultation($project, 'استفسار منهجي');
        $visits->assignConsultation($consultation, $specialist);
        $this->assertSame(Consultation::STATUS_ASSIGNED, $consultation->fresh()->status);

        $visits->closeConsultation($consultation, 'تم الرد');
        $this->assertSame(Consultation::STATUS_CLOSED, $consultation->fresh()->status);
        $this->assertSame(1, $visits->quotas($project)['استشارة']['consumed']);
    }

    public function test_visit_screen_creates_a_corrective_task(): void
    {
        $project = Project::factory()->create();
        $visit = app(VisitService::class)->schedule($project, now()->toDateString());
        app(VisitService::class)->report($visit, null, null, null, ['رفع الشواهد']);

        Livewire::actingAs($this->manager())->test(ProjectExecution::class, ['project' => $project])
            ->call('createCorrectiveTask', $visit->id, 0);

        $this->assertTrue(Task::where('project_id', $project->id)->where('role_label', 'مهمة تصحيحية')->exists());
    }

    // ------------------------------------------------------------ 06B-B4

    public function test_results_compute_from_the_recorded_answers(): void
    {
        $project = Project::factory()->create();
        [$form, $questions] = $this->form();

        $service = app(MeasurementService::class);
        $service->recordResponse($project, $form, MeasurementResponse::PHASE_PRE, [
            $questions[0]->id => 4, $questions[1]->id => 6,
        ]);
        $service->recordResponse($project, $form, MeasurementResponse::PHASE_POST, [
            $questions[0]->id => 9, $questions[1]->id => 9,
        ]);

        $results = $service->results($project);

        $this->assertSame(50.0, $results['pre_percent']);
        $this->assertSame(90.0, $results['post_percent']);
        $this->assertSame(40.0, $results['improvement_percent']);
    }

    public function test_scores_are_capped_by_the_question_maximum(): void
    {
        $project = Project::factory()->create();
        [$form, $questions] = $this->form();

        $response = app(MeasurementService::class)->recordResponse(
            $project, $form, MeasurementResponse::PHASE_PRE, [$questions[0]->id => 999]
        );

        $this->assertSame('10.00', (string) $response->total_score);
    }

    public function test_a_foreign_question_is_rejected(): void
    {
        $project = Project::factory()->create();
        [$form] = $this->form();

        $this->expectException(\InvalidArgumentException::class);
        app(MeasurementService::class)->recordResponse($project, $form, MeasurementResponse::PHASE_PRE, [9999 => 5]);
    }

    public function test_impact_ascends_to_the_organization_record(): void
    {
        $project = $this->contractedProject();
        [$form, $questions] = $this->form();
        BeneficiaryGroup::create(['project_id' => $project->id, 'name' => 'المجموعة أ', 'size' => 60]);

        $service = app(MeasurementService::class);
        $service->recordResponse($project, $form, MeasurementResponse::PHASE_PRE, [$questions[0]->id => 5, $questions[1]->id => 5]);
        $service->recordResponse($project, $form, MeasurementResponse::PHASE_POST, [$questions[0]->id => 8, $questions[1]->id => 8]);

        $record = $service->ascendImpact($project);

        $this->assertNotNull($record);
        $this->assertSame(60, $record->beneficiaries);
        $this->assertSame('30.00', (string) $record->improvement_percent);
        $this->assertSame(1, OrganizationImpactRecord::where('project_id', $project->id)->count());

        // idempotent
        $service->ascendImpact($project);
        $this->assertSame(1, OrganizationImpactRecord::where('project_id', $project->id)->count());
    }

    // ------------------------------------------------------------ 06B-B5

    public function test_project_cannot_close_with_open_critical_items(): void
    {
        $project = Project::factory()->create();
        Task::factory()->create(['project_id' => $project->id, 'status' => 'pending']);

        $blockers = app(ProjectClosureService::class)->blockers($project);
        $this->assertNotEmpty($blockers);

        $this->expectException(\RuntimeException::class);
        app(ProjectClosureService::class)->close($project, $this->manager());
    }

    public function test_final_report_must_be_approved_before_delivery(): void
    {
        $project = Project::factory()->create();
        $closure = app(ProjectClosureService::class);

        $this->expectException(\RuntimeException::class);
        $closure->markDelivered($project);
    }

    public function test_closure_flow_generates_approves_delivers_and_opens_renewal(): void
    {
        $project = $this->readyToCloseProject();
        $closure = app(ProjectClosureService::class);
        $actor = $this->manager();

        $closure->recordLesson($project, 'الالتزام بالمواعيد رفع الحضور');
        $closure->generateFinalReport($project->fresh());
        $closure->approveFinalReport($project->fresh());
        $closure->markDelivered($project->fresh());

        Storage::disk('local')->assertExists($project->fresh()->final_report_path);
        $this->assertSame([], $closure->blockers($project->fresh()));

        $closed = $closure->close($project->fresh(), $actor);

        $this->assertNotNull($closed->closed_at);
        $this->assertSame('مغلق', $closed->status);
        $this->assertTrue(
            Partnership::where('renewed_from_id', $project->partnership_id)->exists(),
            'a renewal opportunity should be opened on closure'
        );
        $this->assertTrue(OrganizationImpactRecord::where('project_id', $project->id)->exists());
    }

    // ------------------------------------------------------------ helpers

    /** @return array{0: MeasurementForm, 1: array<int, MeasurementQuestion>} */
    private function form(string $kind = MeasurementForm::KIND_TEST): array
    {
        $form = MeasurementForm::create(['title' => 'اختبار قبلي/بعدي', 'kind' => $kind]);
        $questions = [
            MeasurementQuestion::create(['measurement_form_id' => $form->id, 'text' => 'س1', 'max_score' => 10, 'position' => 1]),
            MeasurementQuestion::create(['measurement_form_id' => $form->id, 'text' => 'س2', 'max_score' => 10, 'position' => 2]),
        ];

        return [$form->fresh(), $questions];
    }

    /** A project reached through a partnership whose contract bought 2 visits + 1 consultation. */
    private function contractedProject(): Project
    {
        $organization = Organization::create(['name' => 'مدارس الأمل']);
        $partnership = Partnership::create([
            'organization_id' => $organization->id,
            'entity_name' => 'مدارس الأمل',
            'stage' => Partnership::STAGE_EXECUTION,
            'stage_entered_at' => now(),
        ]);
        $project = Project::factory()->create(['partnership_id' => $partnership->id]);
        $partnership->forceFill(['project_id' => $project->id])->save();

        $quote = Quote::create([
            'partnership_id' => $partnership->id,
            'version' => 1,
            'status' => Quote::STATUS_ACCEPTED,
        ]);
        $quote->items()->create([
            'service_type' => ProgramPrice::SERVICE_VISIT,
            'description' => 'زيارات',
            'quantity' => 2,
            'unit_price' => 500,
            'line_total' => 1000,
        ]);
        $quote->items()->create([
            'service_type' => ProgramPrice::SERVICE_CONSULTATION,
            'description' => 'استشارات',
            'quantity' => 1,
            'unit_price' => 300,
            'line_total' => 300,
        ]);

        $partnership->partnershipContracts()->create([
            'quote_id' => $quote->id,
            'status' => 'مؤكد',
            'total_value' => 1300,
        ]);

        return $project->fresh();
    }

    private function readyToCloseProject(): Project
    {
        $project = $this->contractedProject();
        [$form, $questions] = $this->form();

        app(MeasurementService::class)->recordResponse(
            $project, $form, MeasurementResponse::PHASE_POST, [$questions[0]->id => 8]
        );

        return $project->fresh();
    }
}
