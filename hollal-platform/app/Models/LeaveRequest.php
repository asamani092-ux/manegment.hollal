<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveRequest extends Model
{
    use SoftDeletes;

    public const TYPE_ANNUAL = 'سنوية';

    public const TYPE_SICK = 'مرضية';

    public const TYPE_EXCEPTIONAL = 'استثنائية';

    public const STATUS_SUBMITTED = 'مقدم';

    public const STATUS_APPROVED = 'معتمد';

    public const STATUS_REJECTED = 'مرفوض';

    /** @var list<string> */
    protected $fillable = [
        'employee_id', 'type', 'from_date', 'to_date', 'days_count',
        'reason', 'status', 'approver_id', 'approved_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'from_date' => 'date',
            'to_date' => 'date',
            'approved_at' => 'datetime',
            'days_count' => 'integer',
        ];
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /** @return BelongsTo<User, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
