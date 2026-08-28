<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeEvaluation extends Model
{
    public const STATUS_DRAFT = 'مسودة';

    public const STATUS_IN_PROGRESS = 'قيد_التقييم';

    public const STATUS_COMPLETE = 'مكتمل';

    /** @var list<string> */
    protected $fillable = [
        'evaluation_cycle_id', 'employee_id', 'evaluator_id', 'status', 'total_score',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'total_score' => 'decimal:2',
        ];
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

    /** @return HasMany<EmployeeEvaluationScore, $this> */
    public function scores(): HasMany
    {
        return $this->hasMany(EmployeeEvaluationScore::class);
    }
}
