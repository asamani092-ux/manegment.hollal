<?php

namespace App\Services;

use App\Models\RecurringTaskTemplate;
use App\Models\Task;
use Illuminate\Support\Carbon;

/**
 * 02-B3 — generate task instances from recurring templates, either on the
 * scheduled date or when the previous instance is completed. Each instance is
 * independent with its own lifecycle.
 */
class RecurringTaskService
{
    /**
     * Generate instances for every template due on the given date.
     *
     * @return list<Task>
     */
    public function generateDue(?Carbon $date = null): array
    {
        $date ??= today();
        $created = [];

        foreach (RecurringTaskTemplate::where('is_active', true)->get() as $template) {
            if (! $template->isDueOn($date)) {
                continue;
            }

            if ($template->last_generated_on !== null && $template->last_generated_on->isSameDay($date)) {
                continue; // already generated today
            }

            $created[] = $this->createInstance($template, $date);
        }

        return $created;
    }

    /**
     * When a recurring instance is completed, spin up the next one.
     */
    public function onInstanceCompleted(Task $task): ?Task
    {
        if ($task->recurring_template_id === null) {
            return null;
        }

        $template = RecurringTaskTemplate::find($task->recurring_template_id);

        if (! $template || ! $template->is_active) {
            return null;
        }

        return $this->createInstance($template, today());
    }

    private function createInstance(RecurringTaskTemplate $template, Carbon $date): Task
    {
        $task = Task::create([
            'title' => $template->title,
            'description' => $template->description,
            'required_evidence' => $template->required_evidence,
            'type' => 'single',
            'assigned_by' => $template->created_by ?? $template->assigned_to_id,
            'assigned_to' => $template->assigned_to_id,
            'project_id' => $template->project_id,
            'priority' => $template->priority,
            'status' => 'new',
            'due_date' => $date->copy()->endOfDay(),
            'recurring_template_id' => $template->id,
        ]);

        $template->update(['last_generated_on' => $date]);

        return $task;
    }
}
