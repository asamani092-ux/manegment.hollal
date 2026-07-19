<?php

namespace App\Notifications;

use App\Models\ContractPaymentSchedule;
use App\Notifications\Concerns\SendsToPreferredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * 05-B6 — a scheduled payment is past due and still short.
 */
class PartnershipPaymentLate extends Notification implements ShouldQueue
{
    use Queueable;
    use SendsToPreferredChannels;

    public function __construct(public ContractPaymentSchedule $scheduleItem) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable);
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => 'دفعة متأخرة: '.($this->scheduleItem->label ?? 'دفعة')
                .' بمبلغ '.number_format((float) $this->scheduleItem->amount, 2)
                .' استحقت في '.$this->scheduleItem->due_on->format('Y-m-d'),
            'schedule_id' => $this->scheduleItem->id,
            'partnership_id' => $this->scheduleItem->contract?->partnership_id,
        ];
    }
}
