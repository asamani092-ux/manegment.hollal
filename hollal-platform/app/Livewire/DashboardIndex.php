<?php

namespace App\Livewire;

use App\Models\ExpenseRequest;
use App\Models\Meeting;
use App\Models\MeetingItem;
use App\Models\Partnership;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

/**
 * Dashboard — action items, metrics, personal workspace.
 * Time: O(T + E + M + P + U) per render | Space: O(k) result sets.
 */
class DashboardIndex extends Component
{
    use AuthorizesRequests;

    public function mount(): void
    {
        $this->authorize('dashboard.view');
    }

    /**
     * 00-B5 — check-in placeholder. Visible only for attendance-enabled users;
     * timestamp persistence and full logic are wired in 01-B4.
     */
    public function checkIn(): void
    {
        abort_unless((bool) auth()->user()->attendance_enabled, 403);

        $this->dispatch('toast', type: 'info', message: 'تم تسجيل الحضور — سيُفعَّل الاحتساب الكامل مع برنامج الحضور');
    }

    public function checkOut(): void
    {
        abort_unless((bool) auth()->user()->attendance_enabled, 403);

        $this->dispatch('toast', type: 'info', message: 'تم تسجيل الانصراف — سيُفعَّل الاحتساب الكامل مع برنامج الحضور');
    }

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();

        $actionItems = $this->actionItems($user);
        $activeProjects = $this->activeProjectsWithCompletion($user);

        return view('livewire.dashboard-index', [
            'actionItems' => $actionItems,
            'showActionSection' => $actionItems->isNotEmpty(),
            'activeProjectsCount' => $activeProjects->count(),
            'averageCompletionPercent' => $this->averageCompletionPercent($activeProjects),
            'overdueTasksCount' => $this->overdueTasksQuery($user)->count(),
            'monthSpend' => $this->monthSpend($user),
            'showFinanceMetric' => $this->canViewFinance($user),
            'upcomingMeetingsCount' => $this->upcomingMeetingsQuery($user)->count(),
            'myTasksToday' => $this->myTasksToday($user),
            'myOpenTasks' => $this->myOpenTasks($user),
            'myUpcomingMeetings' => $this->myUpcomingMeetings($user),
            'attendanceEnabled' => (bool) $user->attendance_enabled,
            'dutiesFileUrl' => $this->officialDutiesFileUrl(),
        ])->layout('layouts.app', ['title' => 'الرئيسية']);
    }

    /** @return Collection<int, array{kind: string, label: string, url: string|null, meta: string|null}> */
    protected function actionItems(User $user): Collection
    {
        $items = collect();

        foreach ($this->overdueTasksQuery($user)->with(['assignee:id,name', 'project:id,name'])->limit(10)->get() as $task) {
            $assigneeName = $task->assignee?->name ?? '—';
            $items->push([
                'kind' => 'overdue_task',
                'label' => 'مهمة متأخرة: '.$task->title,
                'url' => route('tasks.index'),
                'meta' => 'المكلف: '.$assigneeName,
            ]);
        }

        if ($user->can('finance.expenses.approve')) {
            ExpenseRequest::query()
                ->select(['id', 'type', 'amount', 'requester_id', 'project_id'])
                ->where('status', 'pending')
                ->with(['requester:id,name', 'project:id,name'])
                ->latest()
                ->limit(10)
                ->get()
                ->each(function (ExpenseRequest $expense) use ($items) {
                    $items->push([
                        'kind' => 'expense_pending',
                        'label' => 'مصروف بانتظار الموافقة: '.$expense->type,
                        'url' => route('expenses.index'),
                        'meta' => number_format((float) $expense->amount, 2).' ر.س — '.$expense->requester?->name,
                    ]);
                });
        }

        $this->pastDueDecisionsQuery($user)
            ->with(['meeting:id,title', 'responsible:id,name'])
            ->limit(10)
            ->get()
            ->each(function (MeetingItem $item) use ($items) {
                $items->push([
                    'kind' => 'meeting_decision',
                    'label' => 'قرار متأخر: '.$item->topic,
                    'url' => route('meetings.minutes', $item->meeting_id),
                    'meta' => ($item->meeting?->title ?? '—').' — '.$item->due_date?->format('Y-m-d'),
                ]);
            });

        if ($user->can('partnerships.view')) {
            $this->expiringPartnershipsQuery()
                ->with('project:id,name')
                ->limit(10)
                ->get()
                ->each(function (Partnership $partnership) use ($items) {
                    $expiry = $partnership->token_expires_at?->format('Y-m-d') ?? '—';
                    $items->push([
                        'kind' => 'partnership_expiring',
                        'label' => 'شراكة تنتهي قريباً: '.$partnership->entity_name,
                        'url' => route('projects.index'),
                        'meta' => 'تاريخ الانتهاء: '.$expiry,
                    ]);
                });
        }

        return $items;
    }

    /** @return Builder<Task> */
    protected function overdueTasksQuery(User $user): Builder
    {
        $scopeUserIds = $this->taskScopeUserIds($user);

        return Task::query()
            ->select(['id', 'title', 'due_date', 'status', 'assigned_to', 'assigned_by', 'project_id'])
            ->whereNot('status', 'completed')
            ->where(function (Builder $query) {
                $query->where('status', 'overdue')
                    ->orWhere(function (Builder $inner) {
                        $inner->whereNotNull('due_date')
                            ->where('due_date', '<', now());
                    });
            })
            ->where(function (Builder $query) use ($user, $scopeUserIds) {
                if ($user->can('esnad.tasks.view')) {
                    $query->whereIn('assigned_to', $scopeUserIds);

                    return;
                }

                $query->where('assigned_to', $user->id)
                    ->orWhere('assigned_by', $user->id);
            });
    }

    /** @return list<int> */
    protected function taskScopeUserIds(User $user): array
    {
        $ids = collect([$user->id]);

        if ($user->subordinates()->exists()) {
            $ids = $ids->merge(
                User::query()->where('manager_id', $user->id)->pluck('id')
            );
        }

        return $ids->unique()->values()->all();
    }

    /** @return Builder<MeetingItem> */
    protected function pastDueDecisionsQuery(User $user): Builder
    {
        return MeetingItem::query()
            ->select(['id', 'meeting_id', 'topic', 'decision', 'responsible_id', 'due_date', 'status'])
            ->whereNotNull('decision')
            ->where('decision', '!=', '')
            ->where('status', '!=', 'done')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->where(function (Builder $query) use ($user) {
                $query->where('responsible_id', $user->id)
                    ->orWhereHas('meeting', function (Builder $meetingQuery) use ($user) {
                        $meetingQuery->where('chair_id', $user->id)
                            ->orWhere('secretary_id', $user->id)
                            ->orWhereHas('attendees', fn (Builder $attendeeQuery) => $attendeeQuery->where('users.id', $user->id));
                    });
            });
    }

    /** @return Builder<Partnership> */
    protected function expiringPartnershipsQuery(): Builder
    {
        $query = Partnership::query()
            ->select(['id', 'entity_name', 'project_id', 'token_expires_at', 'status'])
            ->where('status', 'active');

        if (Schema::hasColumn('partnerships', 'end_date')) {
            return $query
                ->whereNotNull('end_date')
                ->whereBetween('end_date', [now()->toDateString(), now()->addDays(30)->toDateString()]);
        }

        return $query
            ->whereNotNull('token_expires_at')
            ->whereBetween('token_expires_at', [now(), now()->addDays(30)]);
    }

    /** @return Collection<int, Project> */
    protected function activeProjectsWithCompletion(User $user): Collection
    {
        $query = Project::query()
            ->select(['id', 'name', 'status', 'manager_id'])
            ->where('status', 'active')
            ->withCount([
                'tasks',
                'tasks as completed_tasks_count' => fn (Builder $taskQuery) => $taskQuery->where('status', 'completed'),
            ]);

        if (! $user->can('projects.view')) {
            $query->where(function (Builder $inner) use ($user) {
                $inner->where('manager_id', $user->id)
                    ->orWhereHas('team', fn (Builder $teamQuery) => $teamQuery->where('users.id', $user->id));
            });
        }

        return $query->orderBy('name')->get();
    }

    /** @param Collection<int, Project> $projects */
    protected function averageCompletionPercent(Collection $projects): int
    {
        if ($projects->isEmpty()) {
            return 0;
        }

        $totalPercent = $projects->sum(function (Project $project) {
            if ($project->tasks_count === 0) {
                return 0;
            }

            return (int) round(($project->completed_tasks_count / $project->tasks_count) * 100);
        });

        return (int) round($totalPercent / $projects->count());
    }

    protected function canViewFinance(User $user): bool
    {
        return $user->can('finance.expenses.view') || $user->can('finance.expenses.approve');
    }

    protected function monthSpend(User $user): float
    {
        if (! $this->canViewFinance($user)) {
            return 0.0;
        }

        $start = now()->startOfMonth();
        $end = now()->endOfMonth();

        return (float) ExpenseRequest::query()
            ->countedAsSpend()
            ->where(function (Builder $query) use ($start, $end) {
                $query->whereBetween('approved_at', [$start, $end])
                    ->orWhere(function (Builder $inner) use ($start, $end) {
                        $inner->whereNull('approved_at')
                            ->whereBetween('created_at', [$start, $end]);
                    });
            })
            ->sum('amount');
    }

    /** @return Builder<Meeting> */
    protected function upcomingMeetingsQuery(User $user): Builder
    {
        return Meeting::query()
            ->select(['id', 'title', 'scheduled_at', 'chair_id', 'secretary_id', 'status'])
            ->where('scheduled_at', '>=', now())
            ->when(! $user->can('meetings.view'), function (Builder $query) use ($user) {
                $query->where(function (Builder $inner) use ($user) {
                    $inner->where('chair_id', $user->id)
                        ->orWhere('secretary_id', $user->id)
                        ->orWhereHas('attendees', fn (Builder $attendeeQuery) => $attendeeQuery->where('users.id', $user->id));
                });
            });
    }

    /** @return Collection<int, Task> */
    protected function myTasksToday(User $user): Collection
    {
        return Task::query()
            ->select(['id', 'title', 'due_date', 'status', 'priority', 'project_id'])
            ->where('assigned_to', $user->id)
            ->whereNot('status', 'completed')
            ->whereDate('due_date', now()->toDateString())
            ->with('project:id,name')
            ->orderBy('due_date')
            ->limit(5)
            ->get();
    }

    /** @return Collection<int, Task> */
    protected function myOpenTasks(User $user): Collection
    {
        return Task::query()
            ->select(['id', 'title', 'due_date', 'status', 'priority', 'project_id'])
            ->where('assigned_to', $user->id)
            ->whereNot('status', 'completed')
            ->with('project:id,name')
            ->orderBy('due_date')
            ->limit(5)
            ->get();
    }

    /** @return Collection<int, Meeting> */
    protected function myUpcomingMeetings(User $user): Collection
    {
        return $this->upcomingMeetingsQuery($user)
            ->orderBy('scheduled_at')
            ->limit(5)
            ->get();
    }

    /**
     * 00-B5 — link slot for the published official duties file. Populated once
     * 07-B1 publishes a duties document; null (slot hidden) until then.
     */
    protected function officialDutiesFileUrl(): ?string
    {
        if (! Schema::hasColumn('documents', 'is_duties_file')) {
            return null;
        }

        return null;
    }
}
