<?php

namespace App\Livewire\Settings;

use App\Models\ReferenceItem;
use App\Models\ReferenceList;
use App\Services\ReferenceListService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Settings screen for versioned reference lists.
 * Time: O(n) items | Space: O(n)
 */
class ReferenceListsIndex extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    public ?string $listKey = null;

    public string $statusFilter = '';

    public string $code = '';

    public string $name_ar = '';

    /** @var array<string, mixed> */
    public array $itemAttributes = [];

    public string $reason = '';

    public string $effective_from = '';

    public ?int $historyId = null;

    public ?TemporaryUploadedFile $importFile = null;

    /** @var array{adds: list<array<string, mixed>>, changes: list<array<string, mixed>>}|null */
    public ?array $importPreview = null;

    public function mount(): void
    {
        abort_unless(
            auth()->user()->can('settings.lists.view') || auth()->user()->can('settings.lists.manage'),
            403
        );
        $this->listKey = request()->query('list');
        $this->effective_from = now()->toDateString();
    }

    public function selectList(string $key): void
    {
        $this->listKey = $key;
        $this->reset(['code', 'name_ar', 'itemAttributes', 'historyId', 'importPreview']);
    }

    public function saveDraft(): void
    {
        $this->authorizeManage();
        $this->validate([
            'listKey' => 'required|string',
            'code' => 'required|string',
            'name_ar' => 'required|string',
            'effective_from' => 'required|date',
        ]);

        app(ReferenceListService::class)->createDraft(
            $this->listKey,
            $this->code,
            $this->name_ar,
            $this->itemAttributes,
            $this->effective_from,
            auth()->user(),
        );
        $this->reset(['code', 'name_ar', 'itemAttributes']);
        $this->dispatch('toast', type: 'success', message: 'تم إنشاء المسودة');
    }

    public function publish(int $id): void
    {
        $this->authorizeManage();
        app(ReferenceListService::class)->publish(ReferenceItem::findOrFail($id), auth()->user(), 'نشر من الواجهة');
    }

    public function suspend(int $id): void
    {
        $this->authorizeManage();
        $this->validate(['reason' => 'required|string']);
        app(ReferenceListService::class)->suspend(ReferenceItem::findOrFail($id), $this->reason, auth()->user());
    }

    public function reactivate(int $id): void
    {
        $this->authorizeManage();
        app(ReferenceListService::class)->reactivate(ReferenceItem::findOrFail($id), auth()->user(), $this->reason ?: null);
    }

    public function revise(int $id): void
    {
        $this->authorizeManage();
        $this->validate([
            'reason' => 'required|string',
            'effective_from' => 'required|date',
            'name_ar' => 'required|string',
        ]);
        app(ReferenceListService::class)->revise(
            ReferenceItem::findOrFail($id),
            ['name_ar' => $this->name_ar, 'attributes' => $this->itemAttributes],
            $this->reason,
            $this->effective_from,
            auth()->user(),
        );
    }

    public function deleteItem(int $id): void
    {
        $this->authorizeManage();
        $this->validate(['reason' => 'required|string']);
        try {
            app(ReferenceListService::class)->delete(ReferenceItem::findOrFail($id), $this->reason, auth()->user());
            $this->dispatch('toast', type: 'success', message: 'تم الحذف');
        } catch (\RuntimeException $e) {
            $this->addError('reason', $e->getMessage());
        }
    }

    public function showHistory(int $id): void
    {
        $this->historyId = $id;
    }

    public function previewImport(): void
    {
        $this->authorizeManage();
        $this->validate([
            'importFile' => 'required|file|mimes:xlsx,xls,csv',
            'effective_from' => 'required|date',
            'listKey' => 'required',
        ]);
        $path = $this->importFile->getRealPath();
        $this->importPreview = app(ReferenceListService::class)->previewImport($this->listKey, $path, $this->effective_from);
    }

    public function commitImport(): void
    {
        $this->authorizeManage();
        $this->validate([
            'importFile' => 'required|file',
            'reason' => 'required|string',
            'effective_from' => 'required|date',
        ]);
        app(ReferenceListService::class)->commitImport(
            $this->listKey,
            $this->importFile->getRealPath(),
            $this->effective_from,
            $this->reason,
            auth()->user(),
        );
        $this->importPreview = null;
        $this->reset('importFile');
        $this->dispatch('toast', type: 'success', message: 'تم استيراد النسخ');
    }

    public function export()
    {
        $this->authorizeManage();
        $path = app(ReferenceListService::class)->export($this->listKey);

        return response()->download($path)->deleteFileAfterSend(true);
    }

    public function render(): View
    {
        $lists = ReferenceList::query()->orderBy('name_ar')->get(['id', 'key', 'name_ar', 'schema']);
        $current = $lists->firstWhere('key', $this->listKey);
        $items = collect();
        if ($current) {
            $items = ReferenceItem::query()
                ->where('reference_list_id', $current->id)
                ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
                ->orderBy('code')
                ->orderByDesc('version')
                ->get();
        }
        $history = $this->historyId
            ? ReferenceItem::query()->where('code', ReferenceItem::find($this->historyId)?->code)
                ->where('reference_list_id', $current?->id)
                ->orderBy('version')
                ->get()
            : collect();

        return view('livewire.settings.reference-lists-index', [
            'lists' => $lists,
            'current' => $current,
            'items' => $items,
            'history' => $history,
            'canManage' => auth()->user()->can('settings.lists.manage'),
        ])->layout('layouts.app', ['title' => 'القوائم المرجعية']);
    }

    private function authorizeManage(): void
    {
        abort_unless(auth()->user()->can('settings.lists.manage'), 403);
    }
}
