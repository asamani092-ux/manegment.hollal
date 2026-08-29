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
 * Monthly calendar grid: task due dates, approved leaves, and meetings.
 * Time: O(n) | Space: O(n)
 */
class TasksCalendar extends Component
{
    use AuthorizesRequests;

    public string $month = '';

    public ?int $selectedTaskId = null;

    public ?int $selectedLeaveId = null;

    public ?int $selectedMeetingId = null;

    public ?string $editDueDate = null;

    public ?string $selectedDay = null;

    public const CHIP_LIMIT = 3;

    public function mount(): void
    {
        $this->authorize('esnad.tasks.view');
        $this->month = now()->format('Y-m');
    }

    public function previousMonth(): void
    {
        $this->month = $this->resolveMonthStart()->copy()->subMonth()->format('Y-m');
        $this->closePeek();
    }

    public function nextMonth(): void
    {
        $this->month = $this->resolveMonthStart()->copy()->addMonth()->format('Y-m');
        $this->closePeek();
    }

    public function goToday(): void
    {
        $this->month = now()->format('Y-m');
        $this->closePeek();
    }

    public function openTask(int $taskId): void
    {
        $this->selectedLeaveId = null;
        $this->selectedMeetingId = null;
        $this->selectedDay = null;
        $this->selectedTaskId = $taskId;
        $task = Task::find($taskId);
        $this->editDueDate = $task?->due_date?->format('Y-m-d\TH:i');
    }

    public function openDay(string $date): void
    {
        $this->selectedTaskId = null;
        $this->selectedLeaveId = null;
        $this->selectedMeetingId = null;
        $this->selectedDay = $date;
    }

    public function openLeave(int $leaveId): void
    {
        $this->selectedTaskId = null;
        $this->selectedMeetingId = null;
        $this->selectedDay = null;
        $this->selectedLeaveId = $leaveId;
    }

    public function openMeeting(int $meetingId): void
    {
        $this->selectedTaskId = null;
        $this->selectedLeaveId = null;
        $this->selectedDay = null;
        $this->selectedMeetingId = $meetingId;
    }

    public function closePeek(): void
    {
        $this->selectedTaskId = null;
        $this->selectedLeaveId = null;
        $this->selectedMeetingId = null;
        $this->selectedDay = null;
        $this->editDueDate = null;
    }

    public function saveDueDate(): void
    {
        $task = Task::findOrFail($this->selectedTaskId);
        $this->authorize('update', $task);

        $this->validate([
            'editDueDate' => 'required|date',
        ], [
            'editDueDate.required' => 'موعد الاستحقاق مطلوب',
        ]);

        $task->update(['due_date' => $this->editDueDate]);
        $this->dispatch('toast', type: 'success', message: 'تم تحديث موعد الاستحقاق');
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

        $tasks = Task::query()
            ->whereIn('assigned_to', $scopeIds)
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$start, $end])
            ->with('assignee:id,name')
            ->orderBy('due_date')
            ->get()
            ->groupBy(fn (Task $task) => $task->due_date->format('Y-m-d'));

        $leavesByDay = [];
        $leavesById = collect();
        if (Schema::hasTable('leave_requests')) {
            $leaves = LeaveRequest::query()
                ->select(['id', 'employee_id', 'type', 'from_date', 'to_date', 'status'])
                ->where('status', LeaveRequest::STATUS_APPROVED)
                ->whereIn('employee_id', $scopeIds)
                ->whereDate('from_date', '<=', $end)
                ->whereDate('to_date', '>=', $start)
                ->with('employee:id,name')
                ->get();

            $leavesById = $leaves->keyBy('id');

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
        $meetingsById = collect();
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

            $meetingsById = $meetings->keyBy('id');

            foreach ($meetings as $meeting) {
                $meetingsByDay[$meeting->scheduled_at->format('Y-m-d')][] = $meeting;
            }
        }

        // Saudi week: Saturday → Friday.
        $gridStart = $start->copy()->startOfWeek(Carbon::SATURDAY);
        $gridEnd = $end->copy()->endOfWeek(Carbon::FRIDAY);
        $cells = [];
        $cursor = $gridStart->copy();
        while ($cursor->lte($gridEnd)) {
            $key = $cursor->format('Y-m-d');
            $dayTasks = $tasks[$key] ?? collect();
            $dayMeetings = $meetingsByDay[$key] ?? [];
            $dayLeaves = $leavesByDay[$key] ?? [];
            $totalChips = $dayTasks->count() + count($dayMeetings) + count($dayLeaves);
            $cells[] = [
                'date' => $key,
                'day' => (int) $cursor->day,
                'inMonth' => $cursor->month === $start->month,
                'isToday' => $cursor->isToday(),
                'tasks' => $dayTasks,
                'leaves' => $dayLeaves,
                'meetings' => $dayMeetings,
                'overflow' => max(0, $totalChips - self::CHIP_LIMIT),
            ];
            $cursor->addDay();
        }

        $selectedTask = $this->selectedTaskId
            ? Task::query()->with('assignee:id,name')->find($this->selectedTaskId)
            : null;
        $selectedLeave = $this->selectedLeaveId
            ? ($leavesById->get($this->selectedLeaveId) ?? LeaveRequest::with('employee:id,name')->find($this->selectedLeaveId))
            : null;
        $selectedMeeting = $this->selectedMeetingId
            ? ($meetingsById->get($this->selectedMeetingId) ?? Meeting::query()->find($this->selectedMeetingId))
            : null;

        $dayPeek = null;
        if ($this->selectedDay) {
            $dayPeek = [
                'date' => $this->selectedDay,
                'tasks' => $tasks[$this->selectedDay] ?? collect(),
                'meetings' => $meetingsByDay[$this->selectedDay] ?? [],
                'leaves' => $leavesByDay[$this->selectedDay] ?? [],
            ];
        }

        return view('livewire.tasks.tasks-calendar', [
            'monthLabel' => $start->translatedFormat('F Y'),
            'dayHeaders' => ['السبت', 'الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة'],
            'cells' => $cells,
            'chipLimit' => self::CHIP_LIMIT,
            'selectedTask' => $selectedTask,
            'selectedLeave' => $selectedLeave,
            'selectedMeeting' => $selectedMeeting,
            'dayPeek' => $dayPeek,
            'statusLabels' => [
                'new' => 'جديدة',
                'in_progress' => 'قيد التنفيذ',
                'pending_review' => 'بانتظار المراجعة',
                'completed' => 'مكتملة',
                'overdue' => 'متأخرة',
            ],
        ])->layout('layouts.app', ['title' => 'تقويم المهام']);
    }
}
