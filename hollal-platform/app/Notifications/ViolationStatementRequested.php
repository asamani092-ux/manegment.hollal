<?php

namespace App\Notifications;

use App\Models\Violation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ViolationStatementRequested extends Notification
{
    use Queueable;

    public function __construct(public Violation $violation) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => 'مطلوب تقديم إفادة عن مخالفة',
            'url' => route('users.profile', $this->violation->employee_id).'?tab=violations',
            'violation_id' => $this->violation->id,
        ];
    }
}
