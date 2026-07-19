<?php

namespace App\Notifications;

use App\Models\Partnership;
use App\Notifications\Concerns\SendsToPreferredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * 05-B2 — a partnership has sat in its stage past the configured threshold.
 */
class PartnershipStale extends Notification implements ShouldQueue
{
    use Queueable;
    use SendsToPreferredChannels;

    public function __construct(public Partnership $partnership, public int $days) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable);
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => 'شراكة راكدة في مرحلة «'.$this->partnership->stageLabel().'» منذ '.$this->days.' يومًا',
            'partnership_id' => $this->partnership->id,
            'stage' => $this->partnership->stage,
            'days' => $this->days,
        ];
    }
}
