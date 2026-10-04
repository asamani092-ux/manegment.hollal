<?php

namespace App\Services;

use App\Models\EmployeeEvaluation;
use App\Models\EvaluationCycle;
use App\Models\EvaluationTemplate;
use App\Models\PeriodicEvaluation;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Flexible evaluation cycles. The quarterly service stays for existing callers.
 * Time: O(1) create, O(n) legacy import | Space: O(1).
 */
class EvaluationCycleService extends QuarterlyEvaluationService
{
    /**
     * @param  array<string, mixed>  $scope
     * @param  list<int>  $projectIds
     */
    public function createFlexibleCycle(
        string $name,
        EvaluationTemplate $template,
        Carbon|string $startsAt,
        Carbon|string $endsAt,
        array $scope = ['all' => true],
        array $projectIds = [],
    ): EvaluationCycle {
        if (! $template->is_active) {
            throw new InvalidArgumentException('لا يمكن استخدام قالب غير نشط.');
        }
        if ($template->items()->count() === 0) {
            throw new InvalidArgumentException('القالب لا يحتوي بنوداً.');
        }
        $starts = Carbon::parse($startsAt)->startOfDay();
        $ends = Carbon::parse($endsAt)->startOfDay();
        if ($ends->lt($starts)) {
            throw new InvalidArgumentException('تاريخ النهاية يجب أن يكون بعد أو يساوي البداية.');
        }

        return EvaluationCycle::query()->create([
            'name' => $name,
            'year' => null,
            'quarter' => null,
            'scope' => $scope,
            'linked_project_ids' => $projectIds,
            'status' => EvaluationCycle::STATUS_DRAFT,
            'evaluation_template_id' => $template->id,
            'starts_at' => $starts->toDateString(),
            'ends_at' => $ends->toDateString(),
        ]);
    }

    public function importLegacy(PeriodicEvaluation $legacy): EvaluationCycle
    {
        $name = 'أرشيف — '.$legacy->period;
        $existing = EvaluationCycle::query()
            ->where('name', $name)
            ->whereHas('employeeEvaluations', fn ($query) => $query->where('employee_id', $legacy->employee_id))
            ->first();
        if ($existing) {
            return $existing;
        }
        $template = EvaluationTemplate::query()->firstOrCreate(
            ['name' => 'أرشيف التقييم'],
            ['is_active' => false],
        );
        $cycle = EvaluationCycle::query()->create([
            'name' => $name,
            'year' => null,
            'quarter' => null,
            'scope' => ['legacy_id' => $legacy->id],
            'status' => EvaluationCycle::STATUS_CLOSED,
            'evaluation_template_id' => $template->id,
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->toDateString(),
        ]);
        EmployeeEvaluation::query()->create([
            'evaluation_cycle_id' => $cycle->id,
            'employee_id' => $legacy->employee_id,
            'evaluator_id' => $legacy->evaluator_id,
            'status' => EmployeeEvaluation::STATUS_ARCHIVED,
        ]);

        return $cycle;
    }
}
