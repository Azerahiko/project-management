<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { getIcon } from '@/components/sidebar/icons';

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
const projects = computed(() => (page.props.projects as ReportProject[]) ?? []);
const taskCounts = computed(() => (page.props.task_counts as TaskCounts) ?? {});

const statusLabel: Record<string, string> = {
    draft: 'Draft',
    active: 'Aktif',
    on_hold: 'Ditahan',
    completed: 'Selesai',
    archived: 'Arsip',
};

const projectStatusColors: Record<string, string> = {
    draft: 'bg-surface-100 text-surface-700 dark:bg-surface-800 dark:text-surface-300',
    active: 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300',
    on_hold:
        'bg-accent-100 text-accent-700 dark:bg-accent-900/30 dark:text-accent-300',
    completed:
        'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300',
    archived:
        'bg-surface-100 text-surface-500 dark:bg-surface-800 dark:text-surface-400',
};

const totalTasks = computed(() =>
    Object.values(taskCounts.value).reduce(
        (total, value) => total + (value ?? 0),
        0,
    ),
);

const totalCompleted = computed(() => taskCounts.value.done ?? 0);
const totalInProgress = computed(() => taskCounts.value.in_progress ?? 0);
const totalReview = computed(() => taskCounts.value.review ?? 0);

const completionRate = computed(() =>
    totalTasks.value > 0
        ? Math.round((totalCompleted.value / totalTasks.value) * 100)
        : 0,
);

const statusBreakdown = computed(() => {
    const total = totalTasks.value;
    const meta = [
        {
            key: 'todo',
            label: 'To Do',
            color: '#D6D3C8',
        },
        {
            key: 'in_progress',
            label: 'Sedang Dikerjakan',
            color: '#6EA980',
        },
        {
            key: 'review',
            label: 'Review',
            color: '#B7D9C1',
        },
        {
            key: 'done',
            label: 'Selesai',
            color: '#0F5132',
        },
    ] as const;

    return meta.map((item) => {
        const count = taskCounts.value[item.key] ?? 0;

        return {
            ...item,
            count,
            percent: total > 0 ? Math.round((count / total) * 100) : 0,
        };
    });
});

const donutGradient = computed(() => {
    const total = totalTasks.value;

    if (total === 0) {
        return 'conic-gradient(#eae6db 0% 100%)';
    }

    let cumulative = 0;
    const stops = statusBreakdown.value.map((status) => {
        const start = cumulative;
        cumulative += (status.count / total) * 100;

        return `${status.color} ${start}% ${cumulative}%`;
    });

    return `conic-gradient(${stops.join(', ')})`;
});

const projectStatusSummary = computed(() =>
    [
        { key: 'active', label: 'Aktif', dot: 'bg-primary-500' },
        { key: 'draft', label: 'Draft', dot: 'bg-surface-400' },
        { key: 'on_hold', label: 'Ditahan', dot: 'bg-accent-500' },
        { key: 'completed', label: 'Selesai', dot: 'bg-emerald-500' },
        { key: 'archived', label: 'Arsip', dot: 'bg-surface-300' },
    ].map((item) => ({
        ...item,
        count: projects.value.filter((project) => project.status === item.key)
            .length,
    })),
);

function projectProgress(project: ReportProject): number {
    if (project.tasks_count === 0) {
        return 0;
    }

    return Math.round(
        (project.completed_tasks_count / project.tasks_count) * 100,
    );
}

function progressBarClass(project: ReportProject): string {
    const progress = projectProgress(project);

    if (progress >= 100) {
        return 'bg-emerald-500';
    }

    if (progress >= 60) {
        return 'bg-primary-600';
    }

    if (progress >= 30) {
        return 'bg-accent-500';
    }

    return 'bg-surface-400';
}

function projectStatusClass(status: string): string {
    return projectStatusColors[status] ?? projectStatusColors.draft;
}
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
        >
            <div>
                <span
                    class="inline-flex h-6 items-center gap-1.5 rounded-full border border-primary-200 bg-primary-50 px-2.5 text-[10px] font-bold tracking-wider text-primary-700 uppercase dark:border-primary-700/40 dark:bg-primary-900/25 dark:text-primary-200"
                >
                    <component :is="getIcon('BarChart3')" class="h-3 w-3" />
                    Analitik & KPI
                </span>
                <h1
                    class="mt-2 text-2xl font-semibold tracking-tight text-surface-950 sm:text-3xl dark:text-surface-50"
                >
                    Laporan
                </h1>
                <p
                    class="mt-1 max-w-2xl text-sm leading-6 text-surface-700 dark:text-surface-200"
                >
                    Ringkasan progres proyek, distribusi status, dan kinerja tim
                    dalam satu tampilan.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span
                    class="inline-flex items-center gap-2 rounded-xl border border-surface-200 bg-white px-3 py-2 text-xs font-semibold text-surface-600 dark:border-surface-700 dark:bg-surface-900 dark:text-surface-300"
                >
                    <span class="h-1.5 w-1.5 rounded-full bg-primary-500" />
                    {{ projects.length }} proyek
                </span>
                <span
                    class="inline-flex items-center gap-2 rounded-xl border border-surface-200 bg-white px-3 py-2 text-xs font-semibold text-surface-600 dark:border-surface-700 dark:bg-surface-900 dark:text-surface-300"
                >
                    <span class="h-1.5 w-1.5 rounded-full bg-accent-500" />
                    {{ totalReview }} menunggu review
                </span>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="card flex items-start gap-4 !p-4">
                <span
                    class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-300"
                >
                    <component :is="getIcon('CheckSquare')" class="h-5 w-5" />
                </span>
                <div class="min-w-0">
                    <p
                        class="text-xs font-medium text-surface-500 dark:text-surface-400"
                    >
                        Total tugas
                    </p>
                    <p
                        class="mt-1 text-2xl font-bold text-surface-950 dark:text-surface-50"
                    >
                        {{ totalTasks }}
                    </p>
                    <p class="mt-0.5 text-[11px] text-surface-400">
                        dari {{ projects.length }} proyek
                    </p>
                </div>
            </div>

            <div class="card flex items-start gap-4 !p-4">
                <span
                    class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300"
                >
                    <component :is="getIcon('CheckCircle2')" class="h-5 w-5" />
                </span>
                <div class="min-w-0">
                    <p
                        class="text-xs font-medium text-surface-500 dark:text-surface-400"
                    >
                        Selesai
                    </p>
                    <p
                        class="mt-1 text-2xl font-bold text-primary-700 dark:text-primary-300"
                    >
                        {{ totalCompleted }}
                    </p>
                    <p class="mt-0.5 text-[11px] text-surface-400">
                        {{ completionRate }}% dari total tugas
                    </p>
                </div>
            </div>

            <div class="card flex items-start gap-4 !p-4">
                <span
                    class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-[#e7f0f9] text-[#28506f] dark:bg-sky-950/40 dark:text-sky-200"
                >
                    <component :is="getIcon('Clock')" class="h-5 w-5" />
                </span>
                <div class="min-w-0">
                    <p
                        class="text-xs font-medium text-surface-500 dark:text-surface-400"
                    >
                        Sedang dikerjakan
                    </p>
                    <p
                        class="mt-1 text-2xl font-bold text-surface-950 dark:text-surface-50"
                    >
                        {{ totalInProgress }}
                    </p>
                    <p class="mt-0.5 text-[11px] text-surface-400">
                        {{ totalReview }} tugas menunggu review
                    </p>
                </div>
            </div>

            <div class="card !p-4">
                <div class="flex items-start gap-4">
                    <span
                        class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-[#f7eed6] text-[#7a5a14] dark:bg-amber-950/40 dark:text-amber-200"
                    >
                        <component
                            :is="getIcon('TrendingUp')"
                            class="h-5 w-5"
                        />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p
                            class="text-xs font-medium text-surface-500 dark:text-surface-400"
                        >
                            Tingkat penyelesaian
                        </p>
                        <p
                            class="mt-1 text-2xl font-bold text-surface-950 dark:text-surface-50"
                        >
                            {{ completionRate }}%
                        </p>
                    </div>
                </div>
                <div
                    class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-surface-100 dark:bg-surface-800"
                >
                    <div
                        class="h-full rounded-full bg-primary-600 transition-all"
                        :style="{ width: `${completionRate}%` }"
                    />
                </div>
            </div>
        </div>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_360px]">
            <!-- Project performance -->
            <div
                class="overflow-hidden rounded-[24px] border border-surface-200 bg-white shadow-sm dark:border-surface-700 dark:bg-surface-900"
            >
                <div
                    class="flex flex-col gap-3 border-b border-surface-200 p-5 sm:flex-row sm:items-center sm:justify-between dark:border-surface-800"
                >
                    <div>
                        <h2
                            class="text-base font-semibold text-surface-950 dark:text-surface-50"
                        >
                            Kinerja proyek
                        </h2>
                        <p
                            class="mt-0.5 text-xs text-surface-500 dark:text-surface-400"
                        >
                            Perbandingan tugas selesai terhadap total tugas.
                        </p>
                    </div>
                    <span
                        class="inline-flex w-fit items-center gap-1.5 rounded-full bg-surface-100 px-2.5 py-1 text-[10px] font-bold text-surface-500 dark:bg-surface-800 dark:text-surface-300"
                    >
                        {{ projects.length }} proyek
                    </span>
                </div>

                <div v-if="projects.length > 0">
                    <div
                        v-for="project in projects"
                        :key="project.id"
                        class="border-b border-surface-100 p-4 transition-colors last:border-b-0 hover:bg-surface-50/70 sm:p-5 dark:border-surface-800 dark:hover:bg-surface-800/30"
                    >
                        <div class="flex items-start gap-3">
                            <span
                                class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300"
                            >
                                <component
                                    :is="getIcon('FolderKanban')"
                                    class="h-4 w-4"
                                />
                            </span>

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p
                                        class="truncate text-sm font-semibold text-surface-900 dark:text-surface-100"
                                    >
                                        {{ project.name }}
                                    </p>
                                    <span
                                        class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold"
                                        :class="
                                            projectStatusClass(project.status)
                                        "
                                    >
                                        {{
                                            statusLabel[project.status] ??
                                            project.status
                                        }}
                                    </span>
                                </div>

                                <div class="mt-3 flex items-center gap-3">
                                    <div
                                        class="h-2 flex-1 overflow-hidden rounded-full bg-surface-100 dark:bg-surface-800"
                                    >
                                        <div
                                            class="h-full rounded-full transition-all"
                                            :class="progressBarClass(project)"
                                            :style="{
                                                width: `${projectProgress(project)}%`,
                                            }"
                                        />
                                    </div>
                                    <span
                                        class="w-9 text-right text-xs font-bold text-surface-700 dark:text-surface-200"
                                    >
                                        {{ projectProgress(project) }}%
                                    </span>
                                </div>
                            </div>

                            <div class="shrink-0 text-right">
                                <p
                                    class="text-sm font-bold text-surface-900 dark:text-surface-100"
                                >
                                    {{ project.completed_tasks_count }}/{{
                                        project.tasks_count
                                    }}
                                </p>
                                <p
                                    class="text-[10px] text-surface-400 dark:text-surface-500"
                                >
                                    tugas selesai
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="p-10 text-center">
                    <component
                        :is="getIcon('FolderKanban')"
                        class="mx-auto h-8 w-8 text-surface-300 dark:text-surface-600"
                    />
                    <p
                        class="mt-3 text-sm font-semibold text-surface-800 dark:text-surface-100"
                    >
                        Belum ada data laporan proyek
                    </p>
                    <p
                        class="mt-1 text-xs text-surface-500 dark:text-surface-400"
                    >
                        Tambahkan proyek dan tugas untuk melihat kinerjanya di
                        sini.
                    </p>
                </div>
            </div>

            <!-- Side summaries -->
            <div class="space-y-5">
                <div class="card">
                    <div>
                        <h2
                            class="text-base font-semibold text-surface-950 dark:text-surface-50"
                        >
                            Distribusi status
                        </h2>
                        <p
                            class="mt-0.5 text-xs text-surface-500 dark:text-surface-400"
                        >
                            Sebaran seluruh tugas berdasarkan status.
                        </p>
                    </div>

                    <div class="mt-5 flex flex-col items-center gap-5">
                        <div
                            class="relative h-40 w-40 rounded-full"
                            :style="{ background: donutGradient }"
                            role="img"
                            :aria-label="`Total ${totalTasks} tugas`"
                        >
                            <div
                                class="absolute inset-[18px] grid place-items-center rounded-full bg-white text-center dark:bg-surface-900"
                            >
                                <div>
                                    <p
                                        class="text-3xl font-bold text-surface-950 dark:text-surface-50"
                                    >
                                        {{ totalTasks }}
                                    </p>
                                    <p
                                        class="text-[10px] font-semibold tracking-wide text-surface-400 uppercase"
                                    >
                                        total tugas
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="w-full space-y-2.5">
                            <div
                                v-for="status in statusBreakdown"
                                :key="status.key"
                                class="flex items-center gap-2"
                            >
                                <span
                                    class="h-2.5 w-2.5 shrink-0 rounded-full"
                                    :style="{
                                        backgroundColor: status.color,
                                    }"
                                />
                                <span
                                    class="min-w-0 flex-1 truncate text-xs font-medium text-surface-600 dark:text-surface-300"
                                >
                                    {{ status.label }}
                                </span>
                                <span
                                    class="text-xs font-bold text-surface-900 dark:text-surface-100"
                                >
                                    {{ status.count }}
                                </span>
                                <span
                                    class="w-9 text-right text-[10px] font-medium text-surface-400"
                                >
                                    {{ status.percent }}%
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <h2
                        class="text-base font-semibold text-surface-950 dark:text-surface-50"
                    >
                        Ringkasan proyek
                    </h2>
                    <div class="mt-4 space-y-3">
                        <div
                            v-for="item in projectStatusSummary"
                            :key="item.key"
                            class="flex items-center gap-2.5"
                        >
                            <span
                                class="h-2 w-2 shrink-0 rounded-full"
                                :class="item.dot"
                            />
                            <span
                                class="flex-1 text-xs font-medium text-surface-600 dark:text-surface-300"
                            >
                                {{ item.label }}
                            </span>
                            <span
                                class="text-xs font-bold text-surface-900 dark:text-surface-100"
                            >
                                {{ item.count }}
                            </span>
                        </div>
                    </div>
                    <p
                        class="mt-4 border-t border-surface-100 pt-3 text-[11px] leading-5 text-surface-400 dark:border-surface-800 dark:text-surface-500"
                    >
                        Data dihitung dari seluruh proyek yang dapat Anda akses.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
