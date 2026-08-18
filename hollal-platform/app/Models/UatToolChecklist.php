<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UatToolChecklist extends Model
{
    public const SLOT_SHARED = 'shared';

    /** @var list<string> */
    protected $fillable = [
        'slot',
        'active_phase',
        'verdicts',
        'tags',
        'notes',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'active_phase' => 'integer',
            'verdicts' => 'array',
            'tags' => 'array',
            'notes' => 'array',
        ];
    }

    /** @return HasMany<UatToolChecklistSnapshot, $this> */
    public function snapshots(): HasMany
    {
        return $this->hasMany(UatToolChecklistSnapshot::class, 'checklist_id');
    }

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
