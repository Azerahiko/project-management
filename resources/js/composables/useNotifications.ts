import { ref, readonly } from 'vue';
import api from '@/lib/api';
import type { Notification, PaginatedResponse } from '@/types';

export type NotificationFilter = 'all' | 'unread' | 'tasks' | 'projects';

// Module-level singleton state
const notifications = ref<Notification[]>([]);
const meta = ref({
    current_page: 1,
    last_page: 1,
    total: 0,
    from: null as number | null,
    to: null as number | null,
});
const loading = ref(false);
const error = ref<string | null>(null);
const unreadCount = ref(0);
let pollTimer: ReturnType<typeof setInterval> | null = null;
let isInitialized = false;

// --- Internal helpers ---

function isUnauthorized(error: unknown): boolean {
    return (
        typeof error === 'object' &&
        error !== null &&
        'response' in error &&
        (error as { response?: { status?: number } }).response?.status === 401
    );
}

async function doFetchNotifications(
    page = 1,
    filter: NotificationFilter = 'all',
): Promise<boolean> {
    loading.value = true;
    error.value = null;

    try {
        const { data: response } = await api.get<
            PaginatedResponse<Notification>
        >('/notifications', {
            params: {
                page,
                per_page: 15,
                ...(filter !== 'all' ? { filter } : {}),
            },
        });

        notifications.value = response.data;
        meta.value = response.meta;

        return true;
    } catch (e) {
        if (isUnauthorized(e)) {
            stopPolling();
            return false;
        }

        error.value = 'Gagal memuat notifikasi. Periksa koneksi Anda.';
        console.error('[Notifications] Fetch failed:', e);

        return false;
    } finally {
        loading.value = false;
    }
}

async function doFetchUnreadCount(): Promise<void> {
    try {
        const { data } = await api.get<{ count: number }>(
            '/notifications/unread-count',
        );
        unreadCount.value = data.count ?? 0;
    } catch (e) {
        if (isUnauthorized(e)) {
            stopPolling();
            return;
        }
        console.error('[Notifications] Unread count fetch failed:', e);
    }
}

// --- Polling lifecycle ---

function startPolling(): void {
    if (pollTimer) return; // Already running
    // Initial fetch immediate
    void doFetchUnreadCount();
    pollTimer = setInterval(doFetchUnreadCount, 30_000);
}

function stopPolling(): void {
    if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
    }
}

// --- Public composable ---

export function useNotifications() {
    // Initialize once per app session: seed unread count + start polling.
    // The list itself is fetched by whoever needs it (page mount / dropdown open).
    if (!isInitialized) {
        isInitialized = true;
        startPolling();
    }

    async function markAsRead(id: string): Promise<void> {
        const index = notifications.value.findIndex((n) => n.id === id);
        const wasUnread = index === -1 || !notifications.value[index].read_at;

        await api.put(`/notifications/${id}/read`);

        if (index !== -1) {
            notifications.value[index] = {
                ...notifications.value[index],
                read_at: new Date().toISOString(),
            };
        }

        if (wasUnread) {
            unreadCount.value = Math.max(0, unreadCount.value - 1);
        }
    }

    async function markAllAsRead(): Promise<void> {
        await api.put('/notifications/read-all');
        const now = new Date().toISOString();
        notifications.value = notifications.value.map((n) => ({
            ...n,
            read_at: now,
        }));
        unreadCount.value = 0;
    }

    async function refresh(): Promise<void> {
        await doFetchNotifications(1);
    }

    // Expose stopPolling for cleanup global (e.g., in AppLayout onUnmounted)
    return {
        notifications: readonly(notifications),
        meta: readonly(meta),
        loading: readonly(loading),
        error: readonly(error),
        unreadCount: readonly(unreadCount),
        fetchNotifications: doFetchNotifications,
        fetchUnreadCount: doFetchUnreadCount,
        markAsRead,
        markAllAsRead,
        refresh,
        startPolling,
        stopPolling,
        _internal: { isInitialized: () => isInitialized }, // for testing
    };
}

// Auto-cleanup when app unmounts (optional, call in AppLayout)
export function cleanupNotifications(): void {
    stopPolling();
    isInitialized = false;
}
