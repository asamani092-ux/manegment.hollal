<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 05-B5 — the unique partner link. Bound to a single partnership: a token can
 * never reach another organization's data.
 */
class PartnerLink extends Model
{
    /** @var list<string> */
    protected $fillable = ['partnership_id', 'token', 'expires_at', 'is_revoked', 'last_used_at', 'created_by'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
            'is_revoked' => 'boolean',
        ];
    }

    /** @return BelongsTo<Partnership, $this> */
    public function partnership(): BelongsTo
    {
        return $this->belongsTo(Partnership::class);
    }

    /** @return HasMany<PartnerPortalActivity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(PartnerPortalActivity::class);
    }

    public function isUsable(): bool
    {
        return ! $this->is_revoked && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
