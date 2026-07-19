<?php

namespace App\Services;

use App\Models\ExpenseRequest;
use App\Models\MeasurementResponse;
use App\Models\Partnership;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * 06B-B5 — closure. Every checklist item is derived, not ticked by hand, and
 * the critical ones block closure outright.
 */
class ProjectClosureService
{
    /**
     * @return array<string, array{label: string, ok: bool, critical: bool}>
     */
    public function checklist(Project $project): array
    {
        $openTasks = $project->tasks()
            ->whereNotIn('status', ['completed', 'cancelled', 'disabled'])
            ->count();

        $missingEvidence = $project->tasks()
            ->whereNotNull('required_evidence')
            ->where('status', 'completed')
            ->whereNull('submitted_file')
            ->count();

        $openExpenses = ExpenseRequest::query()
            ->where('project_id', $project->id)
            ->whereIn('status', ['draft', 'pending', 'approved'])
            ->count();

        $hasPostMeasurement = MeasurementResponse::query()
            ->where('project_id', $project->id)
            ->where('phase', MeasurementResponse::PHASE_POST)
            ->exists();

        return [
            'tasks' => [
                'label' => 'كل المهام معتمدة أو معطلة بمبرر',
                'ok' => $openTasks === 0,
                'critical' => true,
            ],
            'evidence' => [
                'label' => 'الشواهد مرفوعة',
                'ok' => $missingEvidence === 0,
                'critical' => true,
            ],
            'expenses' => [
                'label' => 'المصروفات مغلقة (لا طلبات معلقة)',
                'ok' => $openExpenses === 0,
                'critical' => true,
            ],
            'measurement' => [
                'label' => 'القياس البعدي مُدخل',
                'ok' => $hasPostMeasurement,
                'critical' => true,
            ],
            'final_report' => [
                'label' => 'التقرير الختامي مولّد ومعتمد ومسلّم',
                'ok' => $project->final_report_approved_at !== null && $project->delivered_at !== null,
                'critical' => true,
            ],
            'lesson' => [
                'label' => 'درس مستفاد مسجّل',
                'ok' => filled($project->lesson_learned),
                'critical' => true,
            ],
        ];
    }

    /** @return list<string> labels of unmet critical items */
    public function blockers(Project $project): array
    {
        return collect($this->checklist($project))
            ->filter(fn (array $item) => $item['critical'] && ! $item['ok'])
            ->pluck('label')
            ->values()
            ->all();
    }

    /** Generate the final report PDF and store it on the private disk. */
    public function generateFinalReport(Project $project): string
    {
        $progress = app(ProjectProgressService::class)->summary($project);
        $results = app(MeasurementService::class)->results($project);

        $html = '<div dir="rtl" style="font-family: dejavu sans;">'
            .'<h2>التقرير الختامي — '.e($project->name).'</h2>'
            .'<p>نسبة الإنجاز الموزونة: '.number_format($progress['weighted_percent'], 2).'%</p>'
            .'<p>عدد المهام: '.(int) $progress['total'].' — المقيّمة نهائيًا: '.(int) $progress['evaluated'].'</p>'
            .'<p>المستفيدون: '.(int) $results['beneficiaries'].'</p>'
            .'<p>القياس القبلي: '.($results['pre_percent'] !== null ? number_format($results['pre_percent'], 2).'%' : '—').'</p>'
            .'<p>القياس البعدي: '.($results['post_percent'] !== null ? number_format($results['post_percent'], 2).'%' : '—').'</p>'
            .'<p>نسبة التحسن: '.($results['improvement_percent'] !== null ? number_format($results['improvement_percent'], 2).'%' : '—').'</p>'
            .'<p>الرضا: '.($results['satisfaction_percent'] !== null ? number_format($results['satisfaction_percent'], 2).'%' : '—').'</p>'
            .'<p>درس مستفاد: '.e((string) $project->lesson_learned).'</p>'
            .'</div>';

        $pdf = Pdf::loadHTML($html)->setPaper('a4')->setOption('defaultFont', 'dejavu sans')->output();
        $path = 'projects/'.$project->id.'/final-report.pdf';
        Storage::disk('local')->put($path, $pdf);

        $project->forceFill(['final_report_path' => $path])->save();

        return $path;
    }

    public function approveFinalReport(Project $project): Project
    {
        if (! $project->final_report_path) {
            throw new \RuntimeException('لا يوجد تقرير ختامي مولّد');
        }

        $project->forceFill(['final_report_approved_at' => now()])->save();

        return $project;
    }

    /** Delivery happens through the partner link (05-B5), never by email attachment. */
    public function markDelivered(Project $project): Project
    {
        if (! $project->final_report_approved_at) {
            throw new \RuntimeException('لا يُسلَّم تقرير غير معتمد');
        }

        $project->forceFill(['delivered_at' => now()])->save();

        return $project;
    }

    public function recordLesson(Project $project, string $lesson): Project
    {
        $project->forceFill(['lesson_learned' => $lesson])->save();

        return $project;
    }

    /**
     * Close the project and open the renewal opportunity on the partnership.
     */
    public function close(Project $project, User $actor): Project
    {
        $blockers = $this->blockers($project);

        if ($blockers !== []) {
            throw new \RuntimeException('لا يمكن الإغلاق قبل استيفاء: '.implode('، ', $blockers));
        }

        return DB::transaction(function () use ($project, $actor) {
            $project->forceFill([
                'status' => 'مغلق',
                'closed_at' => now(),
                'closed_by' => $actor->id,
            ])->save();

            app(MeasurementService::class)->ascendImpact($project);
            $this->openRenewalOpportunity($project, $actor);

            return $project->fresh();
        });
    }

    /**
     * 06B-B5 — a closed project suggests a renewal journey for the partnerships
     * manager, linked back to the partnership it came from.
     */
    public function openRenewalOpportunity(Project $project, ?User $actor = null): ?Partnership
    {
        $partnership = $project->partnership;

        if (! $partnership) {
            return null;
        }

        $existing = Partnership::where('renewed_from_id', $partnership->id)->first();

        if ($existing) {
            return $existing;
        }

        $renewal = Partnership::create([
            'organization_id' => $partnership->organization_id,
            'entity_name' => $partnership->entity_name,
            'owner_id' => $partnership->owner_id,
            'renewed_from_id' => $partnership->id,
            'stage' => Partnership::STAGE_OPPORTUNITY,
            'stage_entered_at' => now(),
        ]);

        app(PartnershipPipelineService::class)->moveTo(
            $renewal,
            Partnership::STAGE_OPPORTUNITY,
            $actor,
            'فرصة تجديد بعد إغلاق مشروع: '.$project->name,
        );

        return $renewal;
    }
}
