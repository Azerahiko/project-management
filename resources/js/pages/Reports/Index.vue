<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { getIcon } from '@/components/sidebar/icons';
import projectsRoute from '@/routes/projects';

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
const canViewProjects = computed(() => Boolean(page.props.can_view_projects));

// Search, status filter, and sorting for projects list
const projectSearch = ref('');
const selectedStatusFilter = ref<string>('all');
const sortBy = ref<'progress' | 'tasks' | 'name'>('progress');

const statusLabels: Record<string, string> = {
    draft: 'Draft',
    active: 'Aktif',
    on_hold: 'Ditahan',
    completed: 'Selesai',
    archived: 'Arsip',
};

const projectStatusBadgeClasses: Record<string, string> = {
    active: 'bg-primary-50 text-primary-800 border-primary-200/80 dark:bg-primary-950/40 dark:text-primary-300 dark:border-primary-800/40',
    completed:
        'bg-emerald-50 text-emerald-800 border-emerald-200/80 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800/40',
    on_hold:
        'bg-amber-50 text-amber-800 border-amber-200/80 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800/40',
    draft: 'bg-surface-100 text-surface-700 border-surface-200 dark:bg-surface-800 dark:text-surface-300 dark:border-surface-700',
    archived:
        'bg-surface-100 text-surface-500 border-surface-200 dark:bg-surface-800 dark:text-surface-400 dark:border-surface-700',
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
const totalTodo = computed(() => taskCounts.value.todo ?? 0);

const activeProjectsCount = computed(
    () =>
        projects.value.filter((project) => project.status === 'active').length,
);

const completionRate = computed(() =>
    totalTasks.value > 0
        ? Math.round((totalCompleted.value / totalTasks.value) * 100)
        : 0,
);

function projectProgress(project: ReportProject): number {
    if (project.tasks_count === 0) {
        return 0;
    }

    return Math.round(
        (project.completed_tasks_count / project.tasks_count) * 100,
    );
}

const averageProjectProgress = computed(() => {
    const withTasks = projects.value.filter((p) => p.tasks_count > 0);
    if (withTasks.length === 0) {
        return 0;
    }

    return Math.round(
        withTasks.reduce(
            (total, project) => total + projectProgress(project),
            0,
        ) / withTasks.length,
    );
});

// 4 Cohesive Calm System KPI Cards
const statCards = computed(() => [
    {
        label: 'Total Tugas',
        value: totalTasks.value,
        subtext: `Dari ${projects.value.length} proyek terdaftar`,
        icon: 'CheckSquare',
        badge: `${totalTodo.value} antrean`,
        iconBg: 'bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-300',
        progress: undefined,
    },
    {
        label: 'Tugas Selesai',
        value: totalCompleted.value,
        subtext: `${completionRate.value}% dari seluruh tugas ruang kerja`,
        icon: 'CheckCircle2',
        badge: `${completionRate.value}% tuntas`,
        iconBg: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300',
        progress: completionRate.value,
    },
    {
        label: 'Sedang Berjalan',
        value: totalInProgress.value,
        subtext: 'Dalam pengerjaan aktif oleh tim',
        icon: 'Clock',
        badge: 'Aktif',
        iconBg: 'bg-primary-50 text-primary-800 dark:bg-primary-950/50 dark:text-primary-300',
        progress: undefined,
    },
    {
        label: 'Menunggu Review',
        value: totalReview.value,
        subtext:
            totalReview.value > 0
                ? 'Perlu verifikasi & peninjauan hasil'
                : 'Semua tugas telah ditinjau',
        icon: 'Eye',
        badge: totalReview.value > 0 ? 'Perlu respon' : 'Selesai',
        iconBg: 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300',
        progress: undefined,
    },
]);

// Status Breakdown for Tasks
const statusBreakdown = computed(() => {
    const total = totalTasks.value;
    const stages = [
        {
            key: 'todo',
            label: 'Belum Mulai (To Do)',
            color: '#D6D3C8',
            bgClass: 'bg-[#D6D3C8]',
        },
        {
            key: 'in_progress',
            label: 'Sedang Berjalan',
            color: '#88C199',
            bgClass: 'bg-[#88C199]',
        },
        {
            key: 'review',
            label: 'Peninjauan (Review)',
            color: '#B7D9C1',
            bgClass: 'bg-[#B7D9C1]',
        },
        {
            key: 'done',
            label: 'Selesai (Done)',
            color: '#0F5132',
            bgClass: 'bg-[#0F5132]',
        },
    ] as const;

    return stages.map((item) => {
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

// Project Status Summary breakdown
const projectStatusSummary = computed(() =>
    [
        {
            key: 'active',
            label: 'Aktif',
            dot: 'bg-primary-600 dark:bg-primary-400',
            bar: 'bg-primary-600 dark:bg-primary-500',
        },
        {
            key: 'completed',
            label: 'Selesai',
            dot: 'bg-emerald-600 dark:bg-emerald-400',
            bar: 'bg-emerald-600 dark:bg-emerald-500',
        },
        {
            key: 'on_hold',
            label: 'Ditahan',
            dot: 'bg-amber-500',
            bar: 'bg-amber-500',
        },
        {
            key: 'draft',
            label: 'Draft',
            dot: 'bg-surface-400',
            bar: 'bg-surface-400',
        },
        {
            key: 'archived',
            label: 'Arsip',
            dot: 'bg-surface-300 dark:bg-surface-600',
            bar: 'bg-surface-300 dark:bg-surface-600',
        },
    ].map((item) => {
        const count = projects.value.filter(
            (project) => project.status === item.key,
        ).length;

        return {
            ...item,
            count,
            percent:
                projects.value.length > 0
                    ? Math.round((count / projects.value.length) * 100)
                    : 0,
        };
    }),
);

// Filtered and sorted projects
const filteredProjects = computed(() => {
    let result = [...projects.value];

    // Status filter
    if (selectedStatusFilter.value !== 'all') {
        if (selectedStatusFilter.value === 'other') {
            result = result.filter(
                (p) => !['active', 'completed'].includes(p.status),
            );
        } else {
            result = result.filter(
                (p) => p.status === selectedStatusFilter.value,
            );
        }
    }

    // Search query
    const query = projectSearch.value.trim().toLowerCase();
    if (query) {
        result = result.filter((p) => p.name.toLowerCase().includes(query));
    }

    // Sorting
    result.sort((first, second) => {
        if (sortBy.value === 'progress') {
            return (
                projectProgress(second) - projectProgress(first) ||
                second.tasks_count - first.tasks_count
            );
        }
        if (sortBy.value === 'tasks') {
            return (
                second.tasks_count - first.tasks_count ||
                projectProgress(second) - projectProgress(first)
            );
        }
        return first.name.localeCompare(second.name);
    });

    return result;
});
</script>

<template>
    <div class="space-y-6 pb-12">
        <!-- 1. Page Header with Calm System Aesthetics -->
        <header
            class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <span
                    class="inline-flex h-6 items-center gap-1.5 rounded-full border border-primary-200/80 bg-primary-50 px-2.5 text-[10px] font-bold tracking-wider text-primary-800 uppercase dark:border-primary-800/40 dark:bg-primary-950/40 dark:text-primary-300"
                >
                    <component :is="getIcon('BarChart3')" class="h-3 w-3" />
                    Analitik & KPI Ruang Kerja
                </span>
                <h1
                    class="mt-2 text-2xl font-semibold tracking-tight text-surface-950 sm:text-3xl dark:text-surface-50"
                >
                    Laporan Kinerja Proyek
                </h1>
                <p
                    class="mt-1 max-w-2xl text-sm leading-relaxed text-surface-600 dark:text-surface-300"
                >
                    Pantau kemajuan pengerjaan proyek, alokasi tugas, dan
                    tingkat penyelesaian tim dalam satu tempat.
                </p>
            </div>

            <!-- Action Button / Summary -->
            <div class="flex items-center gap-3">
                <Link
                    v-if="canViewProjects"
                    :href="projectsRoute.index.url()"
                    class="btn btn-secondary text-xs shadow-2xs"
                >
                    <component :is="getIcon('FolderKanban')" class="h-4 w-4" />
                    <span>Daftar Semua Proyek</span>
                </Link>
            </div>
        </header>

        <!-- 2. Row of 4 Metric Cards -->
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div
                v-for="stat in statCards"
                :key="stat.label"
                class="rounded-3xl border border-surface-200/80 bg-white p-5 shadow-xs transition hover:border-surface-300 dark:border-surface-800 dark:bg-surface-900 dark:hover:border-surface-700"
            >
                <div class="flex items-start justify-between gap-3">
                    <p
                        class="text-xs font-semibold tracking-wide text-surface-500 uppercase dark:text-surface-400"
                    >
                        {{ stat.label }}
                    </p>
                    <span
                        class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl"
                        :class="stat.iconBg"
                    >
                        <component :is="getIcon(stat.icon)" class="h-5 w-5" />
                    </span>
                </div>

                <div class="mt-2 flex items-baseline gap-2">
                    <p
                        class="font-serif text-3xl font-normal text-surface-950 sm:text-4xl dark:text-surface-50"
                    >
                        {{ stat.value }}
                    </p>
                    <span
                        class="rounded-full bg-surface-100 px-2 py-0.5 text-[10px] font-semibold text-surface-600 dark:bg-surface-800 dark:text-surface-300"
                    >
                        {{ stat.badge }}
                    </span>
                </div>

                <p class="mt-1 text-xs text-surface-500 dark:text-surface-400">
                    {{ stat.subtext }}
                </p>

                <!-- Subtle progress indicator if available -->
                <div
                    v-if="stat.progress !== undefined"
                    class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-surface-100 dark:bg-surface-800"
                >
                    <div
                        class="h-full rounded-full bg-[#0F5132] transition-all duration-700 dark:bg-primary-400"
                        :style="{ width: `${stat.progress}%` }"
                    />
                </div>
            </div>
        </div>

        <!-- 3. Main Content: Project List (Left) + Analytical Summary (Right) -->
        <div
            class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px] xl:items-start"
        >
            <!-- Left: Project Performance Section -->
            <section
                class="min-w-0 overflow-hidden rounded-3xl border border-surface-200/80 bg-white shadow-xs dark:border-surface-800 dark:bg-surface-900"
            >
                <!-- Card Header -->
                <div
                    class="border-b border-surface-200/80 p-5 sm:p-6 dark:border-surface-800"
                >
                    <div
                        class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <h2
                                class="text-base font-semibold text-surface-950 dark:text-surface-50"
                            >
                                Kinerja & Progres Proyek
                            </h2>
                            <p
                                class="mt-0.5 text-xs text-surface-500 dark:text-surface-400"
                            >
                                Evaluasi persentase penyelesaian tugas pada
                                setiap proyek.
                            </p>
                        </div>

                        <!-- Status Filter Pills -->
                        <div class="flex flex-wrap items-center gap-1.5">
                            <button
                                type="button"
                                class="rounded-xl px-2.5 py-1 text-xs font-semibold transition"
                                :class="
                                    selectedStatusFilter === 'all'
                                        ? 'bg-[#0F5132] text-white shadow-2xs dark:bg-primary-600'
                                        : 'bg-surface-100 text-surface-600 hover:bg-surface-200 dark:bg-surface-800 dark:text-surface-300'
                                "
                                @click="selectedStatusFilter = 'all'"
                            >
                                Semua ({{ projects.length }})
                            </button>
                            <button
                                type="button"
                                class="rounded-xl px-2.5 py-1 text-xs font-semibold transition"
                                :class="
                                    selectedStatusFilter === 'active'
                                        ? 'bg-[#0F5132] text-white shadow-2xs dark:bg-primary-600'
                                        : 'bg-surface-100 text-surface-600 hover:bg-surface-200 dark:bg-surface-800 dark:text-surface-300'
                                "
                                @click="selectedStatusFilter = 'active'"
                            >
                                Aktif ({{ activeProjectsCount }})
                            </button>
                            <button
                                type="button"
                                class="rounded-xl px-2.5 py-1 text-xs font-semibold transition"
                                :class="
                                    selectedStatusFilter === 'completed'
                                        ? 'bg-[#0F5132] text-white shadow-2xs dark:bg-primary-600'
                                        : 'bg-surface-100 text-surface-600 hover:bg-surface-200 dark:bg-surface-800 dark:text-surface-300'
                                "
                                @click="selectedStatusFilter = 'completed'"
                            >
                                Selesai
                            </button>
                            <button
                                type="button"
                                class="rounded-xl px-2.5 py-1 text-xs font-semibold transition"
                                :class="
                                    selectedStatusFilter === 'other'
                                        ? 'bg-[#0F5132] text-white shadow-2xs dark:bg-primary-600'
                                        : 'bg-surface-100 text-surface-600 hover:bg-surface-200 dark:bg-surface-800 dark:text-surface-300'
                                "
                                @click="selectedStatusFilter = 'other'"
                            >
                                Lainnya
                            </button>
                        </div>
                    </div>

                    <!-- Search and Sort Bar -->
                    <div
                        class="mt-4 flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="relative flex-1">
                            <span
                                class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-surface-400"
                            >
                                <component
                                    :is="getIcon('Search')"
                                    class="h-4 w-4"
                                />
                            </span>
                            <input
                                v-model="projectSearch"
                                type="search"
                                placeholder="Cari nama proyek..."
                                class="input py-2 pl-9 text-xs sm:text-sm"
                            />
                        </div>

                        <div class="flex items-center gap-2">
                            <span
                                class="text-xs whitespace-nowrap text-surface-500 dark:text-surface-400"
                            >
                                Urutkan:
                            </span>
                            <select v-model="sortBy" class="input py-2 text-xs">
                                <option value="progress">
                                    Progres Tertinggi
                                </option>
                                <option value="tasks">Tugas Terbanyak</option>
                                <option value="name">Nama (A - Z)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Projects List -->
                <div
                    v-if="filteredProjects.length > 0"
                    class="divide-y divide-surface-100 dark:divide-surface-800/80"
                >
                    <div
                        v-for="project in filteredProjects"
                        :key="project.id"
                        class="group p-4 transition-colors hover:bg-surface-50/70 sm:p-5 dark:hover:bg-surface-800/30"
                    >
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <!-- Project Info -->
                            <div
                                class="flex min-w-0 flex-1 items-center gap-3.5"
                            >
                                <span
                                    class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-primary-50 text-primary-800 shadow-2xs transition group-hover:scale-105 dark:bg-primary-950/50 dark:text-primary-300"
                                >
                                    <component
                                        :is="getIcon('FolderKanban')"
                                        class="h-5 w-5"
                                    />
                                </span>

                                <div class="min-w-0 flex-1">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <Link
                                            v-if="canViewProjects"
                                            :href="
                                                projectsRoute.show.url(
                                                    project.id,
                                                )
                                            "
                                            class="truncate text-sm font-semibold text-surface-900 transition hover:text-primary-700 sm:text-base dark:text-surface-100 dark:hover:text-primary-300"
                                        >
                                            {{ project.name }}
                                        </Link>
                                        <span
                                            v-else
                                            class="truncate text-sm font-semibold text-surface-900 sm:text-base dark:text-surface-100"
                                        >
                                            {{ project.name }}
                                        </span>

                                        <span
                                            class="inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-bold"
                                            :class="
                                                projectStatusBadgeClasses[
                                                    project.status
                                                ] ??
                                                projectStatusBadgeClasses.draft
                                            "
                                        >
                                            {{
                                                statusLabels[project.status] ??
                                                project.status
                                            }}
                                        </span>
                                    </div>

                                    <p
                                        class="mt-1 text-xs text-surface-500 dark:text-surface-400"
                                    >
                                        <strong
                                            class="font-medium text-surface-800 dark:text-surface-200"
                                        >
                                            {{ project.completed_tasks_count }}
                                        </strong>
                                        dari
                                        <strong
                                            class="font-medium text-surface-800 dark:text-surface-200"
                                        >
                                            {{ project.tasks_count }}
                                        </strong>
                                        tugas telah diselesaikan
                                    </p>
                                </div>
                            </div>

                            <!-- Progress Metric -->
                            <div
                                class="flex items-center gap-4 sm:w-56 sm:shrink-0"
                            >
                                <div class="min-w-0 flex-1">
                                    <div
                                        class="mb-1 flex items-center justify-between text-[11px] font-medium text-surface-400"
                                    >
                                        <span>Penyelesaian</span>
                                        <span
                                            class="font-semibold text-surface-700 dark:text-surface-300"
                                        >
                                            {{ projectProgress(project) }}%
                                        </span>
                                    </div>
                                    <div
                                        class="h-2 w-full overflow-hidden rounded-full bg-surface-100 dark:bg-surface-800"
                                    >
                                        <div
                                            class="h-full rounded-full transition-all duration-700"
                                            :class="
                                                projectProgress(project) === 100
                                                    ? 'bg-emerald-600 dark:bg-emerald-400'
                                                    : 'bg-[#0F5132] dark:bg-primary-400'
                                            "
                                            :style="{
                                                width: `${projectProgress(project)}%`,
                                            }"
                                        />
                                    </div>
                                </div>

                                <span
                                    class="hidden font-serif text-xl font-normal text-surface-900 sm:block dark:text-surface-100"
                                >
                                    {{ projectProgress(project) }}%
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else class="p-12 text-center">
                    <component
                        :is="getIcon('FolderKanban')"
                        class="mx-auto h-10 w-10 text-surface-300 dark:text-surface-600"
                    />
                    <h3
                        class="mt-3 text-sm font-semibold text-surface-800 dark:text-surface-100"
                    >
                        Tidak ada proyek yang sesuai
                    </h3>
                    <p
                        class="mt-1 text-xs text-surface-500 dark:text-surface-400"
                    >
                        Coba sesuaikan kata kunci pencarian atau ubah filter
                        status proyek.
                    </p>
                    <button
                        v-if="projectSearch || selectedStatusFilter !== 'all'"
                        type="button"
                        class="btn btn-secondary mt-4 text-xs"
                        @click="
                            projectSearch = '';
                            selectedStatusFilter = 'all';
                        "
                    >
                        Reset Filter
                    </button>
                </div>
            </section>

            <!-- Right: Summaries & Breakdowns -->
            <aside class="space-y-6 xl:sticky xl:top-24">
                <!-- Task Distribution Card -->
                <div
                    class="rounded-3xl border border-surface-200/80 bg-white p-5 shadow-xs sm:p-6 dark:border-surface-800 dark:bg-surface-900"
                >
                    <div>
                        <h2
                            class="text-base font-semibold text-surface-950 dark:text-surface-50"
                        >
                            Distribusi Tugas
                        </h2>
                        <p
                            class="mt-0.5 text-xs text-surface-500 dark:text-surface-400"
                        >
                            Komposisi status seluruh tugas aktif.
                        </p>
                    </div>

                    <!-- Donut Visual -->
                    <div class="mt-6 flex flex-col items-center">
                        <div
                            class="relative h-40 w-40 rounded-full shadow-inner"
                            :style="{ background: donutGradient }"
                            role="img"
                            :aria-label="`Total ${totalTasks} tugas, ${completionRate}% selesai`"
                        >
                            <div
                                class="absolute inset-[20px] grid place-items-center rounded-full bg-white text-center shadow-xs dark:bg-surface-900"
                            >
                                <div>
                                    <p
                                        class="font-serif text-3xl font-normal text-surface-950 dark:text-surface-50"
                                    >
                                        {{ completionRate }}%
                                    </p>
                                    <p
                                        class="text-[10px] font-bold tracking-wider text-surface-400 uppercase dark:text-surface-500"
                                    >
                                        Tuntas
                                    </p>
                                </div>
                            </div>
                        </div>

                        <p
                            class="mt-3.5 text-xs font-medium text-surface-600 dark:text-surface-300"
                        >
                            {{ totalCompleted }} dari {{ totalTasks }} tugas
                            selesai
                        </p>
                    </div>

                    <!-- Stages Legend -->
                    <div
                        class="mt-6 space-y-2.5 border-t border-surface-100 pt-5 dark:border-surface-800"
                    >
                        <div
                            v-for="status in statusBreakdown"
                            :key="status.key"
                            class="flex items-center justify-between text-xs"
                        >
                            <div class="flex min-w-0 items-center gap-2">
                                <span
                                    class="h-2.5 w-2.5 shrink-0 rounded-full"
                                    :style="{ backgroundColor: status.color }"
                                />
                                <span
                                    class="truncate text-surface-700 dark:text-surface-300"
                                >
                                    {{ status.label }}
                                </span>
                            </div>
                            <div class="flex items-center gap-2 font-medium">
                                <span
                                    class="text-surface-950 dark:text-surface-100"
                                >
                                    {{ status.count }}
                                </span>
                                <span class="text-[11px] text-surface-400">
                                    ({{ status.percent }}%)
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Project Status Summary Card -->
                <div
                    class="rounded-3xl border border-surface-200/80 bg-white p-5 shadow-xs sm:p-6 dark:border-surface-800 dark:bg-surface-900"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2
                                class="text-base font-semibold text-surface-950 dark:text-surface-50"
                            >
                                Ringkasan Proyek
                            </h2>
                            <p
                                class="mt-0.5 text-xs text-surface-500 dark:text-surface-400"
                            >
                                Sebaran status {{ projects.length }} proyek tim.
                            </p>
                        </div>
                        <div class="text-right">
                            <span
                                class="text-[10px] font-bold tracking-wider text-surface-400 uppercase"
                            >
                                Rata-rata
                            </span>
                            <p
                                class="font-serif text-lg font-normal text-surface-950 dark:text-surface-50"
                            >
                                {{ averageProjectProgress }}%
                            </p>
                        </div>
                    </div>

                    <!-- Stacked Multi-bar -->
                    <div
                        v-if="projects.length > 0"
                        class="mt-4 flex h-2.5 overflow-hidden rounded-full bg-surface-100 dark:bg-surface-800"
                    >
                        <div
                            v-for="item in projectStatusSummary"
                            v-show="item.count > 0"
                            :key="item.key"
                            class="h-full transition-all"
                            :class="item.bar"
                            :style="{ width: `${item.percent}%` }"
                            :title="`${item.label}: ${item.count} (${item.percent}%)`"
                        />
                    </div>

                    <!-- Status chips -->
                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <div
                            v-for="item in projectStatusSummary"
                            :key="item.key"
                            class="rounded-xl border border-surface-200/70 bg-surface-50/50 p-2.5 dark:border-surface-800 dark:bg-surface-950/30"
                        >
                            <div class="flex items-center gap-1.5">
                                <span
                                    class="h-2 w-2 shrink-0 rounded-full"
                                    :class="item.dot"
                                />
                                <span
                                    class="truncate text-[11px] font-medium text-surface-600 dark:text-surface-400"
                                >
                                    {{ item.label }}
                                </span>
                            </div>
                            <div
                                class="mt-1 flex items-baseline justify-between"
                            >
                                <span
                                    class="font-serif text-base font-normal text-surface-950 dark:text-surface-50"
                                >
                                    {{ item.count }}
                                </span>
                                <span class="text-[10px] text-surface-400">
                                    {{ item.percent }}%
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</template>
