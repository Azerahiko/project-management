<?php

namespace App\Policies;

use App\Enums\ProjectRole;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;

class TaskCommentPolicy
{
    public function view(User $user, TaskComment $comment): bool
    {
        // Only check if user can view the parent task
        return $user->can('view', $comment->task);
    }

    public function create(User $user, Task $task): bool
    {
        return $user->can('view', $task);
    }

    public function update(User $user, TaskComment $comment): bool
    {
        if (! $user->can('view', $comment->task)) {
            return false;
        }

        // Administrators can update any comment
        if ($user->hasRole('administrator')) {
            return true;
        }

        // Comment author can update their own comment
        if ($comment->user_id === $user->id) {
            return true;
        }

        // Project owners and managers can update any comment on tasks in their project
        $role = $comment->task->project->getMemberRole($user);

        return $role === ProjectRole::OWNER || $role === ProjectRole::MANAGER;
    }

    public function delete(User $user, TaskComment $comment): bool
    {
        if (! $user->can('view', $comment->task)) {
            return false;
        }

        // Administrators can delete any comment
        if ($user->hasRole('administrator')) {
            return true;
        }

        // Comment author can delete their own comment
        if ($comment->user_id === $user->id) {
            return true;
        }

        // Project owners can delete any comment on tasks in their project (managers cannot delete)
        $role = $comment->task->project->getMemberRole($user);

        return $role === ProjectRole::OWNER;
    }
}
