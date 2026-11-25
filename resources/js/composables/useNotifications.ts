import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import api from '@/lib/api';
import { useToast } from '@/composables/useToast';
import type { Notification, PaginatedResponse } from '@/types';

export type NotificationFilter = 'all' | 'unread' | 'tasks' | 'projects';

function isUnauthorized(err: unknown): boolean {
    return (
        (typeof err === 'object' &&
            err !== null &&
            'response' in err &&
            (err as { response?: { status?: number } }).response?.status ===
                401) ||
        (err as { response?: { status?: number } }).response?.status === 419
    );
}

function parseNotificationHeadline(notification: Notification): {
    title: string;
    message: string;
} {
    const data = notification.data;
    switch (data.action) {
        case 'assigned':
            return {
                title: 'Tugas Baru Ditugaskan',
                message: `Tugas "${data.task_title || 'Tugas'}" ditugaskan kepada Anda.`,
            };
        case 'comment':
            return {
                title: 'Komentar Baru',
                message: `${data.author_name || 'Rekan'} mengomentari "${data.task_title || 'Tugas'}".`,
            };
        case 'status_changed':
            return {
                title: 'Status Tugas Berubah',
                message: `Status "${data.task_title || 'Tugas'}" diubah menjadi ${data.new_status || 'baru'}.`,
            };
        case 'project_member_added':
            return {
                title: 'Ditambahkan ke Proyek',
                message: `Anda ditambahkan ke proyek "${data.project_name || 'Proyek'}".`,
            };
        case 'project_ownership_transferred':
            return {
                title: 'Kepemilikan Proyek Dialihkan',
                message: `Proyek "${data.project_name || 'Proyek'}" dialihkan kepemilikannya.`,
            };
        default:
            return {
                title: 'Pemberitahuan Baru',
                message: 'Anda menerima pembaruan aktivitas baru.',
            };
    }
}

function dispatchPopups(newItems: Notification[]): void {
    const toast = useToast();

    for (const item of newItems) {
        const { title, message } = parseNotificationHeadline(item);
        const targetUrl = item.data?.url;

        // Single call: Triggers both in-app toast and OS notification via useToast
        // Uses tag: item.id to prevent duplicate pop-ups if called multiple times
        toast.info(
            title,
            message,
            5000,
            targetUrl
                ? {
                      label: 'Buka',
                      onClick: () => {
                          void markAsRead(item.id);
                          router.visit(targetUrl);
                      },
                  }
                : undefined,
            { tag: item.id },
        );
    }
}

async function syncNotifications(): Promise<void> {
    if (isFetching.value) return;
    isFetching.value = true;

    try {
        const [unreadRes, listRes] = await Promise.allSettled([
            api.get<{ count: number }>('/notifications/unread-count'),
            api.get<PaginatedResponse<Notification>>('/notifications', {
                params: { page: 1, per_page: 15 },
            }),
        ]);

        if (unreadRes.status === 'fulfilled') {
            unreadCount.value = unreadRes.value.data.count ?? 0;
        }

        if (listRes.status === 'fulfilled') {
            const fetched = listRes.value.data.data;
            notifications.value = fetched;
            meta.value = listRes.value.data.meta;

            if (!isInitialSyncDone) {
                // Initial load: populate known IDs without firing toasts
                fetched.forEach((n) => knownNotificationIds.add(n.id));
                isInitialSyncDone = true;
            } else {
                // Subsequent syncs: detect genuinely new unread items
                const newUnreadItems: Notification[] = [];
                for (const item of fetched) {
                    if (!knownNotificationIds.has(item.id)) {
                        knownNotificationIds.add(item.id);
                        if (!item.read_at) {
                            newUnreadItems.push(item);
                        }
                    }
                }

                if (newUnreadItems.length > 0) {
                    dispatchPopups(newUnreadItems);
                }
            }
        }
    } catch (e) {
        if (isUnauthorized(e)) {
            stopPolling();
            // Auto-redirect to login if session invalidated (e.g., email changed by admin)
            if (
                typeof window !== 'undefined' &&
                window.location.pathname !== '/login'
            ) {
                window.location.href = '/login';
            }
            return;
        }
        console.error('[Notifications] Sync error:', e);
    } finally {
        isFetching.value = false;
        scheduleNextPoll();
    }
}

function scheduleNextPoll(): void {
    if (!isPollingActive) return;

    if (pollTimer) {
        clearTimeout(pollTimer);
        pollTimer = null;
    }

    const interval =
        typeof document !== 'undefined' && document.hidden
            ? BACKGROUND_POLL_INTERVAL
            : ACTIVE_POLL_INTERVAL;

    pollTimer = setTimeout(() => {
        void syncNotifications();
    }, interval);
}

function startPolling(): void {
    if (isPollingActive) return;
    isPollingActive = true;

    if (typeof document !== 'undefined') {
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && isPollingActive) {
                // Immediate sync when tab becomes active again
                void syncNotifications();
            } else {
                scheduleNextPoll();
            }
        });
    }

    void syncNotifications();
}

function stopPolling(): void {
    isPollingActive = false;
    if (pollTimer) {
        clearTimeout(pollTimer);
        pollTimer = null;
    }
}

async function requestNotificationPermission(): Promise<NotificationPermission> {
    if (typeof window === 'undefined' || !('Notification' in window)) {
        return 'denied';
    }

    try {
        const result = await Notification.requestPermission();
        permissionStatus.value = result;
        return result;
    } catch (e) {
        console.error('[WebNotification] Permission request failed:', e);
        return 'denied';
    }
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

        response.data.forEach((n) => knownNotificationIds.add(n.id));

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

export function useNotifications() {
    // Auto-start polling singleton when composable is used
    if (!isPollingActive) {
        startPolling();
    }

    return {
        notifications,
        meta,
        loading,
        error,
        unreadCount,
        permissionStatus,
        fetchNotifications: doFetchNotifications,
        syncNotifications,
        requestNotificationPermission,
        markAsRead,
        markAllAsRead,
        startPolling,
        stopPolling,
    };
}

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
const permissionStatus = ref<NotificationPermission>(
    typeof window !== 'undefined' && 'Notification' in window
        ? Notification.permission
        : 'default',
);

// New notification tracking
const knownNotificationIds = new Set<string>();
let isInitialSyncDone = false;

// Polling configuration: 5s foreground, 20s background
const ACTIVE_POLL_INTERVAL = 5000;
const BACKGROUND_POLL_INTERVAL = 20000;
let pollTimer: ReturnType<typeof setTimeout> | null = null;
let isPollingActive = false;
let _visibilityHandler: (() => void) | null = null;
