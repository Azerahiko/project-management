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
