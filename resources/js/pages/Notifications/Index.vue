<script setup lang="ts">
import { onMounted, computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { getIcon } from '@/components/sidebar/icons';
import AppLayout from '@/layouts/AppLayout.vue';
import Pagination from '@/components/ui/Pagination.vue';
import { useToast } from '@/composables/useToast';
import {
    useNotifications,
    type NotificationFilter,
} from '@/composables/useNotifications';
import type { Notification } from '@/types';

defineOptions({
    layout: AppLayout,
});

const {
    notifications,
    meta,
    loading,
    error: loadError,
    unreadCount,
    fetchNotifications,
    markAsRead,
    markAllAsRead,
} = useNotifications();
const { error: toastError } = useToast();

const activeTab = ref<NotificationFilter>('all');
const markingAll = ref(false);

const tabs: Array<{ value: NotificationFilter; label: string }> = [
    { value: 'all', label: 'Semua' },
    { value: 'unread', label: 'Belum Dibaca' },
    { value: 'tasks', label: 'Tugas' },
    { value: 'projects', label: 'Proyek' },
];

const emptyCopy = computed(() => {
    if (activeTab.value === 'unread') {
        return {
            title: 'Semua sudah dibaca.',
            description:
                'Tidak ada notifikasi yang belum dibaca saat ini. Anda sudah menyusul semua pembaruan tim.',
            icon: 'CheckCheck',
        };
    }

    if (activeTab.value === 'tasks') {
        return {
            title: 'Belum ada notifikasi tugas.',
            description:
                'Penugasan baru, komentar, dan perubahan status tugas akan muncul di sini.',
            icon: 'CheckSquare',
        };
    }

    if (activeTab.value === 'projects') {
        return {
            title: 'Belum ada notifikasi proyek.',
            description:
                'Pembaruan keanggotaan dan aktivitas proyek akan muncul di sini.',
            icon: 'FolderKanban',
        };
    }

    return {
        title: 'Semua sudah selesai.',
        description:
            'Tidak ada pemberitahuan baru yang memerlukan perhatian Anda saat ini.',
        icon: 'CheckCheck',
    };
});

async function loadNotifications(page = 1): Promise<boolean> {
    const ok = await fetchNotifications(page, activeTab.value);

    if (!ok && loadError.value) {
        toastError('Gagal memuat notifikasi', loadError.value);
    }

    return ok;
}

async function selectTab(tab: NotificationFilter): Promise<void> {
    if (activeTab.value === tab) {
        return;
    }

    activeTab.value = tab;
    await loadNotifications(1);
}

onMounted(() => {
    loadNotifications(1);
});

async function handleNotificationClick(
    notification: Notification,
): Promise<void> {
    if (!notification.read_at) {
        try {
            await markAsRead(notification.id);
        } catch {
            toastError(
                'Gagal memperbarui notifikasi',
                'Status baca tidak dapat disimpan. Coba lagi.',
            );
        }
    }

    if (notification.data.url) {
        router.visit(notification.data.url);
        return;
    }

    if (activeTab.value === 'unread') {
        await loadNotifications(meta.value.current_page);
    }
}

async function handleMarkSingleRead(
    notification: Notification,
    e: Event,
): Promise<void> {
    e.stopPropagation();

    try {
        await markAsRead(notification.id);

        if (activeTab.value === 'unread') {
            await loadNotifications(meta.value.current_page);
        }
    } catch {
        toastError(
            'Gagal memperbarui notifikasi',
            'Status baca tidak dapat disimpan. Coba lagi.',
        );
    }
}

async function handleMarkAllRead(): Promise<void> {
    if (markingAll.value) {
        return;
    }

    markingAll.value = true;

    try {
        await markAllAsRead();

        if (activeTab.value !== 'all') {
            await loadNotifications(1);
        }
    } catch {
        toastError(
            'Gagal menandai semua notifikasi',
            'Terjadi kesalahan saat memperbarui status baca.',
        );
    } finally {
        markingAll.value = false;
    }
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
    if (diffDays === 1) return 'Kemarin';
    if (diffDays < 7) return `${diffDays} hari lalu`;
    return date.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

function getNotificationHeadline(notification: Notification): {
    title: string;
    description: string;
    icon: string;
    category: string;
} {
    const action = notification.data.action;
    switch (action) {
        case 'assigned':
            return {
                title: 'Tugas Baru Ditugaskan',
                description: `Tugas "${notification.data.task_title || 'Tugas'}" dalam proyek ${notification.data.project_name || 'Tim'} ditugaskan kepada Anda.`,
                icon: 'CheckSquare',
                category: 'Tugas Tim',
            };
        case 'comment':
            return {
                title: 'Komentar Baru pada Tugas',
                description: `${notification.data.author_name || 'Rekan'} menambahkan komentar pada "${notification.data.task_title || 'Tugas'}"${notification.data.comment_preview ? `: "${notification.data.comment_preview}"` : ''}`,
                icon: 'MessageSquare',
                category: 'Kolaborasi',
            };
        case 'status_changed':
            return {
                title: 'Perubahan Status Alur Kerja',
                description: `Status tugas "${notification.data.task_title || 'Tugas'}" telah diperbarui.`,
                icon: 'RefreshCw',
                category: 'Alur Tugas',
            };
        case 'project_member_added':
            return {
                title: 'Ditambahkan ke Proyek Baru',
                description: `Anda resmi ditambahkan sebagai anggota dalam proyek "${notification.data.project_name || 'Proyek'}".`,
                icon: 'FolderKanban',
                category: 'Proyek Tim',
            };
        default:
            return {
                title: 'Pemberitahuan Sistem',
                description:
                    'Aktivitas terbaru tercatat pada akun workspace Anda.',
                icon: 'Bell',
                category: 'Sistem',
            };
    }
}

function handlePageChange(pageNum: number): void {
    loadNotifications(pageNum);
}
</script>

<template>
    <div class="space-y-6">
        <!-- 1. Header Section -->
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <span
                    class="inline-flex h-6 items-center gap-1.5 rounded-full border border-primary-200 bg-primary-50 px-2.5 text-[10px] font-bold tracking-wider text-primary-700 uppercase dark:border-primary-700/40 dark:bg-primary-900/25 dark:text-primary-200"
                >
                    <component :is="getIcon('Bell')" class="h-3 w-3" />
                    Pusat Notifikasi
                </span>
                <h1
                    class="mt-2 text-2xl font-semibold tracking-tight text-surface-950 sm:text-3xl dark:text-surface-50"
                >
                    Pemberitahuan & Aktivitas
                </h1>
                <p
                    class="mt-1 max-w-xl text-xs text-surface-600 sm:text-sm dark:text-surface-400"
                >
                    Pantau pembaruan tugas, penugasan baru, dan umpan balik tim
                    secara terpusat.
                </p>
            </div>

            <!-- Actions Header -->
            <div class="flex flex-wrap items-center gap-3">
                <div
                    v-if="unreadCount > 0"
                    class="flex items-center gap-2 text-xs font-medium text-surface-700 dark:text-surface-300"
                >
                    <span
                        class="h-2 w-2 rounded-full bg-primary-600 dark:bg-primary-400"
                    />
                    <span>{{ unreadCount }} belum dibaca</span>
                </div>
                <button
                    v-if="unreadCount > 0"
                    type="button"
                    :disabled="markingAll"
                    class="btn btn-primary btn-sm"
                    @click="handleMarkAllRead"
                >
                    <component
                        :is="getIcon('CheckCheck')"
                        class="h-3.5 w-3.5"
                    />
                    <span>{{
                        markingAll ? 'Memproses...' : 'Tandai Semua Dibaca'
                    }}</span>
                </button>
            </div>
        </div>

        <!-- 2. Segmented Filter Tabs -->
        <div
            class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-surface-200 bg-white p-2.5 shadow-xs dark:border-surface-800 dark:bg-surface-900"
        >
            <div
                class="no-scrollbar flex w-full gap-1 overflow-x-auto rounded-xl border border-surface-200 bg-surface-100 p-1 sm:w-auto sm:flex-nowrap sm:overflow-visible sm:rounded-full dark:border-surface-800 dark:bg-surface-800"
            >
                <button
                    v-for="tab in tabs"
                    :key="tab.value"
                    type="button"
                    class="inline-flex shrink-0 items-center justify-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-medium whitespace-nowrap transition active:scale-95"
                    :class="
                        activeTab === tab.value
                            ? 'bg-primary-700 text-white shadow-2xs dark:bg-primary-300 dark:text-primary-950'
                            : 'text-surface-600 hover:text-surface-900 dark:text-surface-400 dark:hover:text-white'
                    "
                    :aria-pressed="activeTab === tab.value"
                    @click="selectTab(tab.value)"
                >
                    <span>{{ tab.label }}</span>
                    <span
                        v-if="tab.value === 'unread' && unreadCount > 0"
                        class="rounded-md px-1.5 text-[10px] font-bold"
                        :class="
                            activeTab === tab.value
                                ? 'bg-white/20 text-white dark:bg-primary-950/15 dark:text-primary-950'
                                : 'bg-surface-200 text-surface-600 dark:bg-surface-700 dark:text-surface-200'
                        "
                    >
                        {{ unreadCount }}
                    </span>
                </button>
            </div>

            <div class="flex items-center gap-2 px-3 text-xs text-surface-400">
                <span
                    v-if="loading && notifications.length > 0"
                    class="h-3 w-3 animate-spin rounded-full border-2 border-primary-700 border-t-transparent dark:border-primary-300 dark:border-t-transparent"
                />
                Menampilkan {{ meta.total }} pemberitahuan
            </div>
        </div>

        <!-- 3. Notification List (Calm 08 NOTIFICATION Style) -->
        <div v-if="loadError && notifications.length === 0 && !loading">
            <div
                class="flex flex-col items-start gap-3 rounded-3xl border border-rose-200 bg-rose-50 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-rose-900/40 dark:bg-rose-950/20"
                role="alert"
            >
                <div class="flex items-start gap-3">
                    <span
                        class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-white/70 text-rose-600 dark:bg-surface-900/40 dark:text-rose-300"
                    >
                        <component
                            :is="getIcon('AlertTriangle')"
                            class="h-4 w-4"
                        />
                    </span>
                    <div>
                        <p
                            class="text-sm font-semibold text-rose-800 dark:text-rose-200"
                        >
                            Gagal memuat notifikasi
                        </p>
                        <p
                            class="mt-0.5 text-xs text-rose-700 dark:text-rose-300"
                        >
                            {{ loadError }}
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-full border border-rose-300 bg-white px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-200"
                    @click="loadNotifications(1)"
                >
                    <component :is="getIcon('RefreshCw')" class="h-3.5 w-3.5" />
                    Coba lagi
                </button>
            </div>
        </div>

        <div
            v-else-if="loading && notifications.length === 0"
            class="flex justify-center py-20"
        >
            <div
                class="h-8 w-8 animate-spin rounded-full border-3 border-primary-700 border-t-transparent dark:border-primary-300 dark:border-t-transparent"
            />
        </div>

        <!-- Empty State (Calm Style) -->
        <div
            v-else-if="notifications.length === 0"
            class="flex flex-col items-center justify-center rounded-3xl border border-surface-200/80 bg-white p-10 text-center shadow-xs sm:p-16 dark:border-surface-800 dark:bg-surface-900"
        >
            <div
                class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-200"
            >
                <component :is="getIcon(emptyCopy.icon)" class="h-7 w-7" />
            </div>
            <h3
                class="mt-4 text-lg font-bold text-surface-950 dark:text-surface-50"
            >
                {{ emptyCopy.title }}
            </h3>
            <p
                class="mt-1 max-w-sm text-xs leading-5 text-surface-500 dark:text-surface-400"
            >
                {{ emptyCopy.description }}
            </p>
            <button
                v-if="activeTab !== 'all'"
                type="button"
                class="btn btn-secondary btn-sm mt-4"
                @click="selectTab('all')"
            >
                Lihat semua notifikasi
            </button>
        </div>

        <!-- Notification Cards -->
        <div v-else class="space-y-3">
            <article
                v-for="notification in notifications"
                :key="notification.id"
                class="group relative flex cursor-pointer flex-col justify-between gap-4 rounded-2xl border p-4 shadow-xs transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md focus-visible:ring-2 focus-visible:ring-primary-600/40 focus-visible:outline-hidden sm:flex-row sm:items-center sm:p-5"
                :class="
                    !notification.read_at
                        ? 'border-primary-700/25 bg-white dark:border-primary-500/30 dark:bg-surface-900'
                        : 'border-surface-200 bg-surface-50/60 hover:bg-white dark:border-surface-800 dark:bg-surface-900/40 dark:hover:bg-surface-900'
                "
                role="button"
                tabindex="0"
                :aria-label="`Buka notifikasi: ${getNotificationHeadline(notification).title}`"
                @click="handleNotificationClick(notification)"
                @keydown.enter="handleNotificationClick(notification)"
                @keydown.space.prevent="handleNotificationClick(notification)"
            >
                <div class="flex min-w-0 flex-1 items-start gap-3.5 sm:gap-4">
                    <div
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl transition"
                        :class="
                            !notification.read_at
                                ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/40 dark:text-primary-200'
                                : 'bg-surface-200/70 text-surface-500 dark:bg-surface-800 dark:text-surface-400'
                        "
                    >
                        <component
                            :is="
                                getIcon(
                                    getNotificationHeadline(notification).icon,
                                )
                            "
                            class="h-5 w-5"
                        />
                    </div>

                    <!-- Notification Content -->
                    <div class="min-w-0 flex-1">
                        <div
                            class="flex flex-wrap items-center gap-2 text-xs font-medium text-surface-400"
                        >
                            <span>{{
                                getNotificationHeadline(notification).category
                            }}</span>
                            <span class="text-surface-300 dark:text-surface-700"
                                >·</span
                            >
                            <span>{{
                                formatTime(notification.created_at)
                            }}</span>
                            <!-- Unread Indicator Dot -->
                            <span
                                v-if="!notification.read_at"
                                class="inline-flex items-center gap-1 font-semibold text-primary-700 dark:text-primary-300"
                            >
                                <span
                                    class="h-1.5 w-1.5 rounded-full bg-primary-700 dark:bg-primary-300"
                                />
                                <span>Baru</span>
                            </span>
                        </div>

                        <h2
                            class="mt-1 text-sm font-semibold tracking-tight text-surface-950 transition group-hover:text-primary-700 dark:text-surface-50 dark:group-hover:text-primary-300"
                        >
                            {{ getNotificationHeadline(notification).title }}
                        </h2>

                        <p
                            class="mt-1 text-xs leading-relaxed text-surface-600 dark:text-surface-300"
                        >
                            {{
                                getNotificationHeadline(notification)
                                    .description
                            }}
                        </p>
                    </div>
                </div>

                <!-- Right Action / Link -->
                <div
                    class="flex shrink-0 flex-wrap items-center gap-2 sm:self-center"
                >
                    <button
                        v-if="!notification.read_at"
                        type="button"
                        class="rounded-full border border-surface-300 bg-white px-3 py-1 text-xs font-medium text-surface-700 shadow-2xs hover:bg-surface-50 dark:border-surface-700 dark:bg-surface-800 dark:text-surface-300"
                        title="Tandai sudah dibaca"
                        @click="handleMarkSingleRead(notification, $event)"
                    >
                        Tandai Dibaca
                    </button>
                    <span
                        v-if="notification.data.url"
                        class="inline-flex items-center gap-1 text-xs font-medium text-primary-700 transition group-hover:underline dark:text-primary-300"
                    >
                        <span>Buka</span>
                        <span>→</span>
                    </span>
                </div>
            </article>
        </div>

        <!-- 4. Pagination -->
        <Pagination
            v-if="meta.last_page > 1"
            :current-page="meta.current_page"
            :last-page="meta.last_page"
            :from="meta.from"
            :to="meta.to"
            :total="meta.total"
            @change="handlePageChange"
        />
    </div>
</template>
