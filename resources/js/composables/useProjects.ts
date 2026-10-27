import { ref, computed } from 'vue';
import api from '@/lib/api';
import type {
    PaginatedResponse,
    Project,
    ProjectFilters,
    ProjectMember,
} from '@/types';

export function useProjects(filters: ProjectFilters) {
    const projects = ref<Project[]>([]);
    const meta = ref({
        current_page: 1,
        last_page: 1,
        from: null as number | null,
        to: null as number | null,
        total: 0,
    });
    const loading = ref(false);
    const error = ref<string | null>(null);

    async function fetchProjects(): Promise<void> {
        loading.value = true;
        error.value = null;

        try {
            const { data: response } = await api.get<
                PaginatedResponse<Project>
            >('/projects', {
                params: {
                    search: filters.search || undefined,
                    status: filters.status || undefined,
                    priority: filters.priority || undefined,
                    owner_id: filters.owner_id || undefined,
                    sort_by: filters.sort_by || undefined,
                    sort_direction: filters.sort_direction,
                    page: filters.page,
                    per_page: filters.per_page,
                },
            });

            projects.value = response.data;
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
                'Gagal memuat daftar proyek. Silakan coba lagi.';
        } finally {
            loading.value = false;
        }
    }

    async function createProject(data: Partial<Project>): Promise<Project> {
        const { data: response } = await api.post<{ data: Project }>(
            '/projects',
            data,
        );
        return response.data;
    }

    async function updateProject(
        id: number,
        data: Partial<Project>,
    ): Promise<Project> {
        const { data: response } = await api.put<{ data: Project }>(
            `/projects/${id}`,
            data,
        );
        return response.data;
    }

    async function deleteProject(id: number): Promise<void> {
        await api.delete(`/projects/${id}`);
    }

    async function fetchProject(id: number): Promise<Project> {
        const { data: response } = await api.get<{ data: Project }>(
            `/projects/${id}`,
        );
        return response.data;
    }

    async function fetchMembers(projectId: number): Promise<ProjectMember[]> {
        const { data: response } = await api.get<{ data: ProjectMember[] }>(
            `/projects/${projectId}/members`,
        );
        return response.data;
    }

    async function addMember(
        projectId: number,
        userId: number,
        role: string,
    ): Promise<ProjectMember> {
        const { data: response } = await api.post<{ data: ProjectMember }>(
            `/projects/${projectId}/members`,
            {
                user_id: userId,
                role,
            },
        );
        return response.data;
    }

    async function updateMember(
        projectId: number,
        userId: number,
        role: string,
    ): Promise<ProjectMember> {
        const { data: response } = await api.put<{ data: ProjectMember }>(
            `/projects/${projectId}/members/${userId}`,
            {
                role,
            },
        );
        return response.data;
    }

    async function removeMember(
        projectId: number,
        userId: number,
    ): Promise<void> {
        await api.delete(`/projects/${projectId}/members/${userId}`);
    }

    async function fetchManagers(): Promise<
        Array<{
            id: number;
            name: string;
            email: string;
            avatar: string | null;
            initials: string;
        }>
    > {
        const { data: response } = await api.get<{
            data: Array<{
                id: number;
                name: string;
                email: string;
                avatar: string | null;
                initials: string;
            }>;
        }>('/projects/managers');
        return response.data;
    }

    async function fetchAvailableUsers(projectId: number): Promise<
        Array<{
            id: number;
            name: string;
            email: string;
            avatar: string | null;
            initials: string;
        }>
    > {
        const { data: response } = await api.get<{
            data: Array<{
                id: number;
                name: string;
                email: string;
                avatar: string | null;
                initials: string;
            }>;
        }>(`/projects/${projectId}/available-users`);
        return response.data;
    }

    const queryString = computed(() => ({
        search: filters.search || undefined,
        status: filters.status || undefined,
        priority: filters.priority || undefined,
        owner_id: filters.owner_id || undefined,
        page: filters.page,
    }));

    return {
        projects,
        meta,
        loading,
        error,
        fetchProjects,
        createProject,
        updateProject,
        deleteProject,
        fetchProject,
        fetchMembers,
        addMember,
        updateMember,
        removeMember,
        fetchManagers,
        fetchAvailableUsers,
        queryString,
    };
}
