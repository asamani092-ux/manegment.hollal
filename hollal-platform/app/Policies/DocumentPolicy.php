<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

/**
 * Document — confidentiality-scoped view/download; create via documents.create.
 */
class DocumentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('documents.view');
    }

    public function view(User $user, Document $document): bool
    {
        return $this->canAccess($user, $document);
    }

    public function create(User $user): bool
    {
        return $user->can('documents.create');
    }

    public function update(User $user, Document $document): bool
    {
        if ($document->is_auto_archived) {
            return false; // 03-B2 — archived minutes/reports are read-only
        }

        return $user->id === $document->uploader_id || $user->can('documents.create');
    }

    public function delete(User $user, Document $document): bool
    {
        if ($document->is_auto_archived) {
            return false; // 03-B2 — archived minutes/reports cannot be deleted
        }

        return $user->id === $document->uploader_id || $user->can('documents.create');
    }

    public function download(User $user, Document $document): bool
    {
        return $this->canAccess($user, $document);
    }

    protected function canAccess(User $user, Document $document): bool
    {
        if ($user->id === $document->uploader_id) {
            return true;
        }

        return match ($document->confidentiality) {
            'team' => $this->canAccessTeam($user, $document),
            'department' => $this->canAccessDepartment($user, $document),
            'managers' => $this->isManager($user),
            default => false,
        };
    }

    protected function canAccessTeam(User $user, Document $document): bool
    {
        if ($document->project_id) {
            $project = $document->project;

            if (! $project) {
                return false;
            }

            if ($project->manager_id === $user->id) {
                return true;
            }

            return $project->team()->where('users.id', $user->id)->exists();
        }

        return $user->can('documents.view');
    }

    protected function canAccessDepartment(User $user, Document $document): bool
    {
        if (! $user->can('documents.view') || ! $user->department_id) {
            return false;
        }

        $uploader = $document->uploader;

        return $uploader
            && $uploader->department_id
            && $user->department_id === $uploader->department_id;
    }

    protected function isManager(User $user): bool
    {
        return $user->subordinates()->exists()
            || $user->can('hr.salaries.manage')
            || $user->can('structure.departments.manage');
    }
}
