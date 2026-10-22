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
