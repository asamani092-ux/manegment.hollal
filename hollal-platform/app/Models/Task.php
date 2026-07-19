<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Task (Esnad) — soft deletes only.
 */
class Task extends Model
{
    use HasFactory;
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
        'required_evidence',
        'self_rating',
        'pm_rating',
        'final_rating',
        'final_notes',
        'completed_at',
        'template_item_id',
        'parent_task_id',
    ];

    /** @var list<string> valid triple-evaluation ratings */
    public const RATINGS = ['متميز', 'متوسط', 'مقبول', 'متأخر'];

    protected function casts(): array
    {
        return [
            'due_date' => 'datetime',
            'read_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Task, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_task_id');
    }

    /** @return HasMany<Task, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_task_id');
    }

    /** @return HasMany<TaskStatusLog, $this> */
    public function statusLogs(): HasMany
    {
        return $this->hasMany(TaskStatusLog::class);
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

    /** @return BelongsTo<Meeting, $this> */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    /** @return HasMany<TaskNote, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(TaskNote::class)->latest();
    }
}
