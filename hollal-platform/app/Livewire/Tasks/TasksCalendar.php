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

    public ?int $selectedTaskId = null;

    public ?int $selectedLeaveId = null;

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
        $this->selectedTaskId = $taskId;
    }

    public function openLeave(int $leaveId): void
    {
        $this->selectedTaskId = null;
        $this->selectedLeaveId = $leaveId;
    }

    public function closePeek(): void
    {
        $this->selectedTaskId = null;
        $this->selectedLeaveId = null;
    }

    /** الشهر يصل من الواجهة، فأي قيمة غير Y-m ترتد إلى الشهر الحالي بدل رمي استثناء. */
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
        if (\Illuminate\Support\Facades\Schema::hasTable('leave_requests')) {
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
                    $key = $cursor->format('Y-m-d');
                    $leavesByDay[$key][] = $leave;
                    $cursor->addDay();
                }
            }
        }

        // Saudi week: Saturday → Friday (7 columns).
        $gridStart = $start->copy()->startOfWeek(Carbon::SATURDAY);
        $gridEnd = $end->copy()->endOfWeek(Carbon::FRIDAY);
        $cells = [];
        $cursor = $gridStart->copy();
        while ($cursor->lte($gridEnd)) {
            $key = $cursor->format('Y-m-d');
            $cells[] = [
                'date' => $key,
                'day' => (int) $cursor->day,
                'inMonth' => $cursor->month === $start->month,
                'isToday' => $cursor->isToday(),
                'tasks' => $tasks[$key] ?? collect(),
                'leaves' => $leavesByDay[$key] ?? [],
            ];
            $cursor->addDay();
        }

        $selectedTask = $this->selectedTaskId
            ? Task::query()->with('assignee:id,name')->find($this->selectedTaskId)
            : null;
        $selectedLeave = $this->selectedLeaveId
            ? ($leavesById->get($this->selectedLeaveId) ?? LeaveRequest::with('employee:id,name')->find($this->selectedLeaveId))
            : null;

        return view('livewire.tasks.tasks-calendar', [
            'tasksByDay' => $tasks,
            'leavesByDay' => $leavesByDay,
            'monthLabel' => $start->translatedFormat('F Y'),
            'dayHeaders' => ['السبت', 'الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة'],
            'cells' => $cells,
            'selectedTask' => $selectedTask,
            'selectedLeave' => $selectedLeave,
        ])->layout('layouts.app', ['title' => 'تقويم المهام']);
    }
}
