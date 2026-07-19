<?php

namespace App\Notifications;

use App\Models\PayrollRun;
use App\Notifications\Concerns\SendsToPreferredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PayrollExecuted extends Notification implements ShouldQueue
{
    use Queueable;
    use SendsToPreferredChannels;

    public function __construct(public PayrollRun $run) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable);
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => 'تم تنفيذ مسيّر رواتب شهر '.$this->run->month.' بالكامل من المالية',
            'url' => route('payroll-runs.index'),
            'payroll_run_id' => $this->run->id,
        ];
    }
}
