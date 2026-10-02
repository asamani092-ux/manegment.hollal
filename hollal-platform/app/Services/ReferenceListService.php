<?php

namespace App\Services;

use App\Models\ReferenceItem;
use App\Models\ReferenceList;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Versioned reference lists.
 * Time: O(n) per list read, O(1) per mutation | Space: O(n) for exports.
 */
class ReferenceListService
{
    public function __construct(private AuditLogService $audit) {}

    /** @return Collection<int, ReferenceItem> */
    public function activeItems(string $listKey, CarbonInterface|string|null $on = null): Collection
    {
        $onDate = $this->date($on);
        $list = $this->list($listKey);

        $rows = ReferenceItem::query()
            ->where('reference_list_id', $list->id)
            ->whereIn('status', [ReferenceItem::STATUS_ACTIVE, ReferenceItem::STATUS_ARCHIVED])
            ->whereDate('effective_from', '<=', $onDate)
            ->where(function ($q) use ($onDate) {
                $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $onDate);
            })
            ->orderBy('sort_order')
            ->orderByDesc('version')
            ->get();

        return $rows
            ->groupBy('code')
            ->map(fn (Collection $group) => $group->sortByDesc('version')->first())
            ->filter()
            ->sortBy('sort_order')
            ->values();
    }

    public function item(string $listKey, string $code, CarbonInterface|string|null $on = null): ?ReferenceItem
    {
        return $this->activeItems($listKey, $on)->firstWhere('code', $code);
    }

    /** @param array<string, mixed> $attributes */
    public function createDraft(
        string $listKey,
        string $code,
        string $nameAr,
        array $attributes = [],
        ?string $effectiveFrom = null,
        ?User $actor = null,
    ): ReferenceItem {
        $list = $this->list($listKey);
        $this->assertAttributes($list, $attributes);

        $item = ReferenceItem::create([
            'reference_list_id' => $list->id,
            'code' => $code,
            'name_ar' => $nameAr,
            'attributes' => $attributes,
            'status' => ReferenceItem::STATUS_DRAFT,
            'version' => $this->nextVersion($list->id, $code),
            'effective_from' => $effectiveFrom ?? now()->toDateString(),
            'created_by' => ($actor ?? auth()->user())?->id,
        ]);

        $this->audit->record('reference_item.draft_created', $item, [
            'after' => $item->only(['code', 'name_ar', 'attributes', 'status', 'version']),
            'reason' => 'إنشاء مسودة',
        ], $actor);

        return $item;
    }

    public function publish(ReferenceItem $item, ?User $actor = null, ?string $reason = null): ReferenceItem
    {
        $before = $item->only(['status', 'effective_from']);
        $item->update(['status' => ReferenceItem::STATUS_ACTIVE]);
        $this->audit->record('reference_item.published', $item, [
            'before' => $before,
            'after' => $item->only(['status', 'effective_from']),
            'reason' => $reason ?: 'نشر',
        ], $actor);

        return $item->fresh();
    }

    public function suspend(ReferenceItem $item, string $reason, ?User $actor = null): ReferenceItem
    {
        $this->requireReason($reason);
        $before = $item->only(['status']);
        $item->update(['status' => ReferenceItem::STATUS_SUSPENDED]);
        $this->audit->record('reference_item.suspended', $item, [
            'before' => $before,
            'after' => ['status' => ReferenceItem::STATUS_SUSPENDED],
            'reason' => $reason,
        ], $actor);

        return $item->fresh();
    }

    public function reactivate(ReferenceItem $item, ?User $actor = null, ?string $reason = null): ReferenceItem
    {
        $before = $item->only(['status']);
        $item->update(['status' => ReferenceItem::STATUS_ACTIVE]);
        $this->audit->record('reference_item.reactivated', $item, [
            'before' => $before,
            'after' => ['status' => ReferenceItem::STATUS_ACTIVE],
            'reason' => $reason ?: 'إعادة تفعيل',
        ], $actor);

        return $item->fresh();
    }

    /**
     * @param array<string, mixed> $changes keys: name_ar, attributes, sort_order
     */
    public function revise(ReferenceItem $item, array $changes, string $reason, string $effectiveFrom, ?User $actor = null): ReferenceItem
    {
        $this->requireReason($reason);
        $item->loadMissing('list');
        $attributes = array_merge($item->attributes ?? [], $changes['attributes'] ?? []);
        $this->assertAttributes($item->list, $attributes);

        if ($item->isReferenced()) {
            return DB::transaction(function () use ($item, $changes, $reason, $effectiveFrom, $actor, $attributes) {
                $from = Carbon::parse($effectiveFrom)->startOfDay();
                $oldTo = $from->copy()->subDay()->toDateString();
                $before = $item->only(['status', 'effective_to', 'name_ar', 'attributes', 'version']);

                $item->update([
                    'effective_to' => $oldTo,
                    'status' => $from->lte(now()->startOfDay())
                        ? ReferenceItem::STATUS_ARCHIVED
                        : $item->status,
                ]);

                $next = ReferenceItem::create([
                    'reference_list_id' => $item->reference_list_id,
                    'code' => $item->code,
                    'name_ar' => $changes['name_ar'] ?? $item->name_ar,
                    'attributes' => $attributes,
                    'sort_order' => $changes['sort_order'] ?? $item->sort_order,
                    'status' => ReferenceItem::STATUS_ACTIVE,
                    'version' => $item->version + 1,
                    'effective_from' => $from->toDateString(),
                    'supersedes_id' => $item->id,
                    'created_by' => ($actor ?? auth()->user())?->id,
                ]);

                $this->audit->record('reference_item.revised', $next, [
                    'before' => $before,
                    'after' => $next->only(['id', 'version', 'name_ar', 'attributes', 'effective_from']),
                    'reason' => $reason,
                    'supersedes_id' => $item->id,
                ], $actor);

                return $next;
            });
        }

        $before = $item->only(['name_ar', 'attributes', 'sort_order']);
        $item->update([
            'name_ar' => $changes['name_ar'] ?? $item->name_ar,
            'attributes' => $attributes,
            'sort_order' => $changes['sort_order'] ?? $item->sort_order,
            'effective_from' => $effectiveFrom,
        ]);
        $this->audit->record('reference_item.revised_in_place', $item, [
            'before' => $before,
            'after' => $item->only(['name_ar', 'attributes', 'sort_order', 'effective_from']),
            'reason' => $reason,
        ], $actor);

        return $item->fresh();
    }

    public function delete(ReferenceItem $item, string $reason, ?User $actor = null): void
    {
        $this->requireReason($reason);
        if ($item->status !== ReferenceItem::STATUS_DRAFT && $item->isReferenced()) {
            throw new \RuntimeException('لا يمكن حذف عنصر مُشار إليه');
        }
        if ($item->status !== ReferenceItem::STATUS_DRAFT && $item->isReferenced()) {
            throw new \RuntimeException('لا يمكن حذف عنصر مُشار إليه');
        }
        if ($item->isReferenced()) {
            throw new \RuntimeException('لا يمكن حذف عنصر مُشار إليه');
        }

        $this->audit->record('reference_item.deleted', $item, [
            'before' => $item->only(['code', 'name_ar', 'status', 'version']),
            'reason' => $reason,
        ], $actor);
        $item->delete();
    }

    /**
     * @return array{adds: list<array<string, mixed>>, changes: list<array<string, mixed>>}
     */
    public function previewImport(string $listKey, string $path, string $effectiveFrom): array
    {
        $rows = $this->readSheet($path);
        $adds = [];
        $changes = [];

        foreach ($rows as $row) {
            $current = $this->item($listKey, $row['code'], $effectiveFrom);
            if (! $current) {
                $adds[] = $row;
                continue;
            }
            if ($current->name_ar !== $row['name_ar'] || ($current->attributes ?? []) != ($row['attributes'] ?? [])) {
                $changes[] = [
                    'code' => $row['code'],
                    'before' => ['name_ar' => $current->name_ar, 'attributes' => $current->attributes],
                    'after' => ['name_ar' => $row['name_ar'], 'attributes' => $row['attributes'] ?? []],
                ];
            }
        }

        return ['adds' => $adds, 'changes' => $changes];
    }

    public function commitImport(string $listKey, string $path, string $effectiveFrom, string $reason, ?User $actor = null): array
    {
        $this->requireReason($reason);
        $preview = $this->previewImport($listKey, $path, $effectiveFrom);
        $created = [];

        foreach ($preview['adds'] as $row) {
            $draft = $this->createDraft($listKey, $row['code'], $row['name_ar'], $row['attributes'] ?? [], $effectiveFrom, $actor);
            $created[] = $this->publish($draft, $actor, $reason);
        }

        foreach ($preview['changes'] as $change) {
            $current = $this->item($listKey, $change['code'], $effectiveFrom);
            if (! $current) {
                continue;
            }
            $created[] = $this->revise($current, [
                'name_ar' => $change['after']['name_ar'],
                'attributes' => $change['after']['attributes'] ?? [],
            ], $reason, $effectiveFrom, $actor);
        }

        return $created;
    }

    public function export(string $listKey): string
    {
        $list = $this->list($listKey);
        $sheet = new Spreadsheet;
        $ws = $sheet->getActiveSheet();
        $ws->fromArray(['code', 'name_ar', 'status', 'version', 'effective_from', 'attributes_json']);
        $row = 2;
        foreach ($list->items()->orderBy('code')->orderBy('version')->get() as $item) {
            $ws->fromArray([
                $item->code,
                $item->name_ar,
                $item->status,
                $item->version,
                $item->effective_from?->toDateString(),
                json_encode($item->attributes ?? [], JSON_UNESCAPED_UNICODE),
            ], null, 'A'.$row);
            $row++;
        }

        $path = storage_path('app/reference-lists-'.$list->key.'.xlsx');
        IOFactory::createWriter($sheet, 'Xlsx')->save($path);

        return $path;
    }

    private function list(string $key): ReferenceList
    {
        return ReferenceList::query()->where('key', $key)->firstOrFail();
    }

    private function date(CarbonInterface|string|null $on): string
    {
        if ($on instanceof CarbonInterface) {
            return $on->toDateString();
        }

        return $on ? Carbon::parse($on)->toDateString() : now()->toDateString();
    }

    private function nextVersion(int $listId, string $code): int
    {
        $max = (int) ReferenceItem::query()
            ->where('reference_list_id', $listId)
            ->where('code', $code)
            ->max('version');

        return $max + 1;
    }

    /** @param array<string, mixed> $attributes */
    private function assertAttributes(ReferenceList $list, array $attributes): void
    {
        foreach ($list->schema ?? [] as $field) {
            $name = $field['name'] ?? null;
            if (! $name) {
                continue;
            }
            if (! empty($field['required']) && ! array_key_exists($name, $attributes)) {
                throw new \InvalidArgumentException('الحقل '.$name.' مطلوب');
            }
        }
    }

    private function requireReason(string $reason): void
    {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('السبب مطلوب');
        }
    }

    /**
     * @return list<array{code: string, name_ar: string, attributes: array<string, mixed>}>
     */
    private function readSheet(string $path): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);
        $header = array_shift($rows);
        if (! is_array($header)) {
            return [];
        }
        $map = [];
        foreach ($header as $col => $name) {
            $map[$col] = is_string($name) ? trim($name) : '';
        }

        $out = [];
        foreach ($rows as $row) {
            $assoc = [];
            foreach ($map as $col => $name) {
                if ($name !== '') {
                    $assoc[$name] = $row[$col] ?? null;
                }
            }
            if (empty($assoc['code'])) {
                continue;
            }
            $attributes = [];
            if (! empty($assoc['attributes_json']) && is_string($assoc['attributes_json'])) {
                $decoded = json_decode($assoc['attributes_json'], true);
                $attributes = is_array($decoded) ? $decoded : [];
            }
            $out[] = [
                'code' => (string) $assoc['code'],
                'name_ar' => (string) ($assoc['name_ar'] ?? $assoc['code']),
                'attributes' => $attributes,
            ];
        }

        return $out;
    }
}
