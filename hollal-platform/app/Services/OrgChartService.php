<?php

namespace App\Services;

use App\Models\OrgUnit;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * شجرة الهيكل للعرض. تحميل مسبق بلا تكرار استعلامات.
 * Time: O(n) | Space: O(n)
 */
class OrgChartService
{
    /** ألوان الإدارات دون كحلي المناصب ولا الذهبي. */
    public const PALETTE = ['#1B6B93', '#27A588', '#6B4C9A', '#C45C26', '#3D6B4F', '#8A5A44'];

    private int $adminIndex = 0;

    private bool $seenAdministration = false;

    /**
     * @return list<array<string, mixed>>
     */
    public function tree(): array
    {
        $this->adminIndex = 0;
        $this->seenAdministration = false;

        $units = OrgUnit::query()
            ->with('manager:id,name')
            ->orderBy('position')
            ->orderBy('id')
            ->get();
        $people = User::query()
            ->whereNotNull('org_unit_id')
            ->orderBy('name')
            ->get(['id', 'name', 'org_unit_id']);

        $byParent = $units->groupBy(fn (OrgUnit $unit) => $unit->parent_id === null ? 'root' : (string) $unit->parent_id);
        $byUnit = $people->groupBy('org_unit_id');

        $build = function (OrgUnit $unit, string $accent) use (&$build, $byParent, $byUnit): array {
            $own = $this->accentFor($unit, $accent);
            $children = ($byParent[(string) $unit->id] ?? collect())
                ->map(fn (OrgUnit $child) => $build($child, $own))
                ->values()
                ->all();

            return $this->node($unit, $byUnit->get($unit->id, collect()), $own, $children);
        };

        $roots = $byParent['root'] ?? collect();
        $visible = collect();
        foreach ($roots as $root) {
            if ($root->level === OrgUnit::LEVEL_TOP) {
                $visible = $visible->merge($byParent[(string) $root->id] ?? collect());
            } else {
                $visible->push($root);
            }
        }

        $hasTop = $units->contains(fn (OrgUnit $unit) => $unit->level === OrgUnit::LEVEL_TOP_POSITION);
        $linked = [];
        $unlinked = [];
        foreach ($visible as $unit) {
            $node = $build($unit, '#0F3446');
            if ($hasTop && $this->isUnlinkedAdministration($unit, $units)) {
                $unlinked[] = $node;
            } else {
                $linked[] = $node;
            }
        }
        usort($linked, fn (array $a, array $b) => ($a['type'] === 'top' ? 0 : 1) <=> ($b['type'] === 'top' ? 0 : 1));

        return ['roots' => $linked, 'unlinked' => $unlinked];
    }

    /** @param  Collection<int, OrgUnit>  $units */
    private function isUnlinkedAdministration(OrgUnit $unit, Collection $units): bool
    {
        if ($unit->level !== OrgUnit::LEVEL_ADMINISTRATION) {
            return false;
        }
        if ($unit->parent_id === null) {
            return true;
        }
        $parent = $units->firstWhere('id', $unit->parent_id);

        return $parent !== null && $parent->level === OrgUnit::LEVEL_TOP;
    }

    /**
     * @param  Collection<int, User>  $occupants
     * @param  list<array<string, mixed>>  $children
     * @return array<string, mixed>
     */
    private function node(OrgUnit $unit, Collection $occupants, string $accent, array $children): array
    {
        $shown = $occupants->take(2)->map(fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'initials' => mb_substr($user->name, 0, 1),
        ])->values()->all();
        $memberCount = $occupants->count();
        $descendants = count($children);
        foreach ($children as $child) {
            $memberCount += (int) $child['member_count'];
            $descendants += (int) $child['descendants'];
        }
        $type = match ($unit->level) {
            OrgUnit::LEVEL_TOP_POSITION => 'top',
            OrgUnit::LEVEL_ADMINISTRATION => 'admin',
            OrgUnit::LEVEL_UNIT => 'section',
            OrgUnit::LEVEL_JOB => 'job',
            default => 'unit',
        };
        $collapseMobile = false;
        if ($type === 'admin') {
            $collapseMobile = $this->seenAdministration;
            $this->seenAdministration = true;
        }

        return [
            'id' => $unit->id,
            'type' => $type,
            'title' => $unit->name,
            'head' => $unit->manager?->name,
            'occupants' => $shown,
            'extra' => max(0, $occupants->count() - 2),
            'vacant' => $unit->isPosition() && $occupants->isEmpty(),
            'role' => $unit->default_role,
            'accent' => $accent,
            'purpose' => $unit->job_purpose,
            'responsibilities' => $unit->job_responsibilities ?? [],
            'member_count' => $memberCount,
            'descendants' => $descendants,
            'collapse_mobile' => $collapseMobile,
            'children' => $children,
        ];
    }

    private function accentFor(OrgUnit $unit, string $inherited): string
    {
        if ($unit->level === OrgUnit::LEVEL_ADMINISTRATION) {
            $color = self::PALETTE[$this->adminIndex % count(self::PALETTE)];
            $this->adminIndex++;

            return $color;
        }
        if (in_array($unit->level, [OrgUnit::LEVEL_UNIT, OrgUnit::LEVEL_JOB], true)) {
            return $inherited;
        }

        return '#0F3446';
    }
}
