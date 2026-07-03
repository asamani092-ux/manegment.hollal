<?php

namespace App\Livewire\Projects;

use App\Livewire\Concerns\UsesDsPagination;
use App\Models\Partnership;
use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Projects & Partnerships — full CRUD, team sync, pagination.
 * Time: O(n) per page | Space: O(n).
 */
class ProjectsIndex extends Component
{
    use AuthorizesRequests;
    use UsesDsPagination;
    use WithPagination;

    public string $projectSearch = '';

    public string $partnershipSearch = '';

    public bool $showProjectModal = false;

    public bool $showPartnershipModal = false;

    public bool $projectViewOnly = false;

    public bool $partnershipViewOnly = false;

    public ?int $projectId = null;

    public ?int $partnershipId = null;

    public string $name = '';

    public ?int $manager_id = null;

    public ?string $start_date = null;

    public ?string $end_date = null;

    public ?string $budget = null;

    public string $status = 'active';

    public string $idea_goal = '';

    public string $target_audience = '';

    public string $required_outputs = '';

    public string $final_outputs = '';

    public string $current_phase = '';

    /** @var array<int> */
    public array $teamUserIds = [];

    public string $entity_name = '';

    public string $contact_person = '';

    public string $contact_phone = '';

    public ?int $partnership_project_id = null;

    public string $type_quantity = '';

    public string $halal_commitments = '';

    public string $partner_commitments = '';

    public ?string $pricing_amount = null;

    public string $contract_pdf = '';

    public string $partnership_status = 'pending_form';

    protected $queryString = [
        'projectSearch' => ['except' => ''],
        'partnershipSearch' => ['except' => ''],
    ];

    public function mount(): void
    {
        $this->authorize('projects.view');
    }

    public function updatingProjectSearch(): void
    {
        $this->resetPage('projectsPage');
    }

    public function updatingPartnershipSearch(): void
    {
        $this->resetPage('partnershipsPage');
    }

    public function openProjectCreate(): void
    {
        $this->authorize('projects.create');
        $this->resetProjectForm();
        $this->showProjectModal = true;
    }

    public function openProjectEdit(int $id): void
    {
        $this->authorize('projects.update');
        $this->fillProjectForm(Project::with('team:id')->findOrFail($id));
        $this->projectViewOnly = false;
        $this->showProjectModal = true;
    }

    public function openProjectView(int $id): void
    {
        $this->authorize('projects.view');
        $this->fillProjectForm(Project::with('team:id')->findOrFail($id));
        $this->projectViewOnly = true;
        $this->showProjectModal = true;
    }

    public function saveProject(): void
    {
        if ($this->projectId) {
            $this->authorize('projects.update');
        } else {
            $this->authorize('projects.create');
        }

        $this->validate($this->projectRules());

        $isEdit = (bool) $this->projectId;
        $data = $this->projectPayload();
        $project = Project::updateOrCreate(['id' => $this->projectId], $data);

        if (auth()->user()->can('projects.update')) {
            $project->team()->sync($this->teamUserIds);
        }

        $this->closeProjectModal();
        $this->dispatch('toast', type: 'success', message: $isEdit ? 'تم تحديث المشروع' : 'تم إنشاء المشروع');
    }

    public function syncProjectTeam(): void
    {
        $this->authorize('projects.update');
        $project = Project::findOrFail($this->projectId);
        $project->team()->sync($this->teamUserIds);
        $this->dispatch('toast', type: 'success', message: 'تم تحديث فريق المشروع');
    }

    public function deleteProject(int $id): void
    {
        $this->authorize('projects.delete');
        Project::findOrFail($id)->delete();
        $this->dispatch('toast', type: 'success', message: 'تم حذف المشروع');
    }

    public function openPartnershipCreate(): void
    {
        $this->authorize('partnerships.create');
        $this->resetPartnershipForm();
        $this->showPartnershipModal = true;
    }

    public function openPartnershipEdit(int $id): void
    {
        $this->authorize('partnerships.update');
        $this->fillPartnershipForm(Partnership::findOrFail($id));
        $this->partnershipViewOnly = false;
        $this->showPartnershipModal = true;
    }

    public function openPartnershipView(int $id): void
    {
        $this->authorize('partnerships.view');
        $this->fillPartnershipForm(Partnership::findOrFail($id));
        $this->partnershipViewOnly = true;
        $this->showPartnershipModal = true;
    }

    public function savePartnership(): void
    {
        if ($this->partnershipId) {
            $this->authorize('partnerships.update');
        } else {
            $this->authorize('partnerships.create');
        }

        $this->validate($this->partnershipRules());

        $data = [
            'entity_name' => $this->entity_name,
            'contact_person' => $this->contact_person ?: null,
            'contact_phone' => $this->contact_phone ?: null,
            'project_id' => $this->partnership_project_id,
            'type_quantity' => $this->type_quantity ?: null,
            'halal_commitments' => $this->halal_commitments ?: null,
            'partner_commitments' => $this->partner_commitments ?: null,
            'pricing_amount' => $this->pricing_amount,
            'contract_pdf' => $this->contract_pdf ?: null,
            'status' => $this->partnership_status,
        ];

        if (! $this->partnershipId) {
            $data['magic_link_token'] = Str::uuid()->toString();
            $data['token_expires_at'] = now()->addHours(24);
        }

        $isEdit = (bool) $this->partnershipId;

        Partnership::updateOrCreate(['id' => $this->partnershipId], $data);

        $this->closePartnershipModal();
        $this->dispatch('toast', type: 'success', message: $isEdit ? 'تم تحديث الشراكة' : 'تم إنشاء الشراكة');
    }

    public function deletePartnership(int $id): void
    {
        $this->authorize('partnerships.delete');
        Partnership::findOrFail($id)->delete();
        $this->dispatch('toast', type: 'success', message: 'تم حذف الشراكة');
    }

    public function closeProjectModal(): void
    {
        $this->showProjectModal = false;
        $this->resetProjectForm();
    }

    public function closePartnershipModal(): void
    {
        $this->showPartnershipModal = false;
        $this->resetPartnershipForm();
    }

    /** @return array<string, mixed> */
    protected function projectRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'manager_id' => 'nullable|exists:users,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'budget' => 'nullable|numeric|min:0',
            'status' => 'required|in:active,completed,on_hold',
            'idea_goal' => 'nullable|string',
            'target_audience' => 'nullable|string|max:255',
            'required_outputs' => 'nullable|string',
            'final_outputs' => 'nullable|string',
            'current_phase' => 'nullable|string|max:255',
            'teamUserIds' => 'array',
            'teamUserIds.*' => 'integer|exists:users,id',
        ];
    }

    /** @return array<string, mixed> */
    protected function projectPayload(): array
    {
        return [
            'name' => $this->name,
            'manager_id' => $this->manager_id,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'budget' => $this->budget,
            'status' => $this->status,
            'idea_goal' => $this->idea_goal ?: null,
            'target_audience' => $this->target_audience ?: null,
            'required_outputs' => $this->required_outputs ?: null,
            'final_outputs' => $this->final_outputs ?: null,
            'current_phase' => $this->current_phase ?: null,
        ];
    }

    /** @return array<string, mixed> */
    protected function partnershipRules(): array
    {
        return [
            'entity_name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'partnership_project_id' => 'nullable|exists:projects,id',
            'type_quantity' => 'nullable|string|max:255',
            'halal_commitments' => 'nullable|string',
            'partner_commitments' => 'nullable|string',
            'pricing_amount' => 'nullable|numeric|min:0',
            'contract_pdf' => 'nullable|string|max:500',
            'partnership_status' => 'required|in:pending_form,negotiation,active,completed',
        ];
    }

    protected function fillProjectForm(Project $project): void
    {
        $this->projectId = $project->id;
        $this->name = $project->name;
        $this->manager_id = $project->manager_id;
        $this->start_date = $project->start_date?->format('Y-m-d');
        $this->end_date = $project->end_date?->format('Y-m-d');
        $this->budget = $project->budget !== null ? (string) $project->budget : null;
        $this->status = $project->status;
        $this->idea_goal = $project->idea_goal ?? '';
        $this->target_audience = $project->target_audience ?? '';
        $this->required_outputs = $project->required_outputs ?? '';
        $this->final_outputs = $project->final_outputs ?? '';
        $this->current_phase = $project->current_phase ?? '';
        $this->teamUserIds = $project->team->pluck('id')->all();
    }

    protected function fillPartnershipForm(Partnership $partnership): void
    {
        $this->partnershipId = $partnership->id;
        $this->entity_name = $partnership->entity_name;
        $this->contact_person = $partnership->contact_person ?? '';
        $this->contact_phone = $partnership->contact_phone ?? '';
        $this->partnership_project_id = $partnership->project_id;
        $this->type_quantity = $partnership->type_quantity ?? '';
        $this->halal_commitments = $partnership->halal_commitments ?? '';
        $this->partner_commitments = $partnership->partner_commitments ?? '';
        $this->pricing_amount = $partnership->pricing_amount !== null ? (string) $partnership->pricing_amount : null;
        $this->contract_pdf = $partnership->contract_pdf ?? '';
        $this->partnership_status = $partnership->status;
    }

    protected function resetProjectForm(): void
    {
        $this->projectId = null;
        $this->projectViewOnly = false;
        $this->name = '';
        $this->manager_id = null;
        $this->start_date = null;
        $this->end_date = null;
        $this->budget = null;
        $this->status = 'active';
        $this->idea_goal = '';
        $this->target_audience = '';
        $this->required_outputs = '';
        $this->final_outputs = '';
        $this->current_phase = '';
        $this->teamUserIds = [];
        $this->resetValidation();
    }

    protected function resetPartnershipForm(): void
    {
        $this->partnershipId = null;
        $this->partnershipViewOnly = false;
        $this->entity_name = '';
        $this->contact_person = '';
        $this->contact_phone = '';
        $this->partnership_project_id = null;
        $this->type_quantity = '';
        $this->halal_commitments = '';
        $this->partner_commitments = '';
        $this->pricing_amount = null;
        $this->contract_pdf = '';
        $this->partnership_status = 'pending_form';
        $this->resetValidation();
    }

    public function render(): View
    {
        $projects = Project::query()
            ->select(['id', 'name', 'manager_id', 'status', 'start_date', 'end_date', 'budget', 'current_phase', 'target_audience'])
            ->with(['manager:id,name'])
            ->when($this->projectSearch, fn ($q) => $q->where('name', 'like', '%'.$this->projectSearch.'%'))
            ->latest()
            ->paginate(8, pageName: 'projectsPage');

        $partnerships = Partnership::query()
            ->select(['id', 'entity_name', 'contact_person', 'status', 'project_id', 'magic_link_token', 'token_expires_at', 'pricing_amount'])
            ->with(['project:id,name'])
            ->when($this->partnershipSearch, fn ($q) => $q->where('entity_name', 'like', '%'.$this->partnershipSearch.'%'))
            ->latest()
            ->paginate(8, pageName: 'partnershipsPage');

        return view('livewire.projects.projects-index', [
            'projects' => $projects,
            'partnerships' => $partnerships,
            'managers' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'allProjects' => Project::orderBy('name')->get(['id', 'name']),
            'allUsers' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ])->layout('layouts.app', ['title' => 'المشاريع']);
    }
}
