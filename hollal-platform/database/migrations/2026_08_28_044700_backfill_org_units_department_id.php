<?php

use App\Models\Department;
use App\Models\OrgUnit;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Backfill org_units.department_id from administration name ↔ departments.name.
 * Time: O(n) | Space: O(n)
 */
return new class extends Migration
{
    public function up(): void
    {
        $departmentsByName = Department::query()
            ->get(['id', 'name'])
            ->keyBy(fn (Department $dept) => mb_strtolower(trim($dept->name)));

        $units = OrgUnit::query()->get(['id', 'parent_id', 'level', 'name', 'department_id']);
        $byId = $units->keyBy('id');

        $resolveRootAdmin = function (OrgUnit $unit) use ($byId, &$resolveRootAdmin): ?OrgUnit {
            if ($unit->level === OrgUnit::LEVEL_ADMINISTRATION) {
                return $unit;
            }

            if ($unit->parent_id === null) {
                return null;
            }

            $parent = $byId->get($unit->parent_id);

            return $parent ? $resolveRootAdmin($parent) : null;
        };

        foreach ($units as $unit) {
            if ($unit->department_id !== null) {
                continue;
            }

            $root = $resolveRootAdmin($unit);
            if ($root === null) {
                continue;
            }

            $dept = $departmentsByName->get(mb_strtolower(trim($root->name)));
            if ($dept === null) {
                continue;
            }

            DB::table('org_units')->where('id', $unit->id)->update(['department_id' => $dept->id]);
        }
    }

    public function down(): void
    {
        // Append-only backfill — no rollback.
    }
};
