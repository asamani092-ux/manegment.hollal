<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Document — private file storage, confidentiality-scoped access.
 */
class Document extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'title',
        'category',
        'project_id',
        'confidentiality',
        'uploader_id',
        'path',
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    /** @param Builder<Document> $query */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->where('uploader_id', $user->id)
                ->orWhere(function (Builder $team) use ($user) {
                    $team->where('confidentiality', 'team')
                        ->whereNotNull('project_id')
                        ->whereHas('project', function (Builder $project) use ($user) {
                            $project->where('manager_id', $user->id)
                                ->orWhereHas('team', fn (Builder $t) => $t->where('users.id', $user->id));
                        });
                })
                ->orWhere(function (Builder $team) use ($user) {
                    if ($user->can('documents.view')) {
                        $team->where('confidentiality', 'team')->whereNull('project_id');
                    }
                })
                ->orWhere(function (Builder $dept) use ($user) {
                    if ($user->can('documents.view') && $user->department_id) {
                        $dept->where('confidentiality', 'department')
                            ->whereHas('uploader', fn (Builder $u) => $u->where('department_id', $user->department_id));
                    }
                })
                ->orWhere(function (Builder $managers) use ($user) {
                    if ($user->subordinates()->exists()
                        || $user->can('hr.salaries.manage')
                        || $user->can('structure.departments.manage')) {
                        $managers->where('confidentiality', 'managers');
                    }
                });
        });
    }
}
