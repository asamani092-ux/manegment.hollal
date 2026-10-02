<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Violation extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'employee_id', 'reference_item_id', 'occurrence_index', 'source', 'occurred_on',
        'discovered_on', 'facts', 'status', 'decided_penalty', 'decision_reason',
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
        ];
    }
}
