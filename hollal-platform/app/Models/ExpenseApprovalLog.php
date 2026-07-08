<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseApprovalLog extends Model
{
    protected $fillable = [
        'expense_request_id',
        'stage',
        'approver_id',
        'action',
        'notes',
        'acted_at',
    ];

    protected function casts(): array
    {
        return [
            'acted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ExpenseRequest, $this> */
    public function expenseRequest(): BelongsTo
    {
        return $this->belongsTo(ExpenseRequest::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}
