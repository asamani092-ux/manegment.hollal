<?php

namespace App\Services;

use App\Models\ExpenseApprovalLog;
use App\Models\ExpenseRequest;
use App\Models\ExpenseSetting;
use App\Models\User;
use App\Notifications\ExpenseAwaitingApproval;
use App\Notifications\ExpensePaidReady;
use App\Services\AuditLogService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

/**
 * Expense multi-stage approval chain.
 * Time: O(s) stages per request | Space: O(s) for stage list.
 */
class ExpenseApprovalService
{
    public function __construct(protected AuditLogService $auditLog) {}

    public const STAGE_DEPARTMENT_MANAGER = 'department_manager';

    public const STAGE_EXECUTIVE = 'executive';

    public const STAGE_FINANCE = 'finance';

    /**
     * @return list<string>
     */
    public function resolveStages(ExpenseRequest $expense): array
    {
        $amount = round((float) $expense->amount, 2);
        $dynamic = app(ApprovalChainService::class)->stepsFor('expense', $amount);

        if ($dynamic === []) {
            throw new \RuntimeException(
                "لا توجد قاعدة اعتماد مطابقة للنوع expense والمبلغ {$amount}"
            );
        }

        $expense->loadMissing('requester.manager');
        $settings = ExpenseSetting::current();

        if (! ($expense->requester?->manager_id) && $settings->skip_missing_department_manager) {
            $dynamic = array_values(array_filter(
                $dynamic,
                fn (string $s) => $s !== self::STAGE_DEPARTMENT_MANAGER
            ));
        }

        if ($dynamic === []) {
            $dynamic = [self::STAGE_EXECUTIVE, self::STAGE_FINANCE];
        }

        return $dynamic;
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
        app(\App\Services\Approval\ApprovalEngine::class)->snapshotExpense($expense->fresh(), $stages);
    }

    public function canApprove(User $user, ExpenseRequest $expense): bool
    {
        if ($expense->status !== 'pending' || ! $expense->current_approval_stage) {
            return false;
        }

        $stage = (string) $expense->current_approval_stage;
        $direct = match (true) {
            $stage === self::STAGE_DEPARTMENT_MANAGER => $this->isDepartmentManager($user, $expense),
            $stage === self::STAGE_EXECUTIVE => $user->can('finance.expenses.approve'),
            $stage === self::STAGE_FINANCE => $user->can('finance.expenses.pay'),
            str_starts_with($stage, 'user:') => (int) substr($stage, 5) === $user->id,
            str_starts_with($stage, 'users:') => in_array($user->id, array_map('intval', explode(',', substr($stage, 6))), true),
            str_starts_with($stage, 'role:') => $user->hasRole(substr($stage, 5)),
            default => false,
        };

        if ($direct) {
            return $user->id !== $expense->requester_id;
        }

        return app(\App\Services\DelegationService::class)->isActingForStage($user, $expense) && $user->id !== $expense->requester_id;
    }

    /**
     * Arabic hint when the request is pending but the viewer cannot act on the current stage.
     */
    public function cannotApproveReason(User $user, ExpenseRequest $expense): ?string
    {
        if ($expense->status !== 'pending' || ! $expense->current_approval_stage) {
            return null;
        }

        if ($this->canApprove($user, $expense)) {
            return null;
        }

        $stageLabels = [
            self::STAGE_DEPARTMENT_MANAGER => 'مدير القسم المباشر',
            self::STAGE_EXECUTIVE => 'المدير التنفيذي / صاحب صلاحية الاعتماد',
            self::STAGE_FINANCE => 'المالية (صلاحية الصرف)',
        ];

        $stage = $stageLabels[$expense->current_approval_stage] ?? $expense->current_approval_stage;

        return 'لا يمكنك اعتماد هذا الطلب لأن مرحلته الحالية («'.$stage.'») خارج صلاحيتك أو ليست دورك في السلسلة.';
    }

    public function approve(User $approver, ExpenseRequest $expense): void
    {
        $stage = $expense->current_approval_stage;

        $behalf = app(\App\Services\DelegationService::class)->behalfOf($approver, $expense);
        app(\App\Services\Approval\ApprovalEngine::class)->markStepActed($expense, $approver, $behalf, 'approved');

        ExpenseApprovalLog::create([
            'expense_request_id' => $expense->id,
            'stage' => $stage,
            'approver_id' => $approver->id,
            'action' => 'approved',
            'notes' => $behalf ? 'بالإنابة عن مستخدم '.$behalf : null,
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

            $this->auditLog->record('expense.approved', $expense, [
                'stage' => $stage,
                'final' => true,
            ], $approver);

            $expense->requester?->notify(new ExpensePaidReady($expense->fresh()));

            return;
        }

        $expense->update([
            'current_approval_stage' => $nextStage,
            'approver_id' => $approver->id,
            'approved_at' => now(),
        ]);

        $this->auditLog->record('expense.approved', $expense, [
            'stage' => $stage,
            'next_stage' => $nextStage,
        ], $approver);

        $this->notifyApproversForStage($expense->fresh(), $nextStage);
    }

    public function reject(User $approver, ExpenseRequest $expense, string $reason): void
    {
        $stage = $expense->current_approval_stage ?? 'unknown';

        ExpenseApprovalLog::create([
            'expense_request_id' => $expense->id,
            'stage' => $stage,
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

        $this->auditLog->record('expense.rejected', $expense, [
            'stage' => $stage,
            'reason' => $reason,
        ], $approver);
    }

    /**
     * Return for revision (معاد للمراجعة): requester may edit and resubmit.
     */
    public function returnForRevision(User $approver, ExpenseRequest $expense, string $reason): void
    {
        $stage = $expense->current_approval_stage ?? 'unknown';

        ExpenseApprovalLog::create([
            'expense_request_id' => $expense->id,
            'stage' => $stage,
            'approver_id' => $approver->id,
            'action' => 'returned',
            'notes' => $reason,
            'acted_at' => now(),
        ]);

        $expense->update([
            'status' => ExpenseRequest::STATUS_RETURNED,
            'current_approval_stage' => null,
            'approver_id' => $approver->id,
            'approved_at' => now(),
            'rejection_reason' => $reason,
            'paid_ready_at' => null,
        ]);

        $this->auditLog->record('expense.returned', $expense, [
            'stage' => $stage,
            'reason' => $reason,
        ], $approver);
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
        $people = match (true) {
            $stage === self::STAGE_DEPARTMENT_MANAGER => collect([
                $expense->requester?->manager,
            ])->filter(),
            $stage === self::STAGE_EXECUTIVE => User::permission('finance.expenses.approve')
                ->where('is_active', true)
                ->get(),
            $stage === self::STAGE_FINANCE => User::permission('finance.expenses.pay')
                ->where('is_active', true)
                ->get(),
            str_starts_with($stage, 'user:') => collect([User::find((int) substr($stage, 5))])->filter(),
            str_starts_with($stage, 'users:') => User::query()->whereIn('id', array_map('intval', explode(',', substr($stage, 6))))->get(),
            str_starts_with($stage, 'role:') => User::role(substr($stage, 5))->where('is_active', true)->get(),
            default => collect(),
        };

        return app(\App\Services\DelegationService::class)->substitute($people, $expense->requester_id);
    }
}
