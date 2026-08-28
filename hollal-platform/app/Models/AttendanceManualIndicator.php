<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceManualIndicator extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'employee_id', 'cycle_from', 'cycle_to', 'late_hours', 'absence_days', 'notes', 'entered_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'cycle_from' => 'date',
            'cycle_to' => 'date',
            'late_hours' => 'decimal:2',
            'absence_days' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    /** @return BelongsTo<User, $this> */
    public function enterer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }
}
