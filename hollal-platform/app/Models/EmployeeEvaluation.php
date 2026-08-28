<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeEvaluation extends Model
{
    public const STATUS_DRAFT = 'مسودة';

    public const STATUS_IN_PROGRESS = 'قيد_التقييم';

    public const STATUS_APPROVED = 'معتمد';

    public const STATUS_ARCHIVED = 'مؤرشف';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_IN_PROGRESS,
        self::STATUS_APPROVED,
        self::STATUS_ARCHIVED,
    ];

    /** @var list<string> */
    protected $fillable = [
        'evaluation_cycle_id', 'employee_id', 'evaluator_id', 'status', 'total_score',
        'approved_at', 'approved_by', 'archived_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'total_score' => 'decimal:2',
            'approved_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    /** Visible to the employee (after HR approval). */
    public function isVisibleToEmployee(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_ARCHIVED], true);
    }

    public function isEditableByScorers(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_IN_PROGRESS], true);
    }

    /** @return BelongsTo<EvaluationCycle, $this> */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(EvaluationCycle::class, 'evaluation_cycle_id');
    }

    /** @return BelongsTo<User, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    /** @return BelongsTo<User, $this> */
    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluator_id');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return HasMany<EmployeeEvaluationScore, $this> */
    public function scores(): HasMany
    {
        return $this->hasMany(EmployeeEvaluationScore::class);
    }

    /** @return HasMany<EmployeeEvaluationEditLog, $this> */
    public function editLogs(): HasMany
    {
        return $this->hasMany(EmployeeEvaluationEditLog::class)->orderByDesc('id');
    }
}
