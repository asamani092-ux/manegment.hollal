<?php

namespace App\Livewire\Structure;

use App\Models\OrgUnit;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Job cards list + edit.
 * Time: O(n) | Space: O(page)
 */
class JobsIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $parentFilter = '';

    public ?int $edit = null;

    public ?int $editingId = null;

    public string $editName = '';

    public string $editPurpose = '';

    public string $editResponsibilities = '';

    public string $editRequirements = '';

    public ?int $editManagerId = null;

    public ?int $editParentId = null;

    /** @var array<string, array<string, string|null>> */
    protected $queryString = [
        'search' => ['except' => ''],
        'parentFilter' => ['except' => ''],
        'edit' => ['except' => null],
    ];

    public function mount(): void
    {
        abort_unless(
            auth()->user()->can('structure.positions.manage')
            || auth()->user()->can('structure.view'),
            403
        );

        if ($this->edit) {
            $this->openEdit($this->edit);
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingParentFilter(): void
    {
        $this->resetPage();
    }

    public function openEdit(int $id): void
    {
        abort_unless(auth()->user()->can('structure.positions.manage'), 403);

        $job = OrgUnit::query()->where('level', OrgUnit::LEVEL_JOB)->findOrFail($id);
        $this->editingId = $job->id;
        $this->edit = $job->id;
        $this->editName = $job->name;
        $this->editPurpose = (string) ($job->job_purpose ?? '');
        $this->editResponsibilities = collect($job->job_responsibilities ?? [])->implode("\n");
        $this->editRequirements = collect($job->job_requirements ?? [])->implode("\n");
        $this->editManagerId = $job->manager_id;
        $this->editParentId = $job->parent_id;
    }

    public function closeEdit(): void
    {
        $this->editingId = null;
        $this->edit = null;
        $this->resetValidation();
    }

    public function saveEdit(): void
    {
        abort_unless(auth()->user()->can('structure.positions.manage'), 403);

        $this->validate([
            'editName' => 'required|string|max:255',
            'editPurpose' => 'nullable|string|max:2000',
            'editResponsibilities' => 'nullable|string|max:5000',
            'editRequirements' => 'nullable|string|max:5000',
            'editManagerId' => 'nullable|exists:users,id',
            'editParentId' => 'nullable|exists:org_units,id',
        ]);

        $job = OrgUnit::query()->where('level', OrgUnit::LEVEL_JOB)->findOrFail($this->editingId);
        $job->update([
            'name' => $this->editName,
            'job_purpose' => $this->editPurpose !== '' ? $this->editPurpose : null,
            'job_responsibilities' => collect(explode("\n", $this->editResponsibilities))
                ->map(fn ($l) => trim($l))->filter()->values()->all(),
            'job_requirements' => collect(explode("\n", $this->editRequirements))
                ->map(fn ($l) => trim($l))->filter()->values()->all(),
            'manager_id' => $this->editManagerId,
            'parent_id' => $this->editParentId,
        ]);

        $this->closeEdit();
        $this->dispatch('toast', type: 'success', message: 'تم تحديث بطاقة الوظيفة');
    }

    public function render(): View
    {
        return view('livewire.structure.jobs-index', [
            'jobs' => OrgUnit::query()
                ->select(['id', 'name', 'level', 'parent_id', 'manager_id', 'job_purpose', 'job_responsibilities', 'job_requirements'])
                ->where('level', OrgUnit::LEVEL_JOB)
                ->with(['parent:id,name', 'manager:id,name'])
                ->when($this->search, fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
                ->when($this->parentFilter, fn ($q) => $q->where('parent_id', $this->parentFilter))
                ->orderBy('name')
                ->paginate(20),
            'parentUnits' => OrgUnit::query()
                ->select(['id', 'name'])
                ->whereIn('level', [OrgUnit::LEVEL_ADMINISTRATION, OrgUnit::LEVEL_UNIT])
                ->orderBy('name')
                ->get(),
            'managers' => User::query()->select(['id', 'name'])->where('is_active', true)->orderBy('name')->get(),
            'canManage' => auth()->user()->can('structure.positions.manage'),
        ]);
    }
}
