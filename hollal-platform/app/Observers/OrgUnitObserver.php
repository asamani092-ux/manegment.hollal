<?php

namespace App\Observers;

use App\Models\OrgUnit;
use App\Services\OrgStructureService;

/**
 * Recompute derived managers when a unit head changes.
 * Time: O(members) | Space: O(members).
 */
class OrgUnitObserver
{
    public function updated(OrgUnit $unit): void
    {
        if ($unit->wasChanged('manager_id')) {
            app(OrgStructureService::class)->recomputeManagersUnder($unit);
        }
    }
}
