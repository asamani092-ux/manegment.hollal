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
        $this->bootAttributes();
    }

    public function selectList(string $key): void
    {
        $this->listKey = $key;
        $this->reset(['code', 'name_ar', 'itemAttributes', 'historyId', 'importPreview']);
        $this->bootAttributes();
    }

    /**
     * صف جديد في محرر الحقول المركبة. Time: O(1) | Space: O(1)
     */
    public function addJsonRow(string $name): void
    {
        $rows = $this->itemAttributes[$name] ?? [];
        if (! is_array($rows)) {
            $rows = [];
        }
        $rows[] = $this->blankJsonRow($name);
        $this->itemAttributes[$name] = array_values($rows);
    }

    public function removeJsonRow(string $name, int $index): void
    {
        $rows = $this->itemAttributes[$name] ?? [];
        unset($rows[$index]);
        $this->itemAttributes[$name] = array_values($rows);
    }

    /**
     * Time: O(1) | Space: O(1)
     */
    /**
     * Time: O(1) | Space: O(1)
     */
    public function setEffectiveFromPart(string $part, string $value): void
    {
        if (! in_array($part, ['year', 'month', 'day'], true)) {
            return;
        }
        $current = $this->effective_from;
        $parts = preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $current, $match)
            ? [$match[1], $match[2], $match[3]]
            : ['', '', ''];
        $parts[['year' => 0, 'month' => 1, 'day' => 2][$part]] = $value;
        $this->effective_from = ($parts[0] !== '' && $parts[1] !== '' && $parts[2] !== '')
            ? sprintf('%04d-%02d-%02d', (int) $parts[0], (int) $parts[1], (int) $parts[2])
            : '';
    }

    public function setSchemaDate(string $field, string $part, string $value): void
    {
        if (! in_array($part, ['year', 'month', 'day'], true)) {
            return;
        }
        $current = (string) ($this->itemAttributes[$field] ?? '');
        $parts = preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $current, $match)
            ? [$match[1], $match[2], $match[3]]
            : ['', '', ''];
        $parts[['year' => 0, 'month' => 1, 'day' => 2][$part]] = $value;
        $this->itemAttributes[$field] = ($parts[0] !== '' && $parts[1] !== '' && $parts[2] !== '')
            ? sprintf('%04d-%02d-%02d', (int) $parts[0], (int) $parts[1], (int) $parts[2])
            : '';
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
            $this->normalizedAttributes(),
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

    private function bootAttributes(): void
    {
        $list = $this->listKey ? ReferenceList::query()->where('key', $this->listKey)->first() : null;
        $this->itemAttributes = [];
        foreach ($list?->schema ?? [] as $field) {
            $name = $field['name'] ?? null;
            if (! is_string($name) || $name === '') {
                continue;
            }
            $this->itemAttributes[$name] = match ($field['type'] ?? 'string') {
                'json' => [$this->blankJsonRow($name)],
                'boolean' => '0',
                default => '',
            };
        }
    }

    /** @return array<string, mixed> */
    private function blankJsonRow(string $name): array
    {
        $field = $this->schemaField($name);
        $columns = $field['columns'] ?? [];
        if ($columns === []) {
            return ['text' => ''];
        }
        $row = [];
        foreach ($columns as $column) {
            $row[$column['name']] = '';
        }

        return $row;
    }

    /** @return array<string, mixed> */
    private function schemaField(string $name): array
    {
        $list = $this->listKey ? ReferenceList::query()->where('key', $this->listKey)->first() : null;
        foreach ($list?->schema ?? [] as $field) {
            if (($field['name'] ?? '') === $name) {
                return $field;
            }
        }

        return [];
    }

    /** @return array<string, mixed> */
    private function normalizedAttributes(): array
    {
        $out = [];
        $list = ReferenceList::query()->where('key', $this->listKey)->first();
        foreach ($list?->schema ?? [] as $field) {
            $name = $field['name'] ?? null;
            if (! is_string($name)) {
                continue;
            }
            $value = $this->itemAttributes[$name] ?? null;
            $type = $field['type'] ?? 'string';
            if ($type === 'boolean') {
                $out[$name] = in_array($value, [true, 1, '1'], true);
            } elseif ($type === 'integer') {
                $out[$name] = ($value === '' || $value === null) ? null : (int) $value;
            } elseif ($type === 'json') {
                $rows = is_array($value) ? $value : [];
                $clean = [];
                foreach ($rows as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $filled = array_filter($row, fn ($cell) => trim((string) $cell) !== '');
                    if ($filled === []) {
                        continue;
                    }
                    if (($field['columns'] ?? []) === [] && count($row) === 1) {
                        $clean[] = (string) reset($row);
                    } else {
                        $clean[] = $row;
                    }
                }
                $out[$name] = $clean;
            } else {
                $out[$name] = $value;
            }
        }

        return $out;
    }

    private function authorizeManage(): void
    {
        abort_unless(auth()->user()->can('settings.lists.manage'), 403);
    }
}
