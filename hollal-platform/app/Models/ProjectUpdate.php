<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Project weekly update — append-only timeline entries.
 * Time: O(1) create | list O(n) per project.
 */
class ProjectUpdate extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'author_id',
        'done',
        'next',
        'blockers',
        'decision_needed',
        'date',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
