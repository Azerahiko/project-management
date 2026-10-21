import { ref, computed } from 'vue';
import api from '@/lib/api';
import type { PaginatedResponse, User } from '@/types';

export type UserFilters = {
    search: string;
    role: string;
    is_active: string;
    sort_by: string;
    sort_direction: 'asc' | 'desc';
    page: number;
    per_page: number;
};

export function useUsers(filters: UserFilters) {
    const users = ref<User[]>([]);
    const meta = ref({
        current_page: 1,
        last_page: 1,
        from: null as number | null,
        to: null as number | null,
        total: 0,
    });
    const loading = ref(false);
    const error = ref<string | null>(null);

    async function fetchUsers(): Promise<void> {
        loading.value = true;
        error.value = null;

        try {
            const { data: response } = await api.get<PaginatedResponse<User>>(
                '/users',
                {
                    params: {
                        search: filters.search || undefined,
                        role: filters.role || undefined,
                        is_active:
                            filters.is_active === ''
                                ? undefined
                                : filters.is_active,
                        sort_by: filters.sort_by || undefined,
                        sort_direction: filters.sort_direction,
                        page: filters.page,
                        per_page: filters.per_page,
                    },
                },
            );

            users.value = response.data;
            meta.value = {
                current_page: response.meta.current_page,
                last_page: response.meta.last_page,
                from: response.meta.from,
                to: response.meta.to,
                total: response.meta.total,
            };
        } catch (e: any) {
            error.value =
                e?.response?.data?.message ??
                'Gagal memuat daftar pengguna. Silakan coba lagi.';
        } finally {
            loading.value = false;
        }
    }

    async function deleteUser(id: number): Promise<void> {
        await api.delete(`/users/${id}`);
    }

    const queryString = computed(() => ({
        search: filters.search || undefined,
        role: filters.role || undefined,
        is_active: filters.is_active === '' ? undefined : filters.is_active,
        page: filters.page,
    }));

    return { users, meta, loading, error, fetchUsers, deleteUser, queryString };
}
