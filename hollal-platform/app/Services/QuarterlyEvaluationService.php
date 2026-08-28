<?php

namespace App\Services;

use App\Models\EmployeeEvaluation;
use App\Models\EmployeeEvaluationScore;
use App\Models\EmployeeProfile;
use App\Models\EvaluationCycle;
use App\Models\EvaluationCycleItem;
use App\Models\EvaluationTemplate;
use App\Models\EvaluationTemplateItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * HR Round 4 batch 2أ — quarterly evaluation engine:
 * templates (weights = 100) · cycle snapshot · bulk open · weighted totals.
 */
class QuarterlyEvaluationService
{
    /**
     * @param  list<array{section: string, question_text: string, weight: int, sort_order?: int}>  $items
     */
    public function createTemplate(string $name, array $items, bool $isActive = true): EvaluationTemplate
    {
        $this->assertWeightsSumTo100($items);

        return DB::transaction(function () use ($name, $items, $isActive) {
            $template = EvaluationTemplate::create([
                'name' => $name,
                'is_active' => $isActive,
            ]);

            $this->syncTemplateItems($template, $items);

            return $template->load('items');
        });
    }

    /**
     * @param  list<array{section: string, question_text: string, weight: int, sort_order?: int}>  $items
     */
    public function updateTemplate(EvaluationTemplate $template, string $name, array $items, ?bool $isActive = null): EvaluationTemplate
    {
        $this->assertWeightsSumTo100($items);

        return DB::transaction(function () use ($template, $name, $items, $isActive) {
            $payload = ['name' => $name];
            if ($isActive !== null) {
                $payload['is_active'] = $isActive;
            }
            $template->update($payload);
            $template->items()->delete();
            $this->syncTemplateItems($template, $items);

            return $template->fresh('items');
        });
    }

    /**
     * Create a draft cycle bound to a template (snapshot happens on open).
     */
    public function createCycle(
        int $year,
        int $quarter,
        EvaluationTemplate $template,
        Carbon|string $startsAt,
        Carbon|string $endsAt,
    ): EvaluationCycle {
        if ($quarter < 1 || $quarter > 4) {
            throw new InvalidArgumentException('الربع يجب أن يكون بين 1 و 4.');
        }

        if (! $template->is_active) {
            throw new InvalidArgumentException('لا يمكن استخدام قالب غير نشط.');
        }

        if ($template->items()->count() === 0) {
            throw new InvalidArgumentException('القالب لا يحتوي بنوداً.');
        }

        if ((int) $template->items()->sum('weight') !== 100) {
            throw new InvalidArgumentException('مجموع أوزان بنود القالب يجب أن يساوي 100.');
        }

        $exists = EvaluationCycle::query()
            ->where('year', $year)
            ->where('quarter', $quarter)
            ->exists();

        if ($exists) {
            throw new RuntimeException('توجد دورة تقييم لنفس السنة والربع.');
        }

        $starts = Carbon::parse($startsAt)->startOfDay();
        $ends = Carbon::parse($endsAt)->startOfDay();
        if ($ends->lt($starts)) {
            throw new InvalidArgumentException('تاريخ النهاية يجب أن يكون بعد أو يساوي البداية.');
        }

        return EvaluationCycle::create([
            'year' => $year,
            'quarter' => $quarter,
            'status' => EvaluationCycle::STATUS_DRAFT,
            'evaluation_template_id' => $template->id,
            'starts_at' => $starts->toDateString(),
            'ends_at' => $ends->toDateString(),
        ]);
    }

    /**
     * Open a draft cycle: copy template items into a frozen snapshot, mark open.
     */
    public function openCycle(EvaluationCycle $cycle): EvaluationCycle
    {
        if (! $cycle->isDraft()) {
            throw new RuntimeException('لا يمكن فتح دورة ليست مسودة.');
        }

        if ($cycle->items()->exists()) {
            throw new RuntimeException('الدورة تحتوي لقطة بنود مسبقاً.');
        }

        $template = $cycle->template()->with('items')->firstOrFail();
        if ($template->items->isEmpty()) {
            throw new InvalidArgumentException('القالب لا يحتوي بنوداً.');
        }

        if ((int) $template->items->sum('weight') !== 100) {
            throw new InvalidArgumentException('مجموع أوزان بنود القالب يجب أن يساوي 100.');
        }

        return DB::transaction(function () use ($cycle, $template) {
            foreach ($template->items as $item) {
                EvaluationCycleItem::create([
                    'evaluation_cycle_id' => $cycle->id,
                    'section' => $item->section,
                    'question_text' => $item->question_text,
                    'weight' => $item->weight,
                    'sort_order' => $item->sort_order,
                ]);
            }

            $cycle->update([
                'status' => EvaluationCycle::STATUS_OPEN,
                'opened_at' => now(),
            ]);

            return $cycle->fresh(['items', 'template']);
        });
    }

    /**
     * Bulk-open employee evaluations for eligible staff.
     * Excludes: frozen, terminated, mid-quarter joiners (hire_date after cycle starts_at).
     *
     * @return int number of evaluations created
     */
    public function bulkOpen(EvaluationCycle $cycle): int
    {
        if (! $cycle->isOpen()) {
            throw new RuntimeException('الفتح الجماعي متاح للدورة المفتوحة فقط.');
        }

        if ($cycle->items()->count() === 0) {
            throw new RuntimeException('لا توجد بنود لقطة للدورة.');
        }

        $eligible = $this->eligibleEmployeesQuery($cycle)->get();
        $created = 0;

        DB::transaction(function () use ($cycle, $eligible, &$created) {
            foreach ($eligible as $employee) {
                $already = EmployeeEvaluation::query()
                    ->where('evaluation_cycle_id', $cycle->id)
                    ->where('employee_id', $employee->id)
                    ->exists();

                if ($already) {
                    continue;
                }

                EmployeeEvaluation::create([
                    'evaluation_cycle_id' => $cycle->id,
                    'employee_id' => $employee->id,
                    'evaluator_id' => $employee->manager_id,
                    'status' => EmployeeEvaluation::STATUS_DRAFT,
                ]);
                $created++;
            }
        });

        return $created;
    }

    /**
     * Record a score against a frozen cycle item (not the live template).
     */
    public function recordScore(
        EmployeeEvaluation $evaluation,
        EvaluationCycleItem $cycleItem,
        int $score,
        ?string $note = null,
    ): EmployeeEvaluationScore {
        if ($score < 1 || $score > 5) {
            throw new InvalidArgumentException('الدرجة يجب أن تكون بين 1 و 5.');
        }

        if ($cycleItem->evaluation_cycle_id !== $evaluation->evaluation_cycle_id) {
            throw new InvalidArgumentException('بند الدورة لا يتبع تقييم الموظف.');
        }

        if ($evaluation->cycle?->isClosed()) {
            throw new RuntimeException('لا يمكن تعديل درجات دورة مغلقة.');
        }

        $row = EmployeeEvaluationScore::updateOrCreate(
            [
                'employee_evaluation_id' => $evaluation->id,
                'evaluation_cycle_item_id' => $cycleItem->id,
            ],
            [
                'score' => $score,
                'note' => $note,
            ],
        );

        $evaluation->update([
            'status' => EmployeeEvaluation::STATUS_IN_PROGRESS,
            'total_score' => $this->calculateWeightedTotal($evaluation->fresh('scores.cycleItem')),
        ]);

        return $row;
    }

    /**
     * Weighted total: Σ(score × weight) / 100. Null if any scored item missing or empty.
     */
    public function calculateWeightedTotal(EmployeeEvaluation $evaluation): ?float
    {
        $evaluation->loadMissing(['scores.cycleItem', 'cycle.items']);
        $items = $evaluation->cycle?->items ?? collect();

        if ($items->isEmpty()) {
            return null;
        }

        $scoresByItem = $evaluation->scores->keyBy('evaluation_cycle_item_id');
        $sum = 0.0;

        foreach ($items as $item) {
            $row = $scoresByItem->get($item->id);
            if ($row === null || $row->score === null) {
                return null;
            }
            $sum += ((int) $row->score) * ((int) $item->weight);
        }

        return round($sum / 100, 2);
    }

    /**
     * Employees eligible for bulk open of a cycle.
     *
     * @return \Illuminate\Database\Eloquent\Builder<User>
     */
    public function eligibleEmployeesQuery(EvaluationCycle $cycle)
    {
        $startsAt = Carbon::parse($cycle->starts_at)->toDateString();

        return User::query()
            ->where('employment_status', User::STATUS_ACTIVE)
            ->where('is_active', true)
            ->where(function ($q) use ($startsAt) {
                $q->whereDoesntHave('profile')
                    ->orWhereHas('profile', function ($profile) use ($startsAt) {
                        $profile->whereNull('hire_date')
                            ->orWhereDate('hire_date', '<=', $startsAt);
                    });
            });
    }

    public function isEligible(User $employee, EvaluationCycle $cycle): bool
    {
        if ($employee->employment_status !== User::STATUS_ACTIVE || ! $employee->is_active) {
            return false;
        }

        $hireDate = $employee->profile?->hire_date
            ?? EmployeeProfile::query()->where('user_id', $employee->id)->value('hire_date');

        if ($hireDate === null) {
            return true;
        }

        return Carbon::parse($hireDate)->toDateString() <= Carbon::parse($cycle->starts_at)->toDateString();
    }

    /**
     * @param  list<array{section: string, question_text: string, weight: int, sort_order?: int}>  $items
     */
    private function assertWeightsSumTo100(array $items): void
    {
        if ($items === []) {
            throw new InvalidArgumentException('أضف بنداً واحداً على الأقل.');
        }

        $sum = 0;
        foreach ($items as $item) {
            $section = (string) ($item['section'] ?? '');
            if (! in_array($section, EvaluationTemplateItem::SECTIONS, true)) {
                throw new InvalidArgumentException('قسم البند يجب أن يكون «مدير» أو «موارد».');
            }
            $weight = (int) ($item['weight'] ?? 0);
            if ($weight < 1 || $weight > 100) {
                throw new InvalidArgumentException('وزن البند يجب أن يكون بين 1 و 100.');
            }
            $text = trim((string) ($item['question_text'] ?? ''));
            if ($text === '') {
                throw new InvalidArgumentException('نص السؤال مطلوب.');
            }
            $sum += $weight;
        }

        if ($sum !== 100) {
            throw new InvalidArgumentException('مجموع أوزان بنود القالب يجب أن يساوي 100.');
        }
    }

    /**
     * @param  list<array{section: string, question_text: string, weight: int, sort_order?: int}>  $items
     */
    private function syncTemplateItems(EvaluationTemplate $template, array $items): void
    {
        $order = 1;
        foreach ($items as $item) {
            EvaluationTemplateItem::create([
                'evaluation_template_id' => $template->id,
                'section' => $item['section'],
                'question_text' => trim((string) $item['question_text']),
                'weight' => (int) $item['weight'],
                'sort_order' => (int) ($item['sort_order'] ?? $order),
            ]);
            $order++;
        }
    }
}
