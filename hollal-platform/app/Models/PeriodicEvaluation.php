<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PeriodicEvaluation extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'مسودة';

    public const STATUS_PUBLISHED = 'منشور';

    /** @var list<string> */
    protected $fillable = ['employee_id', 'period', 'evaluator_id', 'status', 'employee_comment'];

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /** @return HasMany<EvaluationScore, $this> */
    public function scores(): HasMany
    {
        return $this->hasMany(EvaluationScore::class);
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
}
