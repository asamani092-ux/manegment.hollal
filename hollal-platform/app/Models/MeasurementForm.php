<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 06B-B4 — a measurement form bound to a program. The same form is answered
 * pre and post, so the two runs are directly comparable.
 */
class MeasurementForm extends Model
{
    use SoftDeletes;

    public const KIND_TEST = 'اختبار';

    public const KIND_SATISFACTION = 'رضا';

    /** @var list<string> */
    protected $fillable = ['program_id', 'title', 'kind'];

    /** @return HasMany<MeasurementQuestion, $this> */
    public function questions(): HasMany
    {
        return $this->hasMany(MeasurementQuestion::class)->orderBy('position');
    }

    /** @return BelongsTo<Program, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function maxScore(): float
    {
        return (float) $this->questions()->sum('max_score');
    }
}
