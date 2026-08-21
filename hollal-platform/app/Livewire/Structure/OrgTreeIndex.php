<?php

namespace App\Livewire\Structure;

use App\Models\Committee;
use App\Models\Department;
use App\Models\OrgUnit;
use App\Models\User;
use App\Services\OrgStructureService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/**
 * 09-B1 — org tree with the visual chart, job cards, transfers and committees.
 */
class OrgTreeIndex extends Component
{
    use AuthorizesRequests;

    public string $tab = 'tree'; // tree|jobs|transfers|committees

    /** @var array<string, array<string, mixed>> */
    protected $queryString = [
        'tab' => ['except' => 'tree'],
    ];

    // unit form
    public bool $showUnitModal = false;

    public ?int $parentId = null;

    public string $unitName = '';

    public string $unitLevel = OrgUnit::LEVEL_ADMINISTRATION;

    public ?string $jobPurpose = null;

    public string $jobResponsibilities = '';

    // transfer form
    public ?int $transferUserId = null;

    public ?int $transferUnitId = null;

    public ?int $transferDepartmentId = null;

    public ?string $transferReason = null;

    // committee form
    public string $committeeName = '';

    public ?string $committeeMandate = null;

    public ?int $viewingJobId = null;

    public function mount(): void
    {
        $this->authorize('structure.departments.view');
    }

    public function openUnitModal(?int $parentId = null): void
    {
        $this->authorize('structure.departments.create');

        $this->parentId = $parentId;
        $parent = $parentId ? OrgUnit::find($parentId) : null;
        $this->unitLevel = $parent ? (OrgUnit::CHILD_LEVEL[$parent->level] ?? OrgUnit::LEVEL_JOB) : OrgUnit::LEVEL_ADMINISTRATION;
        $this->unitName = '';
        $this->jobPurpose = null;
        $this->jobResponsibilities = '';
        $this->showUnitModal = true;
    }

    public function saveUnit(): void
    {
        $this->authorize('structure.departments.create');

        $this->validate([
            'unitName' => 'required|string|max:255',
            'unitLevel' => 'required|in:'.implode(',', array_keys(OrgUnit::CHILD_LEVEL)),
            'parentId' => 'nullable|exists:org_units,id',
        ], [], ['unitName' => 'اسم الوحدة']);

        try {
            app(OrgStructureService::class)->createUnit(
                $this->unitName,
                $this->unitLevel,
                $this->parentId ? OrgUnit::findOrFail($this->parentId) : null,
                [
                    'job_purpose' => $this->jobPurpose,
                    'job_responsibilities' => collect(explode("\n", $this->jobResponsibilities))
                        ->map(fn ($line) => trim($line))->filter()->values()->all(),
                ],
            );

            $this->showUnitModal = false;
            $this->dispatch('ds-toast', message: 'تمت إضافة الوحدة');
        } catch (\InvalidArgumentException $e) {
            $this->addError('unitLevel', $e->getMessage());
        }
    }

    public function viewJobCard(int $unitId): void
    {
        $this->viewingJobId = $unitId;
    }

    public function transfer(): void
    {
        $this->authorize('structure.departments.update');

        $this->validate([
            'transferUserId' => 'required|exists:users,id',
            'transferUnitId' => 'nullable|exists:org_units,id',
            'transferDepartmentId' => 'nullable|exists:departments,id',
            'transferReason' => 'nullable|string|max:255',
        ], [], ['transferUserId' => 'الموظف']);

        app(OrgStructureService::class)->transfer(
            User::findOrFail($this->transferUserId),
            $this->transferUnitId ? OrgUnit::find($this->transferUnitId) : null,
            $this->transferDepartmentId,
            $this->transferReason,
            auth()->user(),
        );

        $this->transferReason = null;
        $this->dispatch('ds-toast', message: 'تم النقل مع حفظ السجل السابق');
    }

    public function saveCommittee(): void
    {
        $this->authorize('structure.departments.create');

        $this->validate([
            'committeeName' => 'required|string|max:255',
            'committeeMandate' => 'nullable|string',
        ], [], ['committeeName' => 'اسم اللجنة']);

        Committee::create([
            'name' => $this->committeeName,
            'mandate' => $this->committeeMandate,
            'chair_id' => auth()->id(),
        ]);

        $this->committeeName = '';
        $this->committeeMandate = null;
        $this->dispatch('ds-toast', message: 'أُنشئت اللجنة');
    }

    public function render(): View
    {
        $tree = app(OrgStructureService::class)->tree();

        return view('livewire.structure.org-tree-index', [
            'tree' => $tree,
            'transfers' => \App\Models\EmployeeTransfer::with(['employee', 'fromUnit', 'toUnit'])
                ->orderByDesc('id')->limit(50)->get(),
            'committees' => Committee::with(['chair', 'members'])->orderBy('name')->get(),
            'users' => User::orderBy('name')->get(['id', 'name']),
            'units' => OrgUnit::orderBy('name')->get(['id', 'name', 'level']),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'jobCard' => $this->viewingJobId ? OrgUnit::find($this->viewingJobId) : null,
            'jobs' => OrgUnit::query()
                ->where('level', OrgUnit::LEVEL_JOB)
                ->with(['parent:id,name', 'manager:id,name'])
                ->orderBy('name')
                ->get(['id', 'name', 'parent_id', 'manager_id', 'job_purpose']),
            'adminColors' => $this->administrationColors($tree),
        ])->layout('layouts.app', ['title' => 'الهيكل التنظيمي']);
    }

    /**
     * Distinct color per administration root. Time: O(n) | Space: O(n)
     *
     * @param  \Illuminate\Support\Collection<int, OrgUnit>  $tree
     * @return array<int, string>
     */
    private function administrationColors($tree): array
    {
        $palette = ['#0F3446', '#1B6B93', '#2D6A4F', '#C45C26', '#6B4C9A', '#8B5A2B'];
        $colors = [];
        foreach ($tree as $index => $root) {
            $colors[$root->id] = $palette[$index % count($palette)];
        }

        return $colors;
    }
}
