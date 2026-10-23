<script setup lang="ts">
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { notifications as notificationsRoute } from '@/routes';
import { getIcon } from '@/components/sidebar/icons';
import { gsap } from 'gsap';
import { useNotifications } from '@/composables/useNotifications';
import type { Notification } from '@/types';

interface Props {
    user: {
        id: number;
        name: string;
    };
}

const props = defineProps<Props>();

const {
    notifications,
    loading,
    unreadCount,
    markAsRead,
    markAllAsRead,
    fetchNotifications,
} = useNotifications();

const open = ref(false);
const root = ref<HTMLElement | null>(null);
const content = ref<HTMLElement | null>(null);
let dropdownCtx: gsap.Context | null = null;

function toggle(): void {
    open.value = !open.value;
    if (open.value) {
        fetchNotifications(1);
    }
}

function close(): void {
    open.value = false;
}

function goToNotifications(): void {
    close();
    router.visit(notificationsRoute.url());
}

function onDocumentClick(event: MouseEvent): void {
    if (root.value && !root.value.contains(event.target as Node)) {
        close();
    }
}

function onEscape(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        close();
    }
}

function handleNotificationClick(notification: Notification): void {
    if (!notification.read_at) {
        markAsRead(notification.id);
    }
    if (notification.data.url) {
        router.visit(notification.data.url);
    }
    close();
}

function handleMarkAllRead(event: Event): void {
    event.stopPropagation();
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
    return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
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

function animateOpen(isOpen: boolean): void {
    dropdownCtx?.revert();

    if (!content.value) return;

    dropdownCtx = gsap.context(() => {
        if (isOpen) {
            gsap.fromTo(
                content.value!,
                { opacity: 0, y: -10 },
                { opacity: 1, y: 0, duration: 0.2, ease: 'power2.out' },
            );
        }
    });
}

watch(open, animateOpen);

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onEscape);
});

onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onEscape);
    dropdownCtx?.revert();
});
</script>

<template>
    <div class="relative" ref="root">
        <!-- Bell button -->
        <button
            @click="toggle"
            :aria-expanded="open"
            aria-label="Notifikasi"
            class="relative rounded-xl p-2 text-surface-600 transition-colors hover:bg-surface-100 hover:text-surface-900 dark:text-surface-400 dark:hover:bg-surface-800 dark:hover:text-surface-100"
        >
            <component :is="getIcon('Bell')" class="h-5 w-5" />
            <span
                v-if="unreadCount > 0"
                class="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-[10px] font-semibold text-white"
            >
                {{ unreadCount > 9 ? '9+' : unreadCount }}
            </span>
        </button>

        <!-- Dropdown -->
        <div
            v-show="open"
            ref="content"
            class="absolute right-0 mt-2 w-80 max-w-[calc(100vw-2rem)] rounded-2xl border border-white/20 bg-white/95 px-2 py-2 shadow-lg backdrop-blur-xl dark:border-surface-700/30 dark:bg-surface-900/95"
        >
            <!-- Header -->
            <div class="flex items-center justify-between px-3 py-2">
                <h3
                    class="text-sm font-semibold text-surface-900 dark:text-surface-100"
                >
                    Notifikasi
                </h3>
                <button
                    v-if="unreadCount > 0"
                    @click="handleMarkAllRead"
                    class="text-xs text-primary-600 hover:text-primary-700 dark:text-primary-400 dark:hover:text-primary-300"
                >
                    Tandai semua dibaca
                </button>
            </div>

            <div class="border-t border-surface-200 dark:border-surface-700" />

            <!-- Notification list -->
            <div class="max-h-96 overflow-y-auto">
                <div
                    v-if="notifications.length === 0 && loading"
                    class="flex items-center justify-center py-8"
                >
                    <div
                        class="h-6 w-6 animate-spin rounded-full border-2 border-primary-500 border-t-transparent"
                    />
                </div>

                <div
                    v-else-if="notifications.length === 0"
                    class="flex items-center justify-center py-8"
                >
                    <div class="text-center">
                        <component
                            :is="getIcon('Bell')"
                            class="mx-auto h-10 w-10 text-surface-300 dark:text-surface-600"
                        />
                        <p
                            class="mt-2 text-sm text-surface-500 dark:text-surface-400"
                        >
                            Tidak ada notifikasi
                        </p>
                    </div>
                </div>

                <div
                    v-else
                    class="divide-y divide-surface-200 dark:divide-surface-700"
                >
                    <div
                        v-for="notification in notifications.slice(0, 5)"
                        :key="notification.id"
                        @click="handleNotificationClick(notification)"
                        :class="[
                            'cursor-pointer px-3 py-3 transition-colors hover:bg-surface-50 dark:hover:bg-surface-800/50',
                            {
                                'bg-surface-50/50 dark:bg-surface-800/50':
                                    !notification.read_at,
                            },
                        ]"
                    >
                        <div class="flex items-start gap-3">
                            <div
                                :class="[
                                    'flex h-8 w-8 shrink-0 items-center justify-center rounded-lg',
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
                                        'h-4 w-4',
                                        !notification.read_at
                                            ? 'text-primary-600 dark:text-primary-400'
                                            : 'text-surface-500 dark:text-surface-400',
                                    ]"
                                />
                            </div>

                            <div class="min-w-0 flex-1">
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
                                            Status "{{
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
                                            Status "{{
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

                                <div
                                    v-if="
                                        notification.data.action ===
                                            'comment' &&
                                        notification.data.comment_preview
                                    "
                                    class="mt-1 line-clamp-1 text-xs text-surface-500 dark:text-surface-400"
                                >
                                    {{ notification.data.comment_preview }}
                                </div>

                                <div
                                    v-if="
                                        notification.data.action ===
                                        'status_changed'
                                    "
                                    class="mt-1 text-xs text-surface-500 dark:text-surface-400"
                                >
                                    {{ notification.data.old_status }} →
                                    {{ notification.data.new_status }}
                                </div>

                                <div
                                    v-if="
                                        notification.data.action ===
                                        'project_member_added'
                                    "
                                    class="mt-1 text-xs text-surface-500 dark:text-surface-400"
                                >
                                    Peran: {{ notification.data.role }} ·
                                    Ditambahkan oleh
                                    {{ notification.data.added_by_name }}
                                </div>

                                <p
                                    class="mt-1 text-[11px] text-surface-400 dark:text-surface-500"
                                >
                                    {{ formatTime(notification.created_at) }}
                                </p>
                            </div>

                            <div
                                v-if="!notification.read_at"
                                class="mt-2 flex h-2 w-2 shrink-0 rounded-full bg-primary-500"
                            />
                        </div>
                    </div>

                    <!-- View all link -->
                    <button
                        @click.prevent="goToNotifications"
                        class="w-full rounded-b-xl px-3 py-2 text-center text-sm text-primary-600 transition-colors hover:bg-primary-50 hover:text-primary-700 dark:hover:bg-primary-900/20"
                    >
                        Lihat semua notifikasi
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
