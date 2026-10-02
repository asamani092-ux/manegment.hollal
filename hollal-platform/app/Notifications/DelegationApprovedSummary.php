<?php

namespace App\Notifications;

use App\Models\Delegation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DelegationApprovedSummary extends Notification
{
    use Queueable;

    /** @param array<string, mixed> $summary */
    public function __construct(public Delegation $delegation, public array $summary) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => 'تم اعتماد الإنابة',
            'delegation_id' => $this->delegation->id,
            'sensitive' => $this->summary['sensitive'] ?? [],
        ];
    }
}