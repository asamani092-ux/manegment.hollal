<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeEvaluationScore extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'employee_evaluation_id', 'evaluation_cycle_item_id', 'score', 'note',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['score' => 'integer'];
    }

    /** @return BelongsTo<EmployeeEvaluation, $this> */
    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(EmployeeEvaluation::class, 'employee_evaluation_id');
    }

    /** @return BelongsTo<EvaluationCycleItem, $this> */
    public function cycleItem(): BelongsTo
    {
        return $this->belongsTo(EvaluationCycleItem::class, 'evaluation_cycle_item_id');
    }
}
