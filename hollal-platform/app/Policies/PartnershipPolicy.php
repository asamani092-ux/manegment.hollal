<?php

namespace App\Policies;

use App\Models\Partnership;
use App\Models\User;

/**
 * Partnership (الشراكات) — linked project access or elevated module permission.
 */
class PartnershipPolicy
{
    public function view(User $user, Partnership $partnership): bool
    {
        return $user->can('partnerships.view') || $this->canAccessViaProject($user, $partnership);
    }

    public function update(User $user, Partnership $partnership): bool
    {
        return $user->can('partnerships.update') || $this->canAccessViaProject($user, $partnership);
    }

    public function delete(User $user, Partnership $partnership): bool
    {
        return $user->can('partnerships.delete') || $this->canAccessViaProject($user, $partnership);
    }

    protected function canAccessViaProject(User $user, Partnership $partnership): bool
    {
        if (! $partnership->project_id) {
            return $user->can('projects.update');
        }

        $project = $partnership->project;

        if (! $project) {
            return $user->can('projects.update');
        }

        if ($user->can('projects.update')) {
            return true;
        }

        if ($project->manager_id === $user->id) {
            return true;
        }

        return $project->team()->where('users.id', $user->id)->exists();
    }
}
