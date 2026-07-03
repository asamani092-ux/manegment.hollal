<?php

namespace App\Console\Commands;

use App\Models\ExpenseRequest;
use App\Models\Project;
use App\Models\Task;
use App\Models\WeeklyReport;
use App\Notifications\WeeklyReportGenerated;
use App\Support\WeeklyReportNotificationHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class GenerateWeeklyReport extends Command
{
    protected $signature = 'reports:generate-weekly';

    protected $description = 'Generate and persist the weekly management report';

    public function handle(): int
    {
        $weekEnd = now()->startOfDay();
        $weekStart = $weekEnd->copy()->subDays(6);

        $report = WeeklyReport::create([
            'week_start' => $weekStart,
            'week_end' => $weekEnd,
            'done' => $this->tasksDoneThisWeek($weekStart, $weekEnd),
            'overdue' => $this->overdueTasks(),
            'project_status' => $this->projectStatusSummary(),
            'week_spend' => $this->weekSpend($weekStart, $weekEnd),
            'open_decisions' => $this->openDecisionsPastDue(),
            'generated_at' => now(),
        ]);

        foreach (WeeklyReportNotificationHelper::managers() as $manager) {
            $manager->notify(new WeeklyReportGenerated($report));
        }

        $this->info("Weekly report #{$report->id} generated for {$weekStart->toDateString()} – {$weekEnd->toDateString()}.");

        return self::SUCCESS;
    }

    /** @return list<array<string, mixed>> */
    protected function tasksDoneThisWeek(\Illuminate\Support\Carbon $weekStart, \Illuminate\Support\Carbon $weekEnd): array
    {
        if (! Schema::hasTable('tasks')) {
            return [];
        }

        return Task::query()
            ->select(['id', 'title', 'assigned_to', 'project_id', 'updated_at'])
            ->where('status', 'completed')
            ->whereBetween('updated_at', [$weekStart->copy()->startOfDay(), $weekEnd->copy()->endOfDay()])
            ->with(['assignee:id,name', 'project:id,name'])
            ->orderBy('updated_at')
            ->get()
            ->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'assignee' => $task->assignee?->name,
                'project' => $task->project?->name,
                'completed_at' => $task->updated_at?->toIso8601String(),
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    protected function overdueTasks(): array
    {
        if (! Schema::hasTable('tasks')) {
            return [];
        }

        return Task::query()
            ->select(['id', 'title', 'assigned_to', 'due_date', 'status'])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now())
            ->whereNotIn('status', ['completed'])
            ->with('assignee:id,name')
            ->orderBy('due_date')
            ->get()
            ->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'assignee' => $task->assignee?->name,
                'due_date' => $task->due_date?->toIso8601String(),
                'status' => $task->status,
            ])
            ->all();
    }

    /** @return list<array<string, mixed>> */
    protected function projectStatusSummary(): array
    {
        if (! Schema::hasTable('projects')) {
            return [];
        }

        return Project::query()
            ->select(['id', 'name', 'status'])
            ->where('status', 'active')
            ->withCount([
                'tasks as total_tasks',
                'tasks as completed_tasks' => fn ($q) => $q->where('status', 'completed'),
            ])
            ->orderBy('name')
            ->get()
            ->map(function (Project $project) {
                $total = (int) $project->total_tasks;
                $completed = (int) $project->completed_tasks;

                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'status' => $project->status,
                    'completion_percent' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
                    'total_tasks' => $total,
                    'completed_tasks' => $completed,
                ];
            })
            ->all();
    }

    protected function weekSpend(\Illuminate\Support\Carbon $weekStart, \Illuminate\Support\Carbon $weekEnd): string
    {
        if (! Schema::hasTable('expense_requests')) {
            return '0.00';
        }

        return (string) ExpenseRequest::query()
            ->countedAsSpend()
            ->whereBetween('updated_at', [$weekStart->copy()->startOfDay(), $weekEnd->copy()->endOfDay()])
            ->sum('amount');
    }

    /** @return list<array<string, mixed>> */
    protected function openDecisionsPastDue(): array
    {
        if (! Schema::hasTable('meeting_items')) {
            return [];
        }

        $modelClass = 'App\\Models\\MeetingItem';
        if (! class_exists($modelClass)) {
            return [];
        }

        return $modelClass::query()
            ->select(['id', 'meeting_id', 'topic', 'decision', 'responsible_id', 'due_date', 'status'])
            ->whereNotNull('decision')
            ->where('decision', '!=', '')
            ->where('status', '!=', 'done')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now())
            ->when(
                class_exists('App\\Models\\Meeting'),
                fn ($q) => $q->with(['meeting:id,title', 'responsible:id,name'])
            )
            ->orderBy('due_date')
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'topic' => $item->topic,
                'decision' => $item->decision,
                'meeting' => $item->meeting?->title ?? null,
                'responsible' => $item->responsible?->name ?? null,
                'due_date' => $item->due_date?->format('Y-m-d'),
                'status' => $item->status,
            ])
            ->all();
    }
}
