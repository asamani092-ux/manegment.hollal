<?php

namespace App\Livewire\Tasks;

use App\Models\LeaveRequest;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Carbon;
use Livewire\Component;

/**
 * 02-B3 — monthly calendar of task due dates + approved HR leaves.
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
        $scopeIds = $scopeIds->unique()->values();

        $tasks = Task::query()
            ->whereIn('assigned_to', $scopeIds)
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$start, $end])
            ->with('assignee:id,name')
            ->orderBy('due_date')
            ->get()
            ->groupBy(fn (Task $task) => $task->due_date->format('Y-m-d'));

        $leavesByDay = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('leave_requests')) {
            $leaves = LeaveRequest::query()
                ->select(['id', 'employee_id', 'type', 'from_date', 'to_date', 'status'])
                ->where('status', LeaveRequest::STATUS_APPROVED)
                ->whereIn('employee_id', $scopeIds)
                ->whereDate('from_date', '<=', $end)
                ->whereDate('to_date', '>=', $start)
                ->with('employee:id,name')
                ->get();

            foreach ($leaves as $leave) {
                $cursor = $leave->from_date->copy()->max($start);
                $last = $leave->to_date->copy()->min($end);
                while ($cursor->lte($last)) {
                    $key = $cursor->format('Y-m-d');
                    $leavesByDay[$key][] = $leave;
                    $cursor->addDay();
                }
            }
        }

        return view('livewire.tasks.tasks-calendar', [
            'tasksByDay' => $tasks,
            'leavesByDay' => $leavesByDay,
            'monthLabel' => $start->translatedFormat('F Y'),
        ])->layout('layouts.app', ['title' => 'تقويم المهام']);
    }
}
