<?php

namespace App\Notifications;

use App\Models\WeeklyReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class WeeklyReportGenerated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public WeeklyReport $report) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        $start = $this->report->week_start->format('Y-m-d');
        $end = $this->report->week_end->format('Y-m-d');

        return [
            'message' => 'تم إنشاء التقرير الأسبوعي للفترة من '.$start.' إلى '.$end,
            'url' => route('reports.index', ['report' => $this->report->id]),
            'weekly_report_id' => $this->report->id,
        ];
    }
}
