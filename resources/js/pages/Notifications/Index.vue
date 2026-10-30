<script setup lang="ts">
import { onMounted, computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { getIcon } from '@/components/sidebar/icons';
import AppLayout from '@/layouts/AppLayout.vue';
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
} = useNotifications();

const activeTab = ref<'all' | 'unread' | 'tasks' | 'projects'>('all');

onMounted(() => {
    fetchNotifications(1);
});

const filteredNotifications = computed(() => {
    let list = notifications.value;
    if (activeTab.value === 'unread') {
        list = list.filter((n) => !n.read_at);
    } else if (activeTab.value === 'tasks') {
        list = list.filter(
            (n) =>
                n.data.action === 'assigned' ||
                n.data.action === 'comment' ||
                n.data.action === 'status_changed',
        );
    } else if (activeTab.value === 'projects') {
        list = list.filter((n) => n.data.action === 'project_member_added');
    }
    return list;
});

function handleNotificationClick(notification: Notification): void {
    if (!notification.read_at) {
        markAsRead(notification.id);
    }
    if (notification.data.url) {
        router.visit(notification.data.url);
    }
}

function handleMarkSingleRead(notification: Notification, e: Event): void {
    e.stopPropagation();
    markAsRead(notification.id);
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
    fetchNotifications(pageNum);
}
</script>

<template>
    <div class="space-y-6">
        <!-- 1. Header Section: Calm System Style -->
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <div
                    class="flex items-center gap-2 text-xs font-medium text-surface-500"
                >
                    <span
                        class="h-1.5 w-1.5 rounded-full bg-[#0F5132] dark:bg-[#b7d9c1]"
                    />
                    <span>Pusat Notifikasi</span>
                    <span class="text-surface-300 dark:text-surface-700"
                        >·</span
                    >
                    <span>Tasks Flow Workspace</span>
                </div>
                <h1
                    class="mt-1 font-serif text-2xl font-normal tracking-tight text-surface-950 sm:text-3xl dark:text-surface-50"
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

            <!-- Actions Header (NO pill badges!) -->
            <div class="flex items-center gap-3">
                <div
                    v-if="unreadCount > 0"
                    class="flex items-center gap-2 text-xs font-medium text-surface-700 dark:text-surface-300"
                >
                    <span
                        class="h-2 w-2 rounded-full bg-[#0F5132] dark:bg-[#b7d9c1]"
                    />
                    <span>{{ unreadCount }} belum dibaca</span>
                </div>
                <button
                    v-if="unreadCount > 0"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-full bg-[#0F5132] px-4 py-2 text-xs font-medium text-white shadow-xs transition hover:bg-[#0c4329] active:scale-95 dark:bg-[#0F5132] dark:hover:bg-[#14643f]"
                    @click="handleMarkAllRead"
                >
                    <component
                        :is="getIcon('CheckCheck')"
                        class="h-3.5 w-3.5"
                    />
                    <span>Tandai Semua Dibaca</span>
                </button>
            </div>
        </div>

        <!-- 2. Segmented Filter Tabs (Calm 07 TABS & SLIDER Style) -->
        <div
            class="flex flex-wrap items-center justify-between gap-3 rounded-3xl border border-surface-200/80 bg-white p-2.5 shadow-xs dark:border-surface-800 dark:bg-surface-900"
        >
            <div
                class="flex items-center rounded-full border border-surface-200 bg-surface-100 p-1 dark:border-surface-800 dark:bg-surface-800"
            >
                <button
                    type="button"
                    class="rounded-full px-4 py-1.5 text-xs font-medium transition active:scale-95"
                    :class="
                        activeTab === 'all'
                            ? 'bg-[#0F5132] text-white shadow-2xs'
                            : 'text-surface-600 hover:text-surface-900 dark:text-surface-400 dark:hover:text-white'
                    "
                    @click="activeTab = 'all'"
                >
                    Semua
                </button>
                <button
                    type="button"
                    class="flex items-center gap-1.5 rounded-full px-4 py-1.5 text-xs font-medium transition active:scale-95"
                    :class="
                        activeTab === 'unread'
                            ? 'bg-[#0F5132] text-white shadow-2xs'
                            : 'text-surface-600 hover:text-surface-900 dark:text-surface-400 dark:hover:text-white'
                    "
                    @click="activeTab = 'unread'"
                >
                    <span>Belum Dibaca</span>
                    <span
                        v-if="unreadCount > 0"
                        class="py-0.2 rounded-md bg-white/20 px-1.5 text-[10px] font-bold"
                    >
                        {{ unreadCount }}
                    </span>
                </button>
                <button
                    type="button"
                    class="rounded-full px-4 py-1.5 text-xs font-medium transition active:scale-95"
                    :class="
                        activeTab === 'tasks'
                            ? 'bg-[#0F5132] text-white shadow-2xs'
                            : 'text-surface-600 hover:text-surface-900 dark:text-surface-400 dark:hover:text-white'
                    "
                    @click="activeTab = 'tasks'"
                >
                    Tugas
                </button>
                <button
                    type="button"
                    class="rounded-full px-4 py-1.5 text-xs font-medium transition active:scale-95"
                    :class="
                        activeTab === 'projects'
                            ? 'bg-[#0F5132] text-white shadow-2xs'
                            : 'text-surface-600 hover:text-surface-900 dark:text-surface-400 dark:hover:text-white'
                    "
                    @click="activeTab = 'projects'"
                >
                    Proyek
                </button>
            </div>

            <div class="px-3 text-xs text-surface-400">
                Menampilkan {{ filteredNotifications.length }} pemberitahuan
            </div>
        </div>

        <!-- 3. Notification List (Calm 08 NOTIFICATION Style) -->
        <div
            v-if="loading && notifications.length === 0"
            class="flex justify-center py-20"
        >
            <div
                class="h-8 w-8 animate-spin rounded-full border-3 border-[#0F5132] border-t-transparent dark:border-[#b7d9c1]"
            />
        </div>

        <!-- Empty State (Calm Style) -->
        <div
            v-else-if="filteredNotifications.length === 0"
            class="flex flex-col items-center justify-center rounded-3xl border border-surface-200/80 bg-white p-16 text-center shadow-xs dark:border-surface-800 dark:bg-surface-900"
        >
            <div
                class="flex h-14 w-14 items-center justify-center rounded-2xl bg-[#B7D9C1]/30 text-[#0F5132] dark:bg-[#0F5132]/30 dark:text-[#B7D9C1]"
            >
                <component :is="getIcon('CheckCheck')" class="h-7 w-7" />
            </div>
            <h3
                class="mt-4 font-serif text-xl font-normal text-surface-950 dark:text-surface-50"
            >
                Semua Sudah Selesai.
            </h3>
            <p
                class="mt-1 max-w-sm text-xs text-surface-500 dark:text-surface-400"
            >
                Tidak ada pemberitahuan baru yang memerlukan perhatian Anda saat
                ini.
            </p>
        </div>

        <!-- Notification Cards (Calm 08 NOTIFICATION Style) -->
        <div v-else class="space-y-3">
            <article
                v-for="notification in filteredNotifications"
                :key="notification.id"
                class="group relative flex cursor-pointer flex-col justify-between gap-4 rounded-3xl border p-5 shadow-xs transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md sm:flex-row sm:items-center"
                :class="
                    !notification.read_at
                        ? 'border-[#0F5132]/30 bg-white dark:border-[#0F5132]/40 dark:bg-surface-900'
                        : 'border-surface-200/80 bg-[#FAF9F4]/60 hover:bg-white dark:border-surface-800 dark:bg-surface-900/40 dark:hover:bg-surface-900'
                "
                @click="handleNotificationClick(notification)"
            >
                <div class="flex min-w-0 flex-1 items-start gap-4">
                    <!-- Calm 08 Reference Mint Circle Badge -->
                    <div
                        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl transition"
                        :class="
                            !notification.read_at
                                ? 'bg-[#B7D9C1]/50 text-[#0F5132] dark:bg-[#0F5132]/40 dark:text-[#b7d9c1]'
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
                            class="flex items-center gap-2 text-xs font-medium text-surface-400"
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
                            <!-- Unread Indicator Dot (NO pill badge) -->
                            <span
                                v-if="!notification.read_at"
                                class="inline-flex items-center gap-1 font-semibold text-[#0F5132] dark:text-[#b7d9c1]"
                            >
                                <span
                                    class="h-1.5 w-1.5 rounded-full bg-[#0F5132] dark:bg-[#b7d9c1]"
                                />
                                <span>Baru</span>
                            </span>
                        </div>

                        <!-- Serif Headline (Calm Reference "All set.") -->
                        <h2
                            class="mt-1 font-serif text-base font-normal tracking-tight text-surface-950 transition group-hover:text-[#0F5132] dark:text-surface-50 dark:group-hover:text-[#b7d9c1]"
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
                <div class="flex shrink-0 items-center gap-2 sm:self-center">
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
                        class="inline-flex items-center gap-1 text-xs font-medium text-[#0F5132] transition group-hover:underline dark:text-[#b7d9c1]"
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
