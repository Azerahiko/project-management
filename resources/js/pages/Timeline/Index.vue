<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import projectRoutes from '@/routes/projects';
import taskRoutes from '@/routes/tasks';
import {
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    CircleDot,
    ExternalLink,
    Flag,
    Layers3,
    MoveRight,
} from '@lucide/vue';

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

interface Journey {
    project: TimelineProject;
    start: Date;
    end: Date;
    tasks: TimelineTask[];
    doneCount: number;
    overdueCount: number;
    progress: number;
}

interface BarLayout {
    journey: Journey;
    lane: number;
    left: number;
    width: number;
    clippedStart: boolean;
    clippedEnd: boolean;
}

interface DeliverableBlock {
    journey: Journey;
    row: number;
    left: number;
    width: number;
    visibleTasks: TimelineTask[];
    extraTasks: number;
}

interface RangeBounds {
    start: Date;
    end: Date;
}

interface Tick {
    date: Date;
    left: number;
    label: string;
    isFirst: boolean;
    isLast: boolean;
}

interface MonthGroup {
    label: string;
    left: number;
    width: number;
}

const page = usePage();
const projects = computed(
    () => (page.props.projects as TimelineProject[]) ?? [],
);
const canViewTasks = computed(() => Boolean(page.props.can_view_tasks));

const today = new Date();
today.setHours(0, 0, 0, 0);

const cursor = ref(new Date(today.getFullYear(), today.getMonth(), 1));
const rangePreset = ref<'quarter' | 'all'>('quarter');
const statusFilter = ref('all');
const selectedProjectId = ref<number | null>(null);

const CHART_INSET = 2;
const DELIVERABLE_ROW_HEIGHT = 154;

const statusOrder = [
    { value: 'all', label: 'Semua', dot: 'bg-surface-400' },
    { value: 'active', label: 'Aktif', dot: 'bg-primary-700' },
    { value: 'on_hold', label: 'Ditahan', dot: 'bg-accent-500' },
    { value: 'draft', label: 'Draft', dot: 'bg-surface-300' },
    { value: 'completed', label: 'Selesai', dot: 'bg-primary-400' },
    { value: 'archived', label: 'Arsip', dot: 'bg-surface-500' },
];

const statusMeta: Record<
    string,
    { label: string; dot: string; bar: string; accent: string }
> = {
    active: {
        label: 'Aktif',
        dot: 'bg-primary-700',
        bar: 'bg-primary-950 text-white hover:bg-primary-900 dark:bg-primary-700 dark:hover:bg-primary-600',
        accent: 'bg-primary-700',
    },
    on_hold: {
        label: 'Ditahan',
        dot: 'bg-accent-500',
        bar: 'bg-accent-600 text-white hover:bg-accent-700 dark:bg-accent-500 dark:text-surface-950 dark:hover:bg-accent-400',
        accent: 'bg-accent-500',
    },
    draft: {
        label: 'Draft',
        dot: 'bg-surface-300',
        bar: 'border border-dashed border-surface-300 bg-surface-100 text-surface-700 hover:bg-surface-200 dark:border-surface-600 dark:bg-surface-800 dark:text-surface-200 dark:hover:bg-surface-700',
        accent: 'bg-surface-300',
    },
    completed: {
        label: 'Selesai',
        dot: 'bg-primary-400',
        bar: 'bg-primary-700/90 text-white hover:bg-primary-700 dark:bg-primary-600/80 dark:hover:bg-primary-600',
        accent: 'bg-primary-400',
    },
    archived: {
        label: 'Arsip',
        dot: 'bg-surface-400',
        bar: 'bg-surface-400 text-white hover:bg-surface-500 dark:bg-surface-600 dark:hover:bg-surface-500',
        accent: 'bg-surface-400',
    },
};

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

function addMonths(date: Date, amount: number): Date {
    return new Date(date.getFullYear(), date.getMonth() + amount, 1);
}

function startOfMonth(date: Date): Date {
    return new Date(date.getFullYear(), date.getMonth(), 1);
}

function endOfMonth(date: Date): Date {
    return new Date(date.getFullYear(), date.getMonth() + 1, 0);
}

function diffDays(first: Date, second: Date): number {
    return Math.round((second.getTime() - first.getTime()) / 86_400_000);
}

function clamp(value: number, min: number, max: number): number {
    return Math.min(Math.max(value, min), max);
}

function isSameDay(first: Date, second: Date): boolean {
    return (
        first.getFullYear() === second.getFullYear() &&
        first.getMonth() === second.getMonth() &&
        first.getDate() === second.getDate()
    );
}

function formatShortDate(date: Date, includeYear = false): string {
    return date.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        ...(includeYear ? { year: 'numeric' } : {}),
    });
}

function formatRange(start: Date, end: Date): string {
    const sameYear = start.getFullYear() === end.getFullYear();

    if (isSameDay(start, end)) {
        return formatShortDate(end, true);
    }

    return `${formatShortDate(start, !sameYear)} – ${formatShortDate(end, true)}`;
}

function minDate(dates: Date[]): Date | null {
    if (dates.length === 0) {
        return null;
    }

    return new Date(Math.min(...dates.map((date) => date.getTime())));
}

function maxDate(dates: Date[]): Date | null {
    if (dates.length === 0) {
        return null;
    }

    return new Date(Math.max(...dates.map((date) => date.getTime())));
}

function taskDates(task: TimelineTask): Date[] {
    return [task.start_date, task.due_date]
        .filter((value): value is string => Boolean(value))
        .map(parseIso);
}

function projectStart(project: TimelineProject): Date | null {
    const dates = [
        ...(project.start_date ? [parseIso(project.start_date)] : []),
        ...project.tasks.flatMap(taskDates),
    ];

    return minDate(dates);
}

function projectEnd(project: TimelineProject): Date | null {
    const dates = [
        ...(project.end_date ? [parseIso(project.end_date)] : []),
        ...project.tasks.flatMap(taskDates),
    ];

    return maxDate(dates);
}

function compareTasks(first: TimelineTask, second: TimelineTask): number {
    const firstDate = first.start_date ?? first.due_date ?? '';
    const secondDate = second.start_date ?? second.due_date ?? '';

    if (firstDate !== secondDate) {
        return firstDate.localeCompare(secondDate);
    }

    return first.title.localeCompare(second.title);
}

const journeys = computed<Journey[]>(() =>
    projects.value.flatMap((project) => {
        const start = projectStart(project);
        const end = projectEnd(project);

        if (!start || !end) {
            return [];
        }

        const doneCount = project.tasks.filter(
            (task) => task.status === 'done',
        ).length;
        const overdueCount = project.tasks.filter(
            (task) => task.is_overdue && task.status !== 'done',
        ).length;
        const tasks = [...project.tasks].sort(compareTasks);

        return [
            {
                project,
                start,
                end: end < start ? start : end,
                tasks,
                doneCount,
                overdueCount,
                progress:
                    tasks.length > 0
                        ? Math.round((doneCount / tasks.length) * 100)
                        : 0,
            },
        ];
    }),
);

const filteredJourneys = computed(() =>
    journeys.value.filter(
        (journey) =>
            statusFilter.value === 'all' ||
            journey.project.status === statusFilter.value,
    ),
);

const rangeBounds = computed<RangeBounds>(() => {
    if (rangePreset.value === 'all' && filteredJourneys.value.length > 0) {
        const start = minDate(
            filteredJourneys.value.map((journey) => journey.start),
        );
        const end = maxDate(
            filteredJourneys.value.map((journey) => journey.end),
        );

        if (start && end) {
            return {
                start: addDays(start, -4),
                end: addDays(end, 4),
            };
        }
    }

    const start = new Date(cursor.value);

    return {
        start,
        end: addDays(addMonths(start, 3), -1),
    };
});

const totalDays = computed(
    () => diffDays(rangeBounds.value.start, rangeBounds.value.end) + 1,
);

const rangeLabel = computed(() => {
    const { start, end } = rangeBounds.value;

    if (start.getFullYear() === end.getFullYear()) {
        return `${start.toLocaleDateString('id-ID', { month: 'short' })} – ${end.toLocaleDateString('id-ID', { month: 'short', year: 'numeric' })}`;
    }

    return `${start.toLocaleDateString('id-ID', { month: 'short', year: 'numeric' })} – ${end.toLocaleDateString('id-ID', { month: 'short', year: 'numeric' })}`;
});

function positionPct(date: Date, edge: 'start' | 'end' = 'start'): number {
    const days = diffDays(rangeBounds.value.start, date);

    return clamp(
        ((days + (edge === 'end' ? 1 : 0)) / totalDays.value) * 100,
        0,
        100,
    );
}

function chartX(percent: number): number {
    return CHART_INSET + (percent * (100 - CHART_INSET * 2)) / 100;
}

function chartWidth(percent: number): number {
    return (percent * (100 - CHART_INSET * 2)) / 100;
}

const visibleJourneys = computed(() =>
    filteredJourneys.value
        .filter(
            (journey) =>
                journey.start <= rangeBounds.value.end &&
                journey.end >= rangeBounds.value.start,
        )
        .sort(
            (first, second) => first.start.getTime() - second.start.getTime(),
        ),
);

const barLayout = computed<BarLayout[]>(() => {
    const laneEnds: number[] = [];

    return visibleJourneys.value.map((journey) => {
        const leftPct = positionPct(journey.start);
        const rightPct = positionPct(journey.end, 'end');
        const widthPct = Math.max(rightPct - leftPct, 2.4);
        let lane = laneEnds.findIndex((end) => end <= leftPct + 0.4);

        if (lane === -1) {
            lane = laneEnds.length;
            laneEnds.push(0);
        }

        laneEnds[lane] = leftPct + widthPct;

        return {
            journey,
            lane,
            left: chartX(leftPct),
            width: chartWidth(widthPct),
            clippedStart: journey.start < rangeBounds.value.start,
            clippedEnd: journey.end > rangeBounds.value.end,
        };
    });
});

const laneCount = computed(() =>
    barLayout.value.reduce((max, bar) => Math.max(max, bar.lane + 1), 1),
);

const barsHeight = computed(() => laneCount.value * 50 + 14);

const deliverableBlocks = computed<DeliverableBlock[]>(() => {
    const candidates = visibleJourneys.value.filter(
        (journey) => journey.tasks.length > 0,
    );
    const rowEnds: number[] = [];

    return candidates.map((journey) => {
        const startPct = positionPct(journey.start);
        const endPct = positionPct(journey.end, 'end');
        let leftPct = clamp(startPct, 0, 100);
        let widthPct = clamp(endPct - startPct, 20, 100 - leftPct);

        if (widthPct < 20) {
            leftPct = Math.max(0, 100 - 20);
            widthPct = 20;
        }

        let row = rowEnds.findIndex((end) => end + 1.5 <= leftPct);

        if (row === -1) {
            row = rowEnds.length;
            rowEnds.push(0);
        }

        rowEnds[row] = leftPct + widthPct;

        return {
            journey,
            row,
            left: chartX(leftPct),
            width: chartWidth(widthPct),
            visibleTasks: journey.tasks.slice(0, 4),
            extraTasks: Math.max(0, journey.tasks.length - 4),
        };
    });
});

const deliverableRows = computed(
    () =>
        deliverableBlocks.value.reduce(
            (max, block) => Math.max(max, block.row + 1),
            0,
        ) || 1,
);

const deliverablesHeight = computed(
    () => deliverableRows.value * DELIVERABLE_ROW_HEIGHT,
);

const tickStep = computed(() => {
    if (totalDays.value <= 21) {
        return 2;
    }

    if (totalDays.value <= 45) {
        return 3;
    }

    if (totalDays.value <= 120) {
        return 7;
    }

    if (totalDays.value <= 240) {
        return 14;
    }

    return 30;
});

const ticks = computed<Tick[]>(() => {
    const result: Tick[] = [];
    let date = new Date(rangeBounds.value.start);

    while (date <= rangeBounds.value.end) {
        result.push({
            date: new Date(date),
            left: chartX(positionPct(date)),
            label: formatShortDate(date),
            isFirst: result.length === 0,
            isLast: false,
        });

        date = addDays(date, tickStep.value);
    }

    if (result.length === 0) {
        return result;
    }

    const last = result[result.length - 1];
    const remaining = diffDays(last.date, rangeBounds.value.end);

    if (remaining > tickStep.value / 2) {
        result.push({
            date: rangeBounds.value.end,
            left: chartX(100),
            label: formatShortDate(rangeBounds.value.end),
            isFirst: false,
            isLast: true,
        });
    } else {
        last.isLast = true;
    }

    return result;
});

const monthGroups = computed<MonthGroup[]>(() => {
    const result: MonthGroup[] = [];
    let date = startOfMonth(rangeBounds.value.start);

    while (date <= rangeBounds.value.end) {
        const monthStart =
            date < rangeBounds.value.start ? rangeBounds.value.start : date;
        const monthEnd =
            endOfMonth(date) > rangeBounds.value.end
                ? rangeBounds.value.end
                : endOfMonth(date);
        const left = positionPct(monthStart);
        const right = positionPct(monthEnd, 'end');

        result.push({
            label: date.toLocaleDateString('id-ID', {
                month: 'long',
                year: 'numeric',
            }),
            left: chartX(left),
            width: chartWidth(Math.max(right - left, 1)),
        });

        date = addMonths(date, 1);
    }

    return result;
});

const todayPosition = computed(() => {
    if (today < rangeBounds.value.start || today > rangeBounds.value.end) {
        return null;
    }

    return chartX(positionPct(today));
});

const selectedJourney = computed(() => {
    const selected = visibleJourneys.value.find(
        (journey) => journey.project.id === selectedProjectId.value,
    );

    if (selected) {
        return selected;
    }

    return (
        visibleJourneys.value.find((journey) => journey.tasks.length > 0) ??
        visibleJourneys.value[0] ??
        null
    );
});

const summary = computed(() => {
    const scheduled = visibleJourneys.value.reduce(
        (total, journey) => total + journey.tasks.length,
        0,
    );
    const overdue = visibleJourneys.value.reduce(
        (total, journey) => total + journey.overdueCount,
        0,
    );

    return {
        projects: visibleJourneys.value.length,
        scheduled,
        overdue,
    };
});

const statusCounts = computed(() => {
    const counts: Record<string, number> = { all: projects.value.length };

    for (const project of projects.value) {
        counts[project.status] = (counts[project.status] ?? 0) + 1;
    }

    return counts;
});

const hasActiveFilters = computed(
    () => statusFilter.value !== 'all' || rangePreset.value !== 'quarter',
);

function statusMetaFor(status: string) {
    return statusMeta[status] ?? statusMeta.draft;
}

function barClasses(bar: BarLayout): string {
    const meta = statusMetaFor(bar.journey.project.status);
    const classes = [meta.bar];

    if (selectedJourney.value?.project.id === bar.journey.project.id) {
        classes.push(
            'ring-2 ring-accent-500 ring-offset-2 ring-offset-white dark:ring-offset-surface-900',
        );
    }

    return classes.join(' ');
}

function badgeClasses(status: string): string {
    return status === 'draft'
        ? 'bg-surface-900/5 text-surface-600 dark:bg-white/10 dark:text-surface-200'
        : 'bg-white/15 text-white/90';
}

function taskDot(task: TimelineTask): string {
    if (task.is_overdue && task.status !== 'done') {
        return 'bg-rose-500';
    }

    switch (task.status) {
        case 'done':
            return 'bg-primary-400';
        case 'in_progress':
            return 'bg-primary-700 dark:bg-primary-300';
        case 'review':
            return 'bg-accent-500';
        default:
            return 'bg-surface-300 dark:bg-surface-500';
    }
}

function shiftRange(offset: number): void {
    if (rangePreset.value === 'all') {
        return;
    }

    cursor.value = addMonths(cursor.value, offset * 3);
}

function goToToday(): void {
    cursor.value = startOfMonth(today);
    rangePreset.value = 'quarter';
}

function resetFilters(): void {
    statusFilter.value = 'all';
    goToToday();
}
</script>

<template>
    <div class="space-y-6">
        <!-- Editorial header -->
        <div
            class="flex flex-col gap-6 border-b border-surface-200/80 pb-6 lg:flex-row lg:items-end lg:justify-between dark:border-surface-800"
        >
            <div>
                <div class="flex items-center gap-3">
                    <span class="h-px w-8 bg-accent-500" />
                    <span
                        class="text-[10px] font-bold tracking-[0.22em] text-surface-500 uppercase dark:text-surface-400"
                    >
                        Journey highlights
                    </span>
                </div>
                <h1
                    class="mt-3 font-serif text-3xl font-normal tracking-tight text-surface-950 sm:text-4xl dark:text-surface-50"
                >
                    Timeline
                </h1>
                <p
                    class="mt-2 max-w-xl text-sm leading-6 text-surface-600 dark:text-surface-300"
                >
                    Peta perjalanan proyek dalam satu garis waktu. Lihat fase
                    yang berjalan, rentang tanggal, dan deliverables utama tim
                    tanpa kehilangan konteks.
                </p>
            </div>

            <div class="flex flex-wrap items-end gap-5 sm:gap-7">
                <div class="text-right">
                    <p
                        class="font-serif text-3xl leading-none text-surface-950 dark:text-surface-50"
                    >
                        {{ pad(summary.projects) }}
                        <span
                            class="text-lg text-surface-300 dark:text-surface-600"
                            >/ {{ pad(journeys.length) }}</span
                        >
                    </p>
                    <p
                        class="mt-1.5 text-[10px] font-semibold tracking-[0.14em] text-surface-400 uppercase dark:text-surface-500"
                    >
                        Proyek di rentang
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-full border border-surface-200 bg-white px-3.5 py-2 text-xs font-semibold text-surface-700 shadow-2xs transition hover:border-surface-300 hover:bg-surface-50 active:scale-95 dark:border-surface-700 dark:bg-surface-900 dark:text-surface-200 dark:hover:bg-surface-800"
                        @click="goToToday"
                    >
                        <CalendarDays class="h-3.5 w-3.5" />
                        Hari ini
                    </button>
                    <Link
                        :href="projectRoutes.index.url()"
                        class="inline-flex items-center gap-1.5 rounded-full bg-primary-950 px-3.5 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-primary-900 active:scale-95 dark:bg-white dark:text-surface-950 dark:hover:bg-surface-100"
                    >
                        Semua proyek
                        <ExternalLink class="h-3 w-3" />
                    </Link>
                </div>
            </div>
        </div>

        <!-- Inline stats -->
        <div
            class="flex flex-wrap items-center gap-x-6 gap-y-2 border-b border-surface-200/70 pb-4 text-xs text-surface-600 dark:border-surface-800 dark:text-surface-300"
        >
            <span class="inline-flex items-center gap-2">
                <Layers3
                    class="h-3.5 w-3.5 text-primary-700 dark:text-primary-300"
                />
                <strong
                    class="font-semibold text-surface-900 dark:text-surface-100"
                    >{{ summary.projects }}</strong
                >
                proyek dalam rentang
            </span>
            <span class="inline-flex items-center gap-2">
                <CircleDot
                    class="h-3.5 w-3.5 text-accent-600 dark:text-accent-400"
                />
                <strong
                    class="font-semibold text-surface-900 dark:text-surface-100"
                    >{{ summary.scheduled }}</strong
                >
                tugas terjadwal
            </span>
            <span
                v-if="summary.overdue > 0"
                class="inline-flex items-center gap-2 text-rose-600 dark:text-rose-300"
            >
                <Flag class="h-3.5 w-3.5" />
                <strong class="font-semibold">{{ summary.overdue }}</strong>
                tugas terlambat
            </span>
            <span
                class="ml-auto hidden items-center gap-2 text-[11px] text-surface-400 sm:inline-flex dark:text-surface-500"
            >
                <MoveRight class="h-3.5 w-3.5" />
                Geser horizontal untuk menjelajah rentang
            </span>
        </div>

        <!-- Filters and range navigation -->
        <div
            class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between"
        >
            <div class="flex flex-wrap items-center gap-1.5">
                <button
                    v-for="option in statusOrder"
                    :key="option.value"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-[11px] font-semibold transition"
                    :class="
                        statusFilter === option.value
                            ? 'border-surface-900 bg-surface-900 text-white shadow-xs dark:border-white dark:bg-white dark:text-surface-950'
                            : 'border-surface-200 bg-white text-surface-600 hover:border-surface-300 hover:bg-surface-50 dark:border-surface-700 dark:bg-surface-900 dark:text-surface-300 dark:hover:bg-surface-800'
                    "
                    :aria-pressed="statusFilter === option.value"
                    @click="statusFilter = option.value"
                >
                    <span
                        class="h-1.5 w-1.5 rounded-full"
                        :class="option.dot"
                    />
                    {{ option.label }}
                    <span class="text-[10px] font-bold opacity-50">{{
                        statusCounts[option.value] ?? 0
                    }}</span>
                </button>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div
                    class="inline-flex items-center rounded-full border border-surface-200 bg-white p-1 shadow-2xs dark:border-surface-700 dark:bg-surface-900"
                >
                    <button
                        type="button"
                        class="grid h-7 w-7 place-items-center rounded-full text-surface-500 transition hover:bg-surface-100 hover:text-surface-900 disabled:cursor-not-allowed disabled:opacity-30 dark:text-surface-400 dark:hover:bg-surface-800 dark:hover:text-white"
                        :disabled="rangePreset === 'all'"
                        aria-label="Rentang sebelumnya"
                        @click="shiftRange(-1)"
                    >
                        <ChevronLeft class="h-3.5 w-3.5" />
                    </button>
                    <span
                        class="min-w-36 px-2 text-center text-[11px] font-semibold text-surface-800 dark:text-surface-200"
                    >
                        {{ rangeLabel }}
                    </span>
                    <button
                        type="button"
                        class="grid h-7 w-7 place-items-center rounded-full text-surface-500 transition hover:bg-surface-100 hover:text-surface-900 disabled:cursor-not-allowed disabled:opacity-30 dark:text-surface-400 dark:hover:bg-surface-800 dark:hover:text-white"
                        :disabled="rangePreset === 'all'"
                        aria-label="Rentang berikutnya"
                        @click="shiftRange(1)"
                    >
                        <ChevronRight class="h-3.5 w-3.5" />
                    </button>
                </div>

                <button
                    type="button"
                    class="rounded-full border px-3 py-1.5 text-[11px] font-semibold transition"
                    :class="
                        rangePreset === 'all'
                            ? 'border-surface-900 bg-surface-900 text-white dark:border-white dark:bg-white dark:text-surface-950'
                            : 'border-surface-200 bg-white text-surface-600 hover:border-surface-300 hover:bg-surface-50 dark:border-surface-700 dark:bg-surface-900 dark:text-surface-300 dark:hover:bg-surface-800'
                    "
                    :aria-pressed="rangePreset === 'all'"
                    @click="
                        rangePreset = rangePreset === 'all' ? 'quarter' : 'all'
                    "
                >
                    Semua rentang
                </button>
            </div>
        </div>

        <template v-if="visibleJourneys.length > 0">
            <!-- Timeline canvas -->
            <section
                class="overflow-hidden rounded-[32px] border border-surface-200/80 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900"
            >
                <div
                    class="flex flex-col gap-3 border-b border-surface-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-surface-800"
                >
                    <div class="flex items-center gap-2.5">
                        <span
                            class="grid h-8 w-8 place-items-center rounded-xl bg-primary-50 text-primary-700 ring-1 ring-primary-100 dark:bg-primary-900/30 dark:text-primary-300 dark:ring-primary-800/50"
                        >
                            <Layers3 class="h-4 w-4" />
                        </span>
                        <div>
                            <p
                                class="font-serif text-base text-surface-950 dark:text-surface-50"
                            >
                                Peta perjalanan
                            </p>
                            <p
                                class="text-[11px] text-surface-400 dark:text-surface-500"
                            >
                                {{ rangeLabel }} · {{ summary.projects }} proyek
                                terlihat
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <span
                            v-for="option in statusOrder.slice(1)"
                            :key="`legend-${option.value}`"
                            class="inline-flex items-center gap-1.5 text-[10px] font-medium text-surface-500 dark:text-surface-400"
                        >
                            <span
                                class="h-2 w-2 rounded-full"
                                :class="option.dot"
                            />
                            {{ option.label }}
                        </span>
                        <span
                            class="inline-flex items-center gap-1.5 text-[10px] font-medium text-surface-500 dark:text-surface-400"
                        >
                            <span class="h-3 w-px bg-accent-500" />
                            Hari ini
                        </span>
                    </div>
                </div>

                <div class="overflow-x-auto overscroll-x-contain">
                    <div class="relative min-w-[1120px]">
                        <!-- Dashed date grid -->
                        <div
                            class="pointer-events-none absolute inset-0 z-0"
                            aria-hidden="true"
                        >
                            <div
                                v-for="tick in ticks"
                                :key="`grid-${tick.date.toISOString()}`"
                                class="absolute inset-y-0 border-l border-dashed border-surface-200/70 dark:border-surface-700/50"
                                :style="{ left: `${tick.left}%` }"
                            />
                            <div
                                v-if="todayPosition !== null"
                                class="absolute inset-y-0 z-10 w-px bg-accent-500/60 dark:bg-accent-400/50"
                                :style="{ left: `${todayPosition}%` }"
                            >
                                <span
                                    class="absolute top-2 left-1/2 h-1.5 w-1.5 -translate-x-1/2 rounded-full bg-accent-500 ring-2 ring-white dark:ring-surface-900"
                                />
                            </div>
                        </div>

                        <div class="relative z-10">
                            <!-- Month grouping -->
                            <div
                                class="relative h-9 border-b border-surface-100/80 dark:border-surface-800"
                            >
                                <span
                                    v-for="month in monthGroups"
                                    :key="`month-${month.label}`"
                                    class="absolute top-1/2 -translate-y-1/2 truncate px-2 text-[10px] font-bold tracking-[0.16em] text-surface-500 uppercase dark:text-surface-400"
                                    :style="{
                                        left: `${month.left}%`,
                                        width: `${month.width}%`,
                                    }"
                                >
                                    {{ month.label }}
                                </span>
                            </div>

                            <!-- Tick labels -->
                            <div class="relative h-9">
                                <span
                                    v-for="tick in ticks"
                                    :key="`tick-${tick.date.toISOString()}`"
                                    class="absolute top-1/2 -translate-y-1/2 text-[10px] font-medium whitespace-nowrap text-surface-400 dark:text-surface-500"
                                    :class="{
                                        '-translate-x-full text-right':
                                            tick.isLast,
                                    }"
                                    :style="{ left: `${tick.left}%` }"
                                >
                                    {{ tick.label }}
                                </span>
                            </div>

                            <!-- Project bars -->
                            <div
                                class="relative"
                                :style="{ height: `${barsHeight}px` }"
                            >
                                <button
                                    v-for="bar in barLayout"
                                    :key="bar.journey.project.id"
                                    type="button"
                                    class="absolute flex h-9 items-center gap-2 overflow-hidden rounded-full px-3.5 text-left shadow-xs transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md focus-visible:ring-2 focus-visible:ring-accent-500 focus-visible:outline-none sm:px-4"
                                    :class="barClasses(bar)"
                                    :style="{
                                        left: `${bar.left}%`,
                                        width: `${bar.width}%`,
                                        top: `${8 + bar.lane * 50}px`,
                                    }"
                                    :title="`${bar.journey.project.name} · ${formatRange(bar.journey.start, bar.journey.end)}`"
                                    :aria-pressed="
                                        selectedJourney?.project.id ===
                                        bar.journey.project.id
                                    "
                                    @click="
                                        selectedProjectId =
                                            bar.journey.project.id
                                    "
                                >
                                    <span
                                        class="truncate text-[11px] font-semibold tracking-tight sm:text-xs"
                                    >
                                        {{ bar.journey.project.name }}
                                    </span>
                                    <span
                                        v-if="bar.journey.tasks.length > 0"
                                        class="ml-auto shrink-0 rounded-full px-1.5 py-0.5 text-[9px] font-bold"
                                        :class="
                                            badgeClasses(
                                                bar.journey.project.status,
                                            )
                                        "
                                    >
                                        {{ bar.journey.tasks.length }}
                                    </span>
                                </button>
                            </div>

                            <!-- Deliverables -->
                            <div
                                class="border-t border-surface-200/80 pt-4 dark:border-surface-800"
                            >
                                <div
                                    class="flex flex-wrap items-center justify-between gap-2"
                                >
                                    <div class="flex items-center gap-2">
                                        <Flag
                                            class="h-3.5 w-3.5 text-accent-600 dark:text-accent-400"
                                        />
                                        <span
                                            class="text-[10px] font-bold tracking-[0.18em] text-surface-500 uppercase dark:text-surface-400"
                                        >
                                            Deliverables
                                        </span>
                                        <span
                                            class="text-[10px] text-surface-400 dark:text-surface-500"
                                        >
                                            · {{ deliverableBlocks.length }}
                                            proyek memiliki tugas
                                        </span>
                                    </div>
                                    <span
                                        class="text-[10px] text-surface-400 dark:text-surface-500"
                                    >
                                        Klik bar untuk memilih proyek
                                    </span>
                                </div>

                                <div
                                    v-if="deliverableBlocks.length > 0"
                                    class="relative mt-2"
                                    :style="{
                                        height: `${deliverablesHeight}px`,
                                    }"
                                >
                                    <article
                                        v-for="block in deliverableBlocks"
                                        :key="`deliverable-${block.journey.project.id}`"
                                        class="absolute overflow-hidden rounded-2xl border p-3 transition-all duration-200"
                                        :class="
                                            selectedJourney?.project.id ===
                                            block.journey.project.id
                                                ? 'border-primary-300 bg-primary-50/50 shadow-xs dark:border-primary-700 dark:bg-primary-900/20'
                                                : 'border-surface-200/80 bg-surface-50/60 hover:border-surface-300 hover:bg-surface-50 dark:border-surface-700/80 dark:bg-surface-800/30 dark:hover:border-surface-600'
                                        "
                                        :style="{
                                            left: `${block.left}%`,
                                            width: `${block.width}%`,
                                            top: `${block.row * DELIVERABLE_ROW_HEIGHT + 4}px`,
                                            minHeight: `${DELIVERABLE_ROW_HEIGHT - 12}px`,
                                        }"
                                        @click="
                                            selectedProjectId =
                                                block.journey.project.id
                                        "
                                    >
                                        <div class="flex items-center gap-1.5">
                                            <span
                                                class="text-[9px] font-bold tracking-[0.14em] text-surface-400 dark:text-surface-500"
                                            >
                                                {{
                                                    pad(
                                                        visibleJourneys.findIndex(
                                                            (journey) =>
                                                                journey.project
                                                                    .id ===
                                                                block.journey
                                                                    .project.id,
                                                        ) + 1,
                                                    )
                                                }}
                                            </span>
                                            <span
                                                class="h-1.5 w-1.5 rounded-full"
                                                :class="
                                                    statusMetaFor(
                                                        block.journey.project
                                                            .status,
                                                    ).dot
                                                "
                                            />
                                            <p
                                                class="truncate text-[10px] font-bold tracking-[0.12em] text-surface-700 uppercase dark:text-surface-200"
                                            >
                                                {{ block.journey.project.name }}
                                            </p>
                                        </div>

                                        <div
                                            class="mt-1 flex items-center justify-between gap-2 text-[10px] text-surface-400 dark:text-surface-500"
                                        >
                                            <span class="truncate">
                                                {{
                                                    formatRange(
                                                        block.journey.start,
                                                        block.journey.end,
                                                    )
                                                }}
                                            </span>
                                            <span class="shrink-0">
                                                {{ block.journey.doneCount }}/{{
                                                    block.journey.tasks.length
                                                }}
                                            </span>
                                        </div>

                                        <ul class="mt-2.5 space-y-1.5">
                                            <li
                                                v-for="task in block.visibleTasks"
                                                :key="`block-task-${task.id}`"
                                                class="flex items-start gap-1.5"
                                            >
                                                <span
                                                    class="mt-1.5 h-1 w-1 shrink-0 rounded-full"
                                                    :class="taskDot(task)"
                                                />
                                                <component
                                                    :is="
                                                        canViewTasks
                                                            ? Link
                                                            : 'span'
                                                    "
                                                    :href="
                                                        canViewTasks
                                                            ? taskRoutes.show.url(
                                                                  task.id,
                                                              )
                                                            : undefined
                                                    "
                                                    class="line-clamp-1 text-[11px] leading-4 transition"
                                                    :class="
                                                        task.status === 'done'
                                                            ? 'text-surface-400 line-through dark:text-surface-500'
                                                            : 'text-surface-700 hover:text-primary-700 dark:text-surface-200 dark:hover:text-primary-300'
                                                    "
                                                    :title="task.title"
                                                    @click.stop
                                                >
                                                    {{ task.title }}
                                                </component>
                                            </li>
                                        </ul>

                                        <p
                                            v-if="block.extraTasks > 0"
                                            class="mt-1.5 pl-2.5 text-[10px] font-medium text-surface-400 dark:text-surface-500"
                                        >
                                            +{{ block.extraTasks }} tugas
                                            lainnya
                                        </p>
                                    </article>
                                </div>

                                <div
                                    v-else
                                    class="mt-3 rounded-2xl border border-dashed border-surface-200 bg-surface-50/50 px-4 py-6 text-center dark:border-surface-700 dark:bg-surface-800/20"
                                >
                                    <p
                                        class="text-xs text-surface-500 dark:text-surface-400"
                                    >
                                        Belum ada tugas terjadwal pada proyek di
                                        rentang ini.
                                    </p>
                                    <Link
                                        v-if="canViewTasks"
                                        :href="taskRoutes.index.url()"
                                        class="mt-2 inline-flex items-center gap-1 text-[11px] font-semibold text-primary-700 hover:underline dark:text-primary-300"
                                    >
                                        Tambahkan tugas
                                        <ChevronRight class="h-3 w-3" />
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Selected project focus -->
            <section
                v-if="selectedJourney"
                class="grid gap-4 rounded-[28px] border border-surface-200/80 bg-white p-4 shadow-xs sm:p-5 lg:grid-cols-[1.25fr_0.75fr] lg:items-center dark:border-surface-800 dark:bg-surface-900"
            >
                <div class="flex items-start gap-3.5">
                    <span
                        class="mt-1 h-12 w-1.5 shrink-0 rounded-full"
                        :class="
                            statusMetaFor(selectedJourney.project.status).accent
                        "
                    />
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="text-[10px] font-bold tracking-[0.18em] text-surface-400 uppercase dark:text-surface-500"
                            >
                                Fokus proyek
                            </span>
                            <span
                                class="inline-flex items-center gap-1 rounded-full bg-surface-100 px-2 py-0.5 text-[10px] font-semibold text-surface-600 dark:bg-surface-800 dark:text-surface-300"
                            >
                                <span
                                    class="h-1.5 w-1.5 rounded-full"
                                    :class="
                                        statusMetaFor(
                                            selectedJourney.project.status,
                                        ).dot
                                    "
                                />
                                {{ selectedJourney.project.status_label }}
                            </span>
                        </div>
                        <h2
                            class="mt-1 truncate font-serif text-xl text-surface-950 dark:text-surface-50"
                        >
                            {{ selectedJourney.project.name }}
                        </h2>
                        <p
                            class="mt-0.5 text-xs text-surface-500 dark:text-surface-400"
                        >
                            {{
                                formatRange(
                                    selectedJourney.start,
                                    selectedJourney.end,
                                )
                            }}
                            · {{ selectedJourney.tasks.length }} tugas ·
                            {{ selectedJourney.doneCount }} selesai
                            <span
                                v-if="selectedJourney.overdueCount > 0"
                                class="text-rose-600 dark:text-rose-300"
                            >
                                · {{ selectedJourney.overdueCount }} terlambat
                            </span>
                        </p>
                    </div>
                </div>

                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center lg:justify-end"
                >
                    <div class="min-w-0 flex-1 sm:max-w-48">
                        <div
                            class="flex items-center justify-between text-[10px] font-semibold text-surface-500 dark:text-surface-400"
                        >
                            <span>Progres tugas</span>
                            <span>{{ selectedJourney.progress }}%</span>
                        </div>
                        <div
                            class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-surface-100 dark:bg-surface-800"
                        >
                            <div
                                class="h-full rounded-full bg-primary-700 transition-all duration-500 dark:bg-primary-400"
                                :style="{
                                    width: `${selectedJourney.progress}%`,
                                }"
                            />
                        </div>
                    </div>

                    <Link
                        :href="
                            projectRoutes.show.url(selectedJourney.project.id)
                        "
                        class="inline-flex shrink-0 items-center justify-center gap-1.5 rounded-xl border border-surface-200 bg-white px-3.5 py-2 text-xs font-semibold text-surface-700 transition hover:border-surface-300 hover:bg-surface-50 active:scale-95 dark:border-surface-700 dark:bg-surface-900 dark:text-surface-200 dark:hover:bg-surface-800"
                    >
                        Buka proyek
                        <ExternalLink class="h-3 w-3" />
                    </Link>
                </div>
            </section>
        </template>

        <EmptyState
            v-else
            icon="CalendarRange"
            title="Belum ada proyek di rentang ini"
            :description="
                hasActiveFilters
                    ? 'Tidak ada proyek yang cocok dengan filter atau rentang tanggal saat ini. Coba tampilkan semua status atau kembali ke bulan ini.'
                    : 'Tambahkan tanggal mulai dan selesai pada proyek agar perjalanannya muncul di timeline.'
            "
        >
            <div class="mt-5 flex flex-wrap gap-2">
                <Button
                    v-if="hasActiveFilters"
                    variant="secondary"
                    @click="resetFilters"
                >
                    Reset filter
                </Button>
                <Button variant="primary" @click="goToToday">
                    Kembali ke bulan ini
                </Button>
            </div>
        </EmptyState>
    </div>
</template>
