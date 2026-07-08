<?php

namespace App\Policies;

use App\Models\ExpenseRequest;
use App\Models\User;
use App\Services\ExpenseApprovalService;

/**
 * Expense request — requester, module permissions, approval workflow.
 */
class ExpenseRequestPolicy
{
    public function __construct(protected ExpenseApprovalService $approvalService) {}

    public function viewAny(User $user): bool
    {
        return $user->can('expenses.view')
            || $user->can('expenses.create')
            || $user->can('expenses.approve')
            || $user->can('expenses.pay');
    }

    public function view(User $user, ExpenseRequest $expenseRequest): bool
    {
        return $user->id === $expenseRequest->requester_id
            || $user->can('expenses.view')
            || $user->can('expenses.approve')
            || $user->can('expenses.pay')
            || $this->approvalService->canApprove($user, $expenseRequest);
    }

    public function create(User $user): bool
    {
        return $user->can('expenses.create');
    }

    public function update(User $user, ExpenseRequest $expenseRequest): bool
    {
        return $user->id === $expenseRequest->requester_id
            && $expenseRequest->status === 'draft'
            && $user->can('expenses.create');
    }

    public function delete(User $user, ExpenseRequest $expenseRequest): bool
    {
        return $user->id === $expenseRequest->requester_id
            && $expenseRequest->status === 'draft'
            && $user->can('expenses.create');
    }

    public function submit(User $user, ExpenseRequest $expenseRequest): bool
    {
        return $this->update($user, $expenseRequest);
    }

    public function approve(User $user, ExpenseRequest $expenseRequest): bool
    {
        return $this->approvalService->canApprove($user, $expenseRequest);
    }

    public function reject(User $user, ExpenseRequest $expenseRequest): bool
    {
        return $this->approve($user, $expenseRequest);
    }

    public function pay(User $user, ExpenseRequest $expenseRequest): bool
    {
        return $user->can('expenses.pay')
            && $expenseRequest->status === 'approved'
            && $expenseRequest->paid_ready_at !== null;
    }

    public function downloadAttachment(User $user, ExpenseRequest $expenseRequest): bool
    {
        return $this->view($user, $expenseRequest);
    }
}
