<?php

namespace App\Livewire\Finance;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\User;
use App\Services\AssetService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * 04-B5 UI — asset registry and handovers.
 * Time: O(n) list | Space: O(page size).
 */
class AssetsIndex extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public bool $showCreateModal = false;

    public bool $showHandoverModal = false;

    public ?int $handoverAssetId = null;

    public string $name_ar = '';

    public ?int $category_id = null;

    public ?int $holder_id = null;

    public string $handover_reason = '';

    public string $search = '';

    public string $conditionFilter = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'conditionFilter' => ['except' => ''],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingConditionFilter(): void
    {
        $this->resetPage();
    }

    public function mount(): void
    {
        abort_unless(
            auth()->user()->can('finance.assets.view') || auth()->user()->can('finance.assets.manage'),
            403
        );
    }

    public function openCreateModal(): void
    {
        abort_unless(auth()->user()->can('finance.assets.manage'), 403);
        $this->reset(['name_ar', 'category_id']);
        $this->showCreateModal = true;
    }

    public function saveAsset(): void
    {
        abort_unless(auth()->user()->can('finance.assets.manage'), 403);
        $this->validate([
            'name_ar' => 'required|string|max:255',
            'category_id' => 'nullable|exists:asset_categories,id',
        ]);

        app(AssetService::class)->create($this->name_ar, $this->category_id);
        $this->showCreateModal = false;
        $this->dispatch('toast', type: 'success', message: 'تم تسجيل الأصل');
    }

    public function openHandoverModal(int $assetId): void
    {
        abort_unless(auth()->user()->can('finance.assets.manage'), 403);
        $this->handoverAssetId = $assetId;
        $this->holder_id = null;
        $this->handover_reason = '';
        $this->showHandoverModal = true;
    }

    public function submitHandover(): void
    {
        abort_unless(auth()->user()->can('finance.assets.manage'), 403);
        $this->validate([
            'holder_id' => 'required|exists:users,id',
        ]);

        $asset = Asset::findOrFail($this->handoverAssetId);
        $holder = User::findOrFail($this->holder_id);
        app(AssetService::class)->handover($asset, $holder, $this->handover_reason ?: null);

        $this->showHandoverModal = false;
        $this->dispatch('toast', type: 'success', message: 'تم تسجيل التسليم');
    }

    public function render(): View
    {
        return view('livewire.finance.assets-index', [
            'assets' => Asset::query()
                ->select(['id', 'code', 'name_ar', 'condition', 'current_holder_id', 'holder_since', 'can_be_custody'])
                ->with('currentHolder:id,name')
                ->when($this->search, fn ($q) => $q->where(
                    fn ($w) => $w->where('name_ar', 'like', '%'.$this->search.'%')
                        ->orWhere('code', 'like', '%'.$this->search.'%')
                ))
                ->when($this->conditionFilter, fn ($q) => $q->where('condition', $this->conditionFilter))
                ->orderBy('code')
                ->paginate(10),
            'categories' => AssetCategory::orderBy('name_ar')->get(['id', 'name_ar']),
            'employees' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'conditionOptions' => [
                Asset::CONDITION_GOOD,
                Asset::CONDITION_MAINTENANCE,
                Asset::CONDITION_DAMAGED,
                Asset::CONDITION_RETIRED,
            ],
            'canManage' => auth()->user()->can('finance.assets.manage'),
        ])->layout('layouts.app', ['title' => 'الأصول']);
    }
}
