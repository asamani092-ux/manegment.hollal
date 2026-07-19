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
        'recurring_template_id',
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

    /**
     * 02-B2 — tasks assigned to the manager's direct reports (manager_id tree).
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Task>  $query
     */
    public function scopeTeamOf(\Illuminate\Database\Eloquent\Builder $query, User $manager): void
    {
        $subordinateIds = User::query()->where('manager_id', $manager->id)->pluck('id');
        $query->whereIn('assigned_to', $subordinateIds);
    }

    /** @param \Illuminate\Database\Eloquent\Builder<Task> $query */
    public function scopeOverdue(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->whereNotIn('status', ['completed'])
            ->where(function (\Illuminate\Database\Eloquent\Builder $inner) {
                $inner->where('status', 'overdue')
                    ->orWhere(function (\Illuminate\Database\Eloquent\Builder $q) {
                        $q->whereNotNull('due_date')->where('due_date', '<', now());
                    });
            });
    }

    /**
     * 02-B2 — «بانتظار اعتمادي»: tasks awaiting the given assigner's approval.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Task>  $query
     */
    public function scopePendingApprovalFor(\Illuminate\Database\Eloquent\Builder $query, User $assigner): void
    {
        $query->where('status', 'pending_review')->where('assigned_by', $assigner->id);
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
