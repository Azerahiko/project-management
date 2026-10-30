<script setup lang="ts">
import { getIcon } from '@/components/sidebar/icons';
import EmptyState from '@/components/ui/EmptyState.vue';
import Avatar from '@/components/ui/Avatar.vue';
import { router } from '@inertiajs/vue3';
import type { RecentActivity } from '@/types';

interface Props {
    recent_activities: RecentActivity[];
}

const props = defineProps<Props>();

const activityIcons: Record<string, string> = {
    'task.created': 'PlusCircle',
    'task.updated': 'Edit',
    'task.deleted': 'Trash2',
    'comment.created': 'MessageCircle',
    'comment.updated': 'Edit',
    'comment.deleted': 'Trash2',
};

const activityLabels: Record<string, string> = {
    'task.created': 'Tugas dibuat',
    'task.updated': 'Tugas diperbarui',
    'task.deleted': 'Tugas dihapus',
    'comment.created': 'Komentar ditambahkan',
    'comment.updated': 'Komentar diedit',
    'comment.deleted': 'Komentar dihapus',
};

const fieldLabels: Record<string, string> = {
    status: 'Status',
    priority: 'Prioritas',
    assignee_id: 'Penugasan',
    due_date: 'Deadline',
    start_date: 'Tanggal Mulai',
    content: 'Konten',
};

function formatRelativeTime(dateString: string): string {
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
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function formatFullDateTime(dateString: string): string {
    return new Date(dateString).toLocaleString('id-ID', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function getActivityIcon(type: string): string {
    return activityIcons[type] || 'Activity';
}

function getActivityLabel(type: string): string {
    return activityLabels[type] || type;
}

function getFieldLabel(field: string | null): string {
    if (!field) return '';
    return fieldLabels[field] || field;
}

function getActivityColor(type: string): string {
    if (type.startsWith('comment.'))
        return 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400';
    if (type === 'task.deleted')
        return 'bg-accent-100 text-accent-700 dark:bg-accent-900/30 dark:text-accent-300';
    return 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400';
}

function navigateToTask(task: RecentActivity['task']): void {
    if (task?.url) {
        router.visit(task.url);
    }
}
</script>

<template>
    <section class="card" v-if="recent_activities.length > 0">
        <div
            class="border-b border-white/20 px-6 py-4 dark:border-surface-700/30"
        >
            <h2
                class="text-lg font-semibold text-surface-900 dark:text-surface-100"
            >
                Aktivitas Terbaru
            </h2>
        </div>
        <div class="space-y-4 p-6">
            <div
                v-for="activity in recent_activities"
                :key="activity.id"
                class="group relative pl-10"
            >
                <!-- Timeline line -->
                <div
                    class="absolute top-0 bottom-0 left-3 w-0.5 bg-surface-200 dark:bg-surface-700"
                />
                <div
                    class="absolute top-3 left-0 h-2 w-2 rounded-full border-2 border-surface-100 dark:border-surface-900"
                    :class="getActivityColor(activity.activity_type)"
                />

                <div class="py-2">
                    <div class="flex items-start gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span
                                    class="inline-flex items-center rounded px-2 py-0.5 text-xs font-medium"
                                    :class="
                                        getActivityColor(activity.activity_type)
                                    "
                                >
                                    <component
                                        :is="
                                            getIcon(
                                                getActivityIcon(
                                                    activity.activity_type,
                                                ),
                                            )
                                        "
                                        class="mr-1 h-3 w-3"
                                    />
                                    {{
                                        getActivityLabel(activity.activity_type)
                                    }}
                                </span>
                                <time
                                    class="text-xs whitespace-nowrap text-surface-500 dark:text-surface-400"
                                    :title="
                                        formatFullDateTime(activity.created_at)
                                    "
                                >
                                    {{
                                        formatRelativeTime(activity.created_at)
                                    }}
                                </time>
                            </div>

                            <p
                                class="mt-1 cursor-pointer text-sm text-surface-700 dark:text-surface-300"
                                @click="
                                    activity.task &&
                                    navigateToTask(activity.task)
                                "
                            >
                                {{ activity.description }}
                            </p>

                            <div
                                v-if="
                                    activity.field &&
                                    (activity.old_value || activity.new_value)
                                "
                                class="mt-2 flex items-center gap-2 text-xs text-surface-500 dark:text-surface-400"
                            >
                                <span
                                    class="font-medium text-surface-700 dark:text-surface-300"
                                >
                                    {{ getFieldLabel(activity.field) }}:
                                </span>
                                <span
                                    v-if="activity.old_value"
                                    class="text-accent-700 line-through dark:text-accent-300"
                                >
                                    {{ activity.old_value }}
                                </span>
                                <component
                                    v-if="
                                        activity.old_value && activity.new_value
                                    "
                                    :is="getIcon('ArrowRight')"
                                    class="h-3 w-3"
                                />
                                <span
                                    v-if="activity.new_value"
                                    class="font-medium text-primary-600 dark:text-primary-400"
                                >
                                    {{ activity.new_value }}
                                </span>
                            </div>
                        </div>

                        <div class="ml-4 flex-shrink-0">
                            <Avatar
                                v-if="activity.user"
                                :initials="activity.user.initials"
                                :src="activity.user.avatar"
                                size="sm"
                            />
                            <div
                                v-else
                                class="flex h-8 w-8 items-center justify-center rounded-full bg-surface-200 dark:bg-surface-700"
                            >
                                <component
                                    :is="getIcon('Cpu')"
                                    class="h-4 w-4 text-surface-500"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section v-else class="card">
        <div
            class="border-b border-white/20 px-6 py-4 dark:border-surface-700/30"
        >
            <h2
                class="text-lg font-semibold text-surface-900 dark:text-surface-100"
            >
                Aktivitas Terbaru
            </h2>
        </div>
        <EmptyState
            icon="Activity"
            title="Belum ada aktivitas terbaru"
            description="Aktivitas akan muncul di sini saat ada perubahan pada tugas."
        />
    </section>
</template>
