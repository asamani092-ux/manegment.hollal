<?php

namespace App\Livewire\Structure;

use App\Models\Committee;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Committees list + members/guests management.
 * Time: O(n) | Space: O(page)
 */
class CommitteesIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $activeFilter = '';

    public ?int $managingId = null;

    public ?int $addUserId = null;

    public string $addRoleLabel = 'عضو';

    public string $guestName = '';

    public string $guestRole = 'مستشار';

    public string $guestOrg = '';

    /** @var array<string, array<string, string>> */
    protected $queryString = [
        'search' => ['except' => ''],
        'activeFilter' => ['except' => ''],
    ];

    public function mount(): void
    {
        abort_unless(
            auth()->user()->can('structure.committees.manage')
            || auth()->user()->can('structure.view'),
            403
        );
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingActiveFilter(): void
    {
        $this->resetPage();
    }

    public function openManage(int $id): void
    {
        abort_unless(auth()->user()->can('structure.committees.manage'), 403);
        $this->managingId = $id;
        $this->reset(['addUserId', 'guestName', 'guestOrg']);
        $this->addRoleLabel = 'عضو';
        $this->guestRole = 'مستشار';
    }

    public function closeManage(): void
    {
        $this->managingId = null;
    }

    public function addMember(): void
    {
        abort_unless(auth()->user()->can('structure.committees.manage'), 403);

        $this->validate([
            'addUserId' => 'required|exists:users,id',
            'addRoleLabel' => 'required|string|max:80',
        ]);

        $committee = Committee::findOrFail($this->managingId);
        $committee->members()->syncWithoutDetaching([
            $this->addUserId => ['role_label' => $this->addRoleLabel],
        ]);

        $this->addUserId = null;
        $this->dispatch('toast', type: 'success', message: 'أُضيف الموظف إلى اللجنة');
    }

    public function removeMember(int $userId): void
    {
        abort_unless(auth()->user()->can('structure.committees.manage'), 403);
        Committee::findOrFail($this->managingId)->members()->detach($userId);
        $this->dispatch('toast', type: 'success', message: 'أُزيل العضو');
    }

    public function addGuest(): void
    {
        abort_unless(auth()->user()->can('structure.committees.manage'), 403);

        $this->validate([
            'guestName' => 'required|string|max:120',
            'guestRole' => 'required|string|max:80',
            'guestOrg' => 'nullable|string|max:120',
        ]);

        $committee = Committee::findOrFail($this->managingId);
        $guests = $committee->guests ?? [];
        $guests[] = [
            'id' => uniqid('g_', true),
            'name' => $this->guestName,
            'role_label' => $this->guestRole,
            'organization' => $this->guestOrg !== '' ? $this->guestOrg : null,
        ];
        $committee->forceFill(['guests' => array_values($guests)])->save();

        $this->reset(['guestName', 'guestOrg']);
        $this->guestRole = 'مستشار';
        $this->dispatch('toast', type: 'success', message: 'أُضيف ضيف غير موظف');
    }

    public function removeGuest(string $guestId): void
    {
        abort_unless(auth()->user()->can('structure.committees.manage'), 403);
        $committee = Committee::findOrFail($this->managingId);
        $guests = collect($committee->guests ?? [])->reject(fn ($g) => ($g['id'] ?? '') === $guestId)->values()->all();
        $committee->forceFill(['guests' => $guests])->save();
        $this->dispatch('toast', type: 'success', message: 'أُزيل الضيف');
    }

    public function render(): View
    {
        $managing = $this->managingId
            ? Committee::with(['members:id,name', 'chair:id,name'])->find($this->managingId)
            : null;

        return view('livewire.structure.committees-index', [
            'committees' => Committee::query()
                ->select(['id', 'name', 'mandate', 'chair_id', 'is_active', 'guests', 'created_at'])
                ->with(['chair:id,name', 'members:id,name'])
                ->withCount('members')
                ->when($this->search, fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
                ->when($this->activeFilter !== '', fn ($q) => $q->where('is_active', $this->activeFilter === '1'))
                ->latest()
                ->paginate(20),
            'managing' => $managing,
            'users' => User::query()->select(['id', 'name'])->where('is_active', true)->orderBy('name')->get(),
            'canManage' => auth()->user()->can('structure.committees.manage'),
        ]);
    }
}
