import { ref, readonly } from 'vue';
import api from '@/lib/api';
import type { Notification, PaginatedResponse } from '@/types';

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
const unreadCount = ref(0);
let pollTimer: ReturnType<typeof setInterval> | null = null;
let isInitialized = false;
let initPromise: Promise<void> | null = null;

// --- Internal helpers ---

function isUnauthorized(error: unknown): boolean {
    return (
        typeof error === 'object' &&
        error !== null &&
        'response' in error &&
        (error as { response?: { status?: number } }).response?.status === 401
    );
}

async function doFetchNotifications(page = 1): Promise<void> {
    loading.value = true;
    try {
        const { data: response } = await api.get<
            PaginatedResponse<Notification>
        >('/notifications', {
            params: { page, per_page: 15 },
        });
        if (page === 1) {
            notifications.value = response.data;
        } else {
            notifications.value = [...notifications.value, ...response.data];
        }
        meta.value = response.meta;
    } catch (e) {
        if (isUnauthorized(e)) {
            stopPolling();
            return;
        }
        console.error('[Notifications] Fetch failed:', e);
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
    // Initialize once per app session
    if (!isInitialized && !initPromise) {
        initPromise = (async () => {
            try {
                await doFetchNotifications(1);
                isInitialized = true;
                startPolling();
            } catch (e) {
                console.error('[Notifications] Init failed:', e);
                initPromise = null; // Allow retry on next call
                throw e;
            }
        })();
    }

    async function markAsRead(id: string): Promise<void> {
        await api.put(`/notifications/${id}/read`);
        const idx = notifications.value.findIndex((n) => n.id === id);
        if (idx !== -1) {
            notifications.value[idx] = {
                ...notifications.value[idx],
                read_at: new Date().toISOString(),
            };
        }
        unreadCount.value = Math.max(0, unreadCount.value - 1);
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
    initPromise = null;
}
