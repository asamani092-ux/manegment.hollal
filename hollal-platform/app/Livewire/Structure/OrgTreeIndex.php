<?php

namespace App\Livewire\Structure;

use App\Models\Committee;
use App\Models\OrgUnit;
use App\Models\User;
use App\Services\OrgChartService;
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

    public ?string $transferReason = null;

    // committee form
    public string $committeeName = '';

    public ?string $committeeMandate = null;

    public ?int $committeeDeleteConfirmId = null;

    public ?int $unitDeleteConfirmId = null;

    public ?int $editingUnitId = null;

    public ?int $viewingJobId = null;

    public ?int $viewingUnitId = null;

    public bool $showTopModal = false;

    /** @var array<int, int|string> */
    public array $linkChoice = [];

    public string $topTitle = '';

    public ?int $topOccupantId = null;

    public int $topOrder = 1;

    public function mount(): void
    {
        $this->authorize('structure.view');
    }

    public function gatherUnderTop(): void
    {
        $this->authorize('structure.manage');
        try {
            app(OrgStructureService::class)->gatherAdministrationsUnderTop();
            $this->dispatch('ds-toast', message: 'رُبطت الإدارات بآخر منصب أعلى');
        } catch (\InvalidArgumentException $e) {
            $this->dispatch('ds-toast', type: 'error', message: $e->getMessage());
        }
    }

    public function openTopModal(): void
    {
        $this->authorize('structure.manage');
        $this->topTitle = '';
        $this->topOccupantId = null;
        $this->topOrder = (int) OrgUnit::query()->where('level', OrgUnit::LEVEL_TOP_POSITION)->max('position') + 1;
        $this->showTopModal = true;
    }

    /** Time: O(n) | Space: O(1) */
    public function saveTopPosition(): void
    {
        $this->authorize('structure.manage');
        $this->validate([
            'topTitle' => 'required|string|max:255',
            'topOccupantId' => 'nullable|exists:users,id',
            'topOrder' => 'required|integer|min:0',
        ], [], ['topTitle' => 'المسمى']);
        app(OrgStructureService::class)->addTopPosition($this->topTitle, $this->topOccupantId, $this->topOrder);
        $this->showTopModal = false;
        $this->dispatch('ds-toast', message: 'أُضيف المنصب الأعلى');
    }

    /** Time: O(1) | Space: O(1) */
    public function linkAdministration(int $adminId): void
    {
        $this->followTop($adminId, (int) ($this->linkChoice[$adminId] ?? 0));
    }

    /** Time: O(1) | Space: O(1) */
    public function followTop(int $adminId, int $topId): void
    {
        $this->authorize('structure.manage');
        if ($topId === 0) {
            return;
        }
        app(OrgStructureService::class)->attachAdministration(
            OrgUnit::query()->findOrFail($adminId),
            OrgUnit::query()->findOrFail($topId),
        );
    }

    /** آخر منصب أعلى ليكون الأب الافتراضي للإدارة. Time: O(1) | Space: O(1) */
    private function defaultAdministrationParentId(): ?int
    {
        $id = OrgUnit::query()
            ->where('level', OrgUnit::LEVEL_TOP_POSITION)
            ->orderByDesc('position')
            ->orderByDesc('id')
            ->value('id');

        return $id ? (int) $id : null;
    }

    /** Time: O(1) | Space: O(1) */
    public function askDeleteUnit(int $id): void
    {
        $this->authorize('structure.manage');
        $this->unitDeleteConfirmId = $id;
    }

    public function cancelDeleteUnit(): void
    {
        $this->unitDeleteConfirmId = null;
    }

    /** Time: O(n) | Space: O(n) */
    public function deleteUnit(int $id): void
    {
        $this->authorize('structure.manage');
        try {
            app(OrgStructureService::class)->deleteUnitNode(
                OrgUnit::query()->findOrFail($id),
                auth()->user(),
            );
            if ((int) $this->viewingUnitId === $id) {
                $this->viewingUnitId = null;
            }
            $this->unitDeleteConfirmId = null;
            $this->dispatch('ds-toast', message: 'حُذفت البطاقة');
        } catch (\InvalidArgumentException $e) {
            $this->unitDeleteConfirmId = null;
            $this->dispatch('ds-toast', type: 'error', message: $e->getMessage());
        }
    }

    public function openDrawer(int $unitId): void
    {
        $this->viewingUnitId = $unitId;
    }

    public function closeDrawer(): void
    {
        $this->viewingUnitId = null;
    }

    public function openUnitModal(?int $parentId = null): void
    {
        $this->authorize('structure.manage');
        $this->editingUnitId = null;

        $this->parentId = $parentId ?: $this->defaultAdministrationParentId();
        $parent = $this->parentId ? OrgUnit::find($this->parentId) : null;
        $allowed = $parent ? (OrgUnit::CHILD_LEVEL[$parent->level] ?? []) : [OrgUnit::LEVEL_ADMINISTRATION];
        $this->unitLevel = OrgUnit::addLabel($parent->level ?? '') ?? ($allowed[0] ?? OrgUnit::LEVEL_ADMINISTRATION);
        $this->unitName = '';
        $this->jobPurpose = null;
        $this->jobResponsibilities = '';
        $this->showUnitModal = true;
    }

    /** Time: O(1) | Space: O(1) */
    public function openEditUnit(int $id): void
    {
        $this->authorize('structure.manage');
        $unit = OrgUnit::query()->findOrFail($id);
        $this->editingUnitId = $unit->id;
        $this->unitName = $unit->name;
        $this->unitLevel = $unit->level;
        $this->parentId = $unit->parent_id;
        $this->jobPurpose = $unit->job_purpose;
        $this->jobResponsibilities = implode("\n", $unit->job_responsibilities ?? []);
        $this->showUnitModal = true;
    }

    public function saveUnit(): void
    {
        $this->authorize('structure.manage');

        $this->validate([
            'unitName' => 'required|string|max:255',
            'unitLevel' => 'required|in:'.implode(',', array_keys(OrgUnit::CHILD_LEVEL)),
            'parentId' => 'nullable|exists:org_units,id',
        ], [], ['unitName' => 'اسم الوحدة التنظيمية', 'parentId' => 'يتبع لـ']);

        if ($this->unitLevel === OrgUnit::LEVEL_ADMINISTRATION
            && OrgUnit::query()->where('level', OrgUnit::LEVEL_TOP_POSITION)->exists()
            && ! $this->parentId) {
            $this->addError('parentId', 'اختر المنصب الأعلى الذي تتبعه الإدارة');

            return;
        }

        $details = [
            'job_purpose' => $this->jobPurpose,
            'job_responsibilities' => collect(explode("\n", (string) $this->jobResponsibilities))
                ->map(fn ($line) => trim($line))->filter()->values()->all(),
        ];

        try {
            if ($this->editingUnitId) {
                app(OrgStructureService::class)->updateUnit(
                    OrgUnit::query()->findOrFail($this->editingUnitId),
                    $this->unitName,
                    $this->parentId ? (int) $this->parentId : null,
                    $details,
                );
                $this->editingUnitId = null;
                $this->showUnitModal = false;
                $this->dispatch('ds-toast', message: 'حُفظ التعديل');

                return;
            }

            app(OrgStructureService::class)->createUnit(
                $this->unitName,
                $this->unitLevel,
                $this->parentId ? OrgUnit::findOrFail($this->parentId) : null,
                $details,
            );

            $this->showUnitModal = false;
            $this->dispatch('ds-toast', message: 'تمت إضافة الوحدة التنظيمية');
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
        $this->authorize('structure.manage');

        $this->validate([
            'transferUserId' => 'required|exists:users,id',
            'transferUnitId' => 'nullable|exists:org_units,id',
            'transferReason' => 'nullable|string|max:255',
        ], [], ['transferUserId' => 'الموظف']);

        app(OrgStructureService::class)->transfer(
            User::findOrFail($this->transferUserId),
            $this->transferUnitId ? OrgUnit::find($this->transferUnitId) : null,
            $this->transferReason,
            auth()->user(),
        );

        $this->transferReason = null;
        $this->dispatch('ds-toast', message: 'تم النقل مع حفظ السجل السابق');
    }

    public function saveCommittee(): void
    {
        $this->authorize('structure.manage');

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

    public function askDeleteCommittee(int $id): void
    {
        abort_unless(auth()->user()->can('structure.committees.manage'), 403);
        $this->committeeDeleteConfirmId = $id;
    }

    public function cancelDeleteCommittee(): void
    {
        $this->committeeDeleteConfirmId = null;
    }

    /**
     * Soft-delete committee; block when meetings exist. Time: O(1) | Space: O(1)
     */
    public function deleteCommittee(int $id): void
    {
        abort_unless(auth()->user()->can('structure.committees.manage'), 403);

        $committee = Committee::query()->withCount('meetings')->findOrFail($id);

        if ($committee->meetings_count > 0) {
            $this->committeeDeleteConfirmId = null;
            $this->dispatch('ds-toast',
                type: 'error',
                message: 'لا يمكن حذف اللجنة لوجود '.$committee->meetings_count.' اجتماع مرتبط — أوقفها بدل الحذف'
            );

            return;
        }

        $committee->members()->detach();
        $committee->delete();
        $this->committeeDeleteConfirmId = null;
        $this->dispatch('ds-toast', type: 'success', message: 'تم حذف اللجنة');
    }

    public function deactivateCommittee(int $id): void
    {
        abort_unless(auth()->user()->can('structure.committees.manage'), 403);
        Committee::findOrFail($id)->forceFill(['is_active' => false])->save();
        $this->dispatch('ds-toast', type: 'success', message: 'أُوقفت اللجنة');
    }

    public function activateCommittee(int $id): void
    {
        abort_unless(auth()->user()->can('structure.committees.manage'), 403);
        Committee::findOrFail($id)->forceFill(['is_active' => true])->save();
        $this->dispatch('ds-toast', type: 'success', message: 'فُعّلت اللجنة');
    }

    public function render(): View
    {
        $tree = $this->tab === 'table' ? app(OrgStructureService::class)->tree() : collect();
        $parent = $this->parentId ? OrgUnit::query()->find($this->parentId) : null;

        $deleteTarget = $this->committeeDeleteConfirmId
            ? Committee::query()->select(['id', 'name'])->withCount('meetings')->find($this->committeeDeleteConfirmId)
            : null;

        return view('livewire.structure.org-tree-index', [
            'tree' => $tree,
            'chart' => $this->tab === 'tree' ? app(OrgChartService::class)->tree() : ['roots' => [], 'unlinked' => []],
            'drawer' => $this->viewingUnitId
                ? OrgUnit::query()->with(['manager:id,name', 'members:id,name'])->find($this->viewingUnitId)
                : null,
            'topPositions' => OrgUnit::query()->where('level', OrgUnit::LEVEL_TOP_POSITION)->orderBy('position')->get(['id', 'name']),
            'childLevels' => $parent ? (OrgUnit::CHILD_LEVEL[$parent->level] ?? []) : [OrgUnit::LEVEL_TOP, OrgUnit::LEVEL_ADMINISTRATION],
            'transfers' => \App\Models\EmployeeTransfer::with(['employee', 'fromUnit', 'toUnit'])
                ->orderByDesc('id')->limit(50)->get(),
            'committees' => Committee::query()
                ->with(['chair:id,name', 'members:id,name'])
                ->withCount('meetings')
                ->orderBy('name')
                ->get(['id', 'name', 'chair_id', 'is_active', 'guests']),
            'committeeDeleteTarget' => $deleteTarget,
            'unitDeleteTarget' => $this->unitDeleteConfirmId
                ? OrgUnit::query()->select(['id', 'name', 'level'])->find($this->unitDeleteConfirmId)
                : null,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'units' => OrgUnit::orderBy('name')->get(['id', 'name', 'level']),
            'jobCard' => $this->viewingJobId ? OrgUnit::find($this->viewingJobId) : null,
            'jobs' => OrgUnit::query()
                ->where('level', OrgUnit::LEVEL_JOB)
                ->with(['parent:id,name', 'manager:id,name'])
                ->orderBy('name')
                ->get(['id', 'name', 'parent_id', 'manager_id', 'job_purpose']),
            'adminColors' => $this->tab === 'table' ? $this->administrationColors($tree) : [],
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
        $palette = OrgChartService::PALETTE;
        $colors = [];
        $index = 0;
        $walk = function ($nodes) use (&$walk, &$colors, &$index, $palette): void {
            foreach ($nodes as $node) {
                if ($node->level === OrgUnit::LEVEL_ADMINISTRATION) {
                    $colors[$node->id] = $palette[$index % count($palette)];
                    $index++;
                }
                $walk($node->children ?? []);
            }
        };
        $walk($tree);

        return $colors;
    }
}
