<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Expense request — soft deletes only.
 * Time: O(1) single record | aggregate spend O(n) per project.
 */
class ExpenseRequest extends Model
{
    use HasFactory;
    use SoftDeletes;

    /** @var list<string> */
    public const STATUSES = ['draft', 'pending', 'approved', 'paid', 'rejected'];

    /** @var list<string> */
    public const SPEND_STATUSES = ['approved', 'paid'];

    protected $fillable = [
        'requester_id',
        'project_id',
        'type',
        'amount',
        'reason',
        'payment_method',
        'attachment',
        'status',
        'approver_id',
        'approved_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /** @param Builder<static> $query */
    public function scopeCountedAsSpend(Builder $query): Builder
    {
        return $query->whereIn('status', self::SPEND_STATUSES);
    }
}
