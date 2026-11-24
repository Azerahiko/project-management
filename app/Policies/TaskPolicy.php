<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use App\Support\Roles;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tasks.view');
    }

    public function view(User $user, Task $task): bool
    {
        if (! $user->can('tasks.view')) {
            return false;
        }

        if ($user->hasRole('administrator')) {
            return true;
        }

        if ($task->creator_id === $user->id) {
            return true;
        }

        if ($task->assignee_id === $user->id) {
            return true;
        }

        if ($task->project->hasMember($user)) {
            return true;
        }

        return false;
    }

    public function viewTrashed(User $user): bool
    {
        return $user->can('tasks.view');
    }

    public function create(User $user): bool
    {
        if (! $user->hasRole([Roles::ADMINISTRATOR, Roles::PROJECT_MANAGER])) {
            return false;
        }

        return true;
    }

    public function update(User $user, Task $task): bool
    {
        if ($user->hasRole('administrator')) {
            return true;
        }

        if ($user->hasRole(Roles::PROJECT_MANAGER) && $task->project->hasMember($user)) {
            return true;
        }

        if ($user->hasRole(Roles::DEVELOPER) && $task->assignee_id === $user->id && $task->project->hasMember($user)) {
            return true;
        }

        if ($user->hasRole('reviewer') && $task->project->hasMember($user)) {
            return true;
        }

        return false;
    }

    public function delete(User $user, Task $task): bool
    {
        if ($user->hasRole('administrator')) {
            return true;
        }

        if ($user->hasRole(Roles::PROJECT_MANAGER) && $task->project->hasMember($user)) {
            return true;
        }

        if ($user->hasRole(Roles::DEVELOPER) || $user->hasRole('reviewer') || $user->hasRole('client')) {
            return false;
        }

        return false;
    }

    public function restore(User $user, Task $task): bool
    {
        if ($user->hasRole('administrator')) {
            return true;
        }

        if ($user->hasRole(Roles::PROJECT_MANAGER) && $task->project->hasMember($user)) {
            return true;
        }

        if ($user->hasRole(Roles::DEVELOPER) || $user->hasRole('reviewer') || $user->hasRole('client')) {
            return false;
        }

        return false;
    }

    public function forceDelete(User $user, Task $task): bool
    {
        if ($user->hasRole('administrator')) {
            return true;
        }

        if ($user->hasRole(Roles::PROJECT_MANAGER) && $task->project->hasMember($user)) {
            return true;
        }

        if ($user->hasRole(Roles::DEVELOPER) || $user->hasRole('reviewer') || $user->hasRole('client')) {
            return false;
        }

        return false;
    }
}
