<script setup lang="ts">
import { computed } from 'vue';
import { getIcon } from '@/components/sidebar/icons';
import EmptyState from '@/components/ui/EmptyState.vue';
import type { TaskStatus } from '@/types';

interface Props {
    tasks_by_status: Partial<Record<TaskStatus, number>>;
}

const props = defineProps<Props>();

const statusOrder: TaskStatus[] = ['todo', 'in_progress', 'review', 'done'];

const statusLabels: Record<TaskStatus, string> = {
    todo: 'To Do',
    in_progress: 'Sedang Dikerjakan',
    review: 'Review',
    done: 'Selesai',
};

const statusColors: Record<
    TaskStatus,
    { bg: string; text: string; border: string }
> = {
    todo: {
        bg: 'bg-surface-100 dark:bg-surface-800',
        text: 'text-surface-700 dark:text-surface-300',
        border: 'border-surface-200 dark:border-surface-700',
    },
    in_progress: {
        bg: 'bg-primary-100 dark:bg-primary-900/30',
        text: 'text-primary-700 dark:text-primary-400',
        border: 'border-primary-200 dark:border-primary-800',
    },
    review: {
        bg: 'bg-accent-100 dark:bg-accent-900/30',
        text: 'text-accent-700 dark:text-accent-300',
        border: 'border-accent-200 dark:border-accent-800',
    },
    done: {
        bg: 'bg-primary-100 dark:bg-primary-900/30',
        text: 'text-primary-700 dark:text-primary-400',
        border: 'border-primary-200 dark:border-primary-800',
    },
};

const statusIcons: Record<TaskStatus, string> = {
    todo: 'Square',
    in_progress: 'RotateCcw',
    review: 'Eye',
    done: 'CheckCircle',
};

const totalTasks = computed(() =>
    Object.values(props.tasks_by_status).reduce(
        (sum, count) => sum + (count || 0),
        0,
    ),
);

const chartData = computed(() =>
    statusOrder.map((status) => ({
        status,
        count: props.tasks_by_status[status] || 0,
        percentage:
            totalTasks.value > 0
                ? Math.round(
                      ((props.tasks_by_status[status] || 0) /
                          totalTasks.value) *
                          100,
                  )
                : 0,
    })),
);
</script>

<template>
    <section class="card" v-if="totalTasks > 0">
        <div
            class="border-b border-white/20 px-6 py-4 dark:border-surface-700/30"
        >
            <h2
                class="text-lg font-semibold text-surface-900 dark:text-surface-100"
            >
                Tugas Berdasarkan Status
            </h2>
        </div>
        <div class="space-y-4 p-6">
            <!-- Stacked horizontal bar chart -->
            <div
                class="h-8 w-full overflow-hidden rounded-lg bg-surface-100 dark:bg-surface-800"
            >
                <div
                    class="flex h-full"
                    role="img"
                    :aria-label="`Distribusi tugas: ${totalTasks} total`"
                >
                    <div
                        v-for="item in chartData"
                        :key="item.status"
                        class="relative flex items-center justify-center transition-all duration-500"
                        :style="{
                            width:
                                item.percentage > 0
                                    ? `${item.percentage}%`
                                    : '0%',
                            minWidth: item.percentage > 0 ? '24px' : '0',
                        }"
                    >
                        <div
                            v-if="item.percentage > 10"
                            :class="[
                                'absolute inset-0 h-full',
                                statusColors[item.status].bg,
                            ]"
                        />
                        <span
                            v-if="item.percentage > 10"
                            class="relative text-xs font-medium"
                            :class="statusColors[item.status].text"
                        >
                            {{ item.percentage }}%
                        </span>
                    </div>
                </div>
            </div>

            <!-- Legend & counts -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div
                    v-for="item in chartData"
                    :key="item.status"
                    class="flex items-center gap-3 rounded-lg p-3"
                    :class="
                        statusColors[item.status].bg +
                        ' ' +
                        statusColors[item.status].border
                    "
                >
                    <div
                        class="flex h-8 w-8 items-center justify-center rounded-lg"
                        :class="
                            statusColors[item.status].bg
                                .replace('100', '200')
                                .replace('900/30', '900/50')
                        "
                    >
                        <component
                            :is="getIcon(statusIcons[item.status])"
                            class="h-4 w-4"
                            :class="statusColors[item.status].text"
                        />
                    </div>
                    <div class="min-w-0 flex-1">
                        <p
                            class="truncate text-sm font-medium"
                            :class="statusColors[item.status].text"
                        >
                            {{ statusLabels[item.status] }}
                        </p>
                        <p
                            class="text-xs font-semibold"
                            :class="statusColors[item.status].text"
                        >
                            {{ item.count }}
                        </p>
                    </div>
                </div>
            </div>

            <p
                class="text-center text-sm text-surface-500 dark:text-surface-400"
            >
                Total: {{ totalTasks }} tugas
            </p>
        </div>
    </section>

    <section v-else class="card">
        <div
            class="border-b border-white/20 px-6 py-4 dark:border-surface-700/30"
        >
            <h2
                class="text-lg font-semibold text-surface-900 dark:text-surface-100"
            >
                Tugas Berdasarkan Status
            </h2>
        </div>
        <EmptyState
            icon="CheckSquare"
            title="Belum ada tugas"
            description="Tidak ada tugas yang perlu ditampilkan."
        />
    </section>
</template>
