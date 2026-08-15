<?php

namespace App\Livewire\Finance;

use App\Models\Revenue;
use App\Models\RevenueCategory;
use App\Services\RevenueService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Manual revenue entry + listing with evidence preview/download.
 * Time: O(n) | Space: O(page)
 */
class RevenuesIndex extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;
    use WithPagination;

    public bool $showCreateModal = false;

    public string $amount = '';

    public ?int $category_id = null;

    public string $received_at = '';

    public ?TemporaryUploadedFile $evidence = null;

    public string $sourceFilter = '';

    public string $dateFrom = '';

    public string $dateTo = '';

    protected $queryString = [
        'sourceFilter' => ['except' => ''],
        'dateFrom' => ['except' => ''],
        'dateTo' => ['except' => ''],
    ];

    public function updatingSourceFilter(): void
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

    public function mount(): void
    {
        abort_unless(
            auth()->user()->can('finance.revenues.view') || auth()->user()->can('finance.revenues.manage'),
            403
        );
    }

    public function openCreateModal(): void
    {
        abort_unless(auth()->user()->can('finance.revenues.manage'), 403);
        $this->reset(['amount', 'category_id', 'evidence']);
        $this->received_at = now()->toDateString();
        $this->showCreateModal = true;
    }

    public function saveRevenue(): void
    {
        abort_unless(auth()->user()->can('finance.revenues.manage'), 403);
        $this->validate([
            'amount' => 'required|numeric|min:0.01',
            'category_id' => 'nullable|exists:revenue_categories,id',
            'received_at' => 'required|date',
            'evidence' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png',
        ]);

        $path = $this->evidence->store('revenues/evidence', 'local');

        app(RevenueService::class)->recordManual(
            (float) $this->amount,
            $this->category_id,
            $this->received_at,
            $path,
        );

        $this->showCreateModal = false;
        $this->dispatch('toast', type: 'success', message: 'تم تسجيل الإيراد');
    }

    public function render(): View
    {
        return view('livewire.finance.revenues-index', [
            'revenues' => Revenue::query()
                ->select(['id', 'source_type', 'amount', 'received_at', 'status', 'external_document_path', 'created_at'])
                ->when($this->sourceFilter, fn ($q) => $q->where('source_type', $this->sourceFilter))
                ->when($this->dateFrom, fn ($q) => $q->whereDate('received_at', '>=', $this->dateFrom))
                ->when($this->dateTo, fn ($q) => $q->whereDate('received_at', '<=', $this->dateTo))
                ->latest()
                ->paginate(10),
            'categories' => RevenueCategory::orderBy('name_ar')->get(['id', 'name_ar']),
            'sourceOptions' => [
                Revenue::SOURCE_PARTNERSHIP,
                Revenue::SOURCE_MANUAL,
            ],
            'canManage' => auth()->user()->can('finance.revenues.manage'),
        ])->layout('layouts.app', ['title' => 'الإيرادات']);
    }
}
