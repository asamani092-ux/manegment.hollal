<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use App\Services\TaskLifecycleService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 01-B5 / HR-5 — offboarding starts إسناد checklist tasks; account disable is
 * the last step after every task is completed and holds are cleared.
 */
class OffboardingService
{
    public const CHECKLIST = [
        'تسليم الأعمال الجارية',
        'استلام العهد المالية والأصول',
        'نقل الملفات والمستندات',
        'إصدار المخالصة',
    ];

    /**
     * @return list<string> outstanding holds preventing offboarding close
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

        $incomplete = $this->incompleteTasks($employee)->count();
        if ($employee->offboarding_started_at && $incomplete > 0) {
            $holds[] = 'مهام إنهاء غير مكتملة ('.$incomplete.')';
        }

        return $holds;
    }

    /**
     * Batched variant of holds() for list screens — 2 queries + 1 task query.
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

        $incompleteCounts = Task::query()
            ->whereIn('related_user_id', $employeeIds)
            ->where('role_label', 'إنهاء_علاقة')
            ->where('status', '!=', TaskLifecycleService::STATUS_COMPLETED)
            ->selectRaw('related_user_id, COUNT(*) as aggregate')
            ->groupBy('related_user_id')
            ->pluck('aggregate', 'related_user_id');

        foreach ($incompleteCounts as $employeeId => $count) {
            $result[(int) $employeeId][] = 'مهام إنهاء غير مكتملة ('.$count.')';
        }

        return $result;
    }

    /**
     * Starts the offboarding checklist in إسناد. Does not disable the account.
     * Public signature of offboard() is unchanged; disable moved to complete().
     */
    public function offboard(User $employee, User $actor): void
    {
        if ($employee->employment_status === User::STATUS_TERMINATED) {
            throw new \RuntimeException('علاقة الموظف منتهية بالفعل.');
        }

        if ($employee->offboarding_started_at) {
            throw new \RuntimeException('بدأ إنهاء العلاقة بالفعل — أكمل المهام ثم عطّل الحساب.');
        }

        DB::transaction(function () use ($employee, $actor) {
            $employee->forceFill(['offboarding_started_at' => now()])->save();

            foreach (self::CHECKLIST as $index => $title) {
                Task::create([
                    'title' => $title.' — '.$employee->name,
                    'type' => 'single',
                    'assigned_by' => $actor->id,
                    'assigned_to' => $actor->id,
                    'related_user_id' => $employee->id,
                    'role_label' => 'إنهاء_علاقة',
                    'priority' => 'high',
                    'status' => 'new',
                    'due_date' => now()->addDays(($index + 1) * 2),
                ]);
            }
        });
    }

    /**
     * Last step: disable login after checklist + custody/asset holds are clear.
     * Time: O(t) tasks | Space: O(1)
     */
    public function complete(User $employee, User $actor): void
    {
        if ($employee->employment_status === User::STATUS_TERMINATED) {
            throw new \RuntimeException('علاقة الموظف منتهية بالفعل.');
        }

        if (! $employee->offboarding_started_at) {
            throw new \RuntimeException('ابدأ إنهاء العلاقة أولاً لإنشاء مهام التسليم.');
        }

        $holds = $this->holds($employee);
        if ($holds !== []) {
            throw new \RuntimeException('لا يمكن إغلاق إنهاء العلاقة: '.implode('، ', $holds));
        }

        DB::transaction(function () use ($employee) {
            $employee->transitionStatus(User::STATUS_TERMINATED, viaOffboarding: true);
        });
    }

    /** @return Collection<int, Task> */
    public function incompleteTasks(User $employee): Collection
    {
        return Task::query()
            ->where('related_user_id', $employee->id)
            ->where('role_label', 'إنهاء_علاقة')
            ->where('status', '!=', TaskLifecycleService::STATUS_COMPLETED)
            ->get();
    }
}
