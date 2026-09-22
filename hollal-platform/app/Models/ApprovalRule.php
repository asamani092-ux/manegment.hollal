<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * قاعدة سلسلة اعتماد حسب نوع العملية ونطاق المبلغ.
 * Time: O(1) | Space: O(steps)
 */
class ApprovalRule extends Model
{
    public const TYPE_EXPENSE = 'expense';

    public const TYPE_CUSTODY = 'custody';

    public const TYPE_QUOTE = 'quote';

    public const TYPE_CONTRACT = 'contract';

    public const TYPE_LEAVE = 'leave';

    /** @var list<string> */
    protected $fillable = [
        'transaction_type',
        'min_amount',
        'max_amount',
        'approval_steps',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'approval_steps' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
