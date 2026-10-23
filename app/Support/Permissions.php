<?php

namespace App\Support;

class Permissions
{
    public const DASHBOARD_VIEW = 'dashboard.view';

    public const NOTIFICATIONS_VIEW = 'notifications.view';

    public const PROJECTS_VIEW = 'projects.view';

    public const PROJECTS_CREATE = 'projects.create';

    public const PROJECTS_UPDATE = 'projects.update';

    public const PROJECTS_DELETE = 'projects.delete';

    public const PROJECTS_MANAGE_MEMBERS = 'projects.manage_members';

    public const TASKS_VIEW = 'tasks.view';

    public const TASK_COLLABORATION_VIEW = 'task-collaboration.view';

    public const TEAMS_VIEW = 'teams.view';

    public const CALENDAR_VIEW = 'calendar.view';

    public const DOCUMENTS_VIEW = 'documents.view';

    public const REPORTS_VIEW = 'reports.view';

    public const ACTIVITY_LOG_VIEW = 'activity-log.view';

    public const USERS_VIEW = 'users.view';

    public const USERS_CREATE = 'users.create';

    public const USERS_UPDATE = 'users.update';

    public const USERS_DELETE = 'users.delete';

    public const ROLES_MANAGE = 'roles.manage';

    public const SETTINGS_VIEW = 'settings.view';

    /**
     * All permissions grouped by category.
     *
     * @return array<string, array<string, string>>
     */
    public static function grouped(): array
    {
        return [
            'General' => [
                self::DASHBOARD_VIEW => 'Dashboard View',
                self::NOTIFICATIONS_VIEW => 'Notifications View',
            ],
            'Workspace' => [
                self::PROJECTS_VIEW => 'Projects View',
                self::PROJECTS_CREATE => 'Projects Create',
                self::PROJECTS_UPDATE => 'Projects Update',
                self::PROJECTS_DELETE => 'Projects Delete',
                self::PROJECTS_MANAGE_MEMBERS => 'Projects Manage Members',
                self::TASKS_VIEW => 'Tasks View',
                self::TASK_COLLABORATION_VIEW => 'Task Collaboration View',
                self::TEAMS_VIEW => 'Teams View',
                self::CALENDAR_VIEW => 'Calendar View',
                self::DOCUMENTS_VIEW => 'Documents View',
            ],
            'Monitoring' => [
                self::REPORTS_VIEW => 'Reports View',
                self::ACTIVITY_LOG_VIEW => 'Activity Log View',
            ],
            'Administration' => [
                self::USERS_VIEW => 'Users View',
                self::USERS_CREATE => 'Users Create',
                self::USERS_UPDATE => 'Users Update',
                self::USERS_DELETE => 'Users Delete',
                self::ROLES_MANAGE => 'Roles Manage',
                self::SETTINGS_VIEW => 'Settings View',
            ],
        ];
    }

    /**
     * All permission slugs.
     *
     * @return list<string>
     */
    public static function slugs(): array
    {
        $all = [];

        foreach (self::grouped() as $group) {
            $all = array_merge($all, array_keys($group));
        }

        return $all;
    }

    /**
     * Permission assignments per role.
     *
     * @return array<string, array<string>>
     */
    public static function rolePermissions(): array
    {
        return [
            Roles::ADMINISTRATOR => self::slugs(),
            Roles::PROJECT_MANAGER => [
                self::DASHBOARD_VIEW,
                self::NOTIFICATIONS_VIEW,
                self::PROJECTS_VIEW,
                self::PROJECTS_CREATE,
                self::PROJECTS_UPDATE,
                self::PROJECTS_DELETE,
                self::PROJECTS_MANAGE_MEMBERS,
                self::TASKS_VIEW,
                self::TASK_COLLABORATION_VIEW,
                self::TEAMS_VIEW,
                self::CALENDAR_VIEW,
                self::DOCUMENTS_VIEW,
                self::REPORTS_VIEW,
                self::ACTIVITY_LOG_VIEW,
            ],
            Roles::TEAM_LEADER => [
                self::DASHBOARD_VIEW,
                self::NOTIFICATIONS_VIEW,
                self::PROJECTS_VIEW,
                self::PROJECTS_CREATE,
                self::PROJECTS_UPDATE,
                self::TASKS_VIEW,
                self::TASK_COLLABORATION_VIEW,
                self::TEAMS_VIEW,
                self::CALENDAR_VIEW,
                self::DOCUMENTS_VIEW,
                self::REPORTS_VIEW,
            ],
            Roles::MEMBER => [
                self::DASHBOARD_VIEW,
                self::NOTIFICATIONS_VIEW,
                self::PROJECTS_VIEW,
                self::TASKS_VIEW,
                self::TASK_COLLABORATION_VIEW,
                self::CALENDAR_VIEW,
                self::DOCUMENTS_VIEW,
            ],
            Roles::REVIEWER => [
                self::DASHBOARD_VIEW,
                self::NOTIFICATIONS_VIEW,
                self::PROJECTS_VIEW,
                self::TASKS_VIEW,
                self::TASK_COLLABORATION_VIEW,
                self::DOCUMENTS_VIEW,
            ],
            Roles::CLIENT => [
                self::DASHBOARD_VIEW,
                self::PROJECTS_VIEW,
            ],
        ];
    }
}
