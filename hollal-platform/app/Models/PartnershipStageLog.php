<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 05-B2 — one row per stage transition (who moved it, when, and why).
 */
class PartnershipStageLog extends Model
{
    /** @var list<string> */
    protected $fillable = ['partnership_id', 'from_stage', 'to_stage', 'note', 'changed_by'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['from_stage' => 'integer', 'to_stage' => 'integer'];
    }

    /** @return BelongsTo<Partnership, $this> */
    public function partnership(): BelongsTo
    {
        return $this->belongsTo(Partnership::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
