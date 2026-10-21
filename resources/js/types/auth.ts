export type User = {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    is_active: boolean;
    avatar: string | null;
    initials: string;
    roles: string[];
    permissions: string[];
    passkeys_count?: number;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User | null;
};
