<?php

namespace App\Services;

use App\Models\ReferenceItem;
use App\Models\ReferenceList;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * جدول مخالفات الشركة: رمز تلقائي، جزاءات أربع مرات، واستيراد عربي.
 * Time: O(n) للجدول والاستيراد | Space: O(n)
 */
class ViolationCatalogService
{
    /** @var list<string> */
    public const SAMPLE_CODES = ['draft-sample', 'clarity-late'];

    /** @var array<string, string> */
    public const CATEGORIES = [
        'مواعيد العمل' => 'مواعيد العمل',
        'تنظيم العمل' => 'تنظيم العمل',
        'سلوك العامل' => 'سلوك العامل',
        'أخرى' => 'أخرى',
    ];

    /** @var array<string, string> */
    public const PENALTY_TYPES = [
        'none' => 'لا شيء',
        'warning' => 'إنذار كتابي',
        'deduct_pct_daily' => 'حسم نسبة من أجر يوم',
        'deduct_days' => 'حسم أيام',
        'withhold_raise' => 'حرمان من العلاوة',
        'withhold_promotion' => 'حرمان من الترقية',
        'termination_with_award' => 'فصل مع المكافأة',
        'termination_without_award' => 'فصل دون مكافأة',
    ];

    /** @var array<string, string> */
    public const VALUE_UNITS = [
        'deduct_pct_daily' => '٪',
        'deduct_days' => 'يوم',
    ];

    /** @var array<string, string> */
    public const DETECTION = [
        'none' => 'لا',
        'absence' => 'غياب',
        'late' => 'تأخر',
    ];

    /** @var list<string> */
    public const OCCURRENCES = ['المرة الأولى', 'المرة الثانية', 'المرة الثالثة', 'المرة الرابعة'];

    /** @var list<string> */
    public const HEADERS = [
        'الاسم',
        'التصنيف',
        'الوصف',
        'المرة الأولى — النوع',
        'المرة الأولى — القيمة',
        'المرة الثانية — النوع',
        'المرة الثانية — القيمة',
        'المرة الثالثة — النوع',
        'المرة الثالثة — القيمة',
        'المرة الرابعة — النوع',
        'المرة الرابعة — القيمة',
        'الاكتشاف الآلي',
        'دقائق التأخر',
        'تاريخ السريان',
    ];

    public function __construct(private ReferenceListService $lists) {}

    /** @return list<array{type: string, value: string}> */
    public function blankPenalties(): array
    {
        return array_fill(0, 4, ['type' => 'none', 'value' => '']);
    }

    public function nextCode(): string
    {
        $list = ReferenceList::query()->where('key', 'violations')->first();
        $max = 0;
        if ($list) {
            foreach (ReferenceItem::query()->where('reference_list_id', $list->id)->pluck('code') as $code) {
                if (preg_match('/^V-(\d+)$/', (string) $code, $match)) {
                    $max = max($max, (int) $match[1]);
                }
            }
        }

        return 'V-'.str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * @param  list<array{type?: string, value?: mixed}>  $penalties
     * @param  list<array<string, mixed>>  $baseline
     * @return array<string, mixed>
     */
    public function attributes(string $category, string $description, array $penalties, string $detection, ?int $minutes, array $baseline = []): array
    {
        return [
            'category' => $category,
            'description' => $description,
            'penalties' => $this->storedPenalties($penalties),
            'auto_detectable' => $detection !== '' ? $detection : 'none',
            'late_threshold_minutes' => $detection === 'late' ? (int) $minutes : 0,
            'ministry_baseline' => $baseline,
            'origin' => 'company',
        ];
    }

    /**
     * @param  list<array{type?: string, value?: mixed}>  $penalties
     * @return list<array{type: string, value: float}>
     */
    public function storedPenalties(array $penalties): array
    {
        $rows = [];
        foreach (array_slice(array_values($penalties), 0, 4) as $row) {
            $type = (string) ($row['type'] ?? 'none');
            if (! array_key_exists($type, self::PENALTY_TYPES)) {
                $type = 'none';
            }
            $rows[] = [
                'type' => $type,
                'value' => array_key_exists($type, self::VALUE_UNITS) ? (float) ($row['value'] ?? 0) : 0,
            ];
        }
        while (count($rows) < 4) {
            $rows[] = ['type' => 'none', 'value' => 0];
        }

        return $rows;
    }

    /** @param  list<array{type?: string, value?: mixed}>  $penalties */
    public function penaltyError(array $penalties): ?string
    {
        foreach (array_values($penalties) as $index => $row) {
            if ($index > 3) {
                break;
            }
            $type = (string) ($row['type'] ?? 'none');
            if (! array_key_exists($type, self::PENALTY_TYPES)) {
                return 'نوع الجزاء غير معروف في '.self::OCCURRENCES[$index];
            }
            if (array_key_exists($type, self::VALUE_UNITS) && ($row['value'] ?? '') === '') {
                return 'قيمة الجزاء مطلوبة في '.self::OCCURRENCES[$index];
            }
        }

        return null;
    }

    public function templatePath(): string
    {
        $sheet = new Spreadsheet;
        $sheet->getActiveSheet()->fromArray(self::HEADERS, null, 'A1');
        $path = storage_path('app/violations-template.xlsx');
        IOFactory::createWriter($sheet, 'Xlsx')->save($path);

        return $path;
    }

    /**
     * @return list<array{state: string, name: string, reason: string, payload: array<string, mixed>}>
     */
    public function previewSheet(string $path): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $rows = $sheet->toArray(null, true, false, true);
        $header = array_shift($rows);
        if (! is_array($header)) {
            return [];
        }
        $map = [];
        foreach ($header as $col => $name) {
            $label = trim((string) $name);
            if ($label !== '') {
                $map[$label] = $col;
            }
        }

        $seen = [];
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row) || $this->rowEmpty($row)) {
                continue;
            }
            $read = function (string $header) use ($map, $row): string {
                $col = $map[$header] ?? null;

                return $col ? trim((string) ($row[$col] ?? '')) : '';
            };
            $name = $read('الاسم');
            $reasons = [];
            if ($name === '') {
                $reasons[] = 'الاسم مطلوب';
            } elseif (isset($seen[$name])) {
                $reasons[] = 'الاسم مكرر في الملف';
            }
            $seen[$name] = true;
            $category = $read('التصنيف');
            if (! array_key_exists($category, self::CATEGORIES)) {
                $reasons[] = 'التصنيف غير معروف';
            }
            $penalties = [];
            foreach (self::OCCURRENCES as $label) {
                $typeLabel = $read($label.' — النوع');
                $type = $typeLabel === '' ? 'none' : (array_search($typeLabel, self::PENALTY_TYPES, true) ?: '');
                if ($type === '') {
                    $reasons[] = 'نوع الجزاء غير معروف في '.$label;
                    $type = 'none';
                }
                $penalties[] = ['type' => $type, 'value' => $read($label.' — القيمة')];
            }
            $valueError = $this->penaltyError($penalties);
            if ($valueError) {
                $reasons[] = $valueError;
            }
            $detectionLabel = $read('الاكتشاف الآلي');
            $detection = $detectionLabel === '' ? 'none' : (array_search($detectionLabel, self::DETECTION, true) ?: '');
            if ($detection === '') {
                $reasons[] = 'الاكتشاف الآلي غير معروف';
                $detection = 'none';
            }
            $minutes = $read('دقائق التأخر');
            if ($detection === 'late' && ($minutes === '' || ! is_numeric($minutes))) {
                $reasons[] = 'التأخر يحتاج الدقائق';
            }
            $date = $read('تاريخ السريان');
            if ($date !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $reasons[] = 'تاريخ السريان بصيغة سنة-شهر-يوم';
            }

            $existing = $name !== '' ? $this->activeByName($name) : null;
            $out[] = [
                'state' => $reasons !== [] ? 'خطأ' : ($existing ? 'معدّل' : 'جديد'),
                'name' => $name !== '' ? $name : '—',
                'reason' => $reasons === [] ? ($existing ? 'تحديث بند قائم' : 'بند جديد') : implode('؛ ', array_unique($reasons)),
                'payload' => [
                    'name' => $name,
                    'category' => $category,
                    'description' => $read('الوصف'),
                    'penalties' => $penalties,
                    'detection' => $detection,
                    'minutes' => is_numeric($minutes) ? (int) $minutes : 0,
                    'effective' => $date !== '' ? $date : now()->toDateString(),
                    'item_id' => $existing?->id,
                ],
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{state: string, payload: array<string, mixed>}>  $rows
     * @return list<ReferenceItem>
     */
    public function commitPreview(array $rows, ?User $actor = null): array
    {
        $saved = [];
        foreach ($rows as $row) {
            if (($row['state'] ?? '') === 'خطأ') {
                continue;
            }
            $payload = $row['payload'];
            $attributes = $this->attributes(
                (string) $payload['category'],
                (string) ($payload['description'] ?? ''),
                $payload['penalties'] ?? [],
                (string) ($payload['detection'] ?? 'none'),
                (int) ($payload['minutes'] ?? 0),
            );
            $itemId = $payload['item_id'] ?? null;
            if ($itemId) {
                $current = ReferenceItem::query()->find($itemId);
                if (! $current) {
                    continue;
                }
                $saved[] = $this->lists->revise($current, [
                    'name_ar' => $payload['name'],
                    'attributes' => $attributes,
                ], 'رفع جدول المخالفات', (string) $payload['effective'], $actor);
                continue;
            }
            $draft = $this->lists->createDraft(
                'violations',
                $this->nextCode(),
                (string) $payload['name'],
                $attributes,
                (string) $payload['effective'],
                $actor,
            );
            $saved[] = $this->lists->publish($draft, $actor, 'رفع جدول المخالفات');
        }

        return $saved;
    }

    public function purgeSampleItems(): int
    {
        $list = ReferenceList::query()->where('key', 'violations')->first();
        if (! $list) {
            return 0;
        }
        $deleted = 0;
        $items = ReferenceItem::query()
            ->where('reference_list_id', $list->id)
            ->whereIn('code', self::SAMPLE_CODES)
            ->get();
        foreach ($items as $item) {
            if (Schema::hasTable('violations') && DB::table('violations')->where('reference_item_id', $item->id)->exists()) {
                continue;
            }
            if ($item->isReferenced()) {
                continue;
            }
            $item->delete();
            $deleted++;
        }

        return $deleted;
    }

    public function labelPenalty(string $type): string
    {
        return self::PENALTY_TYPES[$type] ?? 'لا شيء';
    }

    private function activeByName(string $name): ?ReferenceItem
    {
        if (! ReferenceList::query()->where('key', 'violations')->exists()) {
            return null;
        }

        return $this->lists->activeItems('violations')->firstWhere('name_ar', $name);
    }

    /** @param  array<string, mixed>  $row */
    private function rowEmpty(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
