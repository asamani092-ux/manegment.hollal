<?php

namespace App\Livewire\Partnerships;

use App\Livewire\Concerns\UsesDsPagination;
use App\Models\Organization;
use App\Models\Program;
use App\Models\User;
use App\Services\PartnershipQuickCreateService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * 05-B1 — organizations register. An organization is a permanent record:
 * partnerships come and go beneath it, and it is only ever soft-deleted.
 */
class OrganizationsIndex extends Component
{
    use AuthorizesRequests;
    use UsesDsPagination;
    use WithPagination;

    public string $search = '';

    public string $typeFilter = '';

    public bool $showModal = false;

    public bool $showQuickPartnershipModal = false;

    public ?int $editingId = null;

    public ?int $quickOrganizationId = null;

    public ?int $quickOwnerId = null;

    /** @var list<int|string> */
    public array $quickProgramIds = [];

    public string $name = '';

    public ?string $type = null;

    public ?string $typeOther = null;

    public ?string $city = null;

    public ?string $notes = null;

    /** @var list<string> */
    public array $roles = [];

    /** @var list<string> */
    public const TYPES = ['جمعية تحفيظ', 'مدرسة', 'شركة تعليمية', 'وقف', 'جهة حكومية', 'أخرى'];

    /** @var list<string> */
    public const ROLES = ['متعاقدة', 'جهة تنفيذ', 'مانحة'];

    public function mount(): void
    {
        $this->authorize('partnerships.organizations.view');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openCreate(): void
    {
        $this->authorize('partnerships.organizations.manage');
        $this->resetForm();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('partnerships.organizations.manage');
        $organization = Organization::findOrFail($id);

        $this->editingId = $organization->id;
        $this->name = $organization->name;
        $this->type = $organization->type;
        $this->typeOther = $organization->type_other;
        $this->city = $organization->city;
        $this->notes = $organization->notes;
        $this->roles = $organization->roles ?? [];
        $this->showModal = true;
    }

    public function save(): void
    {
        $this->authorize('partnerships.organizations.manage');

        $data = $this->validate([
            'name' => 'required|string|max:255',
            'type' => 'nullable|in:'.implode(',', self::TYPES),
            'typeOther' => 'required_if:type,أخرى|nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'roles' => 'array',
            'roles.*' => 'in:'.implode(',', self::ROLES),
        ], [], ['name' => 'اسم الجهة', 'typeOther' => 'النوع الآخر']);

        $payload = [
            'name' => $data['name'],
            'type' => $data['type'] ?? null,
            'type_other' => ($data['type'] ?? null) === 'أخرى' ? $data['typeOther'] : null,
            'city' => $data['city'] ?? null,
            'notes' => $data['notes'] ?? null,
            'roles' => $data['roles'] ?? [],
        ];

        if ($this->editingId) {
            Organization::findOrFail($this->editingId)->update($payload);
        } else {
            Organization::create($payload);
        }

        $this->showModal = false;
        $this->resetForm();
        $this->dispatch('ds-toast', message: 'تم حفظ الجهة');
    }

    /** Organizations are archived, never destroyed — history stays intact. */
    public function archive(int $id): void
    {
        $this->authorize('partnerships.organizations.manage');
        Organization::findOrFail($id)->delete();

        $this->dispatch('ds-toast', message: 'تمت أرشفة الجهة مع الحفاظ على سجلها');
    }

    public function openQuickPartnership(int $organizationId): void
    {
        $this->authorize('partnerships.organizations.manage');
        $this->quickOrganizationId = $organizationId;
        $this->quickOwnerId = auth()->id();
        $this->quickProgramIds = [];
        $this->showQuickPartnershipModal = true;
    }

    public function createQuickPartnership(): void
    {
        $this->authorize('partnerships.organizations.manage');
        $this->validate([
            'quickOrganizationId' => 'required|exists:organizations,id',
            'quickOwnerId' => 'required|exists:users,id',
            'quickProgramIds' => 'required|array|min:1',
            'quickProgramIds.*' => 'integer|exists:programs,id',
        ], [], ['quickProgramIds' => 'البرامج المسموحة', 'quickOwnerId' => 'المتابع']);

        try {
            $partnership = app(PartnershipQuickCreateService::class)->create(
                Organization::findOrFail($this->quickOrganizationId),
                User::findOrFail($this->quickOwnerId),
                $this->quickProgramIds,
            );
        } catch (\RuntimeException $exception) {
            $this->addError('quickProgramIds', $exception->getMessage());

            return;
        }

        $this->showQuickPartnershipModal = false;
        $this->dispatch('ds-toast', message: 'تم فتح رحلة شراكة في مرحلة فرصة');
        $this->redirectRoute('partnerships.show', ['partnership' => $partnership->id]);
    }

    public function toggleRole(string $role): void
    {
        if (! in_array($role, self::ROLES, true)) {
            return;
        }

        $this->roles = in_array($role, $this->roles, true)
            ? array_values(array_filter($this->roles, fn (string $item) => $item !== $role))
            : [...$this->roles, $role];
    }

    public function render(): View
    {
        $organizations = Organization::query()
            ->when($this->search !== '', fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->when($this->typeFilter !== '', fn ($q) => $q->where('type', $this->typeFilter))
            ->withCount('partnerships')
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.partnerships.organizations-index', [
            'organizations' => $organizations,
            'types' => self::TYPES,
            'roleOptions' => self::ROLES,
            'owners' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'programs' => Program::query()
                ->where('stage', Program::STAGE_ACTIVE)
                ->whereHas('prices', fn ($query) => $query->where('is_active', true))
                ->withCount('prices')
                ->orderBy('name')
                ->get(['id', 'name']),
        ])->layout('layouts.app', ['title' => 'الجهات الشريكة']);
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->type = null;
        $this->typeOther = null;
        $this->city = null;
        $this->notes = null;
        $this->roles = [];
    }
}
