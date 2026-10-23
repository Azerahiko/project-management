import { ref, computed } from 'vue';
import api from '@/lib/api';
import type { PaginatedResponse, TaskComment, TaskActivity } from '@/types';

export function useTaskComments(taskId: number) {
    const comments = ref<TaskComment[]>([]);
    const activities = ref<TaskActivity[]>([]);
    const commentsMeta = ref({
        current_page: 1,
        last_page: 1,
        from: null as number | null,
        to: null as number | null,
        total: 0,
    });
    const commentsLoading = ref(false);
    const activitiesLoading = ref(false);
    const commentsError = ref<string | null>(null);
    const activitiesError = ref<string | null>(null);

    async function fetchComments(page = 1, perPage = 15): Promise<void> {
        commentsLoading.value = true;
        commentsError.value = null;

        try {
            const { data: response } = await api.get<
                PaginatedResponse<TaskComment>
            >(`/tasks/${taskId}/comments`, {
                params: { page, per_page: perPage },
            });

            if (page === 1) {
                comments.value = response.data;
            } else {
                comments.value = [...comments.value, ...response.data];
            }

            commentsMeta.value = {
                current_page: response.meta.current_page,
                last_page: response.meta.last_page,
                from: response.meta.from,
                to: response.meta.to,
                total: response.meta.total,
            };
        } catch (e: any) {
            commentsError.value =
                e?.response?.data?.message ??
                'Gagal memuat komentar. Silakan coba lagi.';
        } finally {
            commentsLoading.value = false;
        }
    }

    async function fetchActivities(limit = 50): Promise<void> {
        activitiesLoading.value = true;
        activitiesError.value = null;

        try {
            const { data: response } = await api.get<{ data: TaskActivity[] }>(
                `/tasks/${taskId}/activities`,
                { params: { limit } },
            );

            activities.value = response.data;
        } catch (e: any) {
            activitiesError.value =
                e?.response?.data?.message ??
                'Gagal memuat aktivitas. Silakan coba lagi.';
        } finally {
            activitiesLoading.value = false;
        }
    }

    async function createComment(content: string): Promise<TaskComment | null> {
        commentsError.value = null;

        try {
            const { data: response } = await api.post<{ data: TaskComment }>(
                `/tasks/${taskId}/comments`,
                { content },
            );

            comments.value.unshift(response.data);
            commentsMeta.value.total += 1;

            return response.data;
        } catch (e: any) {
            commentsError.value =
                e?.response?.data?.message ??
                'Gagal menambahkan komentar. Silakan coba lagi.';
            return null;
        }
    }

    async function updateComment(
        commentId: number,
        content: string,
    ): Promise<TaskComment | null> {
        commentsError.value = null;

        try {
            const { data: response } = await api.put<{ data: TaskComment }>(
                `/comments/${commentId}`,
                { content },
            );

            const index = comments.value.findIndex((c) => c.id === commentId);
            if (index !== -1) {
                comments.value[index] = response.data;
            }

            return response.data;
        } catch (e: any) {
            commentsError.value =
                e?.response?.data?.message ??
                'Gagal memperbarui komentar. Silakan coba lagi.';
            return null;
        }
    }

    async function deleteComment(commentId: number): Promise<boolean> {
        commentsError.value = null;

        try {
            await api.delete(`/comments/${commentId}`);

            comments.value = comments.value.filter((c) => c.id !== commentId);
            commentsMeta.value.total = Math.max(
                0,
                commentsMeta.value.total - 1,
            );

            return true;
        } catch (e: any) {
            commentsError.value =
                e?.response?.data?.message ??
                'Gagal menghapus komentar. Silakan coba lagi.';
            return false;
        }
    }

    const hasMoreComments = computed(
        () => commentsMeta.value.current_page < commentsMeta.value.last_page,
    );
    const nextPage = computed(() => commentsMeta.value.current_page + 1);

    return {
        comments,
        activities,
        commentsMeta,
        commentsLoading,
        activitiesLoading,
        commentsError,
        activitiesError,
        fetchComments,
        fetchActivities,
        createComment,
        updateComment,
        deleteComment,
        hasMoreComments,
        nextPage,
    };
}
