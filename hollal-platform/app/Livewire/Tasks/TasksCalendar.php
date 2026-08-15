<?php

namespace App\Livewire\Tasks;

use App\Models\LeaveRequest;
use App\Models\Meeting;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

/**
 * Monthly calendar: task due dates, approved leaves, and meetings for the user.
 * Time: O(n) | Space: O(n)
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

    public function previousMonth(): void
    {
        $this->month = $this->resolveMonthStart()->copy()->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->month = $this->resolveMonthStart()->copy()->addMonth()->format('Y-m');
    }

    private function resolveMonthStart(): Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();
        } catch (\Throwable) {
            $this->month = now()->format('Y-m');

            return now()->startOfMonth();
        }
    }

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();
        $start = $this->resolveMonthStart();
        $end = $start->copy()->endOfMonth();

        $scopeIds = collect([$user->id]);
        if ($user->can('esnad.tasks.team.view')) {
            $scopeIds = $scopeIds->merge(User::query()->where('manager_id', $user->id)->pluck('id'));
        }
        $scopeIds = $scopeIds->unique()->values();

        $tasksByDay = Task::query()
            ->whereIn('assigned_to', $scopeIds)
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$start, $end])
            ->with('assignee:id,name')
            ->orderBy('due_date')
            ->get()
            ->groupBy(fn (Task $task) => $task->due_date->format('Y-m-d'));

        $leavesByDay = [];
        if (Schema::hasTable('leave_requests')) {
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
                    $leavesByDay[$cursor->format('Y-m-d')][] = $leave;
                    $cursor->addDay();
                }
            }
        }

        $meetingsByDay = [];
        if (Schema::hasTable('meetings') && Schema::hasTable('meeting_user')) {
            $meetings = Meeting::query()
                ->select(['id', 'title', 'scheduled_at', 'location', 'link', 'status'])
                ->whereBetween('scheduled_at', [$start, $end])
                ->where(function ($q) use ($scopeIds) {
                    $q->whereIn('chair_id', $scopeIds)
                        ->orWhereIn('secretary_id', $scopeIds)
                        ->orWhereHas('attendees', fn ($a) => $a->whereIn('users.id', $scopeIds));
                })
                ->orderBy('scheduled_at')
                ->get();

            foreach ($meetings as $meeting) {
                $meetingsByDay[$meeting->scheduled_at->format('Y-m-d')][] = $meeting;
            }
        }

        return view('livewire.tasks.tasks-calendar', [
            'tasksByDay' => $tasksByDay,
            'leavesByDay' => $leavesByDay,
            'meetingsByDay' => $meetingsByDay,
            'monthLabel' => $start->translatedFormat('F Y'),
        ])->layout('layouts.app', ['title' => 'تقويم المهام']);
    }
}
