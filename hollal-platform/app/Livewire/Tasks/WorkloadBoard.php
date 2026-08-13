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
 * 02-B3 — workload board: per-team-member open/overdue/due-this-week counts and
 * last-30-day rating distribution, with an overload badge + recurring reminders.
 */
class WorkloadBoard extends Component
{
    use AuthorizesRequests;

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
        $this->dispatch('toast', type: 'success', message: 'تم إرسال التذكير للموظف');
    }

    public function render(): View
    {
        $service = app(WorkloadService::class);
        /** @var User $manager */
        $manager = auth()->user();
        $subordinateIds = User::query()->where('manager_id', $manager->id)->pluck('id');

        return view('livewire.tasks.workload-board', [
            'rows' => $service->board($manager),
            'threshold' => $service->threshold(),
            'recurringTemplates' => RecurringTaskTemplate::query()
                ->whereIn('assigned_to_id', $subordinateIds)
                ->where('is_active', true)
                ->with('assignee:id,name')
                ->latest()
                ->get(),
        ])->layout('layouts.app', ['title' => 'عبء عمل الفريق']);
    }
}
