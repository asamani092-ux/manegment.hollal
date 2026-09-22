<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * سطر مستورد من كشف البنك.
 */
class BankStatementLine extends Model
{
    public const MATCH_MATCHED = 'matched';

    public const MATCH_UNMATCHED = 'unmatched';

    public const MATCH_MANUAL = 'manual';

    /** @var list<string> */
    protected $fillable = [
        'bank_reconciliation_id',
        'transaction_date',
        'description',
        'debit',
        'credit',
        'balance',
        'reference',
        'match_status',
        'matched_journal_line_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'balance' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<BankReconciliation, $this> */
    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(BankReconciliation::class, 'bank_reconciliation_id');
    }

    /** @return BelongsTo<JournalLine, $this> */
    public function matchedLine(): BelongsTo
    {
        return $this->belongsTo(JournalLine::class, 'matched_journal_line_id');
    }
}
