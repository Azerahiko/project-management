<?php

namespace App\Support;

class Roles
{
    public const ADMINISTRATOR = 'administrator';

    public const PROJECT_MANAGER = 'project_manager';

    public const TEAM_LEADER = 'team_leader';

    public const MEMBER = 'member';

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
            self::MEMBER => 'Member',
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
}
