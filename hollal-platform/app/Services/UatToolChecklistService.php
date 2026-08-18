<?php

namespace App\Services;

use App\Models\UatToolChecklist;
use App\Models\UatToolChecklistSnapshot;
use App\Models\User;

/**
 * Shared UAT checklist: current row + append-only snapshots.
 * Time: O(n) tools per save | Space: O(n) current + O(k·n) history.
 */
class UatToolChecklistService
{
    public function current(?string $slot = null): ?array
    {
        $row = UatToolChecklist::query()
            ->where('slot', $slot ?? UatToolChecklist::SLOT_SHARED)
            ->first();

        if (! $row) {
            return null;
        }

        return $this->toState($row);
    }

    /**
     * @param  array{verdicts?: mixed, tags?: mixed, notes?: mixed, activePhase?: mixed}  $payload
     * @return array{verdicts: array<string, string>, tags: array<string, string>, notes: array<string, string>, activePhase: int}
     */
    public function save(array $payload, ?User $actor = null, bool $snapshot = false, string $source = 'persist'): array
    {
        $state = $this->normalize($payload);

        $row = UatToolChecklist::query()->firstOrNew([
            'slot' => UatToolChecklist::SLOT_SHARED,
        ]);

        $row->fill([
            'active_phase' => $state['activePhase'],
            'verdicts' => $state['verdicts'],
            'tags' => $state['tags'],
            'notes' => $state['notes'],
            'updated_by' => $actor?->id,
        ]);
        $row->save();

        if ($snapshot) {
            UatToolChecklistSnapshot::query()->create([
                'checklist_id' => $row->id,
                'source' => $source !== '' ? $source : 'snapshot',
                'active_phase' => $state['activePhase'],
                'verdicts' => $state['verdicts'],
                'tags' => $state['tags'],
                'notes' => $state['notes'],
                'created_by' => $actor?->id,
                'created_at' => now(),
            ]);
        }

        return $state;
    }

    /**
     * @param  array{verdicts?: mixed, tags?: mixed, notes?: mixed, activePhase?: mixed}  $payload
     * @return array{verdicts: array<string, string>, tags: array<string, string>, notes: array<string, string>, activePhase: int}
     */
    public function normalize(array $payload): array
    {
        $allowedIds = $this->toolIds();
        $allowedVerdicts = array_fill_keys(config('uat_tools.verdicts', []), true);
        $allowedTags = array_fill_keys(config('uat_tools.note_tags', []), true);

        $verdicts = [];
        foreach ($this->stringMap($payload['verdicts'] ?? []) as $id => $value) {
            if (! isset($allowedIds[$id]) || ! isset($allowedVerdicts[$value])) {
                continue;
            }
            $verdicts[$id] = $value;
        }

        $tags = [];
        foreach ($this->stringMap($payload['tags'] ?? []) as $id => $value) {
            if (! isset($allowedIds[$id]) || ! isset($allowedTags[$value])) {
                continue;
            }
            $tags[$id] = $value;
        }

        $notes = [];
        foreach ($this->stringMap($payload['notes'] ?? []) as $id => $value) {
            if (! isset($allowedIds[$id])) {
                continue;
            }
            $notes[$id] = mb_substr($value, 0, 4000);
        }

        $phase = (int) ($payload['activePhase'] ?? 1);
        if ($phase < 1 || $phase > 3) {
            $phase = 1;
        }

        return [
            'verdicts' => $verdicts,
            'tags' => $tags,
            'notes' => $notes,
            'activePhase' => $phase,
        ];
    }

    /** @return array<string, true> */
    private function toolIds(): array
    {
        $ids = [];
        foreach (config('uat_tools.groups', []) as $group) {
            foreach ($group['items'] ?? [] as $item) {
                if (isset($item['id']) && is_string($item['id'])) {
                    $ids[$item['id']] = true;
                }
            }
        }

        return $ids;
    }

    /** @return array<string, string> */
    private function stringMap(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $key => $item) {
            if (! is_string($key) || ! is_scalar($item)) {
                continue;
            }
            $out[$key] = (string) $item;
        }

        return $out;
    }

    /**
     * @return array{verdicts: array<string, string>, tags: array<string, string>, notes: array<string, string>, activePhase: int}
     */
    private function toState(UatToolChecklist $row): array
    {
        return [
            'verdicts' => is_array($row->verdicts) ? $row->verdicts : [],
            'tags' => is_array($row->tags) ? $row->tags : [],
            'notes' => is_array($row->notes) ? $row->notes : [],
            'activePhase' => (int) $row->active_phase,
        ];
    }
}
