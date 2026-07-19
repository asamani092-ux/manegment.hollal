<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;

/**
 * 01-B5 — auto-generate onboarding (إسناد) tasks when an employee is added.
 * Tasks are assigned to the creator (HR/manager) to complete for the new hire.
 */
class OnboardingService
{
    private const CHECKLIST = [
        'استكمال ملف الموظف والوثائق الرسمية',
        'تجهيز حساب النظام والصلاحيات',
        'تعريف الموظف بالمهام والمسؤوليات',
        'تسليم العهد والأجهزة اللازمة',
    ];

    /**
     * @return list<Task>
     */
    public function generateTasks(User $employee, User $creator): array
    {
        $tasks = [];

        foreach (self::CHECKLIST as $index => $title) {
            $tasks[] = Task::create([
                'title' => $title.' — '.$employee->name,
                'type' => 'single',
                'assigned_by' => $creator->id,
                'assigned_to' => $creator->id,
                'priority' => 'medium',
                'status' => 'new',
                'due_date' => now()->addDays(($index + 1) * 2),
            ]);
        }

        return $tasks;
    }
}
