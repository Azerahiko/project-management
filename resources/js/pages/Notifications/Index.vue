<script setup lang="ts">
import { onMounted, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { getIcon } from '@/components/sidebar/icons';
import AppLayout from '@/layouts/AppLayout.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Pagination from '@/components/ui/Pagination.vue';
import { useNotifications } from '@/composables/useNotifications';
import type { Notification } from '@/types';

defineOptions({
    layout: AppLayout,
});

const {
    notifications,
    meta,
    loading,
    unreadCount,
    fetchNotifications,
    markAsRead,
    markAllAsRead,
    refresh,
} = useNotifications();

onMounted(() => {
    fetchNotifications(1);
});

function handleNotificationClick(notification: Notification): void {
    if (!notification.read_at) {
        markAsRead(notification.id);
    }
    if (notification.data.url) {
        router.visit(notification.data.url);
    }
}

function handleMarkAllRead(): void {
    markAllAsRead();
}

function formatTime(dateString: string): string {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return 'Baru saja';
    if (diffMins < 60) return `${diffMins} menit lalu`;
    if (diffHours < 24) return `${diffHours} jam lalu`;
    if (diffDays < 7) return `${diffDays} hari lalu`;
    return date.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

function getNotificationIcon(action: Notification['data']['action']): string {
    switch (action) {
        case 'assigned':
            return 'UserPlus';
        case 'comment':
            return 'MessageSquare';
        case 'status_changed':
            return 'RefreshCw';
        case 'project_member_added':
            return 'Users';
        default:
            return 'Bell';
    }
}

function loadMore(): void {
    if (meta.value.current_page < meta.value.last_page && !loading.value) {
        fetchNotifications(meta.value.current_page + 1);
    }
}
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1
                    class="text-2xl font-bold text-surface-900 dark:text-surface-100"
                >
                    Notifikasi
                </h1>
                <p class="mt-1 text-surface-600 dark:text-surface-400">
                    Kelola notifikasi Anda
                </p>
            </div>

            <div class="flex items-center gap-3">
                <span
                    v-if="unreadCount > 0"
                    class="rounded-full bg-primary-100 px-3 py-1 text-sm font-medium text-primary-700 dark:bg-primary-900/30 dark:text-primary-300"
                >
                    {{ unreadCount }} belum dibaca
                </span>
                <button
                    v-if="unreadCount > 0"
                    @click="handleMarkAllRead"
                    class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-primary-700"
                >
                    Tandai semua dibaca
                </button>
            </div>
        </div>

        <!-- Notification list -->
        <div class="card overflow-hidden">
            <div
                v-if="loading && notifications.length === 0"
                class="flex items-center justify-center py-12"
            >
                <div
                    class="h-8 w-8 animate-spin rounded-full border-3 border-primary-500 border-t-transparent"
                />
            </div>

            <div v-else-if="notifications.length === 0" class="py-16">
                <EmptyState
                    icon="Bell"
                    title="Tidak ada notifikasi"
                    description="Semua tertangkap. Notifikasi baru akan muncul di sini."
                />
            </div>

            <div
                v-else
                class="divide-y divide-surface-200 dark:divide-surface-700"
            >
                <div
                    v-for="notification in notifications"
                    :key="notification.id"
                    @click="handleNotificationClick(notification)"
                    :class="[
                        'cursor-pointer px-6 py-4 transition-colors hover:bg-surface-50 dark:hover:bg-surface-800/50',
                        {
                            'bg-surface-50/50 dark:bg-surface-800/50':
                                !notification.read_at,
                        },
                    ]"
                >
                    <div class="flex items-start gap-4">
                        <div
                            :class="[
                                'flex h-10 w-10 shrink-0 items-center justify-center rounded-xl',
                                !notification.read_at
                                    ? 'bg-primary-100 dark:bg-primary-900/30'
                                    : 'bg-surface-100 dark:bg-surface-800',
                            ]"
                        >
                            <component
                                :is="
                                    getIcon(
                                        getNotificationIcon(
                                            notification.data.action,
                                        ),
                                    )
                                "
                                :class="[
                                    'h-5 w-5',
                                    !notification.read_at
                                        ? 'text-primary-600 dark:text-primary-400'
                                        : 'text-surface-500 dark:text-surface-400',
                                ]"
                            />
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-start justify-between gap-4">
                                <p
                                    class="text-sm text-surface-900 dark:text-surface-100"
                                >
                                    <template v-if="!notification.read_at">
                                        <span
                                            v-if="
                                                notification.data.action ===
                                                'assigned'
                                            "
                                            class="font-medium"
                                        >
                                            Tugas "{{
                                                notification.data.task_title
                                            }}" ditugaskan ke Anda
                                        </span>
                                        <span
                                            v-else-if="
                                                notification.data.action ===
                                                'comment'
                                            "
                                            class="font-medium"
                                        >
                                            {{ notification.data.author_name }}
                                            mengomentari "{{
                                                notification.data.task_title
                                            }}"
                                        </span>
                                        <span
                                            v-else-if="
                                                notification.data.action ===
                                                'status_changed'
                                            "
                                            class="font-medium"
                                        >
                                            Status tugas "{{
                                                notification.data.task_title
                                            }}" berubah
                                        </span>
                                        <span
                                            v-else-if="
                                                notification.data.action ===
                                                'project_member_added'
                                            "
                                            class="font-medium"
                                        >
                                            Anda ditambahkan ke proyek "{{
                                                notification.data.project_name
                                            }}"
                                        </span>
                                    </template>
                                    <template v-else>
                                        <span
                                            v-if="
                                                notification.data.action ===
                                                'assigned'
                                            "
                                            class="text-surface-600 dark:text-surface-400"
                                        >
                                            Tugas "{{
                                                notification.data.task_title
                                            }}" ditugaskan ke Anda
                                        </span>
                                        <span
                                            v-else-if="
                                                notification.data.action ===
                                                'comment'
                                            "
                                            class="text-surface-600 dark:text-surface-400"
                                        >
                                            {{ notification.data.author_name }}
                                            mengomentari "{{
                                                notification.data.task_title
                                            }}"
                                        </span>
                                        <span
                                            v-else-if="
                                                notification.data.action ===
                                                'status_changed'
                                            "
                                            class="text-surface-600 dark:text-surface-400"
                                        >
                                            Status tugas "{{
                                                notification.data.task_title
                                            }}" berubah
                                        </span>
                                        <span
                                            v-else-if="
                                                notification.data.action ===
                                                'project_member_added'
                                            "
                                            class="text-surface-600 dark:text-surface-400"
                                        >
                                            Anda ditambahkan ke proyek "{{
                                                notification.data.project_name
                                            }}"
                                        </span>
                                    </template>
                                </p>

                                <p
                                    class="shrink-0 text-xs whitespace-nowrap text-surface-400 dark:text-surface-500"
                                >
                                    {{ formatTime(notification.created_at) }}
                                </p>
                            </div>

                            <div
                                v-if="
                                    notification.data.action === 'comment' &&
                                    notification.data.comment_preview
                                "
                                class="mt-2 line-clamp-2 text-sm text-surface-500 dark:text-surface-400"
                            >
                                {{ notification.data.comment_preview }}
                            </div>

                            <div
                                v-if="
                                    notification.data.action ===
                                    'status_changed'
                                "
                                class="mt-2 flex items-center gap-2 text-sm"
                            >
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="[
                                        notification.data.new_status === 'done'
                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                            : notification.data.new_status ===
                                                'in_progress'
                                              ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
                                              : notification.data.new_status ===
                                                  'review'
                                                ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400'
                                                : 'bg-surface-100 text-surface-700 dark:bg-surface-800 dark:text-surface-300',
                                    ]"
                                >
                                    {{ notification.data.new_status }}
                                </span>
                                <span
                                    class="text-surface-400 dark:text-surface-500"
                                    >dari</span
                                >
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="[
                                        notification.data.old_status === 'done'
                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                                            : notification.data.old_status ===
                                                'in_progress'
                                              ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
                                              : notification.data.old_status ===
                                                  'review'
                                                ? 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400'
                                                : 'bg-surface-100 text-surface-700 dark:bg-surface-800 dark:text-surface-300',
                                    ]"
                                >
                                    {{ notification.data.old_status }}
                                </span>
                            </div>

                            <div
                                v-if="
                                    notification.data.action ===
                                    'project_member_added'
                                "
                                class="mt-2 text-sm text-surface-500 dark:text-surface-400"
                            >
                                Peran: {{ notification.data.role }} ·
                                Ditambahkan oleh
                                {{ notification.data.added_by_name }}
                            </div>
                        </div>

                        <div
                            v-if="!notification.read_at"
                            class="mt-1 flex h-3 w-3 shrink-0 rounded-full bg-primary-500"
                        />
                    </div>
                </div>

                <!-- Load more / Pagination -->
                <div
                    class="border-t border-surface-200 px-6 py-4 dark:border-surface-700"
                >
                    <Pagination
                        v-if="meta.last_page > 1"
                        :current-page="meta.current_page"
                        :last-page="meta.last_page"
                        :from="meta.from"
                        :to="meta.to"
                        :total="meta.total"
                        @change="fetchNotifications"
                    />

                    <button
                        v-else-if="meta.current_page < meta.last_page"
                        @click="loadMore"
                        :disabled="loading"
                        class="w-full rounded-lg px-4 py-2 text-sm font-medium text-primary-600 transition-colors hover:bg-primary-50 hover:text-primary-700 disabled:cursor-not-allowed disabled:opacity-50 dark:hover:bg-primary-900/20"
                    >
                        <span
                            v-if="loading"
                            class="flex items-center justify-center gap-2"
                        >
                            <span
                                class="h-4 w-4 animate-spin rounded-full border-2 border-primary-500 border-t-transparent"
                            />
                            Memuat...
                        </span>
                        <span v-else>Muat lebih banyak</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
