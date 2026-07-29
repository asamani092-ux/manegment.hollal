<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class LeaveRequested extends Notification implements ShouldQueue
{
    use Queueable;
    use Concerns\SendsToPreferredChannels;

    public function __construct(public LeaveRequest $leaveRequest) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable);
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => 'طلب إجازة جديد من '.$this->leaveRequest->employee?->name,
            'leave_request_id' => $this->leaveRequest->id,
            'type' => $this->leaveRequest->type,
            'from_date' => $this->leaveRequest->from_date?->format('Y-m-d'),
            'to_date' => $this->leaveRequest->to_date?->format('Y-m-d'),
            'url' => route('leaves.index'),
        ];
    }
}
