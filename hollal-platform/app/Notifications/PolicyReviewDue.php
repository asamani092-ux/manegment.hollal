<?php

namespace App\Notifications;

use App\Models\Document;
use App\Notifications\Concerns\SendsToPreferredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * 07-B1 — a policy has reached its review date.
 */
class PolicyReviewDue extends Notification implements ShouldQueue
{
    use Queueable;
    use SendsToPreferredChannels;

    public function __construct(public Document $document) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable);
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => 'حان موعد مراجعة السياسة: '.$this->document->title,
            'document_id' => $this->document->id,
            'review_date' => $this->document->review_date?->toDateString(),
        ];
    }
}
