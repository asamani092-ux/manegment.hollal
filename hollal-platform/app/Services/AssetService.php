<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetMovement;
use App\Models\User;
use App\Support\PdfArabic;
use Illuminate\Support\Facades\Storage;

/**
 * 04-B5 — asset registry with an immutable movement timeline. Every handover
 * generates an Arabic PDF stored on the private disk.
 */
class AssetService
{
    public function create(string $nameAr, ?int $categoryId, array $attributes = []): Asset
    {
        $canBeCustody = false;
        if ($categoryId) {
            $canBeCustody = (bool) (AssetCategory::find($categoryId)?->can_be_custody ?? false);
        }

        return Asset::create(array_merge([
            'code' => $this->nextCode(),
            'name_ar' => $nameAr,
            'category_id' => $categoryId,
            'can_be_custody' => $canBeCustody,
            'condition' => $attributes['condition'] ?? Asset::CONDITION_GOOD,
        ], $attributes));
    }

    public function handover(Asset $asset, User $toHolder, ?string $reason = null): AssetMovement
    {
        $fromHolderId = $asset->current_holder_id;

        $movement = AssetMovement::create([
            'asset_id' => $asset->id,
            'from_holder_id' => $fromHolderId,
            'to_holder_id' => $toHolder->id,
            'moved_at' => now(),
            'reason' => $reason,
            'movement_type' => 'تسليم',
        ]);

        $movement->update(['handover_document_path' => $this->generateHandoverPdf($asset, $movement, $toHolder)]);

        $asset->update(['current_holder_id' => $toHolder->id, 'holder_since' => today()]);

        return $movement;
    }

    /**
     * Return asset from employee to organization. Time: O(1) | Space: O(1)
     */
    public function receive(Asset $asset, ?string $reason = null): AssetMovement
    {
        if ($asset->current_holder_id === null) {
            throw new \RuntimeException('الأصل غير مسلَّم لموظف.');
        }

        $movement = AssetMovement::create([
            'asset_id' => $asset->id,
            'from_holder_id' => $asset->current_holder_id,
            'to_holder_id' => null,
            'moved_at' => now(),
            'reason' => $reason,
            'movement_type' => 'استلام',
        ]);

        $asset->update(['current_holder_id' => null, 'holder_since' => null]);

        return $movement;
    }

    public function updateCondition(Asset $asset, string $condition): Asset
    {
        $old = $asset->condition;
        $asset->update(['condition' => $condition]);

        app(AuditLogService::class)->record('asset.condition_changed', $asset, [
            'old' => $old,
            'new' => $condition,
        ]);

        return $asset;
    }

    public function retire(Asset $asset, ?string $reason = null): AssetMovement
    {
        $movement = AssetMovement::create([
            'asset_id' => $asset->id,
            'from_holder_id' => $asset->current_holder_id,
            'moved_at' => now(),
            'reason' => $reason,
            'movement_type' => 'استبعاد',
        ]);

        $asset->update(['condition' => Asset::CONDITION_RETIRED, 'current_holder_id' => null]);

        return $movement;
    }

    private function nextCode(): string
    {
        $next = (int) (Asset::withTrashed()->max('id') ?? 0) + 1;

        return 'AST-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Registry rows for export/print. Time: O(n) | Space: O(n)
     *
     * @return \Illuminate\Support\Collection<int, Asset>
     */
    public function registryRows(string $statusTab = 'active', ?string $search = null, ?string $condition = null)
    {
        return Asset::query()
            ->select(['id', 'code', 'name_ar', 'category_id', 'condition', 'purchase_amount', 'useful_life_years', 'location', 'current_holder_id', 'holder_since'])
            ->with(['currentHolder:id,name', 'category:id,name_ar'])
            ->when($statusTab === 'active', fn ($q) => $q->active())
            ->when($search, fn ($q) => $q->where(
                fn ($w) => $w->where('name_ar', 'like', '%'.$search.'%')
                    ->orWhere('code', 'like', '%'.$search.'%')
                    ->orWhereHas('currentHolder', fn ($h) => $h->where('name', 'like', '%'.$search.'%'))
            ))
            ->when($condition, fn ($q) => $q->where('condition', $condition))
            ->orderBy('code')
            ->get();
    }

    /** CSV with UTF-8 BOM for Excel. Time: O(n) | Space: O(n) */
    public function exportRegistryCsv(string $statusTab = 'active', ?string $search = null, ?string $condition = null): string
    {
        $rows = $this->registryRows($statusTab, $search, $condition);
        $lines = ["\xEF\xBB\xBF".'الرمز,الاسم,الفئة,الموقع,قيمة الشراء,العمر المحاسبي,القيمة الدفترية,الحالة,حامل العهدة,منذ'];

        foreach ($rows as $asset) {
            $lines[] = implode(',', [
                $this->csvCell((string) $asset->code),
                $this->csvCell((string) $asset->name_ar),
                $this->csvCell((string) ($asset->category?->name_ar ?? '')),
                $this->csvCell((string) ($asset->location ?? '')),
                $asset->purchase_amount !== null ? number_format((float) $asset->purchase_amount, 2, '.', '') : '',
                $asset->useful_life_years !== null ? (string) $asset->useful_life_years : '',
                $asset->bookValue() !== null ? number_format($asset->bookValue(), 2, '.', '') : '',
                $this->csvCell((string) $asset->condition),
                $this->csvCell((string) ($asset->currentHolder?->name ?? '')),
                $asset->holder_since?->format('Y-m-d') ?? '',
            ]);
        }

        return implode("\n", $lines)."\n";
    }

    /** Printable registry PDF. Time: O(n) | Space: O(n) */
    public function exportRegistryPdf(string $statusTab = 'active', ?string $search = null, ?string $condition = null): string
    {
        $rows = $this->registryRows($statusTab, $search, $condition);
        $tr = '';
        foreach ($rows as $asset) {
            // LTR Dompdf: first cell left — put العهدة…الرمز so الاسم/الرمز sit on the right.
            $tr .= '<tr>'
                .'<td>'.e($asset->currentHolder?->name ?? '—').'</td>'
                .'<td>'.e($asset->condition).'</td>'
                .'<td class="num">'.($asset->bookValue() !== null ? number_format($asset->bookValue(), 2) : '—').'</td>'
                .'<td class="num">'.($asset->purchase_amount !== null ? number_format((float) $asset->purchase_amount, 2) : '—').'</td>'
                .'<td>'.e($asset->location ?? '—').'</td>'
                .'<td>'.e($asset->category?->name_ar ?? '—').'</td>'
                .'<td>'.e($asset->name_ar).'</td>'
                .'<td class="num">'.e($asset->code).'</td>'
                .'</tr>';
        }
        if ($tr === '') {
            $tr = '<tr><td colspan="8">لا توجد أصول</td></tr>';
        }

        $label = $statusTab === 'active' ? 'الأصول النشطة' : 'كل الأصول';
        $body = '<p>تاريخ التصدير: '.e(hollal_dt(now())).'</p>'
            .'<table><thead><tr>'
            .'<th>العهدة</th><th>الحالة</th><th class="num">دفترية</th><th class="num">الشراء</th>'
            .'<th>الموقع</th><th>الفئة</th><th>الاسم</th><th class="num">الرمز</th>'
            .'</tr></thead><tbody>'.$tr.'</tbody></table>';

        return PdfArabic::render($label, $body);
    }

    private function csvCell(string $value): string
    {
        $safe = str_replace('"', '""', $value);

        return '"'.$safe.'"';
    }

    private function generateHandoverPdf(Asset $asset, AssetMovement $movement, User $toHolder): string
    {
        if (view()->exists('pdf.asset-handover')) {
            $html = view('pdf.asset-handover', compact('asset', 'movement', 'toHolder'))->render();
            $bytes = PdfArabic::outputFromHtml($html);
        } else {
            $body = '<p>الأصل: '.e($asset->name_ar).' ('.e($asset->code).')</p>'
                .'<p>المستلم: '.e($toHolder->name).'</p>'
                .'<p>التاريخ: '.$movement->moved_at->format('Y-m-d').'</p>';
            $bytes = PdfArabic::render('محضر تسليم واستلام أصل', $body);
        }

        $path = 'assets/handovers/'.$asset->code.'-'.$movement->id.'.pdf';
        Storage::disk('local')->put($path, $bytes);

        return $path;
    }
}
