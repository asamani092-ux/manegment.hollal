<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluationCycle extends Model
{
    public const STATUS_DRAFT = 'مسودة';

    public const STATUS_OPEN = 'مفتوحة';

    public const STATUS_CLOSED = 'مغلقة';

    /** @var list<string> */
    protected $fillable = [
        'year', 'quarter', 'status', 'evaluation_template_id',
        'starts_at', 'ends_at', 'opened_at', 'closed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'quarter' => 'integer',
            'starts_at' => 'date',
            'ends_at' => 'date',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function periodLabel(): string
    {
        return 'الربع '.$this->quarter.' / '.$this->year;
    }

    /** @return BelongsTo<EvaluationTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(EvaluationTemplate::class, 'evaluation_template_id');
    }

    /** @return HasMany<EvaluationCycleItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(EvaluationCycleItem::class)->orderBy('sort_order');
    }

    /** @return HasMany<EmployeeEvaluation, $this> */
    public function employeeEvaluations(): HasMany
    {
        return $this->hasMany(EmployeeEvaluation::class);
    }
}
