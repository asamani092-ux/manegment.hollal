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

        $allowed = $parent ? (OrgUnit::CHILD_LEVEL[$parent->level] ?? []) : [];
        if ($parent && ! in_array($level, $allowed, true)) {
            throw new \InvalidArgumentException(
                'لا يمكن وضع «'.$level.'» تحت «'.$parent->level.'»'
            );
        }

        if (! $parent && $level === OrgUnit::LEVEL_ADMINISTRATION
            && OrgUnit::query()->where('level', OrgUnit::LEVEL_TOP_POSITION)->exists()) {
            throw new \InvalidArgumentException('الإدارة يجب أن تتبع منصباً أعلى');
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
    /**
     * يربط الإدارات الجذرية بآخر منصب أعلى. لا ينشئ المناصب تلقائيًا.
     * Time: O(n) | Space: O(1)
     */
    public function gatherAdministrationsUnderTop(): OrgUnit
    {
        $last = OrgUnit::query()
            ->where('level', OrgUnit::LEVEL_TOP_POSITION)
            ->orderByDesc('position')
            ->orderByDesc('id')
            ->first();
        if (! $last) {
            throw new \InvalidArgumentException('أضف منصبًا أعلى أولًا');
        }

        OrgUnit::query()
            ->where('level', OrgUnit::LEVEL_ADMINISTRATION)
            ->where(function ($query) {
                $query->whereNull('parent_id')
                    ->orWhereHas('parent', fn ($parent) => $parent->where('level', OrgUnit::LEVEL_TOP));
            })
            ->update(['parent_id' => $last->id]);

        return $last;
    }

    /**
     * منصب أعلى جديد في السلسلة، أبوه المنصب السابق أو حاوية الإدارة العليا.
     * Time: O(n) لإعادة ربط السلسلة | Space: O(n)
     */
    public function addTopPosition(string $title, ?int $occupantId, int $order): OrgUnit
    {
        $container = OrgUnit::query()->firstOrCreate(
            ['level' => OrgUnit::LEVEL_TOP, 'name' => 'الإدارة العليا'],
            ['position' => 0]
        );
        $seat = OrgUnit::query()->create([
            'name' => $title,
            'level' => OrgUnit::LEVEL_TOP_POSITION,
            'parent_id' => $container->id,
            'position' => $order,
        ]);
        $this->rechainTopPositions($container);
        if ($occupantId) {
            User::query()->whereKey($occupantId)->update(['org_unit_id' => $seat->id]);
        }

        return $seat->fresh();
    }

    /** Time: O(n) | Space: O(n) */
    public function rechainTopPositions(OrgUnit $container): void
    {
        $chain = OrgUnit::query()
            ->where('level', OrgUnit::LEVEL_TOP_POSITION)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
        $parentId = $container->id;
        foreach ($chain as $node) {
            if ((int) $node->parent_id !== (int) $parentId) {
                $node->forceFill(['parent_id' => $parentId])->save();
            }
            $parentId = $node->id;
        }
    }

    /** Time: O(1) | Space: O(1) */
    public function attachAdministration(OrgUnit $administration, OrgUnit $topPosition): void
    {
        if ($administration->level !== OrgUnit::LEVEL_ADMINISTRATION || $topPosition->level !== OrgUnit::LEVEL_TOP_POSITION) {
            throw new \InvalidArgumentException('الإدارة تتبع منصبًا أعلى');
        }
        $administration->forceFill(['parent_id' => $topPosition->id])->save();
    }

    /**
     * تعديل الاسم وموضع البطاقة دون مسح سجل النقل. Time: O(n) | Space: O(n)
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateUnit(OrgUnit $unit, string $name, ?int $parentId, array $attributes = []): OrgUnit
    {
        if ($this->parentIsInside($unit->id, $parentId)) {
            throw new \InvalidArgumentException('لا يمكن ربط الوحدة بنفسها أو بفرعها');
        }

        if ($unit->level === OrgUnit::LEVEL_ADMINISTRATION) {
            $hasTop = OrgUnit::query()->where('level', OrgUnit::LEVEL_TOP_POSITION)->exists();
            if ($hasTop && ! $parentId) {
                throw new \InvalidArgumentException('الإدارة يجب أن تتبع منصباً أعلى');
            }
            if ($parentId) {
                $parent = OrgUnit::query()->findOrFail($parentId);
                if ($parent->level !== OrgUnit::LEVEL_TOP_POSITION) {
                    throw new \InvalidArgumentException('الإدارة تتبع منصباً أعلى');
                }
            }
        } elseif ($unit->level === OrgUnit::LEVEL_TOP_POSITION || $unit->level === OrgUnit::LEVEL_TOP) {
            $parentId = $unit->parent_id;
        } elseif ($parentId) {
            $parent = OrgUnit::query()->findOrFail($parentId);
            $allowed = OrgUnit::CHILD_LEVEL[$parent->level] ?? [];
            if (! in_array($unit->level, $allowed, true)) {
                throw new \InvalidArgumentException('لا يمكن وضع «'.$unit->level.'» تحت «'.$parent->level.'»');
            }
        } else {
            throw new \InvalidArgumentException('اختر الوحدة التي يتبعها هذا المستوى');
        }

        $unit->forceFill([
            'name' => $name,
            'parent_id' => $parentId,
            'job_purpose' => $attributes['job_purpose'] ?? $unit->job_purpose,
            'job_responsibilities' => $attributes['job_responsibilities'] ?? $unit->job_responsibilities,
        ])->save();

        return $unit->fresh();
    }

    /**
     * إخفاء الإدارة وفروعها دون مسح سجل النقل. Time: O(n) | Space: O(n)
     */
    public function deleteAdministration(OrgUnit $administration, ?User $actor = null): void
    {
        if ($administration->level !== OrgUnit::LEVEL_ADMINISTRATION) {
            throw new \InvalidArgumentException('الحذف من الشجرة للإدارات فقط');
        }

        $this->hideSubtree($administration, $actor, 'حذف الإدارة');
    }

    /**
     * إخفاء أي بطاقة ظاهرة وفروعها. Time: O(n) | Space: O(n)
     */
    public function deleteUnitNode(OrgUnit $unit, ?User $actor = null): void
    {
        if ($unit->level === OrgUnit::LEVEL_TOP) {
            throw new \InvalidArgumentException('لا يُحذف جذر الإدارة العليا');
        }

        $this->hideSubtree($unit, $actor, 'حذف الوحدة');
    }

    /** Time: O(n) | Space: O(n) */
    private function parentIsInside(int $unitId, ?int $parentId): bool
    {
        if (! $parentId || $parentId === $unitId) {
            return $parentId === $unitId;
        }

        $byId = OrgUnit::query()->get(['id', 'parent_id'])->keyBy('id');
        $cursor = $parentId;
        $guard = 0;
        while ($cursor && $guard < $byId->count() + 1) {
            if ((int) $cursor === $unitId) {
                return true;
            }
            $cursor = $byId->get($cursor)?->parent_id;
            $guard++;
        }

        return false;
    }

    /** Time: O(n) | Space: O(n) */
    private function hideSubtree(OrgUnit $unit, ?User $actor, string $reason): void
    {
        $units = OrgUnit::query()->get(['id', 'parent_id']);
        $childrenOf = $units->groupBy(fn (OrgUnit $row) => (string) $row->parent_id);
        $ids = [];
        $walk = function (int $id) use (&$walk, &$ids, $childrenOf): void {
            $ids[] = $id;
            foreach ($childrenOf[(string) $id] ?? [] as $child) {
                $walk((int) $child->id);
            }
        };
        $walk($unit->id);

        DB::transaction(function () use ($ids, $unit, $actor, $reason) {
            User::query()->whereIn('org_unit_id', $ids)->orderBy('id')->each(function (User $user) use ($actor, $reason) {
                $this->transfer($user, null, $reason, $actor);
            });
            OrgUnit::query()->whereIn('id', $ids)->orderByDesc('id')->each(function (OrgUnit $row) {
                $row->delete();
            });
            app(AuditLogService::class)->record('structure.unit.delete', $unit, [
                'ids' => $ids,
            ], $actor);
        });
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
