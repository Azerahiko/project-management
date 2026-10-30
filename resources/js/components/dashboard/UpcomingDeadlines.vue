<script setup lang="ts">
import { computed } from 'vue';
import { getIcon } from '@/components/sidebar/icons';
import EmptyState from '@/components/ui/EmptyState.vue';
import { router } from '@inertiajs/vue3';
import tasks from '@/routes/tasks';
import type { UpcomingDeadline, DeadlineUrgency } from '@/types';

interface Props {
    upcoming_deadlines: UpcomingDeadline[];
}

const props = defineProps<Props>();

const urgencyLabels: Record<DeadlineUrgency, string> = {
    overdue: 'Terlewat',
    critical: 'Kritis (< 24 jam)',
    warning: 'Peringatan (24–72 jam)',
    normal: 'Normal (> 72 jam)',
};

const urgencyColors: Record<
    DeadlineUrgency,
    { bg: string; text: string; icon: string }
> = {
    overdue: {
        bg: 'bg-accent-100 dark:bg-accent-900/30',
        text: 'text-accent-700 dark:text-accent-300',
        icon: 'AlertTriangle',
    },
    critical: {
        bg: 'bg-accent-100 dark:bg-accent-900/30',
        text: 'text-accent-700 dark:text-accent-300',
        icon: 'AlertTriangle',
    },
    warning: {
        bg: 'bg-accent-100 dark:bg-accent-900/30',
        text: 'text-accent-700 dark:text-accent-300',
        icon: 'Clock',
    },
    normal: {
        bg: 'bg-surface-100 dark:bg-surface-800',
        text: 'text-surface-700 dark:text-surface-300',
        icon: 'Calendar',
    },
};

function formatDate(dateString: string | null): string {
    if (!dateString) return '—';
    return new Date(dateString).toLocaleDateString('id-ID', {
        weekday: 'short',
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function formatTime(dateString: string | null): string {
    if (!dateString) return '';
    return new Date(dateString).toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
    });
}

function navigateToTask(taskId: number): void {
    router.visit(tasks.show.url(taskId));
}
</script>

<template>
    <section class="card" v-if="upcoming_deadlines.length > 0">
        <div
            class="border-b border-white/20 px-6 py-4 dark:border-surface-700/30"
        >
            <h2
                class="text-lg font-semibold text-surface-900 dark:text-surface-100"
            >
                Deadline Mendekat
            </h2>
        </div>
        <div class="space-y-3 p-6">
            <div
                v-for="task in upcoming_deadlines"
                :key="task.id"
                class="card-hover group cursor-pointer"
                @click="navigateToTask(task.id)"
            >
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-2">
                            <h3
                                class="truncate font-medium text-surface-900 dark:text-surface-100"
                            >
                                {{ task.title }}
                            </h3>
                            <span
                                class="ml-2 inline-flex flex-shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium"
                                :class="
                                    urgencyColors[task.deadline_urgency].bg +
                                    ' ' +
                                    urgencyColors[task.deadline_urgency].text
                                "
                            >
                                <component
                                    :is="
                                        getIcon(
                                            urgencyColors[task.deadline_urgency]
                                                .icon,
                                        )
                                    "
                                    class="h-3 w-3"
                                />
                                {{ urgencyLabels[task.deadline_urgency] }}
                            </span>
                        </div>
                        <p
                            v-if="task.project"
                            class="mt-1 truncate text-sm text-primary-600 dark:text-primary-400"
                        >
                            {{ task.project.name }}
                        </p>
                        <div
                            class="mt-2 flex items-center gap-4 text-sm text-surface-500 dark:text-surface-400"
                        >
                            <span class="flex items-center gap-1">
                                <component
                                    :is="getIcon('Calendar')"
                                    class="h-4 w-4"
                                />
                                {{ formatDate(task.due_date) }}
                            </span>
                            <span
                                v-if="task.due_date"
                                class="flex items-center gap-1"
                            >
                                <component
                                    :is="getIcon('Clock')"
                                    class="h-4 w-4"
                                />
                                {{ formatTime(task.due_date) }}
                            </span>
                        </div>
                    </div>
                    <component
                        :is="getIcon('ChevronRight')"
                        class="h-5 w-5 flex-shrink-0 text-surface-300 transition-colors group-hover:text-primary-600 dark:text-surface-600 dark:group-hover:text-primary-400"
                    />
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
                Deadline Mendekat
            </h2>
        </div>
        <EmptyState
            icon="Calendar"
            title="Tidak ada deadline mendekat"
            description="Semua tugas sudah selesai atau tidak memiliki deadline."
        />
    </section>
</template>
