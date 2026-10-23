import type { User } from './auth';

export * from './auth';

export type Role = {
    id: number;
    name: string;
    label: string;
    guard_name: string;
    permissions?: Permission[];
    created_at: string;
    updated_at: string;
};

export type Permission = {
    id: number;
    name: string;
    guard_name: string;
    created_at: string;
    updated_at: string;
};

export type ProjectStatus =
    | 'draft'
    | 'active'
    | 'on_hold'
    | 'completed'
    | 'archived';
export type ProjectPriority = 'low' | 'medium' | 'high' | 'critical';
export type ProjectRole = 'owner' | 'manager' | 'member' | 'viewer';

export type TaskStatus = 'todo' | 'in_progress' | 'review' | 'done';
export type TaskPriority = 'low' | 'medium' | 'high' | 'urgent';

export interface TaskStatusOption {
    value: TaskStatus;
    label: string;
    color: string;
}

export interface TaskPriorityOption {
    value: TaskPriority;
    label: string;
    color: string;
}

export type Task = {
    id: number;
    project_id: number;
    title: string;
    description: string | null;
    status: TaskStatus;
    status_label: string;
    status_color: string;
    priority: TaskPriority;
    priority_label: string;
    priority_color: string;
    assignee_id: number | null;
    creator_id: number;
    start_date: string | null;
    due_date: string | null;
    completed_at: string | null;
    assignee?: User;
    creator?: User;
    project?: {
        id: number;
        name: string;
    };
    can_update?: boolean;
    can_delete?: boolean;
    is_overdue?: boolean;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
};

export type TaskComment = {
    id: number;
    task_id: number;
    user_id: number;
    content: string;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
    user?: User;
    can_update?: boolean;
    can_delete?: boolean;
};

export type TaskActivity = {
    id: number;
    task_id: number;
    user_id: number | null;
    activity_type: string;
    field: string | null;
    old_value: string | null;
    new_value: string | null;
    description: string;
    created_at: string;
    user?: User;
};

export type TaskFilters = {
    search: string;
    status: string;
    priority: string;
    assignee_id: string;
    project_id: string;
    sort_by: string;
    sort_direction: 'asc' | 'desc';
    page: number;
    per_page: number;
};

export type PaginatedResponse<T> = {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        path: string;
        per_page: number;
        to: number | null;
        total: number;
    };
};

export interface ProjectStatusOption {
    value: ProjectStatus;
    label: string;
    color: string;
}

export interface ProjectPriorityOption {
    value: ProjectPriority;
    label: string;
    color: string;
}

export interface ProjectRoleOption {
    value: ProjectRole;
    label: string;
}

export type Project = {
    id: number;
    name: string;
    description: string | null;
    status: ProjectStatus;
    status_label: string;
    status_color: string;
    priority: ProjectPriority;
    priority_label: string;
    priority_color: string;
    start_date: string | null;
    end_date: string | null;
    owner_id: number;
    owner?: User;
    members_count?: number;
    members?: ProjectMember[];
    can_update?: boolean;
    can_delete?: boolean;
    can_manage_members?: boolean;
    created_at: string;
    updated_at: string;
    deleted_at: string | null;
};

export type ProjectMember = {
    id: number;
    project_id: number;
    user_id: number;
    role: ProjectRole;
    role_label: string;
    joined_at: string;
    user?: User;
    is_owner: boolean;
    created_at: string;
    updated_at: string;
};

export type ProjectFilters = {
    search: string;
    status: string;
    priority: string;
    owner_id: string;
    sort_by: string;
    sort_direction: 'asc' | 'desc';
    page: number;
    per_page: number;
};

export type PageProps = {
    name: string;
    app: {
        name: string;
        shortName: string;
    };
    auth: {
        user: User | null;
    };
    csrf_token: string;
    flash: {
        success: () => string | null;
        error: () => string | null;
    };
    errors: Record<string, string[]>;
};

export type MenuItem = {
    label: string;
    href: string;
    icon: string;
    permission?: string;
    children?: MenuItem[];
};

export type MenuSection = {
    label: string;
    items: MenuItem[];
};
