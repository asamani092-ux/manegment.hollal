<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MeetingItem extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'meeting_id',
        'topic',
        'item_kind',
        'proposed_by',
        'discussion_summary',
        'decision',
        'responsible_id',
        'due_date',
        'status',
        'task_id',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
        ];
    }

    /**
     * 03-B2 — open decisions older than $days (stale). Age is measured from
     * creation.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<MeetingItem>  $query
     */
    public function scopeStale(\Illuminate\Database\Eloquent\Builder $query, int $days = 30): void
    {
        $query->whereNotNull('decision')
            ->where('decision', '!=', '')
            ->where('status', '!=', 'done')
            ->where('created_at', '<', now()->subDays($days));
    }

    public function ageInDays(): int
    {
        return (int) $this->created_at?->diffInDays(now());
    }

    /** @return BelongsTo<Meeting, $this> */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /** @return BelongsTo<User, $this> */
    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    /** @return BelongsTo<Task, $this> */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
