<?php

namespace App\Support;

use App\Models\OrgUnit;
use Illuminate\Support\Facades\Cache;

/**
 * Shared org-tree helpers for department-scoped document access.
 * Time: O(depth) | Space: O(units under root) when listing.
 */
final class OrgAdministration
{
    public static function rootId(int $unitId): ?int
    {
        $current = OrgUnit::query()->find($unitId, ['id', 'parent_id']);
        while ($current && $current->parent_id) {
            $current = OrgUnit::query()->find($current->parent_id, ['id', 'parent_id']);
        }

        return $current?->id;
    }

    public static function sameRoot(int $aUnitId, int $bUnitId): bool
    {
        $a = self::rootId($aUnitId);
        $b = self::rootId($bUnitId);

        return $a !== null && $a === $b;
    }

    /**
     * @return list<int>
     */
    public static function unitIdsUnderRoot(int $rootId): array
    {
        return Cache::remember('org_admin_units_'.$rootId, 60, function () use ($rootId) {
            $ids = [$rootId];
            $frontier = [$rootId];
            while ($frontier !== []) {
                $children = OrgUnit::query()
                    ->whereIn('parent_id', $frontier)
                    ->pluck('id')
                    ->all();
                $frontier = $children;
                foreach ($children as $id) {
                    $ids[] = (int) $id;
                }
            }

            return $ids;
        });
    }
}
