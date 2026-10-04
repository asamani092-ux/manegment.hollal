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
                'الترتيب الهرمي إدارة ← قسم ← وظيفة لا يسمح بوضع «'.$level.'» تحت «'.$parent->level.'»'
            );
        }

        if (! $parent && ! in_array($level, [OrgUnit::LEVEL_TOP, OrgUnit::LEVEL_ADMINISTRATION], true)) {
            throw new \InvalidArgumentException('جذر الشجرة يجب أن يكون إدارة أو الإدارة العليا');
        }

        unset($attributes['department_id']);

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
        ?string $reason = null,
        ?User $actor = null,
        ?string $effectiveOn = null,
    ): EmployeeTransfer {
        return DB::transaction(function () use ($employee, $toUnit, $reason, $actor, $effectiveOn) {
            $transfer = EmployeeTransfer::create([
                'user_id' => $employee->id,
                'from_org_unit_id' => $employee->org_unit_id,
                'to_org_unit_id' => $toUnit?->id,
                'effective_on' => $effectiveOn ?? now()->toDateString(),
                'reason' => $reason,
                'moved_by' => $actor?->id,
            ]);

            $employee->forceFill([
                'org_unit_id' => $toUnit?->id,
            ])->save();
            $this->recomputeDerivedManager($employee->fresh());

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
    public function gatherAdministrationsUnderTop(): OrgUnit
    {
        $top = OrgUnit::query()->firstOrCreate(
            ['level' => OrgUnit::LEVEL_TOP, 'name' => 'الإدارة العليا'],
            ['position' => 0]
        );
        OrgUnit::query()
            ->where('level', OrgUnit::LEVEL_ADMINISTRATION)
            ->whereNull('parent_id')
            ->update(['parent_id' => $top->id]);

        return $top;
    }

    public function placeEmployee(User $user, OrgUnit $job): User
    {
        if ($job->level !== OrgUnit::LEVEL_JOB) {
            throw new \InvalidArgumentException('التعيين يكون على وظيفة');
        }

        return DB::transaction(function () use ($user, $job) {
            if ($user->auto_role_name && $user->auto_role_name !== $job->default_role) {
                $user->removeRole($user->auto_role_name);
            }
            if ($job->default_role) {
                $user->assignRole($job->default_role);
            }
            $user->forceFill([
                'org_unit_id' => $job->id,
                'auto_role_name' => $job->default_role,
            ])->save();
            $this->recomputeDerivedManager($user->fresh());

            app(AuditLogService::class)->record('structure.place', $user, [
                'org_unit_id' => $job->id,
                'role' => $job->default_role,
                'source' => 'الهيكل',
            ]);

            return $user->fresh();
        });
    }

    /**
     * Head of the nearest ancestor whose manager is someone else.
     * Time: O(depth) | Space: O(1)
     */
    public function deriveManagerId(User $user): ?int
    {
        $unit = $user->orgUnit;
        while ($unit) {
            if ($unit->manager_id && (int) $unit->manager_id !== (int) $user->id) {
                return (int) $unit->manager_id;
            }
            $unit = $unit->parent;
        }

        return null;
    }

    public function recomputeDerivedManager(User $user): void
    {
        if ($user->manager_override_id) {
            return;
        }
        $user->loadMissing('orgUnit.parent');
        $derived = $this->deriveManagerId($user);
        if ($derived === null || (int) $user->manager_id === $derived) {
            return;
        }
        $user->forceFill(['manager_id' => $derived])->save();
    }

    public function recomputeManagersUnder(OrgUnit $unit): void
    {
        $ids = $this->descendantIds($unit);
        $ids[] = $unit->id;
        User::query()->whereIn('org_unit_id', $ids)->each(function (User $user): void {
            $this->recomputeDerivedManager($user);
        });
    }

    /** @return list<int> */
    private function descendantIds(OrgUnit $unit): array
    {
        $ids = [];
        $children = OrgUnit::query()->where('parent_id', $unit->id)->get();
        foreach ($children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $this->descendantIds($child));
        }

        return $ids;
    }

    public function historyFor(User $employee): Collection
    {
        return EmployeeTransfer::where('user_id', $employee->id)
            ->orderByDesc('effective_on')
            ->orderByDesc('id')
            ->get();
    }
}
