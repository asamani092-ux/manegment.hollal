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
        if (! \App\Models\ReferenceList::query()->where('key', 'onboarding_steps')->exists()) {
            return;
        }
        $items = app(ReferenceListService::class)->activeItems('onboarding_steps');
        $user->loadMissing('profile');
        foreach ($items as $item) {
            $row = EmployeeOnboardingItem::query()->firstOrCreate(
                ['user_id' => $user->id, 'reference_item_id' => $item->id],
                ['status' => 'open']
            );
            if ($row->status === 'open' && $this->alreadyFilled($user, $item->code)) {
                $row->update(['status' => 'done', 'acted_by' => $user->id, 'acted_at' => now()]);
            }
        }
    }

    private function alreadyFilled(User $user, string $code): bool
    {
        return match ($code) {
            'login' => true,
            'fingerprint' => (string) ($user->profile?->fingerprint_id ?? '') !== '',
            'role' => $user->roles()->exists(),
            'org' => $user->org_unit_id !== null,
            'manager' => $user->effectiveManager() !== null,
            'salary' => $user->salaryComponents()->where('is_active', true)->exists(),
            'documents' => \App\Models\EmployeeDocument::query()
                ->where('user_id', $user->id)
                ->where('status', 'approved')
                ->exists(),
            'leave_opening' => $user->profile?->annual_leave_balance !== null,
            default => false,
        };
    }

    public function markDone(EmployeeOnboardingItem $item, User $actor): void
    {
        $item->update(['status' => 'done', 'acted_by' => $actor->id, 'acted_at' => now()]);
    }

    public function close(EmployeeOnboardingItem $item, User $actor): void
    {
        $item->update(['status' => 'closed', 'acted_by' => $actor->id, 'acted_at' => now()]);
    }

    public function convertToTask(EmployeeOnboardingItem $item, User $employee, User $actor, ?string $dueDate = null): \App\Models\Task
    {
        $item->loadMissing('referenceItem');
        $assigneeId = $this->assigneeId($item, $employee, $actor);
        $task = \App\Models\Task::create([
            'title' => 'تهيئة — '.$employee->name,
            'type' => 'single',
            'assigned_by' => $actor->id,
            'assigned_to' => $assigneeId,
            'related_user_id' => $employee->id,
            'role_label' => \App\Services\OnboardingService::ROLE_LABEL,
            'priority' => 'medium',
            'status' => 'new',
            'due_date' => $dueDate ? \Illuminate\Support\Carbon::parse($dueDate) : now()->addDays(3),
        ]);
        $item->update([
            'status' => 'converted_to_task',
            'task_id' => $task->id,
            'acted_by' => $actor->id,
            'acted_at' => now(),
        ]);

        return $task;
    }

    private function assigneeId(EmployeeOnboardingItem $item, User $employee, User $actor): int
    {
        $attrs = $item->referenceItem?->attributes ?? [];
        $type = (string) ($attrs['default_assignee_type'] ?? '');
        if ($type === 'direct_manager') {
            return $employee->effectiveManager()?->id ?? $actor->id;
        }
        if ($type === 'user' && ! empty($attrs['default_user_id'])) {
            return (int) $attrs['default_user_id'];
        }
        if ($type === 'role' && ! empty($attrs['default_role'])) {
            $id = User::role((string) $attrs['default_role'])->value('id');
            if ($id) {
                return (int) $id;
            }
        }

        return $actor->id;
    }
}
