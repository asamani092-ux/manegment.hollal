<?php

namespace App\Notifications;

use App\Models\Contract;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ContractExpiring extends Notification implements ShouldQueue
{
    use Queueable;
    use \App\Notifications\Concerns\SendsToPreferredChannels;

    public function __construct(
        public Contract $contract,
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
        $employeeName = $this->contract->employee?->name ?? 'موظف';
        $endDate = $this->contract->end_date->format('Y-m-d');

        return [
            'message' => 'ينتهي عقد «'.$employeeName.'» خلال '.$this->daysRemaining.' يوم (بتاريخ '.$endDate.')',
            'url' => route('contracts.index'),
            'contract_id' => $this->contract->id,
            'days_remaining' => $this->daysRemaining,
        ];
    }
}
