<?php

namespace App\Services;

use App\Models\EmployeeOnboardingItem;
use App\Models\User;

/**
 * Checklist rows from the active onboarding list. Time: O(steps) | Space: O(steps).
 */
class OnboardingChecklistService
{
    public function generate(User $user): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('employee_onboarding_items')) {
            return;
        }
        $items = app(ReferenceListService::class)->activeItems('onboarding_steps');
        $user->loadMissing('profile');
        $fingerprint = (string) ($user->profile?->fingerprint_id ?? '');
        foreach ($items as $item) {
            $row = EmployeeOnboardingItem::query()->firstOrCreate(
                ['user_id' => $user->id, 'reference_item_id' => $item->id],
                ['status' => 'open']
            );
            if ($row->status === 'open' && $item->code === 'fingerprint' && $fingerprint !== '') {
                $row->update(['status' => 'done', 'acted_by' => $user->id]);
            }
        }
    }

    public function markDone(EmployeeOnboardingItem $item, User $actor): void
    {
        $item->update(['status' => 'done', 'acted_by' => $actor->id]);
    }

    public function close(EmployeeOnboardingItem $item, User $actor): void
    {
        $item->update(['status' => 'closed', 'acted_by' => $actor->id]);
    }

    public function convertToTask(EmployeeOnboardingItem $item, User $employee, User $actor): \App\Models\Task
    {
        $task = \App\Models\Task::create([
            'title' => 'تهيئة — '.$employee->name,
            'type' => 'single',
            'assigned_by' => $actor->id,
            'assigned_to' => $actor->id,
            'related_user_id' => $employee->id,
            'role_label' => \App\Services\OnboardingService::ROLE_LABEL,
            'priority' => 'medium',
            'status' => 'new',
            'due_date' => now()->addDays(3),
        ]);
        $item->update([
            'status' => 'converted_to_task',
            'task_id' => $task->id,
            'acted_by' => $actor->id,
        ]);

        return $task;
    }
}
