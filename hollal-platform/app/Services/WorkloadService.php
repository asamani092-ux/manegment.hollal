<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use App\Support\Setting;
use Illuminate\Support\Collection;

/**
 * 02-B3 — workload board metrics per team member.
 */
class WorkloadService
{
    public function threshold(): int
    {
        return (int) Setting::get('attendance.workload_threshold', 10);
    }

    /**
     * Open-task count for a single user (used for the at-creation warning).
     */
    public function openCount(int $userId): int
    {
        return Task::query()
            ->where('assigned_to', $userId)
            ->whereNotIn('status', ['completed'])
            ->count();
    }

    public function isOverloaded(int $userId): bool
    {
        return $this->openCount($userId) > $this->threshold();
    }

    /**
     * Per-subordinate workload rows for a manager.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function board(User $manager): Collection
    {
        $subordinates = User::query()->where('manager_id', $manager->id)->get(['id', 'name']);

        return $subordinates->map(function (User $member) {
            $open = $this->openCount($member->id);

            return [
                'user' => $member,
                'open' => $open,
                'overdue' => Task::query()->where('assigned_to', $member->id)->overdue()->count(),
                'due_this_week' => Task::query()
                    ->where('assigned_to', $member->id)
                    ->whereNotIn('status', ['completed'])
                    ->whereBetween('due_date', [now()->startOfWeek(), now()->endOfWeek()])
                    ->count(),
                'ratings' => Task::query()
                    ->where('assigned_to', $member->id)
                    ->whereNotNull('final_rating')
                    ->where('completed_at', '>=', now()->subDays(30))
                    ->get()
                    ->groupBy('final_rating')
                    ->map->count(),
                'overloaded' => $open > $this->threshold(),
            ];
        });
    }
}
