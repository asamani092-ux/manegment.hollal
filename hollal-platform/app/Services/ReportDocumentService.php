<?php

namespace App\Services;

use App\Models\Document;
use App\Models\ReportSnapshot;
use App\Models\User;
use App\Models\WeeklyReport;
use Illuminate\Support\Facades\Storage;

/**
 * Archive report snapshots / weekly reports into the documents repository.
 * Cumulative — each call creates a new Document (never overwrites).
 */
class ReportDocumentService
{
    public function archiveWeeklyReport(WeeklyReport $report, ?User $uploader = null): Document
    {
        $uploader ??= auth()->user();
        if (! $uploader) {
            throw new \InvalidArgumentException('مطلوب مستخدم لرفع أرشيف التقرير.');
        }

        $weekStart = $report->week_start?->format('Y-m-d') ?? '—';
        $weekEnd = $report->week_end?->format('Y-m-d') ?? '—';
        $title = 'تقرير أسبوعي '.$weekStart.' – '.$weekEnd;

        $body = $this->weeklyReportText($report, $title);
        $path = 'reports/weekly/'.$report->id.'-'.now()->format('YmdHis').'.txt';
        Storage::disk('local')->put($path, $body);

        return Document::create([
            'title' => $title,
            'category' => 'تقرير',
            'source_type' => 'weekly_report',
            'source_id' => $report->id,
            'is_auto_archived' => true,
            'confidentiality' => 'managers',
            'uploader_id' => $uploader->id,
            'path' => $path,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function archiveCenterExport(
        string $tab,
        string $label,
        array $payload,
        ?User $uploader = null,
        ?ReportSnapshot $snapshot = null,
    ): Document
    {
        $uploader ??= auth()->user();
        if (! $uploader) {
            throw new \InvalidArgumentException('مطلوب مستخدم لرفع أرشيف التقرير.');
        }

        $stamp = now()->format('Y-m-d H:i');
        $title = 'تقرير — '.$label.' — '.$stamp;

        $body = $label."\n".$stamp."\n\n".json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $path = 'reports/center/'.$tab.'-'.now()->format('YmdHis').'.txt';
        Storage::disk('local')->put($path, $body);

        return Document::create([
            'title' => $title,
            'category' => 'تقرير',
            'source_type' => $snapshot ? 'report_snapshot' : 'report_export',
            'source_id' => $snapshot?->id,
            'is_auto_archived' => true,
            'confidentiality' => 'managers',
            'uploader_id' => $uploader->id,
            'path' => $path,
        ]);
    }

    private function weeklyReportText(WeeklyReport $report, string $title): string
    {
        $lines = [
            $title,
            'من: '.($report->week_start?->format('Y-m-d') ?? '—'),
            'إلى: '.($report->week_end?->format('Y-m-d') ?? '—'),
            'الإنفاق الأسبوعي: '.(string) $report->week_spend,
            '',
            'المهام المنجزة: '.count($report->done ?? []),
            'المهام المتأخرة: '.count($report->overdue ?? []),
            'المشاريع النشطة: '.count($report->project_status ?? []),
            'القرارات المفتوحة: '.count($report->open_decisions ?? []),
            '',
            json_encode([
                'done' => $report->done,
                'overdue' => $report->overdue,
                'project_status' => $report->project_status,
                'open_decisions' => $report->open_decisions,
            ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT),
        ];

        return implode("\n", $lines);
    }
}
