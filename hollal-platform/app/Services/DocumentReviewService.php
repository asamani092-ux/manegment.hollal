<?php

namespace App\Services;

use App\Models\EmployeeDocument;
use App\Models\User;

/**
 * Employee upload stays pending until HR approves. Time: O(1) | Space: O(1).
 */
class DocumentReviewService
{
    public function submit(User $employee, string $type, ?string $path = null): EmployeeDocument
    {
        return EmployeeDocument::query()->create([
            'user_id' => $employee->id,
            'type' => $type,
            'file_path' => $path,
            'uploaded_by' => $employee->id,
            'status' => 'pending_review',
        ]);
    }

    public function approve(EmployeeDocument $document, User $reviewer): EmployeeDocument
    {
        EmployeeDocument::query()
            ->where('user_id', $document->user_id)
            ->where('type', $document->type)
            ->where('id', '!=', $document->id)
            ->where('status', 'approved')
            ->update(['status' => 'superseded']);

        $document->update([
            'status' => 'approved',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);

        return $document->fresh();
    }

    public function reject(EmployeeDocument $document, User $reviewer, string $reason): EmployeeDocument
    {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('سبب الرفض مطلوب');
        }
        $document->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ]);

        return $document->fresh();
    }
}