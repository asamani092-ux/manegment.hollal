<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EvaluationCycleItem extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'evaluation_cycle_id', 'section', 'question_text', 'weight', 'sort_order',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'weight' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<EvaluationCycle, $this> */
    public function cycle(): BelongsTo
    {
        return $this->belongsTo(EvaluationCycle::class, 'evaluation_cycle_id');
    }

    /** @return HasMany<EmployeeEvaluationScore, $this> */
    public function scores(): HasMany
    {
        return $this->hasMany(EmployeeEvaluationScore::class);
    }
}
