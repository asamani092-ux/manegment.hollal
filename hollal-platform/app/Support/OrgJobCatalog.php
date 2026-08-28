<?php

namespace App\Support;

use App\Models\OrgUnit;
use Illuminate\Support\Collection;

/**
 * Job cards (OrgUnit level=وظيفة) filtered by department.
 * Time: O(k) | Space: O(k) for k jobs in the department.
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

        return OrgUnit::query()
            ->where('level', OrgUnit::LEVEL_JOB)
            ->where('department_id', $departmentId)
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id', 'department_id']);
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
}
