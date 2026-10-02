<?php

namespace App\Services;

use App\Models\Delegation;
use App\Models\ExpenseRequest;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Active delegations substitute approvers and permissions for one hop.
 * Time: O(n) approvers | Space: O(n)
 */
class DelegationService
{
    public function activeForDelegator(int $delegatorId): ?Delegation
    {
        return Delegation::query()
            ->where('delegator_id', $delegatorId)
            ->where('status', Delegation::STATUS_ACTIVE)
            ->whereDate('starts_on', '<=', today())
            ->whereDate('ends_on', '>=', today())
            ->first();
    }

    /** @param  Collection<int, User>  $people */
    public function substitute(Collection $people, ?int $requesterId = null): Collection
    {
        $out = collect();
        foreach ($people as $user) {
            if (! $user instanceof User) {
                continue;
            }
            if ($requesterId !== null && $user->id === $requesterId) {
                continue;
            }
            $delegation = $this->activeForDelegator($user->id);
            $actor = $delegation?->delegate ?? $user;
            if ($requesterId !== null && $actor->id === $requesterId) {
                continue;
            }
            $out->push($actor);
        }

        return $out->unique('id')->values();
    }

    public function behalfOf(User $actor, ExpenseRequest $expense): ?int
    {
        $expense->loadMissing('requester.manager');
        $candidates = collect([$expense->requester?->manager])->filter();
        foreach ($candidates as $person) {
            $delegation = $this->activeForDelegator($person->id);
            if ($delegation && $delegation->delegate_id === $actor->id) {
                return $person->id;
            }
        }

        return null;
    }

    public function isActingForStage(User $user, ExpenseRequest $expense): bool
    {
        return $this->behalfOf($user, $expense) !== null;
    }

    public function activate(Delegation $delegation): Delegation
    {
        $delegation->update([
            'status' => Delegation::STATUS_ACTIVE,
            'activated_at' => now(),
        ]);

        return $delegation->fresh();
    }

    public function end(Delegation $delegation): Delegation
    {
        $delegation->update([
            'status' => Delegation::STATUS_ENDED,
            'ended_at' => now(),
        ]);

        return $delegation->fresh();
    }

    public function extend(Delegation $delegation, string $newEndsOn): Delegation
    {
        $delegation->update(['ends_on' => $newEndsOn]);

        return $delegation->fresh();
    }

    public function cut(Delegation $delegation, string $endsOn): Delegation
    {
        $delegation->update([
            'ends_on' => $endsOn,
            'status' => Delegation::STATUS_ENDED,
            'ended_at' => now(),
        ]);

        return $delegation->fresh();
    }

    /**
     * @return array{permissions: list<string>, sensitive: list<string>, pending_steps: int, direct_reports: list<int>}
     */
    public function preview(User $delegator): array
    {
        $permissions = $delegator->getAllPermissions()->pluck('name')->all();
        $sensitiveNeedles = ['hr.salaries.', 'settings.', 'finance.expenses.pay', 'finance.accounting.'];
        $sensitive = array_values(array_filter($permissions, function (string $name) use ($sensitiveNeedles) {
            foreach ($sensitiveNeedles as $needle) {
                if (str_contains($name, $needle) || str_starts_with($name, $needle)) {
                    return true;
                }
            }

            return false;
        }));

        return [
            'permissions' => $permissions,
            'sensitive' => $sensitive,
            'pending_steps' => ExpenseRequest::query()
                ->where('status', 'pending')
                ->where('requester_id', '!=', $delegator->id)
                ->count(),
            'direct_reports' => User::query()->where('manager_id', $delegator->id)->pluck('id')->all(),
        ];
    }

    public function syncDaily(): void
    {
        Delegation::query()
            ->where('status', Delegation::STATUS_SCHEDULED)
            ->whereDate('starts_on', '<=', today())
            ->each(fn (Delegation $row) => $this->activate($row));

        Delegation::query()
            ->where('status', Delegation::STATUS_ACTIVE)
            ->whereDate('ends_on', '<', today())
            ->each(fn (Delegation $row) => $this->end($row));
    }
}
