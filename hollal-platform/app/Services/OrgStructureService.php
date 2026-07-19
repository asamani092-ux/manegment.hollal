<?php

namespace App\Services;

use App\Models\EmployeeTransfer;
use App\Models\OrgUnit;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * 09-B1 — the org tree and employee transfers.
 */
class OrgStructureService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createUnit(string $name, string $level, ?OrgUnit $parent = null, array $attributes = []): OrgUnit
    {
        if (! array_key_exists($level, OrgUnit::CHILD_LEVEL)) {
            throw new \InvalidArgumentException('مستوى تنظيمي غير معروف');
        }

        if ($parent && OrgUnit::CHILD_LEVEL[$parent->level] !== $level) {
            throw new \InvalidArgumentException(
                'الترتيب الهرمي إدارة ← وحدة ← وظيفة لا يسمح بوضع «'.$level.'» تحت «'.$parent->level.'»'
            );
        }

        if (! $parent && $level !== OrgUnit::LEVEL_ADMINISTRATION) {
            throw new \InvalidArgumentException('جذر الشجرة يجب أن يكون إدارة');
        }

        return OrgUnit::create(array_merge($attributes, [
            'name' => $name,
            'level' => $level,
            'parent_id' => $parent?->id,
            'position' => OrgUnit::where('parent_id', $parent?->id)->count(),
        ]));
    }

    /**
     * The whole tree, eager-loaded for the visual chart.
     *
     * @return Collection<int, OrgUnit>
     */
    public function tree(): Collection
    {
        $units = OrgUnit::orderBy('position')->get();
        $byParent = $units->groupBy('parent_id');

        $attach = function (OrgUnit $unit) use (&$attach, $byParent) {
            $unit->setRelation('children', ($byParent[$unit->id] ?? collect())->each($attach)->values());

            return $unit;
        };

        return ($byParent[null] ?? collect())->each($attach)->values();
    }

    /**
     * Move an employee. The previous placement is recorded, never overwritten:
     * every transfer stays queryable as history.
     */
    public function transfer(
        User $employee,
        ?OrgUnit $toUnit,
        ?int $toDepartmentId = null,
        ?string $reason = null,
        ?User $actor = null,
        ?string $effectiveOn = null,
    ): EmployeeTransfer {
        return DB::transaction(function () use ($employee, $toUnit, $toDepartmentId, $reason, $actor, $effectiveOn) {
            $transfer = EmployeeTransfer::create([
                'user_id' => $employee->id,
                'from_org_unit_id' => $employee->org_unit_id,
                'to_org_unit_id' => $toUnit?->id,
                'from_department_id' => $employee->department_id,
                'to_department_id' => $toDepartmentId ?? $employee->department_id,
                'effective_on' => $effectiveOn ?? now()->toDateString(),
                'reason' => $reason,
                'moved_by' => $actor?->id,
            ]);

            $employee->forceFill([
                'org_unit_id' => $toUnit?->id,
                'department_id' => $toDepartmentId ?? $employee->department_id,
            ])->save();

            app(AuditLogService::class)->record(
                action: 'structure.transfer',
                target: $employee,
                metadata: [
                    'from_org_unit_id' => $transfer->from_org_unit_id,
                    'to_org_unit_id' => $transfer->to_org_unit_id,
                    'reason' => $reason,
                ],
                actor: $actor,
            );

            return $transfer;
        });
    }

    /**
     * @return Collection<int, EmployeeTransfer>
     */
    public function historyFor(User $employee): Collection
    {
        return EmployeeTransfer::where('user_id', $employee->id)
            ->orderByDesc('effective_on')
            ->orderByDesc('id')
            ->get();
    }
}
