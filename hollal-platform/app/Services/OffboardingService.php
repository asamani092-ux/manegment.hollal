<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\EmployeeDocument;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskLifecycleService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 01-B5 / HR-5 — offboarding starts إسناد checklist tasks; account disable is
 * the last step after every task is completed and holds are cleared.
 * Early termination (before contract end) requires a clearance (مخالصة) attachment.
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
     * Active employment contract with the latest end_date, if any.
     */
    public function activeContract(User $employee): ?Contract
    {
        return Contract::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'active')
            ->orderByDesc('end_date')
            ->first();
    }

    /**
     * True when terminating before the active contract's end_date.
     * Time: O(1)
     */
    public function isEarlyTermination(User $employee, ?\Carbon\CarbonInterface $asOf = null): bool
    {
        $contract = $this->activeContract($employee);
        if (! $contract || ! $contract->end_date) {
            return false;
        }

        $asOf ??= now()->startOfDay();

        return $contract->end_date->copy()->startOfDay()->greaterThan($asOf);
    }

    public function hasClearanceDocument(User $employee): bool
    {
        return EmployeeDocument::query()
            ->where('user_id', $employee->id)
            ->where('type', EmployeeDocument::TYPE_CLEARANCE)
            ->whereNotNull('file_path')
            ->exists();
    }

    /**
     * @return list<string> outstanding holds preventing offboarding close
     */
    public function holds(User $employee): array
    {
        $holds = [];

        if (Schema::hasTable('custodies')) {
            $openCustodies = DB::table('custodies')
                ->where('employee_id', $employee->id)
                ->whereNotIn('status', ['مغلقة', 'مرفوضة'])
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

        if ($this->isEarlyTermination($employee) && ! $this->hasClearanceDocument($employee)) {
            $contract = $this->activeContract($employee);
            $holds[] = 'إنهاء مبكر قبل نهاية العقد ('.$contract?->end_date?->format('Y-m-d').') — أرفق مخالصة في الملف الوظيفي';
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
                ->whereNotIn('status', ['مغلقة', 'مرفوضة'])
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

        $offboardingStarted = User::query()
            ->whereIn('id', $employeeIds)
            ->whereNotNull('offboarding_started_at')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($incompleteCounts as $employeeId => $count) {
            if (! in_array((int) $employeeId, $offboardingStarted, true)) {
                continue;
            }
            $result[(int) $employeeId][] = 'مهام إنهاء غير مكتملة ('.$count.')';
        }

        $contracts = Contract::query()
            ->whereIn('employee_id', $employeeIds)
            ->where('status', 'active')
            ->orderByDesc('end_date')
            ->get(['employee_id', 'end_date'])
            ->groupBy('employee_id');

        $clearances = EmployeeDocument::query()
            ->whereIn('user_id', $employeeIds)
            ->where('type', EmployeeDocument::TYPE_CLEARANCE)
            ->whereNotNull('file_path')
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $today = now()->startOfDay();
        foreach ($employeeIds as $employeeId) {
            $contract = $contracts->get($employeeId)?->first();
            if (! $contract || ! $contract->end_date) {
                continue;
            }
            if ($contract->end_date->copy()->startOfDay()->greaterThan($today)
                && ! in_array((int) $employeeId, $clearances, true)) {
                $result[(int) $employeeId][] = 'إنهاء مبكر قبل نهاية العقد ('.$contract->end_date->format('Y-m-d').') — أرفق مخالصة في الملف الوظيفي';
            }
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
     * Early termination requires clearance document.
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
            $contract = $this->activeContract($employee);
            if ($contract && $contract->status === 'active') {
                $contract->forceFill(['status' => 'terminated'])->save();
            }
            $employee->transitionStatus(User::STATUS_TERMINATED, viaOffboarding: true);
        });
    }

    /**
     * Cancel offboarding before final disable: clear flag + delete open checklist tasks.
     * Time: O(t) | Space: O(1)
     */
    public function cancel(User $employee): void
    {
        if ($employee->employment_status === User::STATUS_TERMINATED) {
            throw new \RuntimeException('لا يمكن التراجع بعد انتهاء العلاقة.');
        }

        if (! $employee->offboarding_started_at) {
            throw new \RuntimeException('لا يوجد إنهاء علاقة جارٍ للتراجع عنه.');
        }

        DB::transaction(function () use ($employee) {
            Task::query()
                ->where('related_user_id', $employee->id)
                ->where('role_label', 'إنهاء_علاقة')
                ->where('status', '!=', TaskLifecycleService::STATUS_COMPLETED)
                ->delete();

            $employee->forceFill(['offboarding_started_at' => null])->save();
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
