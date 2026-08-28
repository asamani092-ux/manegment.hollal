<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill org_units.department_id from administration name ↔ departments.name.
 * Uses query builder only (Department model removed in HR Round 4).
 * Time: O(n) | Space: O(n)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('departments') || ! Schema::hasColumn('org_units', 'department_id')) {
            return;
        }

        $departmentsByName = DB::table('departments')
            ->whereNull('deleted_at')
            ->get(['id', 'name'])
            ->keyBy(fn ($dept) => mb_strtolower(trim((string) $dept->name)));

        $units = DB::table('org_units')->whereNull('deleted_at')->get(['id', 'parent_id', 'level', 'name', 'department_id']);
        $byId = $units->keyBy('id');

        $resolveRootAdmin = function ($unit) use ($byId, &$resolveRootAdmin) {
            if ($unit->level === 'إدارة') {
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

            $dept = $departmentsByName->get(mb_strtolower(trim((string) $root->name)));
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
