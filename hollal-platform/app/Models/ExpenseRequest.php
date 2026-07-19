<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    /** @var list<string> */
    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    /** @var list<string> */
    public const PAYMENT_METHODS = ['transfer', 'pos', 'cheque', 'other'];

    protected $fillable = [
        'requester_id',
        'project_id',
        'department_id',
        'category_id',
        'official_document_path',
        'type',
        'amount',
        'reason',
        'priority',
        'payment_method',
        'attachment',
        'status',
        'current_approval_stage',
        'approval_stages',
        'approver_id',
        'approved_at',
        'paid_ready_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'approved_at' => 'datetime',
            'paid_ready_at' => 'datetime',
            'approval_stages' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /** @return BelongsTo<ExpenseCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    /** @return BelongsTo<Department, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
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

    /** @return HasMany<ExpenseApprovalLog, $this> */
    public function approvalLogs(): HasMany
    {
        return $this->hasMany(ExpenseApprovalLog::class);
    }

    /** @param Builder<static> $query */
    public function scopeCountedAsSpend(Builder $query): Builder
    {
        return $query->whereIn('status', self::SPEND_STATUSES);
    }

    /** @param Builder<static> $query */
    public function scopeOrderByPriority(Builder $query): Builder
    {
        return $query->orderByRaw(
            "CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 WHEN 'low' THEN 4 ELSE 5 END"
        );
    }
}
