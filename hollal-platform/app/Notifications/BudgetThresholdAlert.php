<?php

namespace App\Notifications;

use App\Models\Project;
use App\Notifications\Concerns\SendsToPreferredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class BudgetThresholdAlert extends Notification implements ShouldQueue
{
    use Queueable;
    use SendsToPreferredChannels;

    public function __construct(
        public Project $project,
        public int $percent,
        public int $tier = 80,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable);
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => 'تنبيه موازنة ('.$this->tier.'%): مشروع «'.$this->project->name.'» بلغ استهلاكه '.$this->percent.'%',
            'url' => route('projects.show', $this->project->id),
            'project_id' => $this->project->id,
            'percent' => $this->percent,
            'tier' => $this->tier,
        ];
    }
}
