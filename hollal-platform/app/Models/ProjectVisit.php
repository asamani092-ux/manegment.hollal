<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 06B-B3 — a field visit and its report. Recommendations become corrective
 * tasks in Esnad, linked back to the visit.
 */
class ProjectVisit extends Model
{
    use SoftDeletes;

    public const STATUS_SCHEDULED = 'مجدولة';

    public const STATUS_DONE = 'منفذة';

    public const STATUS_CANCELLED = 'ملغاة';

    /** @var list<string> */
    protected $fillable = [
        'project_id', 'visitor_id', 'scheduled_on', 'purpose', 'status', 'notes',
        'positives', 'challenges', 'recommendations', 'evidence_paths',
        'reported_at', 'approved_by', 'approved_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'scheduled_on' => 'date',
            'recommendations' => 'array',
            'evidence_paths' => 'array',
            'reported_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function visitor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'visitor_id');
    }

    public function isConsumed(): bool
    {
        return $this->status === self::STATUS_DONE;
    }
}
