<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingAmendment extends Model
{
    public const STATUS_PENDING = 'معلق';

    public const STATUS_APPROVED = 'معتمد';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = ['meeting_id', 'version', 'note', 'status', 'requested_by', 'approved_by', 'created_at'];

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
