<?php

namespace App\Livewire\Uat;

use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Interactive UAT tools evaluation page (pre-production only).
 * Three gated phases — next unlocks only when current is all «يعتمد».
 * Time: O(n) tools | Space: O(n) client state.
 */
class ToolsChecklist extends Component
{
    public function mount(): void
    {
        abort_unless((bool) config('uat_tools.enabled'), 404);
        abort_unless(auth()->user()?->can('dashboard.view'), 403);
    }

    public function render(): View
    {
        $groups = config('uat_tools.groups', []);
        $phases = config('uat_tools.phases', []);
        $total = collect($groups)->sum(fn (array $g) => count($g['items'] ?? []));

        $phaseTotals = [];
        foreach ($phases as $phase) {
            $ids = $phase['group_ids'] ?? [];
            $phaseTotals[$phase['id']] = collect($groups)
                ->whereIn('id', $ids)
                ->sum(fn (array $g) => count($g['items'] ?? []));
        }

        return view('livewire.uat.tools-checklist', [
            'groups' => $groups,
            'phases' => $phases,
            'phaseTotals' => $phaseTotals,
            'verdicts' => config('uat_tools.verdicts', []),
            'noteTags' => config('uat_tools.note_tags', []),
            'baseline' => config('uat_tools.baseline', []),
            'baselineRound3' => config('uat_tools.baseline_round3', []),
            'baselineRound2' => config('uat_tools.baseline_round2', []),
            'total' => $total,
        ])->layout('layouts.app', ['title' => 'تقييم الأدوات (UAT)']);
    }
}
