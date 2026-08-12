<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class LeaveDecision extends Notification implements ShouldQueue
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
            'message' => 'تم '.$this->leaveRequest->status.' طلب إجازتك ('.$this->leaveRequest->type.')',
            'leave_request_id' => $this->leaveRequest->id,
            'status' => $this->leaveRequest->status,
            'url' => route('leaves.index'),
        ];
    }
}
