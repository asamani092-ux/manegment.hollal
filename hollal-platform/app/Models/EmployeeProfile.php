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
        'user_id', 'job_title', 'employment_type', 'overtime_hour_value', 'hire_date',
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
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
