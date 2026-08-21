<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'employee_id', 'date', 'check_in_at', 'check_out_at', 'type', 'declared_by', 'notes',
        'source', 'device_id', 'work_hours', 'late_minutes', 'field_location', 'field_proof_path', 'approval_status',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'late_minutes' => 'integer',
            'work_hours' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}
