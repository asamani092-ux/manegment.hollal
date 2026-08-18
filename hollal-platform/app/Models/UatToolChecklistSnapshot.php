<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UatToolChecklistSnapshot extends Model
{
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'checklist_id',
        'source',
        'active_phase',
        'verdicts',
        'tags',
        'notes',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'active_phase' => 'integer',
            'verdicts' => 'array',
            'tags' => 'array',
            'notes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<UatToolChecklist, $this> */
    public function checklist(): BelongsTo
    {
        return $this->belongsTo(UatToolChecklist::class, 'checklist_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
