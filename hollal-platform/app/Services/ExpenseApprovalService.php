<?php

namespace App\Services;

use App\Models\ExpenseApprovalLog;
use App\Models\ExpenseRequest;
use App\Models\ExpenseSetting;
use App\Models\User;
use App\Notifications\ExpenseAwaitingApproval;
use App\Notifications\ExpensePaidReady;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Expense multi-stage approval chain.
 * Time: O(s) stages per request | Space: O(s) for stage list.
 */
class ExpenseApprovalService
{
    public const STAGE_DEPARTMENT_MANAGER = 'department_manager';

    public const STAGE_EXECUTIVE = 'executive';

    public const STAGE_FINANCE = 'finance';

    /**
     * @return list<string>
     */
    public function resolveStages(ExpenseRequest $expense): array
    {
        $settings = ExpenseSetting::current();
        $expense->loadMissing('requester.manager');

        $stages = [];

        if ($settings->chain_mode === 'full') {
            $hasManager = (bool) $expense->requester?->manager_id;

            if ($hasManager) {
                $stages[] = self::STAGE_DEPARTMENT_MANAGER;
            } elseif (! $settings->skip_missing_department_manager) {
                $stages[] = self::STAGE_DEPARTMENT_MANAGER;
            }
        }

        $stages[] = self::STAGE_EXECUTIVE;
        $stages[] = self::STAGE_FINANCE;

        return $stages;
    }

    public function initializeChain(ExpenseRequest $expense): void
    {
        $stages = $this->resolveStages($expense);

        $expense->update([
            'status' => 'pending',
            'approval_stages' => $stages,
            'current_approval_stage' => $stages[0] ?? null,
            'approver_id' => null,
            'approved_at' => null,
            'paid_ready_at' => null,
            'rejection_reason' => null,
        ]);

        $this->notifyApproversForStage($expense->fresh(), $stages[0] ?? null);
    }

    public function canApprove(User $user, ExpenseRequest $expense): bool
    {
        if ($expense->status !== 'pending' || ! $expense->current_approval_stage) {
            return false;
        }

        return match ($expense->current_approval_stage) {
            self::STAGE_DEPARTMENT_MANAGER => $this->isDepartmentManager($user, $expense),
            self::STAGE_EXECUTIVE => $user->hasRole('Executive Manager') && $user->can('expenses.approve'),
            self::STAGE_FINANCE => $user->can('expenses.pay'),
            default => false,
        };
    }

    public function approve(User $approver, ExpenseRequest $expense): void
    {
        $stage = $expense->current_approval_stage;

        ExpenseApprovalLog::create([
            'expense_request_id' => $expense->id,
            'stage' => $stage,
            'approver_id' => $approver->id,
            'action' => 'approved',
            'acted_at' => now(),
        ]);

        $stages = $expense->approval_stages ?? [];
        $currentIndex = array_search($stage, $stages, true);
        $nextStage = ($currentIndex !== false && isset($stages[$currentIndex + 1]))
            ? $stages[$currentIndex + 1]
            : null;

        if ($nextStage === null) {
            $expense->update([
                'status' => 'approved',
                'current_approval_stage' => null,
                'approver_id' => $approver->id,
                'approved_at' => now(),
                'paid_ready_at' => now(),
            ]);

            $expense->requester?->notify(new ExpensePaidReady($expense->fresh()));

            return;
        }

        $expense->update([
            'current_approval_stage' => $nextStage,
            'approver_id' => $approver->id,
            'approved_at' => now(),
        ]);

        $this->notifyApproversForStage($expense->fresh(), $nextStage);
    }

    public function reject(User $approver, ExpenseRequest $expense, string $reason): void
    {
        ExpenseApprovalLog::create([
            'expense_request_id' => $expense->id,
            'stage' => $expense->current_approval_stage ?? 'unknown',
            'approver_id' => $approver->id,
            'action' => 'rejected',
            'notes' => $reason,
            'acted_at' => now(),
        ]);

        $expense->update([
            'status' => 'rejected',
            'current_approval_stage' => null,
            'approver_id' => $approver->id,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    protected function isDepartmentManager(User $user, ExpenseRequest $expense): bool
    {
        $expense->loadMissing('requester');

        return $expense->requester?->manager_id === $user->id;
    }

    protected function notifyApproversForStage(ExpenseRequest $expense, ?string $stage): void
    {
        if (! $stage) {
            return;
        }

        $expense->load(['requester.manager', 'project:id,name']);

        $recipients = $this->approversForStage($expense, $stage)
            ->filter(fn (User $user): bool => $user->id !== $expense->requester_id);

        Notification::send($recipients, new ExpenseAwaitingApproval($expense));
    }

    /** @return Collection<int, User> */
    protected function approversForStage(ExpenseRequest $expense, string $stage): Collection
    {
        return match ($stage) {
            self::STAGE_DEPARTMENT_MANAGER => collect([
                $expense->requester?->manager,
            ])->filter(),
            self::STAGE_EXECUTIVE => User::role('Executive Manager')
                ->permission('expenses.approve')
                ->where('is_active', true)
                ->get(),
            self::STAGE_FINANCE => User::permission('expenses.pay')
                ->where('is_active', true)
                ->get(),
            default => collect(),
        };
    }
}
