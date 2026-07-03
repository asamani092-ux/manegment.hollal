<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Task (Esnad) — soft deletes only.
 */
class Task extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'type',
        'recurring_pattern',
        'assigned_by',
        'assigned_to',
        'project_id',
        'meeting_id',
        'priority',
        'status',
        'due_date',
        'read_at',
        'submission_note',
        'attachment_path',
        'submitted_file',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
