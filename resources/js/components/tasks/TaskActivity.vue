<script setup lang="ts">
import { ref } from 'vue';
import { getIcon } from '@/components/sidebar/icons';
import Avatar from '@/components/ui/Avatar.vue';
import type { Task, TaskActivity } from '@/types';

defineProps<{
    task: Task;
    activities: TaskActivity[];
    loading?: boolean;
}>();

const expandedActivityId = ref<number | null>(null);

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
        return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400';
    if (type === 'task.deleted')
        return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400';
    return 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400';
}

function toggleExpand(activity: TaskActivity): void {
    expandedActivityId.value =
        expandedActivityId.value === activity.id ? null : activity.id;
}
</script>

<template>
    <section class="card">
        <h3
            class="mb-4 text-lg font-semibold text-surface-900 dark:text-surface-100"
        >
            Riwayat Aktivitas
        </h3>

        <div v-if="loading" class="space-y-3">
            <div
                v-for="i in 3"
                :key="i"
                class="h-16 animate-pulse rounded-lg bg-surface-100 dark:bg-surface-800"
            />
        </div>

        <div v-else-if="activities.length === 0" class="py-8 text-center">
            <component
                :is="getIcon('Activity')"
                class="mx-auto h-12 w-12 text-surface-300 dark:text-surface-600"
            />
            <p class="mt-3 text-surface-500 dark:text-surface-400">
                Belum ada aktivitas
            </p>
        </div>

        <div v-else class="space-y-3">
            <div
                v-for="activity in activities"
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
                                class="mt-1 text-sm text-surface-700 dark:text-surface-300"
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
                                    class="text-red-600 line-through dark:text-red-400"
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
                                    class="font-medium text-green-600 dark:text-green-400"
                                >
                                    {{ activity.new_value }}
                                </span>
                            </div>

                            <div
                                v-if="
                                    activity.activity_type ===
                                        'comment.deleted' && activity.old_value
                                "
                                class="mt-2 text-sm text-surface-600 italic dark:text-surface-400"
                            >
                                "Komentar dihapus:
                                {{ activity.old_value.substring(0, 100)
                                }}{{
                                    activity.old_value.length > 100
                                        ? '...'
                                        : ''
                                }}"
                            </div>

                            <button
                                v-if="
                                    activity.field ||
                                    activity.old_value ||
                                    activity.new_value
                                "
                                class="mt-2 text-xs text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300"
                                @click="toggleExpand(activity)"
                            >
                                {{
                                    expandedActivityId === activity.id
                                        ? 'Tutup detail'
                                        : 'Lihat detail'
                                }}
                            </button>

                            <div
                                v-if="expandedActivityId === activity.id"
                                class="mt-2 space-y-1 rounded-lg bg-surface-50 p-3 text-xs dark:bg-surface-800/50"
                            >
                                <div class="flex gap-2">
                                    <span
                                        class="w-24 font-medium text-surface-500 dark:text-surface-400"
                                        >Type:</span
                                    >
                                    <span
                                        class="font-mono text-surface-700 dark:text-surface-300"
                                        >{{ activity.activity_type }}</span
                                    >
                                </div>
                                <div v-if="activity.field" class="flex gap-2">
                                    <span
                                        class="w-24 font-medium text-surface-500 dark:text-surface-400"
                                        >Field:</span
                                    >
                                    <span
                                        class="font-mono text-surface-700 dark:text-surface-300"
                                        >{{ activity.field }}</span
                                    >
                                </div>
                                <div
                                    v-if="activity.old_value"
                                    class="flex gap-2"
                                >
                                    <span
                                        class="w-24 font-medium text-surface-500 dark:text-surface-400"
                                        >Old:</span
                                    >
                                    <span
                                        class="font-mono text-surface-700 dark:text-surface-300"
                                        >{{ activity.old_value }}</span
                                    >
                                </div>
                                <div
                                    v-if="activity.new_value"
                                    class="flex gap-2"
                                >
                                    <span
                                        class="w-24 font-medium text-surface-500 dark:text-surface-400"
                                        >New:</span
                                    >
                                    <span
                                        class="font-mono text-surface-700 dark:text-surface-300"
                                        >{{ activity.new_value }}</span
                                    >
                                </div>
                                <div class="flex gap-2">
                                    <span
                                        class="w-24 font-medium text-surface-500 dark:text-surface-400"
                                        >User:</span
                                    >
                                    <span
                                        class="font-mono text-surface-700 dark:text-surface-300"
                                        >{{
                                            activity.user?.name || 'Sistem'
                                        }}</span
                                    >
                                </div>
                                <div class="flex gap-2">
                                    <span
                                        class="w-24 font-medium text-surface-500 dark:text-surface-400"
                                        >Time:</span
                                    >
                                    <span
                                        class="font-mono text-surface-700 dark:text-surface-300"
                                        >{{
                                            formatFullDateTime(
                                                activity.created_at,
                                            )
                                        }}</span
                                    >
                                </div>
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
</template>
