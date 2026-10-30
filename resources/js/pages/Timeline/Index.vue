<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Avatar from '@/components/ui/Avatar.vue';
import Button from '@/components/ui/Button.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Select from '@/components/ui/Select.vue';
import { getIcon } from '@/components/sidebar/icons';
import taskRoutes from '@/routes/tasks';
import {
    AlertTriangle,
    CalendarRange,
    ChevronLeft,
    ChevronRight,
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

interface PositionedTask extends TimelineTask {
    left: number;
    width: number;
    startsBefore: boolean;
    endsAfter: boolean;
}

interface PositionedProject extends TimelineProject {
    positionedTasks: PositionedTask[];
}

interface DayCell {
    date: Date;
    iso: string;
    day: number;
    weekday: string;
    isToday: boolean;
    isWeekend: boolean;
    isCurrentMonth: boolean;
}

const page = usePage();
const projects = computed(
    () => (page.props.projects as TimelineProject[]) ?? [],
);
const canViewTasks = computed(() => Boolean(page.props.can_view_tasks));

const today = new Date();
today.setHours(0, 0, 0, 0);

const cursor = ref(new Date(today.getFullYear(), today.getMonth(), 1));
const projectFilter = ref('');
const statusFilter = ref('');
const board = ref<HTMLElement | null>(null);

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

function diffDays(start: Date, end: Date): number {
    return Math.round((end.getTime() - start.getTime()) / 86_400_000);
}

const monthLabel = computed(() =>
    cursor.value.toLocaleDateString('id-ID', {
        month: 'long',
        year: 'numeric',
    }),
);

const rangeStart = computed(() => {
    const firstOfMonth = new Date(
        cursor.value.getFullYear(),
        cursor.value.getMonth(),
        1,
    );
    const mondayOffset = (firstOfMonth.getDay() + 6) % 7;
    const start = new Date(firstOfMonth);
    start.setDate(firstOfMonth.getDate() - mondayOffset);
    start.setHours(0, 0, 0, 0);

    return start;
});

const totalDays = computed(() => {
    const firstOfMonth = new Date(
        cursor.value.getFullYear(),
        cursor.value.getMonth(),
        1,
    );
    const lastOfMonth = new Date(
        cursor.value.getFullYear(),
        cursor.value.getMonth() + 1,
        0,
    );
    const mondayOffset = (firstOfMonth.getDay() + 6) % 7;
    const weeks = Math.ceil((mondayOffset + lastOfMonth.getDate()) / 7);

    return weeks * 7;
});

const days = computed<DayCell[]>(() =>
    Array.from({ length: totalDays.value }, (_, index) => {
        const date = new Date(rangeStart.value);
        date.setDate(rangeStart.value.getDate() + index);
        date.setHours(0, 0, 0, 0);

        return {
            date,
            iso: toIso(date),
            day: date.getDate(),
            weekday: weekdays[(date.getDay() + 6) % 7],
            isToday: date.getTime() === today.getTime(),
            isWeekend: date.getDay() === 0 || date.getDay() === 6,
            isCurrentMonth: date.getMonth() === cursor.value.getMonth(),
        };
    }),
);

const LABEL_WIDTH = 260;
const DAY_WIDTH = 38;

const gridStyle = computed(() => ({
    gridTemplateColumns: `${LABEL_WIDTH}px repeat(${totalDays.value}, minmax(${DAY_WIDTH}px, 1fr))`,
}));

const gridMinWidth = computed(() => LABEL_WIDTH + totalDays.value * DAY_WIDTH);

const filteredProjects = computed<PositionedProject[]>(() =>
    projects.value
        .filter(
            (project) =>
                !projectFilter.value ||
                String(project.id) === projectFilter.value,
        )
        .map((project) => ({
            ...project,
            positionedTasks: project.tasks
                .filter(
                    (task) =>
                        !statusFilter.value ||
                        task.status === statusFilter.value,
                )
                .map(positionTask)
                .filter((task): task is PositionedTask => task !== null),
        }))
        .filter((project) => project.positionedTasks.length > 0),
);

function positionTask(task: TimelineTask): PositionedTask | null {
    const rawStart = task.start_date ?? task.due_date;
    const rawEnd = task.due_date ?? task.start_date;

    if (!rawStart || !rawEnd) {
        return null;
    }

    const start = parseIso(rawStart);
    const end = parseIso(rawEnd);

    if (end.getTime() < start.getTime()) {
        return null;
    }

    const rangeEnd = days.value[days.value.length - 1].date;

    if (
        end.getTime() < rangeStart.value.getTime() ||
        start.getTime() > rangeEnd.getTime()
    ) {
        return null;
    }

    const clampedStart =
        start.getTime() < rangeStart.value.getTime() ? rangeStart.value : start;
    const clampedEnd = end.getTime() > rangeEnd.getTime() ? rangeEnd : end;
    const startIndex = diffDays(rangeStart.value, clampedStart);
    const endIndex = diffDays(rangeStart.value, clampedEnd);

    return {
        ...task,
        left: (startIndex / totalDays.value) * 100,
        width: ((endIndex - startIndex + 1) / totalDays.value) * 100,
        startsBefore: start.getTime() < rangeStart.value.getTime(),
        endsAfter: end.getTime() > rangeEnd.getTime(),
    };
}

const timelineProjects = filteredProjects;

const visibleTasks = computed(() =>
    timelineProjects.value.flatMap((project) => project.positionedTasks),
);

const stats = computed(() => ({
    projects: timelineProjects.value.length,
    tasks: visibleTasks.value.length,
    inProgress: visibleTasks.value.filter(
        (task) => task.status === 'in_progress',
    ).length,
    overdue: visibleTasks.value.filter((task) => task.is_overdue).length,
}));

const todayPosition = computed(() => {
    if (today.getTime() < rangeStart.value.getTime()) {
        return null;
    }

    const rangeEnd = days.value[days.value.length - 1].date;

    if (today.getTime() > rangeEnd.getTime()) {
        return null;
    }

    return ((diffDays(rangeStart.value, today) + 0.5) / totalDays.value) * 100;
});

function shiftMonth(offset: number): void {
    cursor.value = new Date(
        cursor.value.getFullYear(),
        cursor.value.getMonth() + offset,
        1,
    );
}

function goToToday(): void {
    cursor.value = new Date(today.getFullYear(), today.getMonth(), 1);
    void nextTick(() => scrollToToday('smooth'));
}

function scrollToToday(behavior: ScrollBehavior): void {
    if (!board.value || todayPosition.value === null) {
        return;
    }

    const trackWidth = board.value.scrollWidth - LABEL_WIDTH;
    const todayX = LABEL_WIDTH + (todayPosition.value / 100) * trackWidth;
    const target = todayX - board.value.clientWidth / 2;

    board.value.scrollTo({
        left: Math.max(0, target),
        behavior,
    });
}

onMounted(() => {
    void nextTick(() => scrollToToday('auto'));
});

watch(cursor, () => {
    void nextTick(() => scrollToToday('smooth'));
});

function projectProgress(tasks: TimelineTask[]): number {
    if (tasks.length === 0) return 0;

    return Math.round(
        (tasks.filter((task) => task.status === 'done').length / tasks.length) *
            100,
    );
}

function barClass(status: string): string {
    switch (status) {
        case 'done':
            return 'bg-emerald-500/90 hover:bg-emerald-500';
        case 'in_progress':
            return 'bg-primary-600/90 hover:bg-primary-600';
        case 'review':
            return 'bg-accent-500/90 hover:bg-accent-500';
        default:
            return 'bg-surface-400/80 hover:bg-surface-500';
    }
}

function statusDotClass(status: string): string {
    switch (status) {
        case 'done':
            return 'bg-emerald-500';
        case 'in_progress':
            return 'bg-primary-500';
        case 'review':
            return 'bg-accent-500';
        default:
            return 'bg-surface-400';
    }
}
</script>

<template>
    <div class="space-y-6">
        <!-- Page header -->
        <div
            class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
        >
            <div>
                <div
                    class="flex items-center gap-2 text-xs font-semibold tracking-wider text-primary-700 uppercase dark:text-primary-300"
                >
                    <CalendarRange class="h-4 w-4" />
                    Perencanaan
                </div>
                <h1
                    class="mt-2 text-2xl font-semibold tracking-tight text-surface-950 dark:text-surface-50"
                >
                    Timeline Gantt
                </h1>
                <p
                    class="mt-1 max-w-2xl text-sm leading-6 text-surface-700 dark:text-surface-200"
                >
                    Lihat rentang waktu, progres, dan beban kerja setiap proyek
                    dalam satu tampilan.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Button variant="secondary" size="sm" @click="shiftMonth(-1)">
                    <ChevronLeft class="h-4 w-4" />
                </Button>
                <Button variant="secondary" size="sm" @click="goToToday">
                    Hari ini
                </Button>
                <Button variant="secondary" size="sm" @click="shiftMonth(1)">
                    <ChevronRight class="h-4 w-4" />
                </Button>
                <span
                    class="min-w-36 text-center text-sm font-semibold text-surface-800 dark:text-surface-100"
                >
                    {{ monthLabel }}
                </span>
            </div>
        </div>

        <!-- Summary cards -->
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="card flex items-center gap-4 p-4">
                <span
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300"
                >
                    <component :is="getIcon('FolderKanban')" class="h-5 w-5" />
                </span>
                <div>
                    <p
                        class="text-xs font-medium text-surface-500 dark:text-surface-400"
                    >
                        Proyek aktif
                    </p>
                    <p
                        class="mt-0.5 text-xl font-bold text-surface-950 dark:text-surface-50"
                    >
                        {{ stats.projects }}
                    </p>
                </div>
            </div>
            <div class="card flex items-center gap-4 p-4">
                <span
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-surface-100 text-surface-700 dark:bg-surface-800 dark:text-surface-200"
                >
                    <component :is="getIcon('CheckSquare')" class="h-5 w-5" />
                </span>
                <div>
                    <p
                        class="text-xs font-medium text-surface-500 dark:text-surface-400"
                    >
                        Tugas di rentang ini
                    </p>
                    <p
                        class="mt-0.5 text-xl font-bold text-surface-950 dark:text-surface-50"
                    >
                        {{ stats.tasks }}
                    </p>
                </div>
            </div>
            <div class="card flex items-center gap-4 p-4">
                <span
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300"
                >
                    <component :is="getIcon('Clock')" class="h-5 w-5" />
                </span>
                <div>
                    <p
                        class="text-xs font-medium text-surface-500 dark:text-surface-400"
                    >
                        Sedang dikerjakan
                    </p>
                    <p
                        class="mt-0.5 text-xl font-bold text-surface-950 dark:text-surface-50"
                    >
                        {{ stats.inProgress }}
                    </p>
                </div>
            </div>
            <div class="card flex items-center gap-4 p-4">
                <span
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-accent-50 text-accent-700 dark:bg-accent-900/30 dark:text-accent-300"
                >
                    <AlertTriangle class="h-5 w-5" />
                </span>
                <div>
                    <p
                        class="text-xs font-medium text-surface-500 dark:text-surface-400"
                    >
                        Melewati tenggat
                    </p>
                    <p
                        class="mt-0.5 text-xl font-bold text-surface-950 dark:text-surface-50"
                    >
                        {{ stats.overdue }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card flex flex-col gap-4 p-4 sm:flex-row sm:items-end">
            <div class="w-full sm:max-w-xs">
                <Select
                    v-model="projectFilter"
                    label="Proyek"
                    :options="projectOptions"
                />
            </div>
            <div class="w-full sm:max-w-xs">
                <Select
                    v-model="statusFilter"
                    label="Status tugas"
                    :options="statusOptions"
                />
            </div>
            <div
                class="flex flex-wrap items-center gap-3 pb-1 text-xs text-surface-600 sm:ml-auto dark:text-surface-300"
            >
                <span class="flex items-center gap-1.5"
                    ><span class="h-2.5 w-2.5 rounded-full bg-surface-400" />To
                    Do</span
                >
                <span class="flex items-center gap-1.5"
                    ><span
                        class="h-2.5 w-2.5 rounded-full bg-primary-500"
                    />Dikerjakan</span
                >
                <span class="flex items-center gap-1.5"
                    ><span
                        class="h-2.5 w-2.5 rounded-full bg-accent-500"
                    />Review</span
                >
                <span class="flex items-center gap-1.5"
                    ><span
                        class="h-2.5 w-2.5 rounded-full bg-emerald-500"
                    />Selesai</span
                >
            </div>
        </div>

        <!-- Gantt board -->
        <div
            v-if="timelineProjects.length > 0"
            class="card overflow-hidden !p-0"
        >
            <div ref="board" class="overflow-x-auto">
                <div :style="{ minWidth: `${gridMinWidth}px` }">
                    <!-- Day header -->
                    <div
                        class="sticky top-0 z-20 grid border-b border-surface-200 bg-white/95 backdrop-blur-sm dark:border-surface-800 dark:bg-surface-900/95"
                        :style="gridStyle"
                    >
                        <div
                            class="sticky left-0 z-30 flex items-center border-r border-surface-200 bg-white/95 px-4 py-3 text-[11px] font-semibold tracking-wider text-surface-500 uppercase dark:border-surface-800 dark:bg-surface-900/95 dark:text-surface-400"
                        >
                            Proyek / Tugas
                        </div>
                        <div
                            v-for="day in days"
                            :key="day.iso"
                            class="flex flex-col items-center justify-center border-r border-surface-100 py-2 last:border-r-0 dark:border-surface-800/70"
                            :class="{
                                'bg-surface-50/80 dark:bg-surface-950/30':
                                    day.isWeekend || !day.isCurrentMonth,
                                'bg-primary-50/70 dark:bg-primary-950/20':
                                    day.isToday,
                            }"
                        >
                            <span
                                class="text-[9px] font-medium tracking-wide text-surface-400 uppercase"
                                >{{ day.weekday }}</span
                            >
                            <span
                                class="mt-0.5 flex h-6 w-6 items-center justify-center rounded-full text-xs font-semibold"
                                :class="
                                    day.isToday
                                        ? 'bg-primary-600 text-white'
                                        : day.isCurrentMonth
                                          ? 'text-surface-800 dark:text-surface-100'
                                          : 'text-surface-400 dark:text-surface-500'
                                "
                            >
                                {{ day.day }}
                            </span>
                        </div>
                    </div>

                    <!-- Project groups -->
                    <div
                        v-for="project in timelineProjects"
                        :key="project.id"
                        class="border-b border-surface-200 last:border-b-0 dark:border-surface-800"
                    >
                        <div
                            class="grid items-stretch border-b border-surface-100 bg-surface-50/70 dark:border-surface-800 dark:bg-surface-900/40"
                            :style="gridStyle"
                        >
                            <div
                                class="sticky left-0 z-20 flex min-w-0 items-center gap-3 border-r border-surface-200 bg-surface-50 px-4 py-3 dark:border-surface-800 dark:bg-surface-900"
                            >
                                <span
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-white text-primary-700 ring-1 ring-surface-200 dark:bg-surface-800 dark:text-primary-300 dark:ring-surface-700"
                                >
                                    <component
                                        :is="getIcon('FolderKanban')"
                                        class="h-4 w-4"
                                    />
                                </span>
                                <div class="min-w-0">
                                    <p
                                        class="truncate text-sm font-semibold text-surface-900 dark:text-surface-50"
                                    >
                                        {{ project.name }}
                                    </p>
                                    <p
                                        class="text-[11px] text-surface-500 dark:text-surface-400"
                                    >
                                        {{ project.tasks.length }} tugas ·
                                        {{ project.status_label }}
                                    </p>
                                </div>
                            </div>
                            <div
                                class="flex items-center justify-end gap-3 px-4 py-3"
                                :style="{
                                    gridColumn: `span ${totalDays}`,
                                }"
                            >
                                <div
                                    class="hidden h-1.5 w-28 overflow-hidden rounded-full bg-surface-200 sm:block dark:bg-surface-700"
                                >
                                    <div
                                        class="h-full rounded-full bg-primary-600"
                                        :style="{
                                            width: `${projectProgress(project.tasks)}%`,
                                        }"
                                    />
                                </div>
                                <span
                                    class="text-xs font-semibold text-surface-600 dark:text-surface-300"
                                    >{{ projectProgress(project.tasks) }}%</span
                                >
                            </div>
                        </div>

                        <div
                            v-for="task in project.positionedTasks"
                            :key="task.id"
                            class="grid items-stretch border-b border-surface-100 last:border-b-0 dark:border-surface-800/60"
                            :style="gridStyle"
                        >
                            <div
                                class="sticky left-0 z-10 flex min-w-0 items-center gap-2.5 border-r border-surface-200 bg-white px-4 py-2 dark:border-surface-800 dark:bg-surface-900"
                            >
                                <span
                                    class="h-2 w-2 shrink-0 rounded-full"
                                    :class="statusDotClass(task.status)"
                                />
                                <div class="min-w-0">
                                    <p
                                        class="truncate text-xs font-medium text-surface-800 dark:text-surface-100"
                                        :title="task.title"
                                    >
                                        {{ task.title }}
                                    </p>
                                    <p
                                        class="mt-0.5 flex items-center gap-1.5 text-[10px] text-surface-500 dark:text-surface-400"
                                    >
                                        <span>{{ task.priority_label }}</span>
                                        <span
                                            v-if="task.is_overdue"
                                            class="font-semibold text-rose-600 dark:text-rose-400"
                                            >· Terlambat</span
                                        >
                                    </p>
                                </div>
                                <Avatar
                                    v-if="task.assignee"
                                    :src="task.assignee.avatar"
                                    :initials="task.assignee.initials"
                                    :alt="task.assignee.name"
                                    size="xs"
                                    class="ml-auto shrink-0"
                                />
                            </div>

                            <div
                                class="relative h-14"
                                :style="{ gridColumn: `span ${totalDays}` }"
                            >
                                <div
                                    class="absolute inset-0 grid"
                                    :style="{
                                        gridTemplateColumns: `repeat(${totalDays}, minmax(0, 1fr))`,
                                    }"
                                >
                                    <div
                                        v-for="day in days"
                                        :key="day.iso"
                                        class="border-r border-surface-100/80 last:border-r-0 dark:border-surface-800/40"
                                        :class="{
                                            'bg-surface-50/60 dark:bg-surface-950/20':
                                                day.isWeekend ||
                                                !day.isCurrentMonth,
                                            'bg-primary-50/40 dark:bg-primary-950/10':
                                                day.isToday,
                                        }"
                                    />
                                </div>

                                <div
                                    v-if="todayPosition !== null"
                                    class="pointer-events-none absolute inset-y-0 z-10 w-px bg-rose-400/80 dark:bg-rose-400/60"
                                    :style="{ left: `${todayPosition}%` }"
                                />

                                <component
                                    :is="canViewTasks ? Link : 'div'"
                                    :href="
                                        canViewTasks
                                            ? taskRoutes.show.url(task.id)
                                            : undefined
                                    "
                                    class="absolute top-1/2 z-20 flex h-8 -translate-y-1/2 items-center gap-1.5 overflow-hidden rounded-full px-2.5 text-[10px] font-semibold text-white shadow-xs transition-all hover:z-30 hover:scale-[1.01] hover:shadow-md"
                                    :class="barClass(task.status)"
                                    :style="{
                                        left: `${task.left}%`,
                                        width: `${task.width}%`,
                                    }"
                                    :title="`${task.title} · ${task.status_label}`"
                                >
                                    <span
                                        v-if="task.startsBefore"
                                        class="shrink-0 opacity-80"
                                        >‹</span
                                    >
                                    <span class="truncate">{{
                                        task.title
                                    }}</span>
                                    <span
                                        v-if="task.endsAfter"
                                        class="shrink-0 opacity-80"
                                        >›</span
                                    >
                                </component>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <EmptyState
            v-else
            icon="Calendar"
            title="Belum ada tugas di rentang ini"
            description="Tambahkan tanggal mulai dan tenggat pada tugas, atau pindah ke bulan lain untuk melihat timeline proyek."
        >
            <Button class="mt-5" variant="primary" @click="goToToday">
                Kembali ke bulan ini
            </Button>
        </EmptyState>
    </div>
</template>
