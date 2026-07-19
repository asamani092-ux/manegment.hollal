<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetMovement;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
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
            'condition' => Asset::CONDITION_GOOD,
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

    private function generateHandoverPdf(Asset $asset, AssetMovement $movement, User $toHolder): string
    {
        $html = view()->exists('pdf.asset-handover')
            ? view('pdf.asset-handover', compact('asset', 'movement', 'toHolder'))->render()
            : '<div dir="rtl" style="font-family: dejavu sans;">'
                .'<h2>محضر تسليم واستلام أصل</h2>'
                .'<p>الأصل: '.e($asset->name_ar).' ('.e($asset->code).')</p>'
                .'<p>المستلم: '.e($toHolder->name).'</p>'
                .'<p>التاريخ: '.$movement->moved_at->format('Y-m-d').'</p>'
                .'</div>';

        $pdf = Pdf::loadHTML($html)->setPaper('a4')->setOption('defaultFont', 'dejavu sans');

        $path = 'assets/handovers/'.$asset->code.'-'.$movement->id.'.pdf';
        Storage::disk('local')->put($path, $pdf->output());

        return $path;
    }
}
