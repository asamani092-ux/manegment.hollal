<?php

namespace App\Services\Approval;

use App\Models\ApprovalRequest;
use App\Models\ApprovalRequestStep;
use App\Models\ExpenseRequest;
use App\Models\User;

/**
 * Snapshots approval steps so later rule edits do not change in-flight requests.
 * Time: O(steps) | Space: O(steps)
 */
class ApprovalEngine
{
    public function snapshotExpense(ExpenseRequest $expense, array $stages): ApprovalRequest
    {
        $existing = ApprovalRequest::query()
            ->where('approvable_type', 'expense_request')
            ->where('approvable_id', $expense->id)
            ->first();
        if ($existing) {
            return $existing;
        }

        $request = ApprovalRequest::query()->create([
            'approvable_type' => 'expense_request',
            'approvable_id' => $expense->id,
            'status' => 'pending',
            'current_step' => 0,
            'submitted_by' => $expense->requester_id,
        ]);

        foreach (array_values($stages) as $index => $stage) {
            ApprovalRequestStep::query()->create([
                'approval_request_id' => $request->id,
                'step_index' => $index,
                'definition' => ['legacy_stage' => $stage],
                'status' => $index === 0 ? 'pending' : 'waiting',
            ]);
        }

        return $request;
    }

    public function markStepActed(ExpenseRequest $expense, User $actor, ?int $behalfOf, string $action): void
    {
        $request = ApprovalRequest::query()
            ->where('approvable_type', 'expense_request')
            ->where('approvable_id', $expense->id)
            ->first();
        if (! $request) {
            return;
        }

        $step = $request->steps()->where('status', 'pending')->orderBy('step_index')->first();
        if (! $step) {
            return;
        }

        $step->update([
            'status' => $action === 'approved' ? 'approved' : $action,
            'acted_by' => $actor->id,
            'acted_on_behalf_of' => $behalfOf,
            'acted_at' => now(),
        ]);

        $next = $request->steps()->where('step_index', $step->step_index + 1)->first();
        if ($action === 'approved' && $next) {
            $next->update(['status' => 'pending']);
            $request->update(['current_step' => $next->step_index]);
        } elseif ($action === 'approved') {
            $request->update(['status' => 'approved']);
        }
    }
}
