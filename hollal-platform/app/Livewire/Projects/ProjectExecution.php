<?php

namespace App\Livewire\Projects;

use App\Models\BeneficiaryGroup;
use App\Models\Consultation;
use App\Models\MeasurementForm;
use App\Models\MeasurementResponse;
use App\Models\Project;
use App\Models\ProjectVisit;
use App\Models\User;
use App\Services\MeasurementService;
use App\Services\ProjectClosureService;
use App\Services\ProjectProgressService;
use App\Services\VisitService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/**
 * 06B-B2..06B-B5 — the project execution workspace: plan tree, weighted
 * progress, team (حلل + الجهة), visits & consultations, measurement, closure.
 */
class ProjectExecution extends Component
{
    use AuthorizesRequests;

    public Project $project;

    public string $tab = 'plan'; // plan|visits|measurement|closure

    // visits
    public ?string $visitDate = null;

    public ?string $visitPurpose = null;

    public ?int $reportingVisitId = null;

    public ?string $visitNotes = null;

    public ?string $visitPositives = null;

    public ?string $visitChallenges = null;

    public string $visitRecommendations = '';

    // consultations
    public ?string $consultationSubject = null;

    public ?string $consultationRequest = null;

    // measurement
    public ?int $formId = null;

    public string $phase = MeasurementResponse::PHASE_PRE;

    /** @var array<int|string, string> */
    public array $answers = [];

    public ?string $groupName = null;

    public ?string $groupSize = null;

    // closure
    public ?string $lessonLearned = null;

    public function mount(Project $project): void
    {
        $this->authorize('view', $project);
        $this->project = $project;
        $this->visitDate = now()->addWeek()->toDateString();
        $this->lessonLearned = $project->lesson_learned;
    }

    // ------------------------------------------------------------- 06B-B3

    public function scheduleVisit(): void
    {
        $this->authorize('projects.visits.manage');

        $this->validate([
            'visitDate' => 'required|date',
            'visitPurpose' => 'nullable|string|max:255',
        ], [], ['visitDate' => 'تاريخ الزيارة']);

        app(VisitService::class)->schedule($this->project, $this->visitDate, auth()->user(), $this->visitPurpose);

        $this->visitPurpose = null;
        $this->dispatch('ds-toast', message: 'تمت جدولة الزيارة');
    }

    public function submitVisitReport(int $visitId): void
    {
        $this->authorize('projects.visits.manage');

        $recommendations = collect(explode("\n", (string) $this->visitRecommendations))
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->values()
            ->all();

        app(VisitService::class)->report(
            $this->visit($visitId),
            $this->visitNotes,
            $this->visitPositives,
            $this->visitChallenges,
            $recommendations,
        );

        $this->visitNotes = $this->visitPositives = $this->visitChallenges = null;
        $this->visitRecommendations = '';
        $this->dispatch('ds-toast', message: 'تم رفع تقرير الزيارة');
    }

    public function createCorrectiveTask(int $visitId, int $index): void
    {
        $this->authorize('projects.visits.manage');

        $visit = $this->visit($visitId);
        $recommendation = ($visit->recommendations ?? [])[$index] ?? null;

        abort_if($recommendation === null, 404);

        app(VisitService::class)->createCorrectiveTask($visit, $recommendation, null, auth()->user());

        $this->dispatch('ds-toast', message: 'أُنشئت المهمة التصحيحية');
    }

    public function openConsultation(): void
    {
        $this->authorize('projects.visits.manage');

        $this->validate([
            'consultationSubject' => 'required|string|max:255',
            'consultationRequest' => 'nullable|string',
        ], [], ['consultationSubject' => 'موضوع الاستشارة']);

        app(VisitService::class)->openConsultation($this->project, $this->consultationSubject, $this->consultationRequest);

        $this->consultationSubject = $this->consultationRequest = null;
        $this->dispatch('ds-toast', message: 'تم فتح الاستشارة');
    }

    // ------------------------------------------------------------- 06B-B4

    public function addBeneficiaryGroup(): void
    {
        $this->authorize('projects.measurement.manage');

        $this->validate([
            'groupName' => 'required|string|max:255',
            'groupSize' => 'required|integer|min:1',
        ], [], ['groupName' => 'اسم المجموعة', 'groupSize' => 'العدد']);

        BeneficiaryGroup::create([
            'project_id' => $this->project->id,
            'name' => $this->groupName,
            'size' => (int) $this->groupSize,
        ]);

        $this->groupName = null;
        $this->groupSize = null;
        $this->dispatch('ds-toast', message: 'أُضيفت مجموعة المستفيدين');
    }

    public function saveMeasurement(): void
    {
        $this->authorize('projects.measurement.manage');

        $this->validate([
            'formId' => 'required|exists:measurement_forms,id',
            'phase' => 'required|in:'.MeasurementResponse::PHASE_PRE.','.MeasurementResponse::PHASE_POST,
            'answers' => 'required|array|min:1',
            'answers.*' => 'nullable|numeric|min:0',
        ], [], ['formId' => 'النموذج']);

        app(MeasurementService::class)->recordResponse(
            $this->project,
            MeasurementForm::findOrFail($this->formId),
            $this->phase,
            array_filter($this->answers, fn ($value) => $value !== '' && $value !== null),
        );

        $this->answers = [];
        $this->dispatch('ds-toast', message: 'تم تسجيل القياس');
    }

    // ------------------------------------------------------------- 06B-B5

    public function saveLesson(): void
    {
        $this->authorize('projects.close');

        $this->validate(['lessonLearned' => 'required|string|max:2000'], [], ['lessonLearned' => 'الدرس المستفاد']);

        app(ProjectClosureService::class)->recordLesson($this->project, $this->lessonLearned);
        $this->project->refresh();

        $this->dispatch('ds-toast', message: 'سُجل الدرس المستفاد');
    }

    public function generateFinalReport(): void
    {
        $this->authorize('projects.close');
        app(ProjectClosureService::class)->generateFinalReport($this->project);
        $this->project->refresh();

        $this->dispatch('ds-toast', message: 'تم توليد التقرير الختامي');
    }

    public function approveFinalReport(): void
    {
        $this->authorize('projects.close');

        try {
            app(ProjectClosureService::class)->approveFinalReport($this->project);
            $this->project->refresh();
            $this->dispatch('ds-toast', message: 'اعتُمد التقرير الختامي');
        } catch (\RuntimeException $e) {
            $this->addError('closure', $e->getMessage());
        }
    }

    public function markDelivered(): void
    {
        $this->authorize('projects.close');

        try {
            app(ProjectClosureService::class)->markDelivered($this->project);
            $this->project->refresh();
            $this->dispatch('ds-toast', message: 'سُلّم التقرير للجهة عبر رابطها');
        } catch (\RuntimeException $e) {
            $this->addError('closure', $e->getMessage());
        }
    }

    public function closeProject(): void
    {
        $this->authorize('projects.close');

        try {
            app(ProjectClosureService::class)->close($this->project, auth()->user());
            $this->project->refresh();
            $this->dispatch('ds-toast', message: 'أُغلق المشروع وفُتحت فرصة التجديد');
        } catch (\RuntimeException $e) {
            $this->addError('closure', $e->getMessage());
        }
    }

    public function render(): View
    {
        $progress = app(ProjectProgressService::class);

        return view('livewire.projects.project-execution', [
            'project' => $this->project->load(['partnership.organization', 'program', 'team', 'entityMembers']),
            'planTree' => $progress->planTree($this->project),
            'summary' => $progress->summary($this->project),
            'visits' => $this->project->visits()->orderByDesc('scheduled_on')->get(),
            'consultations' => $this->project->consultations()->orderByDesc('id')->get(),
            'quotas' => app(VisitService::class)->quotas($this->project),
            'forms' => MeasurementForm::orderBy('title')->get(),
            'selectedForm' => $this->formId ? MeasurementForm::with('questions')->find($this->formId) : null,
            'groups' => $this->project->beneficiaryGroups()->get(),
            'results' => app(MeasurementService::class)->results($this->project),
            'checklist' => app(ProjectClosureService::class)->checklist($this->project),
            'specialists' => User::orderBy('name')->get(['id', 'name']),
        ])->layout('layouts.app', ['title' => $this->project->name]);
    }

    private function visit(int $id): ProjectVisit
    {
        return ProjectVisit::where('project_id', $this->project->id)->findOrFail($id);
    }
}
