<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FiscalYearClose extends Model
{
    /** @var list<string> */
    protected $fillable = ['year', 'closing_entry_id', 'closed_by', 'closed_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['closed_at' => 'datetime'];
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function closingEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'closing_entry_id');
    }
}
