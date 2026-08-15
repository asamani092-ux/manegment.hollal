<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * P2 wave C — external guest (no employee account) invited to a meeting via a
 * tokenized short link. Time: O(1) | Space: O(1).
 */
class MeetingGuest extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'meeting_id',
        'name',
        'email',
        'token',
        'invited_by',
        'viewed_at',
        'confirmed_at',
        'signature_image_path',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Meeting, $this> */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /** @return BelongsTo<User, $this> */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
