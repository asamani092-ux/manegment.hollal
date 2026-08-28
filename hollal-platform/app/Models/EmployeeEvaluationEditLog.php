<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeEvaluationEditLog extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'employee_evaluation_id', 'user_id', 'reason', 'before_scores', 'after_scores',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'before_scores' => 'array',
            'after_scores' => 'array',
        ];
    }

    /** @return BelongsTo<EmployeeEvaluation, $this> */
    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(EmployeeEvaluation::class, 'employee_evaluation_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
};
