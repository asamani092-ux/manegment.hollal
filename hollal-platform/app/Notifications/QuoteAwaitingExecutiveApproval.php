<?php

namespace App\Notifications;

use App\Models\Quote;
use App\Notifications\Concerns\SendsToPreferredChannels;
use App\Support\RecordUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Quote is ready for executive final approval.
 * Time: O(1) | Space: O(1)
 */
class QuoteAwaitingExecutiveApproval extends Notification implements ShouldQueue
{
    use Queueable;
    use SendsToPreferredChannels;

    public function __construct(public Quote $quote) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable);
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $this->quote->loadMissing('partnership');
        $name = $this->quote->partnership?->organization?->name
            ?? $this->quote->partnership?->entity_name
            ?? '#'.$this->quote->partnership_id;

        return [
            'message' => 'عرض سعر بانتظار اعتمادك النهائي — '.$name,
            'url' => RecordUrl::partnership((int) $this->quote->partnership_id).'?workspaceStep=2',
            'partnership_id' => $this->quote->partnership_id,
            'quote_id' => $this->quote->id,
        ];
    }
}
