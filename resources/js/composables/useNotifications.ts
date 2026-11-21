import { ref } from 'vue';
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
const isFetching = ref(false);

// Polling lifecycle state
let pollTimer: ReturnType<typeof setInterval> | null = null;
let visibilityHandler: (() => void) | null = null;
let isInitialized = false;
let subscriberCount = 0;

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
    // Skip if a fetch is already in flight or the tab is hidden.
    if (isFetching.value || document.hidden) {
        return;
    }

    isFetching.value = true;

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
    } finally {
        isFetching.value = false;
    }
}

// --- Polling lifecycle ---

function startPolling(): void {
    // Strict no-op if a timer is already running.
    if (pollTimer) return;

    // Initial fetch immediately.
    void doFetchUnreadCount();
    pollTimer = setInterval(doFetchUnreadCount, 30_000);

    // Pause network work while the tab is hidden, resume when visible again.
    if (typeof document !== 'undefined' && !visibilityHandler) {
        visibilityHandler = () => {
            if (document.hidden) {
                if (pollTimer) {
                    clearInterval(pollTimer);
                    pollTimer = null;
                }
            } else {
                // Only restart if there is still at least one active subscriber.
                if (subscriberCount > 0) {
                    startPolling();
                }
            }
        };
        document.addEventListener('visibilitychange', visibilityHandler);
    }

    // Track this composable as an active consumer.
    subscriberCount++;
}

function stopPolling(): void {
    // Decrement the active consumer count.
    subscriberCount--;

    // If no more consumers, clear the timer and remove the listener.
    if (subscriberCount <= 0) {
        subscriberCount = 0;

        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }

        if (visibilityHandler) {
            document.removeEventListener('visibilitychange', visibilityHandler);
            visibilityHandler = null;
        }
    }
}

// --- Public composable ---

export function useNotifications() {
    // Increment the active consumer count so the timer starts on first use.
    subscriberCount++;

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

    // Expose stopPolling for cleanup. When the last consumer calls stopPolling(),
    // the timer and visibility listener are torn down.
    return {
        notifications: notifications,
        meta: meta,
        loading: loading,
        error: error,
        unreadCount: unreadCount,
        fetchNotifications: doFetchNotifications,
        fetchUnreadCount: doFetchUnreadCount,
        markAsRead,
        markAllAsRead,
        refresh,
        startPolling,
        stopPolling,
        _internal: {
            isInitialized: () => isInitialized,
            subscriberCount: () => subscriberCount,
        }, // for testing
    };
}
