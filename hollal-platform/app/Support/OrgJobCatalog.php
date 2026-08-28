<?php

namespace App\Support;

use App\Models\Department;
use App\Models\OrgUnit;
use Illuminate\Support\Collection;

/**
 * Job cards (OrgUnit level=وظيفة) filtered by department.
 * Time: O(k) direct | O(n) fallback | Space: O(k)
 */
final class OrgJobCatalog
{
    /**
     * @return Collection<int, OrgUnit>
     */
    public static function jobsForDepartment(?int $departmentId): Collection
    {
        if ($departmentId === null) {
            return collect();
        }

        $direct = OrgUnit::query()
            ->where('level', OrgUnit::LEVEL_JOB)
            ->where('department_id', $departmentId)
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id', 'department_id']);

        if ($direct->isNotEmpty()) {
            return $direct;
        }

        return self::jobsByAdministrationName($departmentId);
    }

    /**
     * @return list<array{id:int,label:string}>
     */
    public static function optionsForDepartment(?int $departmentId): array
    {
        return self::jobsForDepartment($departmentId)
            ->map(fn (OrgUnit $job) => [
                'id' => (int) $job->id,
                'label' => (string) $job->name,
            ])
            ->values()
            ->all();
    }

    public static function resolveTitle(?int $jobOrgUnitId): ?string
    {
        if ($jobOrgUnitId === null) {
            return null;
        }

        $name = OrgUnit::query()
            ->where('level', OrgUnit::LEVEL_JOB)
            ->whereKey($jobOrgUnitId)
            ->value('name');

        return is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * Fallback: match administration org node name to department name.
     *
     * @return Collection<int, OrgUnit>
     */
    private static function jobsByAdministrationName(int $departmentId): Collection
    {
        $deptName = Department::query()->whereKey($departmentId)->value('name');
        if (! is_string($deptName) || trim($deptName) === '') {
            return collect();
        }

        $normalized = mb_strtolower(trim($deptName));
        $adminIds = OrgUnit::query()
            ->where('level', OrgUnit::LEVEL_ADMINISTRATION)
            ->whereRaw('LOWER(TRIM(name)) = ?', [$normalized])
            ->pluck('id');

        if ($adminIds->isEmpty()) {
            return collect();
        }

        $units = OrgUnit::query()->get(['id', 'parent_id', 'level', 'name']);
        $byParent = $units->groupBy('parent_id');
        $jobIds = collect();

        $walk = function (int $parentId) use (&$walk, $byParent, &$jobIds): void {
            foreach ($byParent[$parentId] ?? [] as $unit) {
                if ($unit->level === OrgUnit::LEVEL_JOB) {
                    $jobIds->push($unit->id);
                } else {
                    $walk((int) $unit->id);
                }
            }
        };

        foreach ($adminIds as $adminId) {
            $walk((int) $adminId);
        }

        if ($jobIds->isEmpty()) {
            return collect();
        }

        return OrgUnit::query()
            ->whereIn('id', $jobIds->unique()->all())
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id', 'department_id']);
    }
}
