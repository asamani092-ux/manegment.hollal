<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

/**
 * Project (المشاريع) — manager, team member, or projects.update holder.
 */
class ProjectPolicy
{
    public function view(User $user, Project $project): bool
    {
        return $this->canAccess($user, $project);
    }

    public function update(User $user, Project $project): bool
    {
        return $this->canAccess($user, $project);
    }

    public function delete(User $user, Project $project): bool
    {
        return $this->canAccess($user, $project);
    }

    public function submitUpdate(User $user, Project $project): bool
    {
        return $project->manager_id === $user->id;
    }

    protected function canAccess(User $user, Project $project): bool
    {
        if ($user->can('projects.update')) {
            return true;
        }

        if ($project->manager_id === $user->id) {
            return true;
        }

        return $project->team()->where('users.id', $user->id)->exists();
    }
}
