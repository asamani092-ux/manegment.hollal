<?php

namespace App\Livewire\Tasks;

use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Carbon;
use Livewire\Component;

/**
 * 02-B3 — monthly calendar of task due dates, scoped by permission. Approved HR
 * leaves are overlaid when the leaves table exists.
 */
class TasksCalendar extends Component
{
    use AuthorizesRequests;

    public string $month = '';

    public function mount(): void
    {
        $this->authorize('esnad.tasks.view');
        $this->month = now()->format('Y-m');
    }

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();
        $start = Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $scopeIds = collect([$user->id]);
        if ($user->can('esnad.tasks.team.view')) {
            $scopeIds = $scopeIds->merge(User::query()->where('manager_id', $user->id)->pluck('id'));
        }

        $tasks = Task::query()
            ->whereIn('assigned_to', $scopeIds->unique())
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$start, $end])
            ->with('assignee:id,name')
            ->orderBy('due_date')
            ->get()
            ->groupBy(fn (Task $task) => $task->due_date->format('Y-m-d'));

        return view('livewire.tasks.tasks-calendar', [
            'tasksByDay' => $tasks,
            'monthLabel' => $start->translatedFormat('F Y'),
        ])->layout('layouts.app', ['title' => 'تقويم المهام']);
    }
}
