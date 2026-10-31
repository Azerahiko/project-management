<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Avatar from '@/components/ui/Avatar.vue';
import Button from '@/components/ui/Button.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Select from '@/components/ui/Select.vue';
import { getIcon } from '@/components/sidebar/icons';
import taskRoutes from '@/routes/tasks';
import { CalendarRange, ChevronLeft, ChevronRight } from '@lucide/vue';

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

interface TimelineTaskItem extends TimelineTask {
    project_id: number;
    project_name: string;
    project_status: string;
    project_status_label: string;
}

interface BoardDay {
    date: Date;
    iso: string;
    weekday: string;
    day: number;
    monthShort: string;
    isToday: boolean;
    isWeekend: boolean;
    isCurrentMonth?: boolean;
}

type ViewMode = 'week' | 'month';

const page = usePage();
const projects = computed(
    () => (page.props.projects as TimelineProject[]) ?? [],
);
const canViewTasks = computed(() => Boolean(page.props.can_view_tasks));

const today = new Date();
today.setHours(0, 0, 0, 0);

const cursor = ref(new Date(today));
const mode = ref<ViewMode>('week');
const projectFilter = ref('');
const statusFilter = ref('');

const weekdays = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];

const projectOptions = computed(() => [
    { value: '', label: 'Semua proyek' },
    ...projects.value.map((project) => ({
        value: String(project.id),
        label: project.name,
    })),
]);

const statusOptions = [
    { value: '', label: 'Semua status' },
    { value: 'todo', label: 'To Do' },
    { value: 'in_progress', label: 'Sedang Dikerjakan' },
    { value: 'review', label: 'Review' },
    { value: 'done', label: 'Selesai' },
];

const legend = [
    { label: 'To Do', dot: 'bg-[#e7dfcd]' },
    { label: 'Dikerjakan', dot: 'bg-[#cbdcf0]' },
    { label: 'Review', dot: 'bg-[#f0cedb]' },
    { label: 'Selesai', dot: 'bg-[#c4dfcd]' },
];

function pad(value: number): string {
    return String(value).padStart(2, '0');
}

function toIso(date: Date): string {
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

function parseIso(value: string): Date {
    const [year, month, day] = value.split('-').map(Number);
    return new Date(year, month - 1, day);
}

function addDays(date: Date, amount: number): Date {
    const result = new Date(date);
    result.setDate(result.getDate() + amount);
    result.setHours(0, 0, 0, 0);

    return result;
}

function startOfWeek(date: Date): Date {
    const result = new Date(date);
    const offset = (result.getDay() + 6) % 7;
    result.setDate(result.getDate() - offset);
    result.setHours(0, 0, 0, 0);

    return result;
}

function isSameDay(first: Date, second: Date): boolean {
    return (
        first.getFullYear() === second.getFullYear() &&
        first.getMonth() === second.getMonth() &&
        first.getDate() === second.getDate()
    );
}

function formatShortDate(date: Date): string {
    return date.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
    });
}

const allTasks = computed<TimelineTaskItem[]>(() =>
    projects.value.flatMap((project) =>
        project.tasks.map((task) => ({
            ...task,
            project_id: project.id,
            project_name: project.name,
            project_status: project.status,
            project_status_label: project.status_label,
        })),
    ),
);

const filteredTasks = computed(() =>
    allTasks.value.filter((task) => {
        const matchesProject =
            !projectFilter.value ||
            String(task.project_id) === projectFilter.value;
        const matchesStatus =
            !statusFilter.value || task.status === statusFilter.value;

        return matchesProject && matchesStatus;
    }),
);

function taskDate(task: TimelineTaskItem): Date | null {
    const raw = task.due_date ?? task.start_date;

    return raw ? parseIso(raw) : null;
}

function taskIso(task: TimelineTaskItem): string | null {
    const date = taskDate(task);

    return date ? toIso(date) : null;
}

const statusOrder: Record<string, number> = {
    in_progress: 0,
    review: 1,
    todo: 2,
    done: 3,
};

const priorityOrder: Record<string, number> = {
    urgent: 0,
    high: 1,
    medium: 2,
    low: 3,
};

function compareTasks(
    first: TimelineTaskItem,
    second: TimelineTaskItem,
): number {
    return (
        (statusOrder[first.status] ?? 9) - (statusOrder[second.status] ?? 9) ||
        (priorityOrder[first.priority] ?? 9) -
            (priorityOrder[second.priority] ?? 9) ||
        first.title.localeCompare(second.title)
    );
}

const tasksByIso = computed(() => {
    const map = new Map<string, TimelineTaskItem[]>();

    for (const task of filteredTasks.value) {
        const iso = taskIso(task);

        if (!iso) {
            continue;
        }

        const list = map.get(iso) ?? [];
        list.push(task);
        map.set(iso, list);
    }

    for (const list of map.values()) {
        list.sort(compareTasks);
    }

    return map;
});

function tasksForDay(day: BoardDay): TimelineTaskItem[] {
    return tasksByIso.value.get(day.iso) ?? [];
}

const weekStart = computed(() => startOfWeek(cursor.value));

const weekDays = computed<BoardDay[]>(() =>
    Array.from({ length: 7 }, (_, index) => {
        const date = addDays(weekStart.value, index);

        return {
            date,
            iso: toIso(date),
            weekday: date
                .toLocaleDateString('id-ID', { weekday: 'short' })
                .toUpperCase(),
            day: date.getDate(),
            monthShort: date.toLocaleDateString('id-ID', { month: 'short' }),
            isToday: isSameDay(date, today),
            isWeekend: date.getDay() === 0 || date.getDay() === 6,
        };
    }),
);

const monthStart = computed(
    () => new Date(cursor.value.getFullYear(), cursor.value.getMonth(), 1),
);

const monthLabel = computed(() =>
    cursor.value.toLocaleDateString('id-ID', {
        month: 'long',
        year: 'numeric',
    }),
);

const monthDays = computed<BoardDay[]>(() => {
    const first = monthStart.value;
    const gridStart = startOfWeek(first);
    const offset = Math.round(
        (first.getTime() - gridStart.getTime()) / 86_400_000,
    );
    const daysInMonth = new Date(
        first.getFullYear(),
        first.getMonth() + 1,
        0,
    ).getDate();
    const totalDays = Math.ceil((offset + daysInMonth) / 7) * 7;

    return Array.from({ length: totalDays }, (_, index) => {
        const date = addDays(gridStart, index);

        return {
            date,
            iso: toIso(date),
            weekday: date
                .toLocaleDateString('id-ID', { weekday: 'short' })
                .toUpperCase(),
            day: date.getDate(),
            monthShort: date.toLocaleDateString('id-ID', { month: 'short' }),
            isToday: isSameDay(date, today),
            isWeekend: date.getDay() === 0 || date.getDay() === 6,
            isCurrentMonth: date.getMonth() === first.getMonth(),
        };
    });
});

const rangeStart = computed(() =>
    mode.value === 'week' ? weekStart.value : monthStart.value,
);

const rangeEnd = computed(() =>
    mode.value === 'week'
        ? addDays(weekStart.value, 6)
        : new Date(
              monthStart.value.getFullYear(),
              monthStart.value.getMonth() + 1,
              0,
          ),
);

const rangeTasks = computed(() => {
    const start = rangeStart.value.getTime();
    const end = rangeEnd.value.getTime();

    return filteredTasks.value.filter((task) => {
        const date = taskDate(task);

        return (
            date !== null && date.getTime() >= start && date.getTime() <= end
        );
    });
});

const rangeStats = computed(() => ({
    total: rangeTasks.value.length,
    inProgress: rangeTasks.value.filter((task) => task.status === 'in_progress')
        .length,
    overdue: rangeTasks.value.filter(
        (task) => task.is_overdue && task.status !== 'done',
    ).length,
}));

const rangeLabel = computed(() => {
    if (mode.value === 'month') {
        return monthLabel.value;
    }

    const start = weekStart.value;
    const end = addDays(start, 6);
    const sameMonth =
        start.getMonth() === end.getMonth() &&
        start.getFullYear() === end.getFullYear();

    if (sameMonth) {
        return `${start.getDate()} – ${end.getDate()} ${end.toLocaleDateString(
            'id-ID',
            { month: 'short', year: 'numeric' },
        )}`;
    }

    return `${start.getDate()} ${start.toLocaleDateString('id-ID', {
        month: 'short',
    })} – ${end.getDate()} ${end.toLocaleDateString('id-ID', {
        month: 'short',
        year: 'numeric',
    })}`;
});

function shiftRange(offset: number): void {
    if (mode.value === 'week') {
        cursor.value = addDays(cursor.value, offset * 7);

        return;
    }

    cursor.value = new Date(
        cursor.value.getFullYear(),
        cursor.value.getMonth() + offset,
        1,
    );
}

function goToToday(): void {
    cursor.value = new Date(today);
}

function openWeek(date: Date): void {
    cursor.value = new Date(date);
    mode.value = 'week';
}

function resetFilters(): void {
    projectFilter.value = '';
    statusFilter.value = '';
}

function formatTaskRange(task: TimelineTaskItem): string {
    const start = task.start_date ? parseIso(task.start_date) : null;
    const end = task.due_date ? parseIso(task.due_date) : null;

    if (start && end) {
        if (isSameDay(start, end)) {
            return formatShortDate(end);
        }

        return `${formatShortDate(start)} – ${formatShortDate(end)}`;
    }

    const single = end ?? start;

    return single ? formatShortDate(single) : 'Tanpa tanggal';
}

function cardTone(task: TimelineTaskItem): string {
    switch (task.status) {
        case 'in_progress':
            return 'border-[#cbdcf0] bg-[#dfeaf8] text-[#1d3b5c] hover:border-[#aac8e6] dark:border-primary-700/40 dark:bg-primary-900/30 dark:text-primary-100';
        case 'review':
            return 'border-[#f0cedb] bg-[#f9dfe8] text-[#652840] hover:border-[#e5aec3] dark:border-rose-800/40 dark:bg-rose-950/30 dark:text-rose-100';
        case 'done':
            return 'border-[#c4dfcd] bg-[#dcefe2] text-[#1c4a30] hover:border-[#a4cbb2] dark:border-emerald-800/40 dark:bg-emerald-950/30 dark:text-emerald-100';
        default:
            return 'border-[#e7dfcd] bg-[#f6f0e2] text-surface-800 hover:border-[#d6c9ae] dark:border-surface-700 dark:bg-surface-800 dark:text-surface-100';
    }
}

function miniTone(task: TimelineTaskItem): string {
    switch (task.status) {
        case 'in_progress':
            return 'bg-[#dfeaf8] text-[#1d3b5c] dark:bg-primary-900/40 dark:text-primary-100';
        case 'review':
            return 'bg-[#f9dfe8] text-[#652840] dark:bg-rose-950/40 dark:text-rose-100';
        case 'done':
            return 'bg-[#dcefe2] text-[#1c4a30] dark:bg-emerald-950/40 dark:text-emerald-100';
        default:
            return 'bg-[#f6f0e2] text-surface-700 dark:bg-surface-800 dark:text-surface-200';
    }
}

function cardIcon(task: TimelineTaskItem): string {
    switch (task.status) {
        case 'in_progress':
            return 'Clock';
        case 'review':
            return 'Eye';
        case 'done':
            return 'CheckCircle2';
        default:
            return 'CheckSquare';
    }
}
</script>

<template>
    <div class="space-y-5">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
        >
            <div>
                <span
                    class="inline-flex h-6 items-center gap-1.5 rounded-full border border-primary-200 bg-primary-50 px-2.5 text-[10px] font-bold tracking-wider text-primary-700 uppercase dark:border-primary-700/40 dark:bg-primary-900/25 dark:text-primary-200"
                >
                    <CalendarRange class="h-3 w-3" />
                    Perencanaan
                </span>
                <h1
                    class="mt-2 text-2xl font-semibold tracking-tight text-surface-950 sm:text-3xl dark:text-surface-50"
                >
                    Timeline
                </h1>
                <p
                    class="mt-1 max-w-2xl text-sm leading-6 text-surface-700 dark:text-surface-200"
                >
                    Lihat jadwal, rentang waktu, dan beban kerja tim dalam
                    tampilan mingguan yang rapi.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div
                    class="inline-flex items-center rounded-full border border-surface-200 bg-white p-1 shadow-xs dark:border-surface-700 dark:bg-surface-900"
                >
                    <button
                        type="button"
                        class="grid h-8 w-8 place-items-center rounded-full text-surface-500 transition hover:bg-surface-100 hover:text-surface-900 dark:text-surface-400 dark:hover:bg-surface-800 dark:hover:text-white"
                        aria-label="Rentang sebelumnya"
                        @click="shiftRange(-1)"
                    >
                        <ChevronLeft class="h-4 w-4" />
                    </button>
                    <span
                        class="min-w-40 px-2 text-center text-sm font-semibold text-surface-900 dark:text-surface-100"
                    >
                        {{ rangeLabel }}
                    </span>
                    <button
                        type="button"
                        class="grid h-8 w-8 place-items-center rounded-full text-surface-500 transition hover:bg-surface-100 hover:text-surface-900 dark:text-surface-400 dark:hover:bg-surface-800 dark:hover:text-white"
                        aria-label="Rentang berikutnya"
                        @click="shiftRange(1)"
                    >
                        <ChevronRight class="h-4 w-4" />
                    </button>
                </div>

                <div
                    class="inline-flex items-center rounded-full border border-surface-200 bg-white p-1 shadow-xs dark:border-surface-700 dark:bg-surface-900"
                >
                    <button
                        type="button"
                        class="rounded-full px-3 py-1.5 text-xs font-semibold text-surface-600 transition hover:text-surface-950 dark:text-surface-300 dark:hover:text-white"
                        @click="goToToday"
                    >
                        Hari ini
                    </button>
                    <button
                        type="button"
                        class="rounded-full px-3 py-1.5 text-xs font-semibold transition"
                        :class="
                            mode === 'week'
                                ? 'bg-surface-950 text-white shadow-sm dark:bg-white dark:text-surface-950'
                                : 'text-surface-600 hover:text-surface-950 dark:text-surface-300 dark:hover:text-white'
                        "
                        :aria-pressed="mode === 'week'"
                        @click="mode = 'week'"
                    >
                        Minggu
                    </button>
                    <button
                        type="button"
                        class="rounded-full px-3 py-1.5 text-xs font-semibold transition"
                        :class="
                            mode === 'month'
                                ? 'bg-surface-950 text-white shadow-sm dark:bg-white dark:text-surface-950'
                                : 'text-surface-600 hover:text-surface-950 dark:text-surface-300 dark:hover:text-white'
                        "
                        :aria-pressed="mode === 'month'"
                        @click="mode = 'month'"
                    >
                        Bulan
                    </button>
                </div>
            </div>
        </div>

        <!-- Filters & summary -->
        <div
            class="flex flex-col gap-3 rounded-2xl border border-surface-200 bg-white p-3 shadow-xs sm:flex-row sm:items-center dark:border-surface-700 dark:bg-surface-900"
        >
            <div class="grid grid-cols-2 gap-2 sm:flex">
                <Select
                    v-model="projectFilter"
                    :options="projectOptions"
                    class="w-full sm:w-52"
                />
                <Select
                    v-model="statusFilter"
                    :options="statusOptions"
                    class="w-full sm:w-44"
                />
            </div>

            <div
                class="flex flex-wrap items-center gap-2 text-[11px] font-semibold sm:ml-auto"
            >
                <span
                    class="inline-flex items-center gap-1.5 rounded-full bg-surface-100 px-2.5 py-1 text-surface-700 dark:bg-surface-800 dark:text-surface-200"
                >
                    <component :is="getIcon('CheckSquare')" class="h-3 w-3" />
                    {{ rangeStats.total }} tugas
                </span>
                <span
                    class="inline-flex items-center gap-1.5 rounded-full bg-primary-50 px-2.5 py-1 text-primary-700 dark:bg-primary-900/30 dark:text-primary-200"
                >
                    <span class="h-1.5 w-1.5 rounded-full bg-primary-500" />
                    {{ rangeStats.inProgress }} dikerjakan
                </span>
                <span
                    v-if="rangeStats.overdue > 0"
                    class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-2.5 py-1 text-rose-700 dark:bg-rose-950/30 dark:text-rose-300"
                >
                    <span class="h-1.5 w-1.5 rounded-full bg-rose-500" />
                    {{ rangeStats.overdue }} terlambat
                </span>
            </div>
        </div>

        <!-- Board -->
        <div v-if="filteredTasks.length > 0" class="space-y-3">
            <!-- Week view -->
            <div
                v-if="mode === 'week'"
                class="overflow-hidden rounded-[28px] border border-surface-200/80 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900"
            >
                <div class="overflow-x-auto">
                    <div class="min-w-[920px]">
                        <div
                            class="grid grid-cols-7 border-b border-surface-200/80 dark:border-surface-800"
                        >
                            <div
                                v-for="day in weekDays"
                                :key="day.iso"
                                class="border-r border-surface-100 px-2 py-3 text-center last:border-r-0 dark:border-surface-800/70"
                                :class="
                                    day.isToday
                                        ? 'bg-surface-950 dark:bg-white'
                                        : day.isWeekend
                                          ? 'bg-surface-50/80 dark:bg-surface-950/30'
                                          : ''
                                "
                            >
                                <p
                                    class="text-[10px] font-bold tracking-wider uppercase"
                                    :class="
                                        day.isToday
                                            ? 'text-white/70 dark:text-surface-900/60'
                                            : 'text-surface-400'
                                    "
                                >
                                    {{ day.weekday }}
                                </p>
                                <p
                                    class="mt-1 text-lg font-bold"
                                    :class="
                                        day.isToday
                                            ? 'text-white dark:text-surface-950'
                                            : day.isWeekend
                                              ? 'text-rose-400'
                                              : 'text-surface-900 dark:text-surface-100'
                                    "
                                >
                                    {{ day.day }}
                                </p>
                                <p
                                    class="text-[10px]"
                                    :class="
                                        day.isToday
                                            ? 'text-white/60 dark:text-surface-900/60'
                                            : 'text-surface-400'
                                    "
                                >
                                    {{ day.monthShort }}
                                </p>
                            </div>
                        </div>

                        <div
                            class="grid grid-cols-7 bg-surface-50/50 dark:bg-surface-950/20"
                        >
                            <div
                                v-for="day in weekDays"
                                :key="`body-${day.iso}`"
                                class="min-h-[440px] border-r border-surface-100 p-2.5 last:border-r-0 dark:border-surface-800/60"
                                :class="
                                    day.isWeekend
                                        ? 'bg-surface-100/40 dark:bg-surface-950/30'
                                        : ''
                                "
                            >
                                <div class="flex flex-col gap-2.5">
                                    <component
                                        :is="canViewTasks ? Link : 'div'"
                                        v-for="task in tasksForDay(day)"
                                        :key="task.id"
                                        :href="
                                            canViewTasks
                                                ? taskRoutes.show.url(task.id)
                                                : undefined
                                        "
                                        class="group relative block rounded-2xl border p-3 shadow-xs transition-all hover:-translate-y-0.5 hover:shadow-md"
                                        :class="[
                                            cardTone(task),
                                            task.is_overdue &&
                                            task.status !== 'done'
                                                ? 'ring-1 ring-rose-300/80 dark:ring-rose-500/40'
                                                : '',
                                        ]"
                                        :title="`${task.title} · ${task.project_name}`"
                                    >
                                        <div class="flex items-start gap-2">
                                            <span
                                                class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-white/60 ring-1 ring-white/50 dark:bg-white/10 dark:ring-white/10"
                                            >
                                                <component
                                                    :is="
                                                        getIcon(cardIcon(task))
                                                    "
                                                    class="h-3.5 w-3.5"
                                                />
                                            </span>
                                            <div class="min-w-0 flex-1">
                                                <p
                                                    class="truncate text-[10px] font-bold tracking-wide uppercase opacity-60"
                                                >
                                                    {{ task.project_name }}
                                                </p>
                                                <p
                                                    class="mt-0.5 line-clamp-2 text-[13px] leading-snug font-semibold"
                                                >
                                                    {{ task.title }}
                                                </p>
                                            </div>
                                        </div>

                                        <p
                                            class="mt-2 text-[10px] font-medium opacity-70"
                                        >
                                            {{ formatTaskRange(task) }}
                                        </p>

                                        <div
                                            class="mt-3 flex items-center justify-between gap-2"
                                        >
                                            <div
                                                class="flex min-w-0 items-center gap-1.5"
                                            >
                                                <Avatar
                                                    v-if="task.assignee"
                                                    :src="task.assignee.avatar"
                                                    :initials="
                                                        task.assignee.initials
                                                    "
                                                    :alt="task.assignee.name"
                                                    size="xs"
                                                />
                                                <span
                                                    v-else
                                                    class="grid h-6 w-6 place-items-center rounded-full bg-white/60 text-[9px] font-bold opacity-70 dark:bg-white/10"
                                                >
                                                    —
                                                </span>
                                                <span
                                                    class="truncate text-[10px] font-semibold opacity-80"
                                                >
                                                    {{
                                                        task.assignee?.name ??
                                                        'Belum ditugaskan'
                                                    }}
                                                </span>
                                            </div>

                                            <span
                                                v-if="
                                                    task.status ===
                                                    'in_progress'
                                                "
                                                class="shrink-0 rounded-full bg-white/70 px-2 py-0.5 text-[9px] font-bold text-primary-700 dark:bg-white/15 dark:text-white"
                                            >
                                                Proses
                                            </span>
                                            <span
                                                v-else-if="
                                                    task.is_overdue &&
                                                    task.status !== 'done'
                                                "
                                                class="shrink-0 rounded-full bg-rose-100 px-2 py-0.5 text-[9px] font-bold text-rose-700 dark:bg-rose-900/40 dark:text-rose-200"
                                            >
                                                Terlambat
                                            </span>
                                            <span
                                                v-else-if="
                                                    task.status === 'done'
                                                "
                                                class="shrink-0 rounded-full bg-white/70 px-2 py-0.5 text-[9px] font-bold text-emerald-700 dark:bg-white/15 dark:text-white"
                                            >
                                                Selesai
                                            </span>
                                        </div>
                                    </component>

                                    <div
                                        v-if="tasksForDay(day).length === 0"
                                        class="flex h-24 flex-col items-center justify-center gap-1 rounded-2xl border border-dashed border-surface-200/80 text-center dark:border-surface-800"
                                    >
                                        <span
                                            class="h-1.5 w-1.5 rounded-full bg-surface-300 dark:bg-surface-600"
                                        />
                                        <p
                                            class="text-[10px] font-medium text-surface-400"
                                        >
                                            Tidak ada tugas
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Month view -->
            <div
                v-else
                class="overflow-hidden rounded-[28px] border border-surface-200/80 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900"
            >
                <div class="overflow-x-auto">
                    <div class="min-w-[920px]">
                        <div
                            class="grid grid-cols-7 border-b border-surface-200/80 bg-surface-50/70 dark:border-surface-800 dark:bg-surface-950/30"
                        >
                            <div
                                v-for="weekday in weekdays"
                                :key="weekday"
                                class="px-2 py-2.5 text-center text-[10px] font-bold tracking-wider text-surface-400 uppercase"
                            >
                                {{ weekday }}
                            </div>
                        </div>

                        <div class="grid grid-cols-7">
                            <div
                                v-for="day in monthDays"
                                :key="day.iso"
                                class="min-h-[126px] border-r border-b border-surface-100 p-2 last:border-r-0 dark:border-surface-800/60"
                                :class="
                                    !day.isCurrentMonth
                                        ? 'bg-surface-50/60 dark:bg-surface-950/30'
                                        : day.isWeekend
                                          ? 'bg-surface-50/40 dark:bg-surface-950/10'
                                          : ''
                                "
                            >
                                <div class="flex items-center justify-between">
                                    <button
                                        type="button"
                                        class="grid h-6 min-w-6 place-items-center rounded-full px-1 text-xs font-bold transition"
                                        :class="
                                            day.isToday
                                                ? 'bg-surface-950 text-white dark:bg-white dark:text-surface-950'
                                                : day.isCurrentMonth
                                                  ? 'text-surface-700 hover:bg-surface-100 dark:text-surface-200 dark:hover:bg-surface-800'
                                                  : 'text-surface-300 dark:text-surface-600'
                                        "
                                        @click="openWeek(day.date)"
                                    >
                                        {{ day.day }}
                                    </button>
                                    <span
                                        v-if="tasksForDay(day).length"
                                        class="text-[9px] font-semibold text-surface-400"
                                    >
                                        {{ tasksForDay(day).length }}
                                    </span>
                                </div>

                                <div class="mt-1.5 space-y-1">
                                    <component
                                        :is="canViewTasks ? Link : 'div'"
                                        v-for="task in tasksForDay(day).slice(
                                            0,
                                            2,
                                        )"
                                        :key="task.id"
                                        :href="
                                            canViewTasks
                                                ? taskRoutes.show.url(task.id)
                                                : undefined
                                        "
                                        class="block truncate rounded-md px-1.5 py-1 text-[10px] font-semibold transition hover:brightness-95"
                                        :class="miniTone(task)"
                                        :title="task.title"
                                    >
                                        {{ task.title }}
                                    </component>
                                    <button
                                        v-if="tasksForDay(day).length > 2"
                                        type="button"
                                        class="px-1.5 text-[10px] font-bold text-surface-500 transition hover:text-primary-700 dark:text-surface-400 dark:hover:text-primary-300"
                                        @click="openWeek(day.date)"
                                    >
                                        +{{ tasksForDay(day).length - 2 }} lagi
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div
                class="flex flex-wrap items-center gap-3 text-[11px] font-medium text-surface-500 dark:text-surface-400"
            >
                <span
                    v-for="item in legend"
                    :key="item.label"
                    class="inline-flex items-center gap-1.5"
                >
                    <span class="h-2.5 w-2.5 rounded-full" :class="item.dot" />
                    {{ item.label }}
                </span>
                <span class="ml-auto hidden sm:inline">
                    Klik kartu untuk membuka detail tugas
                </span>
            </div>
        </div>

        <EmptyState
            v-else
            icon="Calendar"
            title="Belum ada tugas di rentang ini"
            :description="
                projectFilter || statusFilter
                    ? 'Tidak ada tugas yang cocok dengan filter saat ini. Coba ubah filter proyek atau status.'
                    : 'Tambahkan tanggal mulai dan tenggat pada tugas, atau pindah ke minggu atau bulan lain untuk melihat jadwal proyek.'
            "
        >
            <div class="mt-5 flex flex-wrap gap-2">
                <Button
                    v-if="projectFilter || statusFilter"
                    variant="secondary"
                    @click="resetFilters"
                >
                    Reset filter
                </Button>
                <Button variant="primary" @click="goToToday">
                    Kembali ke hari ini
                </Button>
            </div>
        </EmptyState>
    </div>
</template>
