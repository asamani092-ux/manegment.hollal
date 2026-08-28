<?php

namespace App\Support;

use App\Models\OrgUnit;
use Illuminate\Support\Collection;

/**
 * Cascading org placement: إدارة → قسم → وظيفة from org_units only.
 */
final class OrgJobCatalog
{
    /**
     * @return Collection<int, OrgUnit>
     */
    public static function administrations(): Collection
    {
        return OrgUnit::query()
            ->where('level', OrgUnit::LEVEL_ADMINISTRATION)
            ->orderBy('name')
            ->get(['id', 'name', 'level', 'parent_id']);
    }

    /**
     * Children at مستوى قسم under an administration. Time: O(k) | Space: O(k)
     *
     * @return Collection<int, OrgUnit>
     */
    public static function unitsForAdministration(?int $administrationId): Collection
    {
        if ($administrationId === null) {
            return collect();
        }

        return OrgUnit::query()
            ->where('level', OrgUnit::LEVEL_UNIT)
            ->where('parent_id', $administrationId)
            ->orderBy('name')
            ->get(['id', 'name', 'level', 'parent_id']);
    }

    /**
     * Job cards under a قسم. Time: O(k) | Space: O(k)
     *
     * @return Collection<int, OrgUnit>
     */
    public static function jobsForUnit(?int $unitId): Collection
    {
        if ($unitId === null) {
            return collect();
        }

        return OrgUnit::query()
            ->where('level', OrgUnit::LEVEL_JOB)
            ->where('parent_id', $unitId)
            ->orderBy('name')
            ->get(['id', 'name', 'level', 'parent_id']);
    }

    /**
     * @return list<array{id:int,label:string}>
     */
    public static function optionsForUnit(?int $unitId): array
    {
        return self::jobsForUnit($unitId)
            ->map(fn (OrgUnit $job) => [
                'id' => (int) $job->id,
                'label' => (string) $job->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id:int,label:string}>
     */
    public static function optionsForAdministrations(): array
    {
        return self::administrations()
            ->map(fn (OrgUnit $u) => ['id' => (int) $u->id, 'label' => (string) $u->name])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id:int,label:string}>
     */
    public static function optionsForUnits(?int $administrationId): array
    {
        return self::unitsForAdministration($administrationId)
            ->map(fn (OrgUnit $u) => ['id' => (int) $u->id, 'label' => (string) $u->name])
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
     * Resolve إدارة + قسم ancestors from a وظيفة node. Time: O(1) | Space: O(1)
     *
     * @return array{administration_id:?int,unit_id:?int}
     */
    public static function cascadeFromJob(?int $jobOrgUnitId): array
    {
        if ($jobOrgUnitId === null) {
            return ['administration_id' => null, 'unit_id' => null];
        }

        $job = OrgUnit::query()->whereKey($jobOrgUnitId)->first(['id', 'level', 'parent_id']);
        if (! $job || $job->level !== OrgUnit::LEVEL_JOB) {
            return ['administration_id' => null, 'unit_id' => null];
        }

        $unit = $job->parent_id
            ? OrgUnit::query()->whereKey($job->parent_id)->first(['id', 'level', 'parent_id'])
            : null;

        if (! $unit || $unit->level !== OrgUnit::LEVEL_UNIT) {
            return ['administration_id' => null, 'unit_id' => null];
        }

        $admin = $unit->parent_id
            ? OrgUnit::query()->whereKey($unit->parent_id)->first(['id', 'level'])
            : null;

        return [
            'administration_id' => ($admin && $admin->level === OrgUnit::LEVEL_ADMINISTRATION)
                ? (int) $admin->id
                : null,
            'unit_id' => (int) $unit->id,
        ];
    }
}
