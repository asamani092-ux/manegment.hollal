<?php

namespace App\Notifications;

use App\Models\EmployeeDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class EmployeeDocumentExpiring extends Notification implements ShouldQueue
{
    use Queueable;
    use \App\Notifications\Concerns\SendsToPreferredChannels;

    public function __construct(
        public EmployeeDocument $document,
        public int $daysRemaining,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable);
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $employeeName = $this->document->user?->name ?? 'موظف';
        $endDate = $this->document->expiry_date?->format('Y-m-d') ?? '—';

        return [
            'message' => 'تنتهي وثيقة «'.$this->document->type.'» للموظف «'.$employeeName.'» خلال '.$this->daysRemaining.' يوم (بتاريخ '.$endDate.')',
            'url' => route('users.profile', $this->document->user_id).'?tab=documents',
            'employee_document_id' => $this->document->id,
            'days_remaining' => $this->daysRemaining,
        ];
    }
}
