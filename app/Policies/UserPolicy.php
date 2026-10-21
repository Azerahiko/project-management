<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any users.
     */
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    /**
     * Determine whether the user can view the given user.
     */
    public function view(User $user, User $targetUser): bool
    {
        return $user->can('users.view');
    }

    /**
     * Determine whether the user can create users.
     */
    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    /**
     * Determine whether the user can update the given user.
     */
    public function update(User $user, User $targetUser): bool
    {
        if (! $user->can('users.update')) {
            return false;
        }

        // Only administrators may modify an administrator account.
        if ($targetUser->hasRole('administrator') && ! $user->hasRole('administrator')) {
            return false;
        }

        // The last active administrator must never be deactivated.
        if ($this->isActiveAdministrator($targetUser) && $this->activeAdministratorsExcluding($targetUser) === 0) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can delete the given user.
     */
    public function delete(User $user, User $targetUser): bool
    {
        if (! $user->can('users.delete')) {
            return false;
        }

        // Administrators may not delete their own account.
        if ($user->id === $targetUser->id) {
            return false;
        }

        // Only administrators may delete an administrator account.
        if ($targetUser->hasRole('administrator') && ! $user->hasRole('administrator')) {
            return false;
        }

        // The last active administrator must never be deleted.
        if ($this->isActiveAdministrator($targetUser) && $this->activeAdministratorsExcluding($targetUser) === 0) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the target user is an active administrator.
     */
    protected function isActiveAdministrator(User $targetUser): bool
    {
        return $targetUser->is_active && $targetUser->hasRole('administrator');
    }

    /**
     * Count the active administrators other than the given user.
     */
    protected function activeAdministratorsExcluding(User $targetUser): int
    {
        return User::role('administrator')
            ->where('is_active', true)
            ->whereKeyNot($targetUser->getKey())
            ->count();
    }
}
