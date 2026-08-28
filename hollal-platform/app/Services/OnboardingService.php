<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;

/**
 * 01-B5 — auto-generate onboarding (إسناد) tasks when an employee is added.
 * One assignee covers all four checklist tasks (not one assignee per task).
 */
class OnboardingService
{
    public const ROLE_LABEL = 'تهيئة';

    public const CHECKLIST = [
        'استكمال ملف الموظف والوثائق الرسمية',
        'تجهيز حساب النظام والصلاحيات',
        'تعريف الموظف بالمهام والمسؤوليات',
        'تسليم العهد والأجهزة اللازمة',
    ];

    /**
     * @return list<Task>
     */
    public function generateTasks(User $employee, User $creator, ?User $assignee = null): array
    {
        $assignee ??= $creator;
        $tasks = [];

        foreach (self::CHECKLIST as $index => $title) {
            $tasks[] = Task::create([
                'title' => $title.' — '.$employee->name,
                'type' => 'single',
                'assigned_by' => $creator->id,
                'assigned_to' => $assignee->id,
                'related_user_id' => $employee->id,
                'role_label' => self::ROLE_LABEL,
                'priority' => 'medium',
                'status' => 'new',
                'due_date' => now()->addDays(($index + 1) * 2),
            ]);
        }

        return $tasks;
    }
}
