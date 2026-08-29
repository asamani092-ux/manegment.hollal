<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingAmendment extends Model
{
    public const STATUS_PENDING = 'معلق';

    /** Request approved — minutes items may be edited until finalize. */
    public const STATUS_EDITING = 'جاري_التعديل';

    public const STATUS_APPROVED = 'معتمد';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = ['meeting_id', 'version', 'note', 'status', 'requested_by', 'approved_by', 'created_at'];

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isEditing(): bool
    {
        return $this->status === self::STATUS_EDITING;
    }

    public function isFullyApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** @return BelongsTo<Meeting, $this> */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }
}
