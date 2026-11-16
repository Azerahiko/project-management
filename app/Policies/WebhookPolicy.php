<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Webhook;
use App\Support\Permissions;
use App\Support\Roles;

class WebhookPolicy
{
    /**
     * Any authenticated user allowed on the integrations page may see it.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Permissions::SETTINGS_VIEW);
    }

    /**
     * Administrators see every webhook; other users only their own.
     */
    public function view(User $user, Webhook $webhook): bool
    {
        return $user->hasRole(Roles::ADMINISTRATOR) || $webhook->user_id === $user->id;
    }

    /**
     * Registering webhooks stays restricted to administrators by default.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(Roles::ADMINISTRATOR);
    }

    /**
     * Administrators manage every webhook; other managers only their own.
     */
    public function update(User $user, Webhook $webhook): bool
    {
        if ($user->hasRole(Roles::ADMINISTRATOR)) {
            return true;
        }

        return $user->can(Permissions::ROLES_MANAGE) && $webhook->user_id === $user->id;
    }

    public function delete(User $user, Webhook $webhook): bool
    {
        return $this->update($user, $webhook);
    }

    public function test(User $user, Webhook $webhook): bool
    {
        return $this->update($user, $webhook);
    }
}
