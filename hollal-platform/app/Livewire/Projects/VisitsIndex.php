<?php

namespace App\Livewire\Projects;

use App\Models\Project;
use App\Models\ProjectVisit;
use App\Services\VisitService;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/** Cross-project visits list + quick create. Time: O(n) | Space: O(page). */
class VisitsIndex extends Component
{
    use WithPagination;

    public string $projectFilter = '';

    public string $statusFilter = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    public bool $showCreateModal = false;

    public ?int $createProjectId = null;

    public string $createDate = '';

    public ?string $createPurpose = null;

    /** @var array<string, array<string, string>> */
    protected $queryString = [
        'projectFilter' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'dateFrom' => ['except' => ''],
        'dateTo' => ['except' => ''],
    ];

    public function mount(): void
    {
        abort_unless(auth()->user()->can('projects.visits.view'), 403);
        $this->createDate = now()->addWeek()->toDateString();
    }

    public function updatingProjectFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatingDateTo(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        abort_unless(auth()->user()->can('projects.visits.manage'), 403);
        $this->createProjectId = $this->projectFilter !== '' ? (int) $this->projectFilter : null;
        $this->createDate = now()->addWeek()->toDateString();
        $this->createPurpose = null;
        $this->showCreateModal = true;
    }

    public function createVisit(): void
    {
        abort_unless(auth()->user()->can('projects.visits.manage'), 403);

        $this->validate([
            'createProjectId' => 'required|exists:projects,id',
            'createDate' => 'required|date',
            'createPurpose' => 'nullable|string|max:255',
        ], [], [
            'createProjectId' => 'المشروع',
            'createDate' => 'تاريخ الزيارة',
        ]);

        $project = Project::findOrFail($this->createProjectId);
        app(VisitService::class)->schedule(
            $project,
            $this->createDate,
            auth()->user(),
            $this->createPurpose,
        );

        $this->showCreateModal = false;
        $this->dispatch('ds-toast', message: 'تمت جدولة الزيارة');
    }

    public function render(): View
    {
        return view('livewire.projects.visits-index', [
            'visits' => ProjectVisit::query()
                ->select(['id', 'project_id', 'visitor_id', 'scheduled_on', 'purpose', 'status'])
                ->with(['project:id,name', 'visitor:id,name'])
                ->when($this->projectFilter, fn ($q) => $q->where('project_id', $this->projectFilter))
                ->when($this->statusFilter, fn ($q) => $q->where('status', $this->statusFilter))
                ->when($this->dateFrom, fn ($q) => $q->whereDate('scheduled_on', '>=', $this->dateFrom))
                ->when($this->dateTo, fn ($q) => $q->whereDate('scheduled_on', '<=', $this->dateTo))
                ->latest('scheduled_on')
                ->paginate(20),
            'projects' => Project::orderBy('name')->get(['id', 'name']),
            'statusOptions' => [
                ProjectVisit::STATUS_SCHEDULED,
                ProjectVisit::STATUS_DONE,
                ProjectVisit::STATUS_CANCELLED,
            ],
        ]);
    }
}
