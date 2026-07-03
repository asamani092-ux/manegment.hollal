<?php

namespace App\Policies;

use App\Models\Meeting;
use App\Models\User;

class MeetingPolicy
{
    public function view(User $user, Meeting $meeting): bool
    {
        return $this->canAccess($user, $meeting);
    }

    public function update(User $user, Meeting $meeting): bool
    {
        return $this->canAccess($user, $meeting);
    }

    public function delete(User $user, Meeting $meeting): bool
    {
        if ($user->can('meetings.delete')) {
            return true;
        }

        return $user->id === $meeting->chair_id || $user->id === $meeting->secretary_id;
    }

    protected function canAccess(User $user, Meeting $meeting): bool
    {
        if ($user->can('meetings.update')) {
            return true;
        }

        if ($user->id === $meeting->chair_id || $user->id === $meeting->secretary_id) {
            return true;
        }

        return $meeting->attendees()->where('users.id', $user->id)->exists();
    }
}
