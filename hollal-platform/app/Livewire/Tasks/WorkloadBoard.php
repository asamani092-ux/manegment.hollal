<?php

namespace App\Livewire\Tasks;

use App\Models\RecurringTaskTemplate;
use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskReminder;
use App\Services\WorkloadService;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/**
 * Workload board with tabs: loads, recurring follow-up, reminders.
 * Time: O(n) | Space: O(n)
 */
class WorkloadBoard extends Component
{
    use AuthorizesRequests;

    public string $tab = 'loads';

    public function mount(): void
    {
        $this->authorize('esnad.tasks.team.view');
    }

    public function sendReminder(int $userId, ?int $templateId = null): void
    {
        $this->authorize('esnad.tasks.team.view');

        /** @var User $manager */
        $manager = auth()->user();
        $employee = User::query()
            ->where('id', $userId)
            ->where('manager_id', $manager->id)
            ->firstOrFail();

        $task = null;
        $message = 'تذكير: راجع مهامك المفتوحة في إسناد';

        if ($templateId) {
            $template = RecurringTaskTemplate::query()
                ->where('id', $templateId)
                ->where('assigned_to_id', $employee->id)
                ->firstOrFail();
            $task = Task::query()
                ->where('recurring_template_id', $template->id)
                ->where('assigned_to', $employee->id)
                ->whereNotIn('status', ['completed'])
                ->latest()
                ->first();
            $message = 'تذكير بالمهمة المتكررة: '.$template->title;
        }

        $employee->notify(new TaskReminder($task, $message));

        $channels = ['إشعار داخل المنصة'];
        if (! empty($employee->email)) {
            $channels[] = 'بريد';
        }

        $this->dispatch('toast', type: 'success', message: 'أُرسل التذكير: '.implode(' + ', $channels));
    }

    public function render(): View
    {
        $service = app(WorkloadService::class);
        /** @var User $manager */
        $manager = auth()->user();
        $subordinateIds = User::query()->where('manager_id', $manager->id)->pluck('id');

        $recurringTemplates = RecurringTaskTemplate::query()
            ->whereIn('assigned_to_id', $subordinateIds)
            ->with(['assignee:id,name'])
            ->withCount([
                'generatedTasks as open_instances_count' => fn ($q) => $q->whereNotIn('status', ['completed']),
                'generatedTasks as completed_instances_count' => fn ($q) => $q->where('status', 'completed'),
            ])
            ->latest()
            ->get();

        $overdueForTeam = Task::query()
            ->overdue()
            ->whereIn('assigned_to', $subordinateIds)
            ->with('assignee:id,name')
            ->latest('due_date')
            ->limit(30)
            ->get();

        return view('livewire.tasks.workload-board', [
            'rows' => $service->board($manager),
            'threshold' => $service->threshold(),
            'recurringTemplates' => $recurringTemplates,
            'overdueForTeam' => $overdueForTeam,
            'statusLabels' => [
                'new' => 'جديدة',
                'in_progress' => 'قيد التنفيذ',
                'pending_review' => 'بانتظار المراجعة',
                'completed' => 'مكتملة',
                'overdue' => 'متأخرة',
            ],
        ])->layout('layouts.app', ['title' => 'عبء عمل الفريق']);
    }
}
