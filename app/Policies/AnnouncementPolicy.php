<?php

namespace App\Policies;

use App\Models\Announcement;
use App\Models\User;
use App\Support\Permissions;

class AnnouncementPolicy
{
    /**
     * Determine whether the user can update the announcement.
     */
    public function update(User $user, Announcement $announcement): bool
    {
        // Users with teams.view permission can update any announcement
        if ($user->can(Permissions::TEAMS_VIEW)) {
            return true;
        }

        // Only the creator can update their own announcement
        return $user->id === $announcement->user_id;
    }

    /**
     * Determine whether the user can delete the announcement.
     */
    public function delete(User $user, Announcement $announcement): bool
    {
        // Users with teams.view permission can delete any announcement
        if ($user->can(Permissions::TEAMS_VIEW)) {
            return true;
        }

        // Only the creator can delete their own announcement
        return $user->id === $announcement->user_id;
    }

    /**
     * Determine whether the user can create announcements.
     */
    public function create(User $user): bool
    {
        // Only users with teams.view permission can create announcements
        return $user->can(Permissions::TEAMS_VIEW);
    }
}
