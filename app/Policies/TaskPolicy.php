<?php

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    /**
     * Determine whether the user can view any tasks.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('tasks.view');
    }

    /**
     * Determine whether the user can view the task.
     */
    public function view(User $user, Task $task): bool
    {
        if (! $user->can('tasks.view')) {
            return false;
        }

        // Administrators can view all tasks
        if ($user->hasRole('administrator')) {
            return true;
        }

        // Task creator can view
        if ($task->creator_id === $user->id) {
            return true;
        }

        // Task assignee can view
        if ($task->assignee_id === $user->id) {
            return true;
        }

        // Members of the project can view
        if ($task->project->hasMember($user)) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create tasks.
     */
    public function create(User $user): bool
    {
        return $user->can('tasks.view');
    }

    /**
     * Determine whether the user can update the task.
     */
    public function update(User $user, Task $task): bool
    {
        if (! $user->can('tasks.view')) {
            return false;
        }

        // Administrators can update all tasks
        if ($user->hasRole('administrator')) {
            return true;
        }

        // Task creator can update
        if ($task->creator_id === $user->id) {
            return true;
        }

        // Task assignee can update
        if ($task->assignee_id === $user->id) {
            return true;
        }

        // Project owners and managers can update tasks in their project
        $role = $task->project->getMemberRole($user);
        if ($role === ProjectRole::OWNER || $role === ProjectRole::MANAGER) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the task.
     */
    public function delete(User $user, Task $task): bool
    {
        if (! $user->can('tasks.view')) {
            return false;
        }

        // Administrators can delete all tasks
        if ($user->hasRole('administrator')) {
            return true;
        }

        // Task creator can delete
        if ($task->creator_id === $user->id) {
            return true;
        }

        // Project owners can delete tasks in their project
        if ($task->project->owner_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the task.
     */
    public function restore(User $user, Task $task): bool
    {
        return $this->delete($user, $task);
    }

    /**
     * Determine whether the user can permanently delete the task.
     */
    public function forceDelete(User $user, Task $task): bool
    {
        return $this->delete($user, $task);
    }
}
