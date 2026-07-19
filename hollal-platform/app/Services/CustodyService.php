<?php

namespace App\Services;

use App\Models\Custody;
use App\Models\CustodySettlementItem;
use App\Models\User;

/**
 * 04-B3 — custody lifecycle with the fixed chain employee → executive → finance
 * and a reconciliation gate (disbursed = Σ settlement items + returned).
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

    public function disburse(Custody $custody): Custody
    {
        $this->assertStatus($custody, Custody::STATUS_APPROVED, 'الصرف');
        $custody->update(['status' => Custody::STATUS_DISBURSED, 'disbursed_amount' => $custody->amount]);

        return $custody;
    }

    public function addSettlementItem(Custody $custody, string $description, float $amount, ?int $categoryId = null, ?string $invoiceFile = null): CustodySettlementItem
    {
        if (! in_array($custody->status, [Custody::STATUS_DISBURSED, Custody::STATUS_SETTLING], true)) {
            throw new \RuntimeException('لا يمكن تسوية عهدة قبل صرفها.');
        }

        $item = CustodySettlementItem::create([
            'custody_id' => $custody->id,
            'description' => $description,
            'amount' => $amount,
            'category_id' => $categoryId,
            'invoice_file' => $invoiceFile,
        ]);

        $custody->update(['status' => Custody::STATUS_SETTLING]);

        return $item;
    }

    /**
     * Finance verifies the match and closes: disbursed = Σ items + returned.
     */
    public function close(Custody $custody, float $returnedAmount = 0): Custody
    {
        $this->assertStatus($custody, Custody::STATUS_SETTLING, 'الإغلاق');

        $disbursed = (float) $custody->disbursed_amount;
        $reconciled = round($custody->settledTotal() + $returnedAmount, 2);

        if (round($disbursed, 2) !== $reconciled) {
            throw new \RuntimeException('عدم تطابق التسوية: المصروف '.$disbursed.' ≠ البنود + المرتجع '.$reconciled);
        }

        $custody->update(['status' => Custody::STATUS_CLOSED, 'returned_amount' => $returnedAmount]);

        return $custody;
    }

    private function assertStatus(Custody $custody, string $expected, string $action): void
    {
        if ($custody->status !== $expected) {
            throw new \RuntimeException('حالة العهدة لا تسمح بـ'.$action.'.');
        }
    }
}
