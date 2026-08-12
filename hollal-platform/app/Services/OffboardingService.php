<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 01-B5 — offboarding: block while the employee still holds custodies/assets,
 * otherwise disable the account (منتهية_علاقته) and raise handover tasks.
 * The hold checks read the custodies/assets tables when they exist (04-B3 /
 * 04-B5); until then there are no holds.
 */
class OffboardingService
{
    /**
     * @return list<string> outstanding holds preventing offboarding
     */
    public function holds(User $employee): array
    {
        $holds = [];

        if (Schema::hasTable('custodies')) {
            $openCustodies = DB::table('custodies')
                ->where('employee_id', $employee->id)
                ->whereNotIn('status', ['مغلقة'])
                ->whereNull('deleted_at')
                ->count();

            if ($openCustodies > 0) {
                $holds[] = 'يوجد عهد مالية مفتوحة ('.$openCustodies.')';
            }
        }

        if (Schema::hasTable('assets')) {
            $heldAssets = DB::table('assets')
                ->where('current_holder_id', $employee->id)
                ->whereNull('deleted_at')
                ->count();

            if ($heldAssets > 0) {
                $holds[] = 'يوجد أصول بعهدة الموظف ('.$heldAssets.')';
            }
        }

        return $holds;
    }

    /**
     * Batched variant of holds() for list screens — 2 queries total instead of 2×n.
     * Time: O(n) | Space: O(n).
     *
     * @param  list<int>  $employeeIds
     * @return array<int, list<string>>
     */
    public function holdsForMany(array $employeeIds): array
    {
        $result = array_fill_keys($employeeIds, []);

        if ($employeeIds === []) {
            return $result;
        }

        if (Schema::hasTable('custodies')) {
            $rows = DB::table('custodies')
                ->selectRaw('employee_id, COUNT(*) as aggregate')
                ->whereIn('employee_id', $employeeIds)
                ->whereNotIn('status', ['مغلقة'])
                ->whereNull('deleted_at')
                ->groupBy('employee_id')
                ->pluck('aggregate', 'employee_id');

            foreach ($rows as $employeeId => $count) {
                $result[(int) $employeeId][] = 'يوجد عهد مالية مفتوحة ('.$count.')';
            }
        }

        if (Schema::hasTable('assets')) {
            $rows = DB::table('assets')
                ->selectRaw('current_holder_id, COUNT(*) as aggregate')
                ->whereIn('current_holder_id', $employeeIds)
                ->whereNull('deleted_at')
                ->groupBy('current_holder_id')
                ->pluck('aggregate', 'current_holder_id');

            foreach ($rows as $employeeId => $count) {
                $result[(int) $employeeId][] = 'يوجد أصول بعهدة الموظف ('.$count.')';
            }
        }

        return $result;
    }

    public function offboard(User $employee, User $actor): void
    {
        if ($employee->employment_status === User::STATUS_TERMINATED) {
            throw new \RuntimeException('علاقة الموظف منتهية بالفعل.');
        }

        $holds = $this->holds($employee);

        if ($holds !== []) {
            throw new \RuntimeException('لا يمكن إنهاء الخدمة: '.implode('، ', $holds));
        }

        DB::transaction(function () use ($employee, $actor) {
            $employee->transitionStatus(User::STATUS_TERMINATED, viaOffboarding: true);

            Task::create([
                'title' => 'تسليم المهام والعهد — '.$employee->name,
                'type' => 'single',
                'assigned_by' => $actor->id,
                'assigned_to' => $actor->id,
                'priority' => 'high',
                'status' => 'new',
                'due_date' => now()->addDays(3),
            ]);
        });
    }
}
