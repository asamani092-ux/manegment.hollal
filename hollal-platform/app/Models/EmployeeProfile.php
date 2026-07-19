<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeProfile extends Model
{
    use SoftDeletes;

    protected $table = 'employees_profile';

    /** @var list<string> */
    protected $fillable = [
        'user_id', 'job_title', 'employment_type', 'overtime_hour_value', 'weekly_hours',
        'overtime_unlocked', 'overtime_days_this_month', 'hire_date',
        'national_id', 'birth_date', 'gender', 'marital_status', 'address',
        'emergency_contact_name', 'emergency_contact_phone', 'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'hire_date' => 'date',
            'birth_date' => 'date',
            'overtime_hour_value' => 'decimal:2',
            'weekly_hours' => 'integer',
            'overtime_unlocked' => 'boolean',
            'overtime_days_this_month' => 'integer',
        ];
    }

    /**
     * 01-B4 — HR unlocks overtime entry per employee (locked by default).
     */
    public function unlockOvertime(): void
    {
        $this->update(['overtime_unlocked' => true]);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
