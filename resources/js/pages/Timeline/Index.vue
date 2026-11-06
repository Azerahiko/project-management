<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import Avatar from '@/components/ui/Avatar.vue';
import { getIcon } from '@/components/sidebar/icons';
import projectRoutes from '@/routes/projects';
import taskRoutes from '@/routes/tasks';

defineOptions({
    layout: AppLayout,
});

interface TimelineAssignee {
    id: number;
    name: string;
    avatar: string | null;
    initials: string;
}

interface TimelineTask {
    id: number;
    title: string;
    status: string;
    status_label: string;
    priority: string;
    priority_label: string;
    start_date: string | null;
    due_date: string | null;
    is_overdue: boolean;
    assignee: TimelineAssignee | null;
}

interface TimelineProject {
    id: number;
    name: string;
    status: string;
    status_label: string;
    start_date: string | null;
    end_date: string | null;
    tasks: TimelineTask[];
}

const page = usePage();
const rawProjects = computed(
    () => (page.props.projects as TimelineProject[]) ?? [],
);
const canViewTasks = computed(() => Boolean(page.props.can_view_tasks));

// Date helpers
const now = new Date();
now.setHours(0, 0, 0, 0);

function parseIso(value: string): Date {
    const [year, month, day] = value.split('-').map(Number);
    return new Date(year, month - 1, day);
}

function formatShortDate(date: Date): string {
    return date.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

function formatMonthYear(date: Date): string {
    return date.toLocaleDateString('id-ID', {
        month: 'long',
        year: 'numeric',
    });
}

// Range selection: 'month' (current + next), 'quarter' (3 months), 'all' (entire projects span)
const rangeMode = ref<'quarter' | 'month' | 'all'>('quarter');
const cursorDate = ref(new Date(now.getFullYear(), now.getMonth(), 1));

const statusFilter = ref('all');
const searchQuery = ref('');
const expandedProjects = ref<Record<number, boolean>>({});
const selectedProject = ref<TimelineProject | null>(null);

function toggleProjectExpand(id: number): void {
    expandedProjects.value[id] = !expandedProjects.value[id];
}

// Expand all or collapse all
function expandAll(): void {
    const next: Record<number, boolean> = {};
    for (const p of rawProjects.value) {
        next[p.id] = true;
    }
    expandedProjects.value = next;
}

function collapseAll(): void {
    expandedProjects.value = {};
}

// Timeline range bounds calculation
const timelineBounds = computed(() => {
    if (rangeMode.value === 'month') {
        const start = new Date(
            cursorDate.value.getFullYear(),
            cursorDate.value.getMonth(),
            1,
        );
        const end = new Date(
            cursorDate.value.getFullYear(),
            cursorDate.value.getMonth() + 2,
            0,
        );
        return { start, end };
    }

    if (rangeMode.value === 'quarter') {
        const start = new Date(
            cursorDate.value.getFullYear(),
            cursorDate.value.getMonth() - 1,
            1,
        );
        const end = new Date(
            cursorDate.value.getFullYear(),
            cursorDate.value.getMonth() + 2,
            0,
        );
        return { start, end };
    }

    // 'all': find min and max across all projects and tasks
    const allDates: Date[] = [];
    for (const p of rawProjects.value) {
        if (p.start_date) allDates.push(parseIso(p.start_date));
        if (p.end_date) allDates.push(parseIso(p.end_date));
        for (const t of p.tasks) {
            if (t.start_date) allDates.push(parseIso(t.start_date));
            if (t.due_date) allDates.push(parseIso(t.due_date));
        }
    }

    if (allDates.length === 0) {
        const start = new Date(now.getFullYear(), now.getMonth(), 1);
        const end = new Date(now.getFullYear(), now.getMonth() + 2, 0);
        return { start, end };
    }

    const min = new Date(Math.min(...allDates.map((d) => d.getTime())));
    const max = new Date(Math.max(...allDates.map((d) => d.getTime())));

    // Pad to start of min month and end of max month
    const start = new Date(min.getFullYear(), min.getMonth(), 1);
    const end = new Date(max.getFullYear(), max.getMonth() + 1, 0);

    return { start, end };
});

const totalTimelineDays = computed(() => {
    const diff =
        timelineBounds.value.end.getTime() -
        timelineBounds.value.start.getTime();
    return Math.max(1, Math.round(diff / (1000 * 60 * 60 * 24)) + 1);
});

// Month headers in the timeline
const timelineMonths = computed(() => {
    const months = [];
    const current = new Date(
        timelineBounds.value.start.getFullYear(),
        timelineBounds.value.start.getMonth(),
        1,
    );
    const end = timelineBounds.value.end;

    while (current <= end) {
        const monthStart = new Date(
            Math.max(current.getTime(), timelineBounds.value.start.getTime()),
        );
        const monthEnd = new Date(
            Math.min(
                new Date(
                    current.getFullYear(),
                    current.getMonth() + 1,
                    0,
                ).getTime(),
                end.getTime(),
            ),
        );
        const daysInMonth =
            Math.round(
                (monthEnd.getTime() - monthStart.getTime()) /
                    (1000 * 60 * 60 * 24),
            ) + 1;

        const startDiff = Math.round(
            (monthStart.getTime() - timelineBounds.value.start.getTime()) /
                (1000 * 60 * 60 * 24),
        );
        const leftPercent = (startDiff / totalTimelineDays.value) * 100;
        const widthPercent = (daysInMonth / totalTimelineDays.value) * 100;

        months.push({
            name: current.toLocaleDateString('id-ID', {
                month: 'long',
                year: 'numeric',
            }),
            shortName: current.toLocaleDateString('id-ID', { month: 'short' }),
            year: current.getFullYear(),
            left: leftPercent,
            width: widthPercent,
        });

        current.setMonth(current.getMonth() + 1);
    }

    return months;
});

// Calculate Gantt bar left & width percentage
function getBarCoords(
    startDateStr: string | null,
    endDateStr: string | null,
): { left: number; width: number; isVisible: boolean } {
    const boundStart = timelineBounds.value.start.getTime();
    const boundEnd = timelineBounds.value.end.getTime();

    const start = startDateStr
        ? parseIso(startDateStr).getTime()
        : endDateStr
          ? parseIso(endDateStr).getTime()
          : boundStart;
    const end = endDateStr ? parseIso(endDateStr).getTime() : start;

    if (end < boundStart || start > boundEnd) {
        return { left: 0, width: 0, isVisible: false };
    }

    const clampedStart = Math.max(boundStart, start);
    const clampedEnd = Math.min(boundEnd, end);

    const startDays = (clampedStart - boundStart) / (1000 * 60 * 60 * 24);
    const durationDays = Math.max(
        1,
        (clampedEnd - clampedStart) / (1000 * 60 * 60 * 24) + 1,
    );

    const left = (startDays / totalTimelineDays.value) * 100;
    const width = Math.max(1.5, (durationDays / totalTimelineDays.value) * 100);

    return { left, width, isVisible: true };
}

// Today marker line position
const todayMarker = computed(() => {
    const boundStart = timelineBounds.value.start.getTime();
    const boundEnd = timelineBounds.value.end.getTime();
    const todayTime = now.getTime();

    if (todayTime < boundStart || todayTime > boundEnd) {
        return null;
    }

    const diffDays = (todayTime - boundStart) / (1000 * 60 * 60 * 24);
    return (diffDays / totalTimelineDays.value) * 100;
});

// Shift timeline range
function shiftTimeline(direction: -1 | 1): void {
    const stepMonths = rangeMode.value === 'quarter' ? 3 : 1;
    cursorDate.value = new Date(
        cursorDate.value.getFullYear(),
        cursorDate.value.getMonth() + direction * stepMonths,
        1,
    );
}

function goToToday(): void {
    cursorDate.value = new Date(now.getFullYear(), now.getMonth(), 1);
}

// Filtered projects
const filteredProjects = computed(() => {
    let list = rawProjects.value;

    if (statusFilter.value !== 'all') {
        list = list.filter((p) => p.status === statusFilter.value);
    }

    const query = searchQuery.value.trim().toLowerCase();
    if (query) {
        list = list.filter(
            (p) =>
                p.name.toLowerCase().includes(query) ||
                p.tasks.some((t) => t.title.toLowerCase().includes(query)),
        );
    }

    return list;
});

// Project stats
const summary = computed(() => {
    const totalProjects = rawProjects.value.length;
    let scheduledTasks = 0;
    let completedTasks = 0;
    let overdueTasks = 0;

    for (const p of rawProjects.value) {
        for (const t of p.tasks) {
            scheduledTasks++;
            if (t.status === 'done') completedTasks++;
            if (t.is_overdue) overdueTasks++;
        }
    }

    return {
        totalProjects,
        scheduledTasks,
        completedTasks,
        overdueTasks,
        completionRate:
            scheduledTasks > 0
                ? Math.round((completedTasks / scheduledTasks) * 100)
                : 0,
    };
});

function projectCompletion(project: TimelineProject): number {
    if (project.tasks.length === 0) return 0;
    const done = project.tasks.filter((t) => t.status === 'done').length;
    return Math.round((done / project.tasks.length) * 100);
}

const statusBadgeClasses: Record<string, string> = {
    active: 'bg-primary-50 text-primary-800 border-primary-200/80 dark:bg-primary-950/40 dark:text-primary-300 dark:border-primary-800/40',
    completed:
        'bg-emerald-50 text-emerald-800 border-emerald-200/80 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800/40',
    on_hold:
        'bg-amber-50 text-amber-800 border-amber-200/80 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800/40',
    draft: 'bg-surface-100 text-surface-600 border-surface-200 dark:bg-surface-800 dark:text-surface-300 dark:border-surface-700',
    archived:
        'bg-surface-100 text-surface-500 border-surface-200 dark:bg-surface-800 dark:text-surface-400 dark:border-surface-700',
};

const priorityDotColors: Record<string, string> = {
    urgent: 'bg-rose-500',
    high: 'bg-amber-500',
    medium: 'bg-primary-600',
    low: 'bg-surface-400',
};

const priorityBarColors: Record<string, string> = {
    urgent: 'bg-rose-500/90 text-white',
    high: 'bg-amber-600/90 text-white',
    medium: 'bg-[#0F5132]/90 text-white dark:bg-primary-600/90',
    low: 'bg-surface-500/80 text-white dark:bg-surface-600',
};

// Expand first project by default
if (rawProjects.value.length > 0) {
    expandedProjects.value[rawProjects.value[0].id] = true;
}
</script>

<template>
    <div class="space-y-6 pb-12">
        <!-- 1. Header -->
        <header
            class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
        >
            <div>
                <span
                    class="inline-flex h-6 items-center gap-1.5 rounded-full border border-primary-200/80 bg-primary-50 px-2.5 text-[10px] font-bold tracking-wider text-primary-800 uppercase dark:border-primary-800/40 dark:bg-primary-950/40 dark:text-primary-300"
                >
                    <component :is="getIcon('Calendar')" class="h-3 w-3" />
                    Timeline & Roadmap Proyek
                </span>
                <h1
                    class="mt-2 text-2xl font-semibold tracking-tight text-surface-950 sm:text-3xl dark:text-surface-50"
                >
                    Garis Waktu Proyek
                </h1>
                <p
                    class="mt-1 max-w-2xl text-sm leading-relaxed text-surface-600 dark:text-surface-300"
                >
                    Visualisasi jadwal pengerjaan, durasi fase, dan capaian
                    tugas tim secara teratur dan berurutan.
                </p>
            </div>

            <!-- Header Quick Stats -->
            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    class="btn btn-secondary text-xs shadow-2xs"
                    @click="goToToday"
                >
                    <component :is="getIcon('Calendar')" class="h-3.5 w-3.5" />
                    <span>Hari Ini</span>
                </button>

                <Link
                    :href="projectRoutes.index.url()"
                    class="btn btn-primary text-xs shadow-2xs"
                >
                    <component :is="getIcon('FolderKanban')" class="h-4 w-4" />
                    <span>Daftar Proyek</span>
                </Link>
            </div>
        </header>

        <!-- 2. KPI Summary Bar -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
            <div
                class="rounded-2xl border border-surface-200/80 bg-white p-4 shadow-xs dark:border-surface-800 dark:bg-surface-900"
            >
                <div class="flex items-center justify-between text-surface-400">
                    <span
                        class="text-xs font-medium text-surface-500 dark:text-surface-400"
                        >Proyek Aktif</span
                    >
                    <component :is="getIcon('FolderKanban')" class="h-4 w-4" />
                </div>
                <p
                    class="mt-2 font-serif text-2xl font-normal text-surface-950 dark:text-surface-50"
                >
                    {{ summary.totalProjects }}
                </p>
            </div>

            <div
                class="rounded-2xl border border-surface-200/80 bg-white p-4 shadow-xs dark:border-surface-800 dark:bg-surface-900"
            >
                <div class="flex items-center justify-between text-surface-400">
                    <span
                        class="text-xs font-medium text-surface-500 dark:text-surface-400"
                        >Tugas Terjadwal</span
                    >
                    <component :is="getIcon('CheckSquare')" class="h-4 w-4" />
                </div>
                <p
                    class="mt-2 font-serif text-2xl font-normal text-surface-950 dark:text-surface-50"
                >
                    {{ summary.scheduledTasks }}
                </p>
            </div>

            <div
                class="rounded-2xl border border-surface-200/80 bg-white p-4 shadow-xs dark:border-surface-800 dark:bg-surface-900"
            >
                <div class="flex items-center justify-between text-surface-400">
                    <span
                        class="text-xs font-medium text-surface-500 dark:text-surface-400"
                        >Tugas Selesai</span
                    >
                    <component
                        :is="getIcon('CheckCircle2')"
                        class="h-4 w-4 text-emerald-600"
                    />
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <p
                        class="font-serif text-2xl font-normal text-surface-950 dark:text-surface-50"
                    >
                        {{ summary.completedTasks }}
                    </p>
                    <span class="text-xs font-semibold text-emerald-600">
                        ({{ summary.completionRate }}%)
                    </span>
                </div>
            </div>

            <div
                class="rounded-2xl border border-surface-200/80 bg-white p-4 shadow-xs dark:border-surface-800 dark:bg-surface-900"
            >
                <div class="flex items-center justify-between text-surface-400">
                    <span
                        class="text-xs font-medium text-surface-500 dark:text-surface-400"
                        >Lewat Tenggat</span
                    >
                    <component
                        :is="getIcon('AlertTriangle')"
                        class="h-4 w-4 text-rose-500"
                    />
                </div>
                <p
                    class="mt-2 font-serif text-2xl font-normal"
                    :class="
                        summary.overdueTasks > 0
                            ? 'text-rose-600'
                            : 'text-surface-950 dark:text-surface-50'
                    "
                >
                    {{ summary.overdueTasks }}
                </p>
            </div>
        </div>

        <!-- 3. Toolbar: Status Filter, Range Navigator & Search -->
        <div
            class="flex flex-col gap-3.5 rounded-3xl border border-surface-200/80 bg-white p-4 shadow-xs lg:flex-row lg:items-center lg:justify-between dark:border-surface-800 dark:bg-surface-900"
        >
            <!-- Left: Status Tabs -->
            <div class="no-scrollbar flex items-center gap-1.5 overflow-x-auto">
                <button
                    v-for="s in [
                        { val: 'all', label: 'Semua Status' },
                        { val: 'active', label: 'Aktif' },
                        { val: 'completed', label: 'Selesai' },
                        { val: 'on_hold', label: 'Ditahan' },
                        { val: 'draft', label: 'Draft' },
                    ]"
                    :key="s.val"
                    type="button"
                    class="shrink-0 rounded-xl px-3 py-1.5 text-xs font-semibold transition"
                    :class="
                        statusFilter === s.val
                            ? 'bg-[#0F5132] text-white shadow-2xs dark:bg-primary-600'
                            : 'bg-surface-100 text-surface-700 hover:bg-surface-200 dark:bg-surface-800 dark:text-surface-300'
                    "
                    @click="statusFilter = s.val"
                >
                    {{ s.label }}
                </button>
            </div>

            <!-- Right: Search, Date Range Shift, and Zoom Preset -->
            <div class="flex flex-wrap items-center gap-2.5">
                <!-- Search -->
                <div class="relative min-w-44 flex-1 sm:w-56 sm:flex-initial">
                    <span
                        class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-surface-400"
                    >
                        <component
                            :is="getIcon('Search')"
                            class="h-3.5 w-3.5"
                        />
                    </span>
                    <input
                        v-model="searchQuery"
                        type="search"
                        placeholder="Cari proyek / tugas..."
                        class="input py-1.5 pl-9 text-xs"
                    />
                </div>

                <!-- Range Mode (Kuartal / Bulan / Semua) -->
                <div
                    class="flex items-center rounded-xl border border-surface-200 bg-surface-50 p-0.5 dark:border-surface-700 dark:bg-surface-800"
                >
                    <button
                        type="button"
                        class="rounded-lg px-2.5 py-1 text-xs font-semibold transition"
                        :class="
                            rangeMode === 'quarter'
                                ? 'bg-white text-surface-900 shadow-2xs dark:bg-surface-900 dark:text-surface-100'
                                : 'text-surface-500'
                        "
                        @click="rangeMode = 'quarter'"
                    >
                        Kuartal
                    </button>
                    <button
                        type="button"
                        class="rounded-lg px-2.5 py-1 text-xs font-semibold transition"
                        :class="
                            rangeMode === 'month'
                                ? 'bg-white text-surface-900 shadow-2xs dark:bg-surface-900 dark:text-surface-100'
                                : 'text-surface-500'
                        "
                        @click="rangeMode = 'month'"
                    >
                        2 Bulan
                    </button>
                    <button
                        type="button"
                        class="rounded-lg px-2.5 py-1 text-xs font-semibold transition"
                        :class="
                            rangeMode === 'all'
                                ? 'bg-white text-surface-900 shadow-2xs dark:bg-surface-900 dark:text-surface-100'
                                : 'text-surface-500'
                        "
                        @click="rangeMode = 'all'"
                    >
                        Semua
                    </button>
                </div>

                <!-- Prev / Next Stepper -->
                <div v-if="rangeMode !== 'all'" class="flex items-center gap-1">
                    <button
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded-xl border border-surface-200 text-surface-600 transition hover:bg-surface-100 dark:border-surface-700 dark:text-surface-300 dark:hover:bg-surface-800"
                        @click="shiftTimeline(-1)"
                        aria-label="Rentang sebelumnya"
                    >
                        <component
                            :is="getIcon('ChevronLeft')"
                            class="h-4 w-4"
                        />
                    </button>
                    <button
                        type="button"
                        class="flex h-8 w-8 items-center justify-center rounded-xl border border-surface-200 text-surface-600 transition hover:bg-surface-100 dark:border-surface-700 dark:text-surface-300 dark:hover:bg-surface-800"
                        @click="shiftTimeline(1)"
                        aria-label="Rentang berikutnya"
                    >
                        <component
                            :is="getIcon('ChevronRight')"
                            class="h-4 w-4"
                        />
                    </button>
                </div>

                <!-- Expand/Collapse all -->
                <button
                    type="button"
                    class="rounded-xl border border-surface-200 px-2.5 py-1.5 text-xs font-semibold text-surface-600 transition hover:bg-surface-100 dark:border-surface-700 dark:text-surface-300 dark:hover:bg-surface-800"
                    @click="
                        Object.keys(expandedProjects).length > 0
                            ? collapseAll()
                            : expandAll()
                    "
                >
                    {{
                        Object.keys(expandedProjects).length > 0
                            ? 'Tutup Semua'
                            : 'Buka Semua'
                    }}
                </button>
            </div>
        </div>

        <!-- 4. Modern Structured Split Gantt Chart -->
        <section
            class="overflow-hidden rounded-3xl border border-surface-200/80 bg-white shadow-xs dark:border-surface-800 dark:bg-surface-900"
        >
            <div class="flex flex-col lg:flex-row">
                <!-- ============================================== -->
                <!-- LEFT PANE: Project & Task Tree Column          -->
                <!-- ============================================== -->
                <div
                    class="w-full shrink-0 border-b border-surface-200/80 lg:w-[360px] lg:border-r lg:border-b-0 dark:border-surface-800"
                >
                    <!-- Column Header -->
                    <div
                        class="flex h-14 items-center justify-between border-b border-surface-200/80 bg-surface-50/70 px-4 dark:border-surface-800 dark:bg-surface-950/40"
                    >
                        <span
                            class="text-xs font-bold tracking-wider text-surface-500 uppercase dark:text-surface-400"
                        >
                            Proyek & Rincian Tugas
                        </span>
                        <span
                            class="text-xs font-semibold text-surface-600 dark:text-surface-400"
                        >
                            {{ filteredProjects.length }} Proyek
                        </span>
                    </div>

                    <!-- Project Rows -->
                    <div
                        class="divide-y divide-surface-100 dark:divide-surface-800/60"
                    >
                        <template
                            v-for="project in filteredProjects"
                            :key="`project-row-${project.id}`"
                        >
                            <!-- Project Header Row -->
                            <div
                                class="group flex h-14 items-center justify-between px-3.5 transition-colors hover:bg-surface-50/80 dark:hover:bg-surface-800/40"
                                :class="
                                    selectedProject?.id === project.id
                                        ? 'bg-primary-50/40 dark:bg-primary-950/20'
                                        : ''
                                "
                                @click="selectedProject = project"
                            >
                                <div
                                    class="flex min-w-0 flex-1 items-center gap-2"
                                >
                                    <!-- Expand / Collapse button -->
                                    <button
                                        type="button"
                                        class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md text-surface-400 transition hover:bg-surface-200 hover:text-surface-700 dark:hover:bg-surface-700"
                                        @click.stop="
                                            toggleProjectExpand(project.id)
                                        "
                                    >
                                        <component
                                            :is="
                                                getIcon(
                                                    expandedProjects[project.id]
                                                        ? 'ChevronDown'
                                                        : 'ChevronRight',
                                                )
                                            "
                                            class="h-3.5 w-3.5"
                                        />
                                    </button>

                                    <!-- Status indicator dot -->
                                    <span
                                        class="h-2 w-2 shrink-0 rounded-full"
                                        :class="
                                            project.status === 'completed'
                                                ? 'bg-emerald-500'
                                                : project.status === 'active'
                                                  ? 'bg-[#0F5132] dark:bg-primary-400'
                                                  : 'bg-amber-500'
                                        "
                                    />

                                    <!-- Project Name -->
                                    <span
                                        class="truncate text-xs font-bold text-surface-900 group-hover:text-primary-700 dark:text-surface-100 dark:group-hover:text-primary-300"
                                        :title="project.name"
                                    >
                                        {{ project.name }}
                                    </span>
                                </div>

                                <!-- Tasks Count Pill -->
                                <span
                                    class="ml-2 shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold"
                                    :class="
                                        statusBadgeClasses[project.status] ??
                                        statusBadgeClasses.draft
                                    "
                                >
                                    {{ project.tasks.length }} tugas
                                </span>
                            </div>

                            <!-- Expanded Subtask Rows -->
                            <template v-if="expandedProjects[project.id]">
                                <div
                                    v-for="task in project.tasks"
                                    :key="`task-left-${task.id}`"
                                    class="flex h-11 items-center justify-between border-t border-surface-100/60 bg-surface-50/30 pr-3 pl-9 transition-colors hover:bg-surface-50 dark:border-surface-800/40 dark:bg-surface-950/20 dark:hover:bg-surface-800/30"
                                >
                                    <div
                                        class="flex min-w-0 flex-1 items-center gap-2"
                                    >
                                        <span
                                            class="h-1.5 w-1.5 shrink-0 rounded-full"
                                            :class="
                                                priorityDotColors[
                                                    task.priority
                                                ] ?? 'bg-surface-400'
                                            "
                                        />
                                        <Link
                                            v-if="canViewTasks"
                                            :href="taskRoutes.show.url(task.id)"
                                            class="truncate text-xs text-surface-700 transition hover:text-primary-700 dark:text-surface-300 dark:hover:text-primary-300"
                                            :class="
                                                task.status === 'done'
                                                    ? 'line-through opacity-60'
                                                    : ''
                                            "
                                            :title="task.title"
                                        >
                                            {{ task.title }}
                                        </Link>
                                        <span
                                            v-else
                                            class="truncate text-xs text-surface-700 dark:text-surface-300"
                                            :class="
                                                task.status === 'done'
                                                    ? 'line-through opacity-60'
                                                    : ''
                                            "
                                        >
                                            {{ task.title }}
                                        </span>
                                    </div>

                                    <!-- Assignee avatar -->
                                    <Avatar
                                        v-if="task.assignee"
                                        :src="task.assignee.avatar"
                                        :initials="task.assignee.initials"
                                        :alt="task.assignee.name"
                                        size="xs"
                                        class="ml-2 shrink-0"
                                    />
                                </div>
                            </template>
                        </template>

                        <div
                            v-if="filteredProjects.length === 0"
                            class="p-8 text-center text-xs text-surface-500"
                        >
                            Tidak ada proyek yang sesuai dengan filter.
                        </div>
                    </div>
                </div>

                <!-- ============================================== -->
                <!-- RIGHT PANE: Horizontal Gantt Canvas            -->
                <!-- ============================================== -->
                <div class="min-w-0 flex-1 overflow-x-auto">
                    <div class="min-w-[700px] select-none">
                        <!-- Canvas Header: Months scale -->
                        <div
                            class="relative flex h-14 border-b border-surface-200/80 bg-surface-50/70 dark:border-surface-800 dark:bg-surface-950/40"
                        >
                            <div
                                v-for="(m, idx) in timelineMonths"
                                :key="`header-month-${idx}`"
                                class="absolute top-0 bottom-0 flex flex-col justify-center border-l border-surface-200/80 px-3 dark:border-surface-800"
                                :style="{
                                    left: `${m.left}%`,
                                    width: `${m.width}%`,
                                }"
                            >
                                <span
                                    class="truncate text-xs font-bold text-surface-800 dark:text-surface-200"
                                >
                                    {{ m.name }}
                                </span>
                                <span class="text-[10px] text-surface-400">
                                    {{ m.year }}
                                </span>
                            </div>
                        </div>

                        <!-- Canvas Body Rows (Directly matching left rows height!) -->
                        <div
                            class="relative divide-y divide-surface-100 dark:divide-surface-800/60"
                        >
                            <!-- Background Vertical Grid Lines -->
                            <div
                                class="pointer-events-none absolute inset-0 z-0"
                                aria-hidden="true"
                            >
                                <div
                                    v-for="(m, idx) in timelineMonths"
                                    :key="`grid-month-${idx}`"
                                    class="absolute inset-y-0 border-l border-surface-200/50 dark:border-surface-800/50"
                                    :style="{ left: `${m.left}%` }"
                                />

                                <!-- Today Vertical Line -->
                                <div
                                    v-if="todayMarker !== null"
                                    class="absolute inset-y-0 z-10 w-0.5 bg-rose-500 dark:bg-rose-400"
                                    :style="{ left: `${todayMarker}%` }"
                                >
                                    <span
                                        class="absolute -top-1 -left-1.5 h-3.5 w-3.5 rounded-full bg-rose-500 ring-2 ring-white dark:ring-surface-900"
                                        title="Hari ini"
                                    />
                                </div>
                            </div>

                            <!-- Gantt Bars for each project -->
                            <template
                                v-for="project in filteredProjects"
                                :key="`project-canvas-row-${project.id}`"
                            >
                                <!-- Project Bar Row (h-14) -->
                                <div
                                    class="relative h-14 transition-colors hover:bg-surface-50/50 dark:hover:bg-surface-800/20"
                                    :class="
                                        selectedProject?.id === project.id
                                            ? 'bg-primary-50/20'
                                            : ''
                                    "
                                    @click="selectedProject = project"
                                >
                                    <!-- Render project Gantt bar -->
                                    <div
                                        v-if="
                                            getBarCoords(
                                                project.start_date,
                                                project.end_date,
                                            ).isVisible
                                        "
                                        class="group absolute top-2.5 h-9 overflow-hidden rounded-xl bg-[#0F5132] px-3 text-white shadow-xs transition hover:brightness-110 dark:bg-primary-600"
                                        :style="{
                                            left: `${getBarCoords(project.start_date, project.end_date).left}%`,
                                            width: `${getBarCoords(project.start_date, project.end_date).width}%`,
                                            minWidth: '40px',
                                        }"
                                        :title="`${project.name} (${project.start_date || '—'} s/d ${project.end_date || '—'})`"
                                    >
                                        <!-- Internal Progress Bar Fill -->
                                        <div
                                            class="absolute top-0 bottom-0 left-0 bg-white/20 transition-all duration-500"
                                            :style="{
                                                width: `${projectCompletion(project)}%`,
                                            }"
                                        />

                                        <div
                                            class="relative z-10 flex h-full items-center justify-between gap-2"
                                        >
                                            <span
                                                class="truncate text-xs font-semibold"
                                            >
                                                {{ project.name }}
                                            </span>
                                            <span
                                                class="shrink-0 text-[10px] font-bold opacity-80"
                                            >
                                                {{
                                                    projectCompletion(project)
                                                }}%
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Expanded Tasks Gantt Rows (h-11) -->
                                <template v-if="expandedProjects[project.id]">
                                    <div
                                        v-for="task in project.tasks"
                                        :key="`task-canvas-${task.id}`"
                                        class="relative h-11 transition-colors hover:bg-surface-50/50 dark:hover:bg-surface-800/20"
                                    >
                                        <div
                                            v-if="
                                                getBarCoords(
                                                    task.start_date,
                                                    task.due_date,
                                                ).isVisible
                                            "
                                            class="absolute top-2 h-7 overflow-hidden rounded-lg px-2.5 shadow-2xs transition hover:scale-[1.01]"
                                            :class="
                                                priorityBarColors[
                                                    task.priority
                                                ] ?? 'bg-surface-600 text-white'
                                            "
                                            :style="{
                                                left: `${getBarCoords(task.start_date, task.due_date).left}%`,
                                                width: `${getBarCoords(task.start_date, task.due_date).width}%`,
                                                minWidth: '28px',
                                            }"
                                            :title="`${task.title} (${task.start_date || '—'} s/d ${task.due_date || '—'})`"
                                        >
                                            <div
                                                class="flex h-full items-center justify-between gap-1.5"
                                            >
                                                <span
                                                    class="truncate text-[11px] font-medium"
                                                >
                                                    {{ task.title }}
                                                </span>
                                                <span
                                                    class="shrink-0 text-[9px] font-bold uppercase opacity-80"
                                                >
                                                    {{ task.priority_label }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 5. Selected Project Inspector Drawer / Focus Card -->
        <div
            v-if="selectedProject"
            class="rounded-3xl border border-surface-200/80 bg-white p-5 shadow-xs transition sm:p-6 dark:border-surface-800 dark:bg-surface-900"
        >
            <div
                class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
            >
                <div>
                    <span
                        class="text-[10px] font-bold tracking-wider text-surface-400 uppercase"
                    >
                        Fokus Proyek Terpilih
                    </span>
                    <h2
                        class="mt-1 text-lg font-semibold text-surface-950 sm:text-xl dark:text-surface-50"
                    >
                        {{ selectedProject.name }}
                    </h2>
                    <p
                        class="mt-0.5 text-xs text-surface-500 dark:text-surface-400"
                    >
                        Rentang:
                        {{
                            selectedProject.start_date
                                ? formatShortDate(
                                      parseIso(selectedProject.start_date),
                                  )
                                : '—'
                        }}
                        s/d
                        {{
                            selectedProject.end_date
                                ? formatShortDate(
                                      parseIso(selectedProject.end_date),
                                  )
                                : '—'
                        }}
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <span
                        class="rounded-full px-2.5 py-1 text-xs font-bold"
                        :class="
                            statusBadgeClasses[selectedProject.status] ??
                            statusBadgeClasses.draft
                        "
                    >
                        {{ selectedProject.status_label }}
                    </span>
                    <Link
                        :href="projectRoutes.show.url(selectedProject.id)"
                        class="btn btn-secondary text-xs"
                    >
                        <span>Buka Detail Proyek</span>
                        <component
                            :is="getIcon('ArrowRight')"
                            class="h-3.5 w-3.5"
                        />
                    </Link>
                </div>
            </div>

            <!-- Tasks list preview in the selected project -->
            <div
                class="mt-5 border-t border-surface-100 pt-4 dark:border-surface-800"
            >
                <h3
                    class="mb-3 text-xs font-bold tracking-wider text-surface-500 uppercase dark:text-surface-400"
                >
                    Daftar Tugas dalam Proyek Ini ({{
                        selectedProject.tasks.length
                    }})
                </h3>
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="task in selectedProject.tasks"
                        :key="`preview-task-${task.id}`"
                        class="flex items-center justify-between rounded-2xl border border-surface-200/70 bg-surface-50/50 p-3 dark:border-surface-800 dark:bg-surface-950/30"
                    >
                        <div class="min-w-0 flex-1 pr-2">
                            <p
                                class="truncate text-xs font-semibold text-surface-900 dark:text-surface-100"
                            >
                                {{ task.title }}
                            </p>
                            <p class="mt-0.5 text-[10px] text-surface-400">
                                Tenggat:
                                {{
                                    task.due_date
                                        ? formatShortDate(
                                              parseIso(task.due_date),
                                          )
                                        : '—'
                                }}
                            </p>
                        </div>
                        <span
                            class="shrink-0 rounded-full px-2 py-0.5 text-[9px] font-bold uppercase"
                            :class="
                                priorityDotColors[task.priority]
                                    ? 'text-white ' +
                                      priorityDotColors[task.priority]
                                    : 'bg-surface-200 text-surface-700'
                            "
                        >
                            {{ task.priority_label }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
