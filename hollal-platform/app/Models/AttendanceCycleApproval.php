<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceCycleApproval extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'cycle_from', 'cycle_to', 'status', 'approved_by', 'approved_at', 'snapshot',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'cycle_from' => 'date',
            'cycle_to' => 'date',
            'approved_at' => 'datetime',
            'snapshot' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
