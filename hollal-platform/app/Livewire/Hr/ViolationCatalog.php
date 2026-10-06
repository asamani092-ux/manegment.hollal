<?php

namespace App\Livewire\Hr;

use App\Models\ReferenceItem;
use App\Services\ReferenceListService;
use App\Services\ViolationCatalogService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * محرر جدول المخالفات لموظف غير تقني.
 * Time: O(n) للعرض | Space: O(1) للنموذج
 */
class ViolationCatalog extends Component
{
    use WithFileUploads;

    public string $nameAr = '';

    public string $category = 'مواعيد العمل';

    public string $description = '';

    /** @var list<array{type: string, value: string}> */
    public array $penalties = [];

    public string $autoDetect = 'none';

    public string $lateMinutes = '';

    public string $effectiveFrom = '';

    public ?int $editingId = null;

    public bool $editingReferenced = false;

    public string $revisionReason = '';

    /** @var list<array{type: string, value: float}> */
    public array $baselineRows = [];

    public ?TemporaryUploadedFile $importFile = null;

    /** @var list<array<string, mixed>> */
    public array $importRows = [];

    public function mount(): void
    {
        abort_unless($this->allowed(), 403);
        $this->penalties = app(ViolationCatalogService::class)->blankPenalties();
        $this->effectiveFrom = now()->toDateString();
    }

    public function editItem(int $id): void
    {
        abort_unless($this->canEdit(), 403);
        $item = ReferenceItem::query()->findOrFail($id);
        $attrs = $item->attributes ?? [];
        $this->editingId = $item->id;
        $this->editingReferenced = $item->isReferenced()
            || DB::table('violations')->where('reference_item_id', $item->id)->exists();
        $this->nameAr = $item->name_ar;
        $this->category = (string) ($attrs['category'] ?? 'مواعيد العمل');
        $this->description = (string) ($attrs['description'] ?? '');
        $this->autoDetect = (string) ($attrs['auto_detectable'] ?? 'none');
        $this->lateMinutes = (string) ($attrs['late_threshold_minutes'] ?? '');
        $this->effectiveFrom = $item->effective_from?->toDateString() ?? now()->toDateString();
        $this->revisionReason = '';
        $stored = $attrs['penalties'] ?? [];
        $rows = app(ViolationCatalogService::class)->blankPenalties();
        foreach (array_values(is_array($stored) ? $stored : []) as $index => $row) {
            if ($index > 3 || ! is_array($row)) {
                continue;
            }
            $rows[$index] = [
                'type' => (string) ($row['type'] ?? 'none'),
                'value' => array_key_exists((string) ($row['type'] ?? ''), ViolationCatalogService::VALUE_UNITS)
                    ? (string) ($row['value'] ?? '')
                    : '',
            ];
        }
        $this->penalties = $rows;
        $baseline = $attrs['ministry_baseline'] ?? [];
        $this->baselineRows = is_array($baseline) ? array_values(array_filter($baseline, 'is_array')) : [];
    }

    public function save(): void
    {
        $this->persist(false);
    }

    public function saveDraft(): void
    {
        $this->persist(true);
    }

    /**
     * Time: O(1) | Space: O(1)
     */
    public function setEffectivePart(string $part, string $value): void
    {
        if (! in_array($part, ['year', 'month', 'day'], true)) {
            return;
        }
        $current = $this->effectiveFrom;
        $parts = preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $current, $match)
            ? [$match[1], $match[2], $match[3]]
            : ['', '', ''];
        $index = ['year' => 0, 'month' => 1, 'day' => 2][$part];
        $parts[$index] = $value;
        $this->effectiveFrom = ($parts[0] !== '' && $parts[1] !== '' && $parts[2] !== '')
            ? sprintf('%04d-%02d-%02d', (int) $parts[0], (int) $parts[1], (int) $parts[2])
            : '';
    }

    public function downloadTemplate()
    {
        abort_unless($this->canEdit(), 403);
        $path = app(ViolationCatalogService::class)->templatePath();

        return response()->download($path, 'نموذج-المخالفات.xlsx')->deleteFileAfterSend(true);
    }

    public function previewExcel(): void
    {
        abort_unless($this->canEdit(), 403);
        $this->validate(['importFile' => 'required|file|mimes:xlsx,xls']);
        $this->importRows = app(ViolationCatalogService::class)->previewSheet($this->importFile->getRealPath());
    }

    public function confirmExcel(): void
    {
        abort_unless($this->canEdit(), 403);
        if ($this->importRows === []) {
            return;
        }
        app(ViolationCatalogService::class)->commitPreview($this->importRows, auth()->user());
        $this->importRows = [];
        $this->reset('importFile');
        $this->dispatch('toast', type: 'success', message: 'اعتُمد الملف');
    }

    public function render(): View
    {
        $items = \App\Models\ReferenceList::query()->where('key', 'violations')->exists()
            ? app(ReferenceListService::class)->activeItems('violations')
            : collect();
        $drafts = \App\Models\ReferenceList::query()->where('key', 'violations')->first()
            ? ReferenceItem::query()
                ->where('reference_list_id', \App\Models\ReferenceList::query()->where('key', 'violations')->value('id'))
                ->where('status', ReferenceItem::STATUS_DRAFT)
                ->orderByDesc('id')
                ->get()
            : collect();

        return view('livewire.hr.violation-catalog', [
            'items' => $items,
            'drafts' => $drafts,
            'canEdit' => $this->canEdit(),
            'catalog' => app(ViolationCatalogService::class),
        ]);
    }

    private function persist(bool $asDraft): void
    {
        abort_unless($this->canEdit(), 403);
        $this->validate([
            'nameAr' => 'required|string|max:200',
            'category' => 'required|in:'.implode(',', array_keys(ViolationCatalogService::CATEGORIES)),
            'effectiveFrom' => 'required|date',
        ], [
            'nameAr.required' => 'الاسم مطلوب',
            'effectiveFrom.required' => 'تاريخ السريان مطلوب',
        ]);
        if ($this->autoDetect === 'late' && ($this->lateMinutes === '' || ! is_numeric($this->lateMinutes))) {
            $this->addError('lateMinutes', 'التأخر يحتاج الدقائق');

            return;
        }
        $catalog = app(ViolationCatalogService::class);
        $penaltyError = $catalog->penaltyError($this->penalties);
        if ($penaltyError) {
            $this->addError('penalties', $penaltyError);

            return;
        }
        $attributes = $catalog->attributes(
            $this->category,
            $this->description,
            $this->penalties,
            $this->autoDetect,
            $this->lateMinutes === '' ? 0 : (int) $this->lateMinutes,
            $this->baselineRows,
        );
        $lists = app(ReferenceListService::class);
        if ($this->editingId) {
            $item = ReferenceItem::query()->findOrFail($this->editingId);
            if ($this->editingReferenced) {
                $this->validate(['revisionReason' => 'required|string'], ['revisionReason.required' => 'سبب النسخة الجديدة مطلوب']);
                $lists->revise($item, [
                    'name_ar' => $this->nameAr,
                    'attributes' => $attributes,
                ], $this->revisionReason, $this->effectiveFrom, auth()->user());
            } else {
                $item->update([
                    'name_ar' => $this->nameAr,
                    'attributes' => $attributes,
                    'effective_from' => $this->effectiveFrom,
                ]);
                if (! $asDraft && $item->status === ReferenceItem::STATUS_DRAFT) {
                    $lists->publish($item->fresh(), auth()->user(), 'نشر من المحرر');
                }
            }
        } else {
            $draft = $lists->createDraft('violations', $catalog->nextCode(), $this->nameAr, $attributes, $this->effectiveFrom, auth()->user());
            if (! $asDraft) {
                $lists->publish($draft, auth()->user(), 'نشر من المحرر');
            }
        }
        $this->resetForm();
        $this->dispatch('toast', type: 'success', message: $asDraft ? 'حُفظت مسودة' : 'نُشرت المخالفة');
    }

    private function resetForm(): void
    {
        $this->reset(['nameAr', 'description', 'editingId', 'editingReferenced', 'revisionReason', 'lateMinutes', 'importRows', 'baselineRows']);
        $this->category = 'مواعيد العمل';
        $this->autoDetect = 'none';
        $this->penalties = app(ViolationCatalogService::class)->blankPenalties();
        $this->effectiveFrom = now()->toDateString();
    }

    private function allowed(): bool
    {
        $user = auth()->user();

        return $user->can('hr.violations.view')
            || $user->can('hr.violations.manage')
            || $user->can('settings.lists.view')
            || $user->can('settings.lists.manage');
    }

    private function canEdit(): bool
    {
        $user = auth()->user();

        return $user->can('hr.violations.manage') || $user->can('settings.lists.manage');
    }
}
