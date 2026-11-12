import { ref, computed } from 'vue';
import api from '@/lib/api';
import type { PaginatedResponse, Task, TaskFilters } from '@/types';

export function useTrashedTasks(projectId: number, filters: TaskFilters) {
    const tasks = ref<Task[]>([]);
    const meta = ref({
        current_page: 1,
        last_page: 1,
        from: null as number | null,
        to: null as number | null,
        total: 0,
    });
    const loading = ref(false);
    const error = ref<string | null>(null);

    async function fetchTasks(): Promise<void> {
        loading.value = true;
        error.value = null;

        try {
            const { data: response } = await api.get<PaginatedResponse<Task>>(
                `/projects/${projectId}/tasks/trashed`,
                {
                    params: {
                        search: filters.search || undefined,
                        status: filters.status || undefined,
                        priority: filters.priority || undefined,
                        assignee_id: filters.assignee_id || undefined,
                        sort_by: filters.sort_by || undefined,
                        sort_direction: filters.sort_direction,
                        page: filters.page,
                        per_page: filters.per_page,
                    },
                },
            );

            tasks.value = response.data;
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
                'Gagal memuat daftar tugas yang dihapus. Silakan coba lagi.';
        } finally {
            loading.value = false;
        }
    }

    async function restoreTask(id: number): Promise<void> {
        await api.post(`/tasks/${id}/restore`);
    }

    return {
        tasks,
        meta,
        loading,
        error,
        fetchTasks,
        restoreTask,
    };
}