<?php

namespace App\Livewire\Partnerships;

use App\Livewire\Concerns\UsesDsPagination;
use App\Models\Organization;
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

    public ?int $editingId = null;

    public string $name = '';

    public ?string $type = null;

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
            'city' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'roles' => 'array',
            'roles.*' => 'in:'.implode(',', self::ROLES),
        ], [], ['name' => 'اسم الجهة']);

        if ($this->editingId) {
            Organization::findOrFail($this->editingId)->update($data);
        } else {
            Organization::create($data);
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
        ])->layout('layouts.app', ['title' => 'الجهات الشريكة']);
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->type = null;
        $this->city = null;
        $this->notes = null;
        $this->roles = [];
    }
}
