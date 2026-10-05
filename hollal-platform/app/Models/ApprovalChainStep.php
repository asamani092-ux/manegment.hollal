<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * خطوة مرتبة داخل سلسلة اعتماد.
 * Time: O(1) | Space: O(1)
 */
class ApprovalChainStep extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'approval_chain_id',
        'position',
        'approver_type',
        'user_id',
        'user_ids',
        'condition_operator',
        'condition_value',
        'on_unresolved',
        'label_ar',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_ids' => 'array',
            'condition_value' => 'decimal:2',
            'position' => 'integer',
        ];
    }

    /** @return BelongsTo<ApprovalChain, $this> */
    public function chain(): BelongsTo
    {
        return $this->belongsTo(ApprovalChain::class, 'approval_chain_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isUnresolved(): bool
    {
        if ($this->approver_type === 'user') {
            return $this->user_id === null;
        }
        if ($this->approver_type === 'any_of_users') {
            return $this->user_ids === null || $this->user_ids === [];
        }

        return false;
    }
}
