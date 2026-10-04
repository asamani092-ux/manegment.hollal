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

    /**
     * Open a chain for any approvable record. Time: O(steps) | Space: O(steps)
     *
     * @param  list<string>  $stages
     */
    public function open(string $type, int $id, ?int $submittedBy, array $stages): ApprovalRequest
    {
        $existing = $this->find($type, $id);
        if ($existing) {
            return $existing;
        }

        $request = ApprovalRequest::query()->create([
            'approvable_type' => $type,
            'approvable_id' => $id,
            'status' => 'pending',
            'current_step' => 0,
            'submitted_by' => $submittedBy,
        ]);

        foreach (array_values($stages) as $index => $stage) {
            ApprovalRequestStep::query()->create([
                'approval_request_id' => $request->id,
                'step_index' => $index,
                'definition' => ['legacy_stage' => $stage],
                'status' => $index === 0 ? 'pending' : 'waiting',
            ]);
        }

        return $request->load('steps');
    }

    public function find(string $type, int $id): ?ApprovalRequest
    {
        return ApprovalRequest::query()
            ->where('approvable_type', $type)
            ->where('approvable_id', $id)
            ->first();
    }

    public function allows(ApprovalRequest $request, User $actor): bool
    {
        $step = $request->steps()->where('status', 'pending')->orderBy('step_index')->first();
        $stage = (string) ($step?->definition['legacy_stage'] ?? '');
        if ($stage === '') {
            return false;
        }
        if (str_starts_with($stage, 'user:')) {
            return (int) substr($stage, 5) === (int) $actor->id;
        }
        if (str_starts_with($stage, 'users:')) {
            return in_array($actor->id, array_map('intval', explode(',', substr($stage, 6))), true);
        }
        if (str_starts_with($stage, 'role:')) {
            return $actor->hasRole(substr($stage, 5));
        }

        return match ($stage) {
            'department_manager' => true,
            'executive' => $actor->can('finance.expenses.approve') || $actor->hasRole('Executive Manager'),
            'finance' => $actor->can('finance.expenses.pay') || $actor->hasRole('Finance'),
            default => false,
        };
    }

    /**
     * null = no chain, caller finishes immediately.
     * false = a later step remains.
     * true = this action completed the chain.
     * Time: O(steps) | Space: O(steps)
     */
    public function gate(string $type, int $id, ?int $submittedBy, float $metric, User $actor): ?bool
    {
        $existing = $this->find($type, $id);
        if (! $existing) {
            $steps = app(\App\Services\ApprovalChainService::class)->stepsFor($type, $metric);
            if ($steps === []) {
                return null;
            }
            $existing = $this->open($type, $id, $submittedBy, $steps);
        }
        if ($existing->status === 'approved') {
            return true;
        }

        return $this->advance($existing, $actor);
    }

    /** @return bool true when the whole chain is approved */
    public function advance(ApprovalRequest $request, User $actor): bool
    {
        if (! $this->allows($request, $actor)) {
            throw new \RuntimeException('ليست هذه خطوتك في سلسلة الاعتماد');
        }

        $step = $request->steps()->where('status', 'pending')->orderBy('step_index')->first();
        $step?->update([
            'status' => 'approved',
            'acted_by' => $actor->id,
            'acted_at' => now(),
        ]);
        $next = $request->steps()->where('step_index', ($step?->step_index ?? 0) + 1)->first();
        if ($next) {
            $next->update(['status' => 'pending']);
            $request->update(['current_step' => $next->step_index]);

            return false;
        }
        $request->update(['status' => 'approved']);

        return true;
    }
}
