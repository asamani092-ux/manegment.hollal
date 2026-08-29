<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\EmployeeEvaluation;
use App\Models\EmployeeEvaluationEditLog;
use App\Models\EmployeeEvaluationScore;
use App\Models\EmployeeProfile;
use App\Models\EvaluationCycle;
use App\Models\EvaluationCycleItem;
use App\Models\EvaluationTemplate;
use App\Models\EvaluationTemplateItem;
use App\Models\Task;
use App\Models\User;
use App\Notifications\EvaluationApproved;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use RuntimeException;

/**
 * HR Round 4 — quarterly evaluation engine (2أ templates/cycles + 2ب runtime).
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
     * Record a score against a frozen cycle item (draft / in-progress only).
     */
    public function recordScore(
        EmployeeEvaluation $evaluation,
        EvaluationCycleItem $cycleItem,
        int $score,
        ?string $note = null,
        ?User $scoredBy = null,
    ): EmployeeEvaluationScore {
        if ($score < 1 || $score > 5) {
            throw new InvalidArgumentException('الدرجة يجب أن تكون بين 1 و 5.');
        }

        if ($cycleItem->evaluation_cycle_id !== $evaluation->evaluation_cycle_id) {
            throw new InvalidArgumentException('بند الدورة لا يتبع تقييم الموظف.');
        }

        if ($evaluation->cycle?->isClosed() || $evaluation->isArchived()) {
            throw new RuntimeException('لا يمكن تعديل درجات دورة مغلقة أو تقييم مؤرشف.');
        }

        if ($evaluation->isApproved()) {
            throw new RuntimeException('التقييم معتمد — استخدم التعديل بسبب إلزامي.');
        }

        if (! $evaluation->isEditableByScorers()) {
            throw new RuntimeException('لا يمكن تسجيل درجات في هذه الحالة.');
        }

        $payload = [
            'score' => $score,
            'note' => $note,
        ];
        if ($scoredBy !== null) {
            $payload['scored_by'] = $scoredBy->id;
        }

        $row = EmployeeEvaluationScore::updateOrCreate(
            [
                'employee_evaluation_id' => $evaluation->id,
                'evaluation_cycle_item_id' => $cycleItem->id,
            ],
            $payload,
        );

        $evaluation->update([
            'status' => EmployeeEvaluation::STATUS_IN_PROGRESS,
            'total_score' => $this->calculateWeightedTotal($evaluation->fresh('scores.cycleItem')),
        ]);

        return $row;
    }

    /**
     * Save several scores for one section (مدير|موارد).
     * Pass $scoredBy when HR fills on behalf of the manager (or any actor tracking).
     *
     * @param  array<int, array{score: int|string|null, note?: string|null}>  $inputs  keyed by cycle item id
     */
    public function recordSectionScores(
        EmployeeEvaluation $evaluation,
        string $section,
        array $inputs,
        ?User $scoredBy = null,
    ): void {
        if (! in_array($section, EvaluationTemplateItem::SECTIONS, true)) {
            throw new InvalidArgumentException('قسم غير صالح.');
        }

        $evaluation->loadMissing('cycle.items');
        $items = $evaluation->cycle?->items->where('section', $section) ?? collect();

        foreach ($items as $item) {
            $input = $inputs[$item->id] ?? null;
            if ($input === null) {
                continue;
            }
            $raw = $input['score'] ?? '';
            if ($raw === '' || $raw === null) {
                continue;
            }
            $note = isset($input['note']) && trim((string) $input['note']) !== ''
                ? trim((string) $input['note'])
                : null;
            $this->recordScore($evaluation->fresh(), $item, (int) $raw, $note, $scoredBy);
        }
    }

    /** True when every cycle item has a non-null score. */
    public function isEvaluationFullyScored(EmployeeEvaluation $evaluation): bool
    {
        $evaluation->loadMissing(['cycle.items', 'scores']);
        $items = $evaluation->cycle?->items ?? collect();
        if ($items->isEmpty()) {
            return false;
        }

        $scored = $evaluation->scores->keyBy('evaluation_cycle_item_id');
        foreach ($items as $item) {
            $row = $scored->get($item->id);
            if ($row === null || $row->score === null) {
                return false;
            }
        }

        return true;
    }

    /**
     * Bulk-approve every evaluation in an open cycle — only when all employees
     * have scores for every cycle item. Individual approve is not the happy path.
     *
     * @return int number of newly approved evaluations
     */
    public function approveAll(EvaluationCycle $cycle, User $approver): int
    {
        if (! $cycle->isOpen()) {
            throw new RuntimeException('الاعتماد الجماعي متاح للدورة المفتوحة فقط.');
        }

        $evaluations = EmployeeEvaluation::query()
            ->where('evaluation_cycle_id', $cycle->id)
            ->with(['cycle.items', 'scores', 'employee'])
            ->get();

        if ($evaluations->isEmpty()) {
            throw new RuntimeException('لا توجد تقييمات للاعتماد — نفّذ الفتح الجماعي أولاً.');
        }

        $incomplete = $evaluations->filter(
            fn (EmployeeEvaluation $evaluation) => ! $evaluation->isApproved()
                && ! $evaluation->isArchived()
                && ! $this->isEvaluationFullyScored($evaluation)
        );

        if ($incomplete->isNotEmpty()) {
            throw new RuntimeException(
                'لا يمكن الاعتماد الجماعي — لم تكتمل درجات كل الموظفين لكل البنود.'
            );
        }

        $approved = 0;

        DB::transaction(function () use ($evaluations, $approver, &$approved) {
            foreach ($evaluations as $evaluation) {
                if ($evaluation->isArchived() || $evaluation->isApproved()) {
                    continue;
                }
                $this->approve($evaluation, $approver);
                $approved++;
            }
        });

        return $approved;
    }

    public function isSectionComplete(EmployeeEvaluation $evaluation, string $section): bool
    {
        $evaluation->loadMissing(['cycle.items', 'scores']);
        $items = $evaluation->cycle?->items->where('section', $section) ?? collect();
        if ($items->isEmpty()) {
            return true;
        }

        $scored = $evaluation->scores->keyBy('evaluation_cycle_item_id');
        foreach ($items as $item) {
            $row = $scored->get($item->id);
            if ($row === null || $row->score === null) {
                return false;
            }
        }

        return true;
    }

    public function sectionCompletionLabel(EmployeeEvaluation $evaluation, string $section): string
    {
        return $this->isSectionComplete($evaluation, $section) ? 'مكتمل' : 'غير مكتمل';
    }

    /**
     * HR approve — employee sees the evaluation immediately (no publish state).
     */
    public function approve(EmployeeEvaluation $evaluation, User $approver): EmployeeEvaluation
    {
        $evaluation->loadMissing('cycle');

        if ($evaluation->cycle?->isClosed()) {
            throw new RuntimeException('الدورة مغلقة.');
        }

        if ($evaluation->isArchived()) {
            throw new RuntimeException('التقييم مؤرشف مسبقاً.');
        }

        if ($evaluation->isApproved()) {
            throw new RuntimeException('التقييم معتمد مسبقاً.');
        }

        $evaluation->update([
            'status' => EmployeeEvaluation::STATUS_APPROVED,
            'approved_at' => now(),
            'approved_by' => $approver->id,
            'total_score' => $this->calculateWeightedTotal($evaluation->fresh('scores.cycleItem'))
                ?? $evaluation->total_score,
        ]);

        $fresh = $evaluation->fresh(['employee', 'cycle']);
        if ($fresh?->employee) {
            Notification::send($fresh->employee, new EvaluationApproved($fresh));
        }

        return $fresh;
    }

    /**
     * Edit scores after approval — mandatory reason + cumulative log.
     *
     * @param  array<int, array{score: int|string|null, note?: string|null}>  $inputs
     */
    public function amendAfterApproval(
        EmployeeEvaluation $evaluation,
        array $inputs,
        string $reason,
        User $actor,
    ): EmployeeEvaluation {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('سبب التعديل إلزامي بعد الاعتماد.');
        }

        $evaluation->loadMissing(['cycle.items', 'scores']);

        if ($evaluation->cycle?->isClosed() || $evaluation->isArchived()) {
            throw new RuntimeException('لا يمكن تعديل تقييم مؤرشف أو دورة مغلقة.');
        }

        if (! $evaluation->isApproved()) {
            throw new RuntimeException('التعديل بسبب متاح بعد الاعتماد فقط.');
        }

        $before = $this->scoresSnapshot($evaluation);

        return DB::transaction(function () use ($evaluation, $inputs, $reason, $actor, $before) {
            foreach ($evaluation->cycle->items as $item) {
                $input = $inputs[$item->id] ?? null;
                if ($input === null) {
                    continue;
                }
                $raw = $input['score'] ?? '';
                if ($raw === '' || $raw === null) {
                    continue;
                }
                $score = (int) $raw;
                if ($score < 1 || $score > 5) {
                    throw new InvalidArgumentException('الدرجة يجب أن تكون بين 1 و 5.');
                }
                $note = isset($input['note']) && trim((string) $input['note']) !== ''
                    ? trim((string) $input['note'])
                    : null;

                EmployeeEvaluationScore::updateOrCreate(
                    [
                        'employee_evaluation_id' => $evaluation->id,
                        'evaluation_cycle_item_id' => $item->id,
                    ],
                    [
                        'score' => $score,
                        'note' => $note,
                        'scored_by' => $actor->id,
                    ],
                );
            }

            $fresh = $evaluation->fresh('scores.cycleItem');
            $after = $this->scoresSnapshot($fresh);
            $total = $this->calculateWeightedTotal($fresh);

            EmployeeEvaluationEditLog::create([
                'employee_evaluation_id' => $evaluation->id,
                'user_id' => $actor->id,
                'reason' => $reason,
                'before_scores' => $before,
                'after_scores' => $after,
            ]);

            $evaluation->update(['total_score' => $total]);

            return $evaluation->fresh(['scores.cycleItem', 'editLogs']);
        });
    }

    /**
     * Close cycle: unapproved → approve with zero total, then archive all, mark cycle closed.
     */
    public function closeCycle(EvaluationCycle $cycle, ?User $actor = null): EvaluationCycle
    {
        if (! $cycle->isOpen()) {
            throw new RuntimeException('إغلاق الدورة متاح للدورة المفتوحة فقط.');
        }

        return DB::transaction(function () use ($cycle, $actor) {
            $evaluations = EmployeeEvaluation::query()
                ->where('evaluation_cycle_id', $cycle->id)
                ->lockForUpdate()
                ->get();

            foreach ($evaluations as $evaluation) {
                if ($evaluation->isArchived()) {
                    continue;
                }

                if (! $evaluation->isApproved()) {
                    $evaluation->update([
                        'status' => EmployeeEvaluation::STATUS_APPROVED,
                        'approved_at' => now(),
                        'approved_by' => $actor?->id,
                        'total_score' => 0,
                    ]);
                    $evaluation = $evaluation->fresh();
                    if ($evaluation->employee) {
                        Notification::send($evaluation->employee, new EvaluationApproved($evaluation));
                    }
                }

                $evaluation->update([
                    'status' => EmployeeEvaluation::STATUS_ARCHIVED,
                    'archived_at' => now(),
                ]);
            }

            $cycle->update([
                'status' => EvaluationCycle::STATUS_CLOSED,
                'closed_at' => now(),
            ]);

            return $cycle->fresh();
        });
    }

    /**
     * Reference-only attendance + tasks for the cycle window (no auto scoring).
     *
     * @return array{attendance: Collection<int, AttendanceRecord>, tasks: Collection<int, Task>}
     */
    public function referenceReports(EmployeeEvaluation $evaluation): array
    {
        $evaluation->loadMissing('cycle');
        $cycle = $evaluation->cycle;
        if ($cycle === null) {
            return ['attendance' => collect(), 'tasks' => collect()];
        }

        $from = Carbon::parse($cycle->starts_at)->startOfDay();
        $to = Carbon::parse($cycle->ends_at)->endOfDay();

        $attendance = AttendanceRecord::query()
            ->where('employee_id', $evaluation->employee_id)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->get();

        $tasks = Task::query()
            ->where('assigned_to', $evaluation->employee_id)
            ->where(function ($q) use ($from, $to) {
                $q->whereBetween('due_date', [$from, $to])
                    ->orWhereBetween('completed_at', [$from, $to])
                    ->orWhere(function ($inner) use ($from, $to) {
                        $inner->whereNull('completed_at')
                            ->where('created_at', '<=', $to)
                            ->where(function ($status) use ($from) {
                                $status->whereNull('due_date')
                                    ->orWhere('due_date', '>=', $from);
                            });
                    });
            })
            ->orderByDesc('due_date')
            ->limit(100)
            ->get(['id', 'title', 'status', 'due_date', 'completed_at', 'final_rating']);

        return ['attendance' => $attendance, 'tasks' => $tasks];
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

    public function currentOpenCycle(): ?EvaluationCycle
    {
        return EvaluationCycle::query()
            ->where('status', EvaluationCycle::STATUS_OPEN)
            ->orderByDesc('year')
            ->orderByDesc('quarter')
            ->first();
    }

    /**
     * @return list<array{item_id: int, score: int|null, note: string|null}>
     */
    private function scoresSnapshot(EmployeeEvaluation $evaluation): array
    {
        $evaluation->loadMissing('scores');

        return $evaluation->scores->map(fn (EmployeeEvaluationScore $row) => [
            'item_id' => (int) $row->evaluation_cycle_item_id,
            'score' => $row->score !== null ? (int) $row->score : null,
            'note' => $row->note,
        ])->values()->all();
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
