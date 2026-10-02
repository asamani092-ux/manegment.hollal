<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delegation extends Model
{
    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ENDED = 'ended';

    public const STATUS_CANCELLED = 'cancelled';

    /** @var list<string> */
    protected $fillable = [
        'delegator_id', 'delegate_id', 'starts_on', 'ends_on', 'source_type', 'source_id',
        'reason', 'status', 'activated_at', 'ended_at', 'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'activated_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function delegator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegator_id');
    }

    /** @return BelongsTo<User, $this> */
    public function delegate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegate_id');
    }
}
