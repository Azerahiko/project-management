<script setup lang="ts">
import { computed } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import {
    Plus,
    FolderKanban,
    CheckSquare,
    AlertCircle,
    Users,
    ArrowUpRight,
    Clock,
    CheckCircle2,
    TrendingUp,
    Calendar,
    ChevronRight,
} from '@lucide/vue';
import projects from '@/routes/projects';
import tasks from '@/routes/tasks';
import type {
    DashboardStats,
    UpcomingDeadline,
    RecentActivity,
    ProjectProgress,
    TaskStatus,
} from '@/types';

defineOptions({
    layout: AppLayout,
});

const page = usePage();
const user = computed(() => page.props.auth?.user);

const stats = computed(() => page.props.stats as DashboardStats | undefined);
const tasksByStatus = computed(
    () =>
        page.props.tasks_by_status as
            | Partial<Record<TaskStatus, number>>
            | undefined,
);
const upcomingDeadlines = computed(
    () =>
        (page.props.upcoming_deadlines as UpcomingDeadline[] | undefined) ?? [],
);
const recentActivities = computed(
    () => (page.props.recent_activities as RecentActivity[] | undefined) ?? [],
);
const projectProgress = computed(
    () => (page.props.project_progress as ProjectProgress[] | undefined) ?? [],
);

interface FocusMetrics {
    on_time_rate: number;
    urgent_tasks_count: number;
    review_tasks_count: number;
    due_this_week_count: number;
    completed_tasks_count: number;
    total_tasks_count: number;
    completion_rate: number;
    my_tasks_count: number;
    overdue_count: number;
}

const focusMetrics = computed<FocusMetrics>(() => {
    const raw = page.props.focus_metrics as FocusMetrics | undefined;
    if (raw) return raw;
    return {
        on_time_rate: 100,
        urgent_tasks_count: 0,
        review_tasks_count: 0,
        due_this_week_count: 0,
        completed_tasks_count: 0,
        total_tasks_count: 0,
        completion_rate: 0,
        my_tasks_count: stats.value?.my_tasks_count ?? 0,
        overdue_count: stats.value?.overdue_count ?? 0,
    };
});

const greetingTime = computed(() => {
    const hour = Number(
        new Intl.DateTimeFormat('en-GB', {
            hour: '2-digit',
            hour12: false,
        }).format(new Date()),
    );

    if (hour < 11) return 'Selamat Pagi';
    if (hour < 15) return 'Selamat Siang';
    if (hour < 18) return 'Selamat Sore';
    return 'Selamat Malam';
});

// Pipeline distribution
const todoCount = computed(() => tasksByStatus.value?.todo ?? 0);
const inProgressCount = computed(() => tasksByStatus.value?.in_progress ?? 0);
const reviewCount = computed(() => tasksByStatus.value?.review ?? 0);
const doneCount = computed(() => tasksByStatus.value?.done ?? 0);
const totalTasks = computed(
    () =>
        todoCount.value +
        inProgressCount.value +
        reviewCount.value +
        doneCount.value,
);

const todoPct = computed(() =>
    totalTasks.value
        ? Math.round((todoCount.value / totalTasks.value) * 100)
        : 0,
);
const inProgressPct = computed(() =>
    totalTasks.value
        ? Math.round((inProgressCount.value / totalTasks.value) * 100)
        : 0,
);
const reviewPct = computed(() =>
    totalTasks.value
        ? Math.round((reviewCount.value / totalTasks.value) * 100)
        : 0,
);
const donePct = computed(() =>
    totalTasks.value
        ? Math.round((doneCount.value / totalTasks.value) * 100)
        : 0,
);

function formatRelative(dateStr: string): string {
    try {
        const d = new Date(dateStr);
        const diffMs = Date.now() - d.getTime();
        const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
        if (diffDays === 0) return 'Hari ini';
        if (diffDays === 1) return 'Kemarin';
        if (diffDays < 7) return `${diffDays} hari lalu`;
        return d.toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'short',
        });
    } catch {
        return dateStr;
    }
}

function getUrgencyBadge(urgency: string): { label: string; class: string } {
    if (urgency === 'overdue')
        return {
            label: 'Lewat Tenggat',
            class: 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
        };
    if (urgency === 'critical')
        return {
            label: 'Hari Ini',
            class: 'bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300',
        };
    if (urgency === 'warning')
        return {
            label: 'Mendekati',
            class: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
        };
    return {
        label: 'Tepat Waktu',
        class: 'bg-surface-200 text-surface-700 dark:bg-surface-800 dark:text-surface-300',
    };
}
</script>

<template>
    <div class="mx-auto max-w-7xl space-y-6">
        <!-- 1. Executive Command Center Header -->
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span
                        class="inline-flex h-2 w-2 rounded-full bg-primary-600 dark:bg-primary-500"
                    />
                    <span
                        class="font-mono text-[11px] font-semibold tracking-wider text-surface-500 uppercase dark:text-surface-400"
                    >
                        {{ page.props.app.name }} &bull; Workspace Telemetry
                    </span>
                </div>
                <h1
                    class="text-xl font-bold tracking-tight text-surface-900 sm:text-2xl dark:text-surface-50"
                >
                    {{ greetingTime }}, {{ user?.name }}
                </h1>
                <p class="text-xs text-surface-500 dark:text-surface-400">
                    Pusat kendali operasional tim untuk memantau progres
                    portofolio, beban kerja, dan prioritas tindakan.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <Link
                    :href="projects.index.url()"
                    class="btn btn-secondary btn-sm"
                >
                    <FolderKanban class="h-3.5 w-3.5 text-surface-400" />
                    <span>Semua Proyek</span>
                </Link>
                <Link :href="tasks.index.url()" class="btn btn-primary btn-sm">
                    <Plus class="h-3.5 w-3.5 stroke-[2.5]" />
                    <span>Buat Tugas</span>
                </Link>
            </div>
        </div>

        <!-- 2. Integrated Telemetry Matrix (Layered Elevation Strip) -->
        <div
            class="grid grid-cols-1 divide-y divide-surface-200/80 overflow-hidden rounded-xl border border-surface-200/90 bg-white shadow-card sm:grid-cols-2 sm:divide-x sm:divide-y-0 lg:grid-cols-4 dark:divide-surface-800/80 dark:border-surface-800 dark:bg-surface-900/90"
        >
            <!-- Metric 1: Proyek Aktif -->
            <Link
                :href="projects.index.url()"
                class="group p-4 transition-all duration-150 hover:bg-surface-50/80 dark:hover:bg-surface-800/50"
            >
                <div
                    class="flex items-center justify-between text-surface-500 dark:text-surface-400"
                >
                    <span
                        class="font-mono text-[11px] font-semibold tracking-wider uppercase"
                        >Proyek Aktif</span
                    >
                    <FolderKanban
                        class="h-3.5 w-3.5 text-primary-600 dark:text-primary-400"
                    />
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span
                        class="font-mono text-2xl font-bold text-surface-900 dark:text-surface-50"
                    >
                        {{ stats?.projects_count ?? 0 }}
                    </span>
                    <span
                        class="text-[11px] text-surface-500 dark:text-surface-400"
                    >
                        / {{ projectProgress.length }} dipantau
                    </span>
                </div>
                <div
                    class="mt-2.5 flex items-center justify-between text-[11px] text-surface-500 dark:text-surface-400"
                >
                    <span>Penyelesaian</span>
                    <span
                        class="font-mono font-semibold text-primary-600 dark:text-primary-400"
                        >{{ focusMetrics.completion_rate }}%</span
                    >
                </div>
                <div
                    class="mt-1.5 h-1 w-full overflow-hidden rounded-full bg-surface-100 shadow-inner dark:bg-surface-800"
                >
                    <div
                        class="h-full bg-primary-600 transition-all duration-500 dark:bg-primary-400"
                        :style="{ width: `${focusMetrics.completion_rate}%` }"
                    />
                </div>
            </Link>

            <!-- Metric 2: Tugas Saya -->
            <Link
                :href="tasks.index.url()"
                class="group p-4 transition-all duration-150 hover:bg-surface-50/80 dark:hover:bg-surface-800/50"
            >
                <div
                    class="flex items-center justify-between text-surface-500 dark:text-surface-400"
                >
                    <span
                        class="font-mono text-[11px] font-semibold tracking-wider uppercase"
                        >Tugas Saya</span
                    >
                    <CheckSquare
                        class="h-3.5 w-3.5 text-primary-600 dark:text-primary-400"
                    />
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span
                        class="font-mono text-2xl font-bold text-surface-900 dark:text-surface-50"
                    >
                        {{ stats?.my_tasks_count ?? 0 }}
                    </span>
                    <span
                        class="text-[11px] text-surface-500 dark:text-surface-400"
                    >
                        / {{ stats?.active_tasks_count ?? 0 }} total berjalan
                    </span>
                </div>
                <div
                    class="mt-2.5 flex items-center justify-between text-[11px] text-surface-500 dark:text-surface-400"
                >
                    <span>Sedang diproses</span>
                    <span
                        class="font-mono font-medium text-surface-700 dark:text-surface-300"
                        >{{ inProgressCount }} tugas</span
                    >
                </div>
                <div
                    class="mt-1.5 h-1 w-full overflow-hidden rounded-full bg-surface-100 shadow-inner dark:bg-surface-800"
                >
                    <div
                        class="h-full bg-blue-500 transition-all duration-500"
                        :style="{
                            width: `${totalTasks ? Math.round((inProgressCount / totalTasks) * 100) : 0}%`,
                        }"
                    />
                </div>
            </Link>

            <!-- Metric 3: Tindakan & Urgensi -->
            <div
                class="p-4 transition-colors"
                :class="
                    (stats?.overdue_count ?? 0) > 0
                        ? 'bg-rose-50/30 dark:bg-rose-950/20'
                        : ''
                "
            >
                <div
                    class="flex items-center justify-between text-surface-500 dark:text-surface-400"
                >
                    <span
                        class="font-mono text-[11px] font-semibold tracking-wider uppercase"
                        >Perlu Tindakan</span
                    >
                    <AlertCircle
                        class="h-3.5 w-3.5"
                        :class="
                            (stats?.overdue_count ?? 0) > 0
                                ? 'text-rose-600 dark:text-rose-400'
                                : 'text-surface-400'
                        "
                    />
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span
                        class="font-mono text-2xl font-bold"
                        :class="
                            (stats?.overdue_count ?? 0) > 0
                                ? 'text-rose-600 dark:text-rose-400'
                                : 'text-surface-900 dark:text-surface-50'
                        "
                    >
                        {{ stats?.overdue_count ?? 0 }}
                    </span>
                    <span
                        v-if="(stats?.overdue_count ?? 0) > 0"
                        class="badge badge-danger text-[10px]"
                    >
                        Lewat Tenggat
                    </span>
                    <span
                        v-else
                        class="text-[11px] text-emerald-600 dark:text-emerald-400"
                    >
                        Tepat waktu
                    </span>
                </div>
                <div
                    class="mt-2.5 flex items-center justify-between text-[11px] text-surface-500 dark:text-surface-400"
                >
                    <span>Mendesak pekan ini</span>
                    <span
                        class="font-mono font-medium text-surface-700 dark:text-surface-300"
                        >{{ focusMetrics.due_this_week_count }} tugas</span
                    >
                </div>
                <div
                    class="mt-1.5 h-1 w-full overflow-hidden rounded-full bg-surface-100 shadow-inner dark:bg-surface-800"
                >
                    <div
                        class="h-full bg-rose-500 transition-all duration-500"
                        :style="{
                            width: `${(stats?.overdue_count ?? 0) > 0 ? 100 : 0}%`,
                        }"
                    />
                </div>
            </div>

            <!-- Metric 4: Efisiensi & Ketepatan -->
            <div class="p-4">
                <div
                    class="flex items-center justify-between text-surface-500 dark:text-surface-400"
                >
                    <span
                        class="font-mono text-[11px] font-semibold tracking-wider uppercase"
                        >Ketepatan Jadwal</span
                    >
                    <TrendingUp
                        class="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400"
                    />
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span
                        class="font-mono text-2xl font-bold text-surface-900 dark:text-surface-50"
                    >
                        {{ focusMetrics.on_time_rate }}%
                    </span>
                    <span
                        class="text-[11px] text-surface-500 dark:text-surface-400"
                    >
                        / {{ stats?.team_members_count ?? 0 }} tim
                    </span>
                </div>
                <div
                    class="mt-2.5 flex items-center justify-between text-[11px] text-surface-500 dark:text-surface-400"
                >
                    <span>Selesai tuntas</span>
                    <span
                        class="font-mono font-medium text-surface-700 dark:text-surface-300"
                        >{{ doneCount }}/{{ totalTasks }}</span
                    >
                </div>
                <div
                    class="mt-1.5 h-1 w-full overflow-hidden rounded-full bg-surface-100 shadow-inner dark:bg-surface-800"
                >
                    <div
                        class="h-full bg-emerald-500 transition-all duration-500"
                        :style="{ width: `${focusMetrics.on_time_rate}%` }"
                    />
                </div>
            </div>
        </div>

        <!-- 3. Main Command Grid (8-col / 4-col Asymmetric Composition) -->
        <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-12">
            <!-- LEFT COLUMN: Pipeline Alur Kerja & Proyek Berjalan (8 cols) -->
            <div class="space-y-6 lg:col-span-8">
                <!-- Section A: Alur Kerja & Pipeline Matrix -->
                <div
                    class="space-y-4 rounded-xl border border-surface-200/90 bg-white p-5 shadow-card dark:border-surface-800 dark:bg-surface-900/90"
                >
                    <div class="flex items-center justify-between">
                        <div class="space-y-0.5">
                            <h2
                                class="text-sm font-bold text-surface-900 dark:text-surface-100"
                            >
                                Alur Kerja & Distribusi Status
                            </h2>
                            <p
                                class="text-xs text-surface-500 dark:text-surface-400"
                            >
                                Sebaran {{ totalTasks }} tugas dalam pipeline
                                tim
                            </p>
                        </div>
                        <Link
                            :href="tasks.index.url()"
                            class="inline-flex items-center gap-1 text-xs font-semibold text-primary-600 transition hover:text-primary-700 dark:text-primary-400"
                        >
                            <span>Buka Papan Tugas</span>
                            <ArrowUpRight class="h-3.5 w-3.5" />
                        </Link>
                    </div>

                    <!-- Progress Distribution Ribbon with Dimensional Bevel -->
                    <div class="space-y-1.5">
                        <div
                            class="flex h-2.5 w-full overflow-hidden rounded-full bg-surface-100 shadow-inner dark:bg-surface-800"
                        >
                            <div
                                class="bg-surface-400 transition-all duration-500"
                                :style="{ width: `${todoPct}%` }"
                                title="Belum Mulai"
                            />
                            <div
                                class="bg-blue-500 transition-all duration-500"
                                :style="{ width: `${inProgressPct}%` }"
                                title="Sedang Berjalan"
                            />
                            <div
                                class="bg-amber-500 transition-all duration-500"
                                :style="{ width: `${reviewPct}%` }"
                                title="Peninjauan"
                            />
                            <div
                                class="bg-emerald-500 transition-all duration-500"
                                :style="{ width: `${donePct}%` }"
                                title="Selesai"
                            />
                        </div>
                    </div>

                    <!-- Pipeline Stage Cards -->
                    <div class="grid grid-cols-2 gap-2.5 pt-1 sm:grid-cols-4">
                        <div
                            class="rounded-lg border border-surface-200 bg-surface-50/70 p-3 shadow-xs dark:border-surface-800 dark:bg-surface-800/40"
                        >
                            <div
                                class="flex items-center gap-1.5 text-surface-500 dark:text-surface-400"
                            >
                                <span
                                    class="h-2 w-2 rounded-full bg-surface-400"
                                />
                                <span class="text-[11px] font-semibold"
                                    >To Do</span
                                >
                            </div>
                            <div
                                class="mt-1.5 font-mono text-lg font-bold text-surface-900 dark:text-surface-100"
                            >
                                {{ todoCount }}
                            </div>
                            <div class="text-[10px] text-surface-400">
                                {{ todoPct }}% beban tim
                            </div>
                        </div>

                        <div
                            class="rounded-lg border border-blue-200/80 bg-blue-50/40 p-3 shadow-xs dark:border-blue-900/40 dark:bg-blue-950/20"
                        >
                            <div
                                class="flex items-center gap-1.5 text-blue-600 dark:text-blue-400"
                            >
                                <span
                                    class="h-2 w-2 rounded-full bg-blue-500"
                                />
                                <span class="text-[11px] font-semibold"
                                    >In Progress</span
                                >
                            </div>
                            <div
                                class="mt-1.5 font-mono text-lg font-bold text-blue-900 dark:text-blue-200"
                            >
                                {{ inProgressCount }}
                            </div>
                            <div class="text-[10px] text-blue-500/80">
                                {{ inProgressPct }}% dikerjakan
                            </div>
                        </div>

                        <div
                            class="rounded-lg border border-amber-200/80 bg-amber-50/40 p-3 shadow-xs dark:border-amber-900/40 dark:bg-amber-950/20"
                        >
                            <div
                                class="flex items-center gap-1.5 text-amber-600 dark:text-amber-400"
                            >
                                <span
                                    class="h-2 w-2 rounded-full bg-amber-500"
                                />
                                <span class="text-[11px] font-semibold"
                                    >Review</span
                                >
                            </div>
                            <div
                                class="mt-1.5 font-mono text-lg font-bold text-amber-900 dark:text-amber-200"
                            >
                                {{ reviewCount }}
                            </div>
                            <div class="text-[10px] text-amber-500/80">
                                {{ reviewPct }}% validasi
                            </div>
                        </div>

                        <div
                            class="rounded-lg border border-emerald-200/80 bg-emerald-50/40 p-3 shadow-xs dark:border-emerald-900/40 dark:bg-emerald-950/20"
                        >
                            <div
                                class="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400"
                            >
                                <span
                                    class="h-2 w-2 rounded-full bg-emerald-500"
                                />
                                <span class="text-[11px] font-semibold"
                                    >Selesai</span
                                >
                            </div>
                            <div
                                class="mt-1.5 font-mono text-lg font-bold text-emerald-900 dark:text-emerald-200"
                            >
                                {{ doneCount }}
                            </div>
                            <div class="text-[10px] text-emerald-500/80">
                                {{ donePct }}% tuntas
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section B: Proyek Berjalan (Linear-style Detailed List) -->
                <div
                    class="space-y-4 rounded-xl border border-surface-200/90 bg-white p-5 shadow-card dark:border-surface-800 dark:bg-surface-900/90"
                >
                    <div class="flex items-center justify-between">
                        <div class="space-y-0.5">
                            <h2
                                class="text-sm font-bold text-surface-900 dark:text-surface-100"
                            >
                                Proyek Berjalan
                            </h2>
                            <p
                                class="text-xs text-surface-500 dark:text-surface-400"
                            >
                                Progres dan capaian proyek dalam sprint ini
                            </p>
                        </div>
                        <Link
                            :href="projects.index.url()"
                            class="inline-flex items-center gap-1 text-xs font-medium text-primary-600 transition hover:text-primary-700 dark:text-primary-400"
                        >
                            <span>Kelola Portofolio</span>
                            <ArrowUpRight class="h-3.5 w-3.5" />
                        </Link>
                    </div>

                    <div
                        class="divide-y divide-surface-100 dark:divide-surface-800/60"
                    >
                        <div
                            v-for="proj in projectProgress"
                            :key="proj.id"
                            class="group flex flex-col gap-3 py-3.5 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div class="min-w-0 flex-1 space-y-1">
                                <div class="flex items-center gap-2">
                                    <Link
                                        :href="`/projects/${proj.id}`"
                                        class="truncate text-sm font-semibold text-surface-900 transition-colors group-hover:text-primary-600 dark:text-surface-100 dark:group-hover:text-primary-400"
                                    >
                                        {{ proj.name }}
                                    </Link>
                                    <span
                                        class="py-0.2 rounded bg-surface-100 px-1.5 text-[10px] font-semibold text-surface-600 dark:bg-surface-800 dark:text-surface-300"
                                    >
                                        {{ (proj as any).status || 'Aktif' }}
                                    </span>
                                </div>
                                <p
                                    class="line-clamp-1 text-xs text-surface-500 dark:text-surface-400"
                                >
                                    {{
                                        proj.description ||
                                        'Tidak ada deskripsi proyek.'
                                    }}
                                </p>
                            </div>

                            <div
                                class="flex shrink-0 items-center justify-between gap-4 sm:w-48 sm:justify-end"
                            >
                                <div class="w-28 space-y-1">
                                    <div
                                        class="flex justify-between text-[10px] font-medium text-surface-500 dark:text-surface-400"
                                    >
                                        <span
                                            >{{ proj.completed_tasks_count }}/{{
                                                proj.tasks_count
                                            }}
                                            Tugas</span
                                        >
                                        <span
                                            class="font-bold text-surface-900 dark:text-surface-200"
                                            >{{ proj.progress_percent }}%</span
                                        >
                                    </div>
                                    <div
                                        class="h-1.5 w-full overflow-hidden rounded-full bg-surface-100 dark:bg-surface-800"
                                    >
                                        <div
                                            class="h-full bg-primary-600 transition-all duration-300 dark:bg-primary-400"
                                            :style="{
                                                width: `${proj.progress_percent}%`,
                                            }"
                                        />
                                    </div>
                                </div>

                                <Link
                                    :href="`/projects/${proj.id}`"
                                    class="rounded-lg p-1 text-surface-400 transition hover:bg-surface-100 hover:text-surface-700 dark:hover:bg-surface-800 dark:hover:text-surface-200"
                                >
                                    <ChevronRight class="h-4 w-4" />
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN: Urgensi & Aktivitas Stream (4 cols) -->
            <div class="space-y-6 lg:col-span-4">
                <!-- Section C: Antrean Tenggat Waktu (Urgent Deadlines) -->
                <div
                    class="space-y-3.5 rounded-xl border border-surface-200/90 bg-white p-5 shadow-card dark:border-surface-800 dark:bg-surface-900/90"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <Clock class="h-4 w-4 text-rose-500" />
                            <h2
                                class="text-sm font-bold text-surface-900 dark:text-surface-100"
                            >
                                Tenggat Waktu
                            </h2>
                        </div>
                        <Link
                            :href="tasks.index.url()"
                            class="text-xs font-semibold text-surface-500 transition hover:text-surface-900 dark:text-surface-400 dark:hover:text-surface-100"
                        >
                            Semua
                        </Link>
                    </div>

                    <div
                        v-if="upcomingDeadlines.length === 0"
                        class="py-6 text-center text-xs text-surface-400"
                    >
                        Tidak ada tenggat waktu mendesak.
                    </div>

                    <div v-else class="space-y-2.5">
                        <div
                            v-for="task in upcomingDeadlines"
                            :key="task.id"
                            class="rounded-lg border border-surface-200/80 bg-surface-50/70 p-3 shadow-xs transition hover:border-surface-300 hover:bg-white dark:border-surface-800/80 dark:bg-surface-800/40 dark:hover:border-surface-700 dark:hover:bg-surface-800/80"
                        >
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <span
                                    class="py-0.2 rounded px-1.5 text-[9.5px] font-bold"
                                    :class="
                                        getUrgencyBadge(task.deadline_urgency)
                                            .class
                                    "
                                >
                                    {{
                                        getUrgencyBadge(task.deadline_urgency)
                                            .label
                                    }}
                                </span>
                                <span class="text-[10px] text-surface-400">
                                    {{ formatRelative(task.due_date || '') }}
                                </span>
                            </div>

                            <div class="mt-1.5">
                                <Link
                                    :href="`/tasks/${task.id}`"
                                    class="line-clamp-1 text-xs font-semibold text-surface-900 transition-colors hover:text-primary-600 dark:text-surface-100 dark:hover:text-primary-400"
                                >
                                    {{ task.title }}
                                </Link>
                                <span class="text-[10.5px] text-surface-400">
                                    {{
                                        (task as any).project?.name ||
                                        'Proyek Tim'
                                    }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section D: Log Aktivitas Tim (Real-time Audit Trail) -->
                <div
                    class="space-y-3.5 rounded-xl border border-surface-200/90 bg-white p-5 shadow-card dark:border-surface-800 dark:bg-surface-900/90"
                >
                    <div class="flex items-center justify-between">
                        <h2
                            class="text-sm font-bold text-surface-900 dark:text-surface-100"
                        >
                            Aktivitas Terkini
                        </h2>
                        <Link
                            href="/activity-log"
                            class="text-xs font-medium text-surface-500 transition hover:text-surface-900 dark:text-surface-400 dark:hover:text-surface-100"
                        >
                            Log Lengkap
                        </Link>
                    </div>

                    <div
                        v-if="recentActivities.length === 0"
                        class="py-6 text-center text-xs text-surface-400"
                    >
                        Belum ada aktivitas tercatat.
                    </div>

                    <div v-else class="space-y-3">
                        <div
                            v-for="act in recentActivities.slice(0, 5)"
                            :key="act.id"
                            class="flex items-start gap-2.5 text-xs"
                        >
                            <div class="relative mt-0.5 shrink-0">
                                <img
                                    v-if="act.user?.avatar"
                                    :src="act.user.avatar"
                                    :alt="act.user.name"
                                    class="h-6 w-6 rounded-full object-cover ring-1 ring-surface-200 dark:ring-surface-700"
                                />
                                <div
                                    v-else
                                    class="flex h-6 w-6 items-center justify-center rounded-full bg-primary-700 text-[9px] font-bold text-white dark:bg-primary-600"
                                >
                                    {{
                                        act.user?.name
                                            ?.slice(0, 2)
                                            .toUpperCase() || 'SA'
                                    }}
                                </div>
                            </div>

                            <div class="min-w-0 flex-1 space-y-0.5">
                                <p
                                    class="leading-tight text-surface-700 dark:text-surface-300"
                                >
                                    <span
                                        class="font-semibold text-surface-900 dark:text-surface-100"
                                    >
                                        {{ act.user?.name }}
                                    </span>
                                    {{ ' ' }}
                                    <span
                                        class="text-surface-600 dark:text-surface-400"
                                    >
                                        {{
                                            (act as any).description ||
                                            (act as any).action ||
                                            'memperbarui tugas'
                                        }}
                                    </span>
                                </p>
                                <span class="text-[10px] text-surface-400">
                                    {{ formatRelative(act.created_at) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
