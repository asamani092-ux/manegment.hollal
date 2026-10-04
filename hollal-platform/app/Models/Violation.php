<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Violation extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'employee_id', 'reference_item_id', 'occurrence_index', 'source', 'source_ref',
        'occurred_on', 'discovered_on', 'facts', 'attachments', 'status', 'decided_penalty',
        'decision_reason', 'decided_by', 'exclusion_reason_item_id', 'cap_flagged',
        'statement_deadline_on', 'statement_submitted_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
            'discovered_on' => 'date',
            'statement_deadline_on' => 'date',
            'statement_submitted_at' => 'datetime',
            'decided_penalty' => 'array',
            'attachments' => 'array',
            'cap_flagged' => 'boolean',
        ];
    }

    /** @return BelongsTo<ReferenceItem, $this> */
    public function referenceItem(): BelongsTo
    {
        return $this->belongsTo(ReferenceItem::class);
    }

    /** @return HasMany<EmployeeStatement, $this> */
    public function statements(): HasMany
    {
        return $this->hasMany(EmployeeStatement::class);
    }
}
