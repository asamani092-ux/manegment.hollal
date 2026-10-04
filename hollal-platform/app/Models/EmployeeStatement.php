<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeStatement extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'violation_id', 'employee_id', 'body', 'attachments', 'submitted_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Violation, $this> */
    public function violation(): BelongsTo
    {
        return $this->belongsTo(Violation::class);
    }
}
