<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 06B-B3 — a consultation request: raised internally or by the entity through
 * its link, assigned to a specialist, answered, then closed.
 */
class Consultation extends Model
{
    use SoftDeletes;

    public const STATUS_OPEN = 'مفتوحة';

    public const STATUS_ASSIGNED = 'مسندة';

    public const STATUS_CLOSED = 'مغلقة';

    public const VIA_INTERNAL = 'داخلي';

    public const VIA_PORTAL = 'رابط الجهة';

    /** @var list<string> */
    protected $fillable = [
        'project_id', 'subject', 'request', 'requested_via',
        'specialist_id', 'response', 'status', 'closed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['closed_at' => 'datetime'];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function specialist(): BelongsTo
    {
        return $this->belongsTo(User::class, 'specialist_id');
    }

    public function isConsumed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }
}
