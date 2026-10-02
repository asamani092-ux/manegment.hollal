<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendanceCycleDay extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'attendance_cycle_id', 'employee_id', 'date', 'status', 'late_minutes',
        'chargeable_late_minutes', 'overtime_hours', 'correction_reason', 'downstream_decided',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'overtime_hours' => 'decimal:2',
            'downstream_decided' => 'boolean',
        ];
    }
}
