<?php

namespace App\Notifications;

use App\Models\EmployeeEvaluation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class EvaluationApproved extends Notification implements ShouldQueue
{
    use Queueable;
    use Concerns\SendsToPreferredChannels;

    public function __construct(public EmployeeEvaluation $evaluation) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $this->preferredChannels($notifiable);
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $this->evaluation->loadMissing('cycle');
        $label = $this->evaluation->cycle?->periodLabel() ?? 'تقييم ربعى';

        return [
            'message' => 'اعتمدت الموارد البشرية تقييمك لـ '.$label.' — يظهر في أرشيف ملفك الوظيفي.',
            'employee_evaluation_id' => $this->evaluation->id,
            'url' => route('users.profile', $this->evaluation->employee_id, absolute: false).'?tab=evaluations',
        ];
    }
}
