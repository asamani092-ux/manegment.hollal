<?php

namespace App\Services;

use App\Models\Custody;
use App\Models\CustodySettlementItem;
use App\Models\User;

/**
 * دورة حياة العهدة: طلب → اعتماد → صرف → تسوية متعددة الفواتير → إغلاق.
 * Time: O(items) | Space: O(1)
 */
class CustodyService
{
    public function request(User $employee, float $amount, string $purpose, ?int $categoryId, ?int $projectId, ?string $dueDate, User $requestedBy): Custody
    {
        return Custody::create([
            'employee_id' => $employee->id,
            'amount' => $amount,
            'purpose' => $purpose,
            'category_id' => $categoryId,
            'project_id' => $projectId,
            'due_date' => $dueDate,
            'requested_by' => $requestedBy->id,
            'status' => Custody::STATUS_REQUESTED,
        ]);
    }

    public function approve(Custody $custody, User $executive): Custody
    {
        $this->assertStatus($custody, Custody::STATUS_REQUESTED, 'الاعتماد');
        $custody->update(['status' => Custody::STATUS_APPROVED, 'approved_by' => $executive->id]);

        return $custody;
    }

    public function reject(Custody $custody, User $executive, string $reason): Custody
    {
        $this->assertStatus($custody, Custody::STATUS_REQUESTED, 'الرفض');

        if (trim($reason) === '') {
            throw new \InvalidArgumentException('سبب الرفض إلزامي.');
        }

        $custody->update([
            'status' => Custody::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'approved_by' => $executive->id,
        ]);

        return $custody;
    }

    public function disburse(Custody $custody, string $disbursementProofPath): Custody
    {
        $this->assertStatus($custody, Custody::STATUS_APPROVED, 'الصرف');

        if (trim($disbursementProofPath) === '') {
            throw new \InvalidArgumentException('إثبات الصرف إلزامي.');
        }

        $custody->update([
            'status' => Custody::STATUS_DISBURSED,
            'disbursed_amount' => $custody->amount,
            'disbursement_proof_path' => $disbursementProofPath,
        ]);

        try {
            app(JournalService::class)->postCustodyDisbursed($custody->fresh(['category.account']));
        } catch (\Throwable $e) {
            report($e);
        }

        return $custody;
    }

    /**
     * @param  array{description?: string, amount?: float, vat_rate?: float, category_id?: ?int, invoice_number?: ?string, invoice_date?: ?string, invoice_file?: ?string, vendor_name?: ?string}|string  $descriptionOrData
     */
    public function addSettlementItem(
        Custody $custody,
        array|string $descriptionOrData,
        float $amount = 0,
        ?int $categoryId = null,
        ?string $invoiceFile = null,
    ): CustodySettlementItem {
        if (is_string($descriptionOrData)) {
            $data = [
                'description' => $descriptionOrData,
                'amount' => $amount,
                'category_id' => $categoryId,
                'invoice_file' => $invoiceFile,
                'vat_rate' => 0.0, // اختبارات قديمة بلا ضريبة
            ];
        } else {
            $data = $descriptionOrData;
        }

        if (! in_array($custody->status, [Custody::STATUS_DISBURSED, Custody::STATUS_SETTLING], true)) {
            throw new \RuntimeException('لا يمكن تسوية عهدة قبل صرفها.');
        }

        $tax = CustodySettlementItem::computeTax(
            (float) ($data['amount'] ?? 0),
            (float) ($data['vat_rate'] ?? 0.15),
        );

        $item = CustodySettlementItem::create([
            'custody_id' => $custody->id,
            'description' => (string) ($data['description'] ?? ''),
            'amount' => $tax['amount'],
            'vat_rate' => $tax['vat_rate'],
            'vat_amount' => $tax['vat_amount'],
            'total_amount' => $tax['total_amount'],
            'category_id' => $data['category_id'] ?? null,
            'invoice_number' => $data['invoice_number'] ?? null,
            'invoice_date' => $data['invoice_date'] ?? null,
            'invoice_file' => $data['invoice_file'] ?? null,
            'vendor_name' => $data['vendor_name'] ?? null,
        ]);

        $custody->update(['status' => Custody::STATUS_SETTLING]);

        return $item;
    }

    /**
     * إغلاق التسوية: إن زاد إجمالي الفواتير عن المصروف يُحتسب مطالبة للموظف، وإلا مرتجع.
     */
    public function close(Custody $custody, ?float $returnedAmount = null): Custody
    {
        if (! in_array($custody->status, [Custody::STATUS_SETTLING, Custody::STATUS_DISBURSED], true)) {
            throw new \RuntimeException('حالة العهدة لا تسمح بالإغلاق.');
        }

        if ($custody->settlementItems()->count() === 0) {
            throw new \RuntimeException('أضف فاتورة تسوية واحدة على الأقل قبل الإغلاق.');
        }

        $disbursed = round((float) $custody->disbursed_amount, 2);
        $itemsTotal = $custody->settledTotal();
        $diff = round($itemsTotal - $disbursed, 2);

        if ($returnedAmount === null) {
            $returnedAmount = $diff < 0 ? abs($diff) : 0.0;
        }

        $claim = $diff > 0 ? $diff : 0.0;
        $returnedAmount = round((float) $returnedAmount, 2);

        // التحقق: مصروف + مطالبة = فواتير، أو مصروف = فواتير + مرتجع
        $left = round($disbursed + $claim, 2);
        $right = round($itemsTotal + ($claim > 0 ? 0 : $returnedAmount), 2);
        if ($claim > 0) {
            $right = $itemsTotal;
            $left = round($disbursed + $claim, 2);
        } else {
            $left = $disbursed;
            $right = round($itemsTotal + $returnedAmount, 2);
        }

        if (abs($left - $right) >= 0.005) {
            throw new \RuntimeException('عدم تطابق التسوية: المصروف '.$disbursed.' والفواتير '.$itemsTotal);
        }

        $custody->update([
            'status' => Custody::STATUS_CLOSED,
            'returned_amount' => $returnedAmount,
        ]);

        try {
            app(JournalService::class)->postCustodySettled($custody->fresh(['category.account', 'settlementItems.category.account']));
        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }

        return $custody;
    }

    private function assertStatus(Custody $custody, string $expected, string $action): void
    {
        if ($custody->status !== $expected) {
            throw new \RuntimeException('حالة العهدة لا تسمح بـ'.$action.'.');
        }
    }
}
