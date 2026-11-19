<?php

namespace App\Support;

use App\Models\User;

class Roles
{
    public const ADMINISTRATOR = 'administrator';

    public const PROJECT_MANAGER = 'project_manager';

    public const TEAM_LEADER = 'team_leader';

    public const DEVELOPER = 'developer';

    public const REVIEWER = 'reviewer';

    public const CLIENT = 'client';

    /**
     * All role slugs keyed by their display label.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        return [
            self::ADMINISTRATOR => 'Administrator',
            self::PROJECT_MANAGER => 'Project Manager',
            self::TEAM_LEADER => 'Team Leader',
            self::DEVELOPER => 'Developer',
            self::REVIEWER => 'Reviewer',
            self::CLIENT => 'Client',
        ];
    }

    /**
     * All role slugs.
     *
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_keys(self::all());
    }

    /**
     * Roles the given actor is allowed to assign.
     *
     * Only administrators may grant the administrator role.
     *
     * @return list<string>
     */
    public static function assignableBy(?User $actor): array
    {
        if ($actor?->hasRole(self::ADMINISTRATOR)) {
            return self::slugs();
        }

        return array_values(array_diff(self::slugs(), [self::ADMINISTRATOR]));
    }
}
