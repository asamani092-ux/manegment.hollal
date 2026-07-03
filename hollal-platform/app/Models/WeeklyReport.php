<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Persisted weekly management report — soft deletes only.
 * Time: O(1) single | list O(n).
 */
class WeeklyReport extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'week_start',
        'week_end',
        'done',
        'overdue',
        'project_status',
        'week_spend',
        'open_decisions',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'week_start' => 'date',
            'week_end' => 'date',
            'done' => 'array',
            'overdue' => 'array',
            'project_status' => 'array',
            'week_spend' => 'decimal:2',
            'open_decisions' => 'array',
            'generated_at' => 'datetime',
        ];
    }
}
