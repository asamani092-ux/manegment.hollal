<?php

namespace App\Services;

use App\Models\Revenue;

/**
 * 04-B4 — revenue recording. Partnership payments produce exactly one revenue on
 * confirmation (idempotent by source); manual revenues are entered directly.
 */
class RevenueService
{
    public function recordManual(float $amount, ?int $categoryId, ?string $receivedAt, ?string $externalDocumentPath = null): Revenue
    {
        return Revenue::create([
            'source_type' => Revenue::SOURCE_MANUAL,
            'category_id' => $categoryId,
            'amount' => $amount,
            'received_at' => $receivedAt,
            'external_document_path' => $externalDocumentPath,
            'status' => Revenue::STATUS_RECORDED,
        ]);
    }

    /**
     * Idempotent: at most one confirmed revenue per partnership payment.
     */
    public function recordFromPartnershipPayment(int $paymentId, float $amount, ?int $categoryId, int $confirmedBy): Revenue
    {
        $existing = Revenue::query()
            ->where('source_type', Revenue::SOURCE_PARTNERSHIP)
            ->where('source_id', $paymentId)
            ->first();

        if ($existing) {
            return $existing;
        }

        return Revenue::create([
            'source_type' => Revenue::SOURCE_PARTNERSHIP,
            'source_id' => $paymentId,
            'category_id' => $categoryId,
            'amount' => $amount,
            'received_at' => now()->toDateString(),
            'confirmed_at' => now(),
            'confirmed_by' => $confirmedBy,
            'status' => Revenue::STATUS_CONFIRMED,
        ]);
    }
}
