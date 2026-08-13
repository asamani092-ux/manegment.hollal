<?php

namespace App\Livewire\Uat;

use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Interactive UAT tools evaluation page (pre-production only).
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
        $total = collect($groups)->sum(fn (array $g) => count($g['items'] ?? []));

        return view('livewire.uat.tools-checklist', [
            'groups' => $groups,
            'verdicts' => config('uat_tools.verdicts', []),
            'noteTags' => config('uat_tools.note_tags', []),
            'baseline' => config('uat_tools.baseline', []),
            'total' => $total,
        ])->layout('layouts.app', ['title' => 'تقييم الأدوات (UAT)']);
    }
}
