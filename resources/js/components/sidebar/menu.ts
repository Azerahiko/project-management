import {
    activityLog,
    calendar,
    collaboration,
    dashboard,
    documents,
    notifications,
    projects,
    reports,
    tasks,
    teams,
} from '@/routes';
import users from '@/routes/users';

export type MenuItem = {
    label: string;
    href: string;
    icon: string;
    permission: string;
};

export type MenuSection = {
    label: string;
    items: MenuItem[];
};

export const menuSections: MenuSection[] = [
    {
        label: 'GENERAL',
        items: [
            {
                label: 'Dashboard',
                href: dashboard.url(),
                icon: 'LayoutDashboard',
                permission: 'dashboard.view',
            },
            {
                label: 'Notifications',
                href: notifications.url(),
                icon: 'Bell',
                permission: 'notifications.view',
            },
        ],
    },
    {
        label: 'WORKSPACE',
        items: [
            {
                label: 'Projects',
                href: projects.url(),
                icon: 'FolderKanban',
                permission: 'projects.view',
            },
            {
                label: 'Tasks',
                href: tasks.url(),
                icon: 'CheckSquare',
                permission: 'tasks.view',
            },
            {
                label: 'Task Collaboration',
                href: collaboration.url(),
                icon: 'Users',
                permission: 'task-collaboration.view',
            },
            {
                label: 'Teams',
                href: teams.url(),
                icon: 'UsersRound',
                permission: 'teams.view',
            },
            {
                label: 'Calendar',
                href: calendar.url(),
                icon: 'Calendar',
                permission: 'calendar.view',
            },
            {
                label: 'Documents',
                href: documents.url(),
                icon: 'FileText',
                permission: 'documents.view',
            },
        ],
    },
    {
        label: 'MONITORING',
        items: [
            {
                label: 'Reports',
                href: reports.url(),
                icon: 'BarChart3',
                permission: 'reports.view',
            },
            {
                label: 'Activity Log',
                href: activityLog.url(),
                icon: 'Activity',
                permission: 'activity-log.view',
            },
        ],
    },
    {
        label: 'ADMINISTRATION',
        items: [
            {
                label: 'User Management',
                href: users.index.url(),
                icon: 'Users',
                permission: 'users.view',
            },
            {
                label: 'Settings',
                href: '/settings',
                icon: 'Settings',
                permission: 'settings.view',
            },
        ],
    },
];
