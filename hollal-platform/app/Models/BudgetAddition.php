<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BudgetAddition extends Model
{
    public const STATUS_PENDING = 'معلق';

    public const STATUS_APPROVED = 'معتمد';

    public const STATUS_REJECTED = 'مرفوض';

    /** @var list<string> */
    protected $fillable = [
        'project_id', 'revenue_id', 'amount', 'note', 'status',
        'requested_by', 'approved_by', 'approved_at', 'rejection_reason',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'approved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Revenue, $this> */
    public function revenue(): BelongsTo
    {
        return $this->belongsTo(Revenue::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
