<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceCycleApproval extends Model
{
    public const STATUS_DRAFT = 'مسودة';

    public const STATUS_APPROVED = 'معتمد';

    public const STATUS_CORRECTION_PENDING = 'بانتظار_تصحيح';

    /** @var list<string> */
    protected $fillable = [
        'cycle_from', 'cycle_to', 'status', 'approved_by', 'approved_at', 'snapshot',
        'correction_reason', 'correction_requested_by', 'correction_requested_at',
        'correction_approved_by', 'correction_approved_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'cycle_from' => 'date',
            'cycle_to' => 'date',
            'approved_at' => 'datetime',
            'snapshot' => 'array',
            'correction_requested_at' => 'datetime',
            'correction_approved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return BelongsTo<User, $this> */
    public function correctionRequester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'correction_requested_by');
    }

    /** @return BelongsTo<User, $this> */
    public function correctionApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'correction_approved_by');
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isCorrectionPending(): bool
    {
        return $this->status === self::STATUS_CORRECTION_PENDING;
    }
}
