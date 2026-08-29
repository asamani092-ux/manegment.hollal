<?php

namespace App\Livewire\Tasks;

use Illuminate\Http\RedirectResponse;
use Livewire\Component;

/**
 * غلاف تحويل — لوحة الأحمال دُمجت في متابعة الفريق.
 */
class WorkloadBoard extends Component
{
    public function mount(?string $tab = null): RedirectResponse
    {
        $requested = $tab ?: request()->query('tab');
        $map = [
            'loads' => 'loads',
            'recurring' => 'recurring',
            'reminders' => 'reminders',
        ];
        $target = $map[$requested] ?? 'loads';

        return redirect()->route('team-tasks.index', ['tab' => $target]);
    }

    public function render(): never
    {
        abort(404);
    }
}
