<?php

namespace App\Support;

use App\Models\User;

class TaskNotificationHelper
{
    public static function alreadyNotified(User $user, string $notificationClass, int $taskId, ?bool $escalation = null): bool
    {
        $query = $user->notifications()
            ->where('type', $notificationClass)
            ->where('data->task_id', $taskId);

        if ($escalation === true) {
            $query->where('data->escalation', true);
        } elseif ($escalation === false) {
            $query->where(function ($q) {
                $q->whereNull('data->escalation')
                    ->orWhere('data->escalation', false);
            });
        }

        return $query->exists();
    }
}
