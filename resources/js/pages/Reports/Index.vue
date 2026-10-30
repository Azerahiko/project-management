<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Table from '@/components/ui/Table.vue';

defineOptions({
    layout: AppLayout,
});

interface ReportProject {
    id: number;
    name: string;
    status: string;
    tasks_count: number;
    completed_tasks_count: number;
}

interface TaskCounts {
    todo?: number;
    in_progress?: number;
    review?: number;
    done?: number;
}

const page = usePage();
const projects = (page.props.projects as ReportProject[]) ?? [];
const taskCounts = (page.props.task_counts as TaskCounts) ?? {};

const statusLabel: Record<string, string> = {
    draft: 'Draft',
    active: 'Aktif',
    on_hold: 'Ditahan',
    completed: 'Selesai',
    archived: 'Arsip',
};

const totalTasks = computed(() =>
    Object.values(taskCounts).reduce((sum, value) => sum + (value ?? 0), 0),
);
const totalCompleted = computed(() => taskCounts.done ?? 0);

const statusColors: Record<string, string> = {
    draft: 'bg-surface-100 text-surface-700 dark:bg-surface-800 dark:text-surface-300',
    active: 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300',
    on_hold:
        'bg-accent-100 text-accent-700 dark:bg-accent-900/30 dark:text-accent-400',
    completed:
        'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400',
    archived:
        'bg-surface-100 text-surface-500 dark:bg-surface-800 dark:text-surface-400',
};

const columns = [
    { key: 'name', label: 'Proyek', class: 'min-w-[200px]' },
    { key: 'status', label: 'Status', class: 'w-44' },
    { key: 'total', label: 'Total Tugas', class: 'w-32' },
    { key: 'completed', label: 'Selesai', class: 'w-32' },
    { key: 'progress', label: 'Progres', class: 'w-48' },
];
</script>

<template>
    <div class="space-y-6">
        <div>
            <h1
                class="text-2xl font-semibold tracking-tight text-surface-950 dark:text-surface-50"
            >
                Laporan
            </h1>
            <p
                class="mt-1 text-sm leading-6 text-surface-700 dark:text-surface-200"
            >
                Ringkasan progres proyek dan tugas
            </p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="card p-4">
                <p
                    class="text-xs font-medium text-surface-500 uppercase dark:text-surface-400"
                >
                    Total Tugas
                </p>
                <p
                    class="mt-1 text-2xl font-bold text-surface-900 dark:text-surface-100"
                >
                    {{ totalTasks }}
                </p>
            </div>
            <div class="card p-4">
                <p
                    class="text-xs font-medium text-surface-500 uppercase dark:text-surface-400"
                >
                    Selesai
                </p>
                <p
                    class="mt-1 text-2xl font-bold text-primary-700 dark:text-primary-300"
                >
                    {{ totalCompleted }}
                </p>
            </div>
            <div class="card p-4">
                <p
                    class="text-xs font-medium text-surface-500 uppercase dark:text-surface-400"
                >
                    To Do
                </p>
                <p
                    class="mt-1 text-2xl font-bold text-surface-700 dark:text-surface-200"
                >
                    {{ taskCounts.todo ?? 0 }}
                </p>
            </div>
            <div class="card p-4">
                <p
                    class="text-xs font-medium text-surface-500 uppercase dark:text-surface-400"
                >
                    Sedang Dikerjakan
                </p>
                <p
                    class="mt-1 text-2xl font-bold text-primary-700 dark:text-primary-300"
                >
                    {{ taskCounts.in_progress ?? 0 }}
                </p>
            </div>
        </div>

        <Table
            :columns="columns"
            :data="projects"
            rowKey="id"
            emptyMessage="Belum ada data laporan proyek."
        >
            <template #name="{ row }">
                <span
                    class="font-medium text-surface-700 dark:text-surface-200"
                >
                    {{ row.name }}
                </span>
            </template>
            <template #status="{ row }">
                <span
                    :class="[
                        'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium',
                        statusColors[row.status],
                    ]"
                >
                    {{ statusLabel[row.status] ?? row.status }}
                </span>
            </template>
            <template #total="{ row }">
                <span class="text-surface-700 dark:text-surface-200">
                    {{ row.tasks_count }}
                </span>
            </template>
            <template #completed="{ row }">
                <span class="text-surface-700 dark:text-surface-200">
                    {{ row.completed_tasks_count }}
                </span>
            </template>
            <template #progress="{ row }">
                <div class="w-full">
                    <span
                        class="mb-1 flex justify-between text-xs text-surface-600 dark:text-surface-300"
                    >
                        <span>{{
                            row.tasks_count === 0
                                ? '0%'
                                : Math.round(
                                      (row.completed_tasks_count /
                                          row.tasks_count) *
                                          100,
                                  ) + '%'
                        }}</span>
                    </span>
                    <div
                        class="h-2 w-full overflow-hidden rounded-full bg-surface-200 dark:bg-surface-700"
                    >
                        <div
                            class="h-full bg-primary-600"
                            :style="{
                                width:
                                    row.tasks_count === 0
                                        ? '0%'
                                        : Math.round(
                                              (row.completed_tasks_count /
                                                  row.tasks_count) *
                                                  100,
                                          ) + '%',
                            }"
                        />
                    </div>
                </div>
            </template>
        </Table>
    </div>
</template>
