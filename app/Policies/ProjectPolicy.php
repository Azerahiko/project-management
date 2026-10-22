<?php

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /**
     * Determine whether the user can view any projects.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('projects.view');
    }

    /**
     * Determine whether the user can view the project.
     */
    public function view(User $user, Project $project): bool
    {
        if (! $user->can('projects.view')) {
            return false;
        }

        // Administrators can view all projects
        if ($user->hasRole('administrator')) {
            return true;
        }

        // Owner can view
        if ($project->owner_id === $user->id) {
            return true;
        }

        // Members can view
        if ($project->hasMember($user)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create projects.
     */
    public function create(User $user): bool
    {
        return $user->can('projects.create');
    }

    /**
     * Determine whether the user can update the project.
     */
    public function update(User $user, Project $project): bool
    {
        if (! $user->can('projects.update')) {
            return false;
        }

        // Administrators can update all projects
        if ($user->hasRole('administrator')) {
            return true;
        }

        // Owner can update
        if ($project->owner_id === $user->id) {
            return true;
        }

        // Project managers (with manager role in project) can update
        if ($project->getMemberRole($user) === ProjectRole::MANAGER) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the project.
     */
    public function delete(User $user, Project $project): bool
    {
        if (! $user->can('projects.delete')) {
            return false;
        }

        // Administrators can delete all projects
        if ($user->hasRole('administrator')) {
            return true;
        }

        // Only owner can delete
        if ($project->owner_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can manage members of the project.
     */
    public function manageMembers(User $user, Project $project): bool
    {
        if (! $user->can('projects.manage_members')) {
            return false;
        }

        // Administrators can manage members of all projects
        if ($user->hasRole('administrator')) {
            return true;
        }

        // Owner can manage members
        if ($project->owner_id === $user->id) {
            return true;
        }

        // Project managers (with manager role in project) can manage members
        if ($project->getMemberRole($user) === ProjectRole::MANAGER) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the project.
     */
    public function restore(User $user, Project $project): bool
    {
        return $this->delete($user, $project);
    }

    /**
     * Determine whether the user can permanently delete the project.
     */
    public function forceDelete(User $user, Project $project): bool
    {
        return $this->delete($user, $project);
    }
}
