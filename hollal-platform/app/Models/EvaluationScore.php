<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvaluationScore extends Model
{
    /** @var list<string> */
    protected $fillable = ['periodic_evaluation_id', 'responsibility_id', 'score', 'note'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['score' => 'integer'];
    }

    /** @return BelongsTo<PeriodicEvaluation, $this> */
    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(PeriodicEvaluation::class, 'periodic_evaluation_id');
    }

    /** @return BelongsTo<Responsibility, $this> */
    public function responsibility(): BelongsTo
    {
        return $this->belongsTo(Responsibility::class);
    }
}
