<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgramVersion extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'program_id', 'version_label', 'changed_by', 'change_reason', 'is_current',
        'snapshot', 'notes', 'approved_by', 'approved_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'is_current' => 'boolean',
            'snapshot' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /** @return BelongsTo<Program, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
