<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 06B-B2 — a member of the entity's side of the project team. They have no
 * platform account; they act through the partner link.
 */
class ProjectEntityMember extends Model
{
    /** @var list<string> */
    protected $fillable = ['project_id', 'name', 'role_label', 'phone', 'email'];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
