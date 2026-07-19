<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 10-B1 — a permission granted to one person outside their role. It always
 * carries a reason and a date, and it is highlighted on the grant screen.
 */
class ExceptionalGrant extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'user_id', 'permission', 'reason', 'granted_on', 'expires_on', 'granted_by', 'revoked_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'granted_on' => 'date',
            'expires_on' => 'date',
            'revoked_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null
            && ($this->expires_on === null || ! $this->expires_on->isPast());
    }
}
