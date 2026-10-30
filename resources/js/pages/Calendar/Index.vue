<script setup lang="ts">
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import { calendar } from '@/routes';
import taskRoutes from '@/routes/tasks';
import AppLayout from '@/layouts/AppLayout.vue';
import Avatar from '@/components/ui/Avatar.vue';
import { getIcon } from '@/components/sidebar/icons';

defineOptions({
    layout: AppLayout,
});

interface CalendarTask {
    id: number;
    title: string;
    status: string;
    status_label: string;
    priority: string;
    priority_label: string;
    priority_color: string;
    due_date: string;
    project_name: string;
    assignee: {
        id: number;
        name: string;
        avatar: string | null;
        initials: string;
    } | null;
}

interface DayCell {
    date: number;
    iso: string;
    isCurrentMonth: boolean;
    isToday: boolean;
    isWeekend: boolean;
    tasks: CalendarTask[];
}

interface WeekDay {
    label: string;
    isWeekend: boolean;
}

interface StatItem {
    label: string;
    value: number;
    hint: string;
    icon: string;
    tone: 'primary' | 'accent';
}

const page = usePage();
const now = new Date();
const month =
    (page.props.month as string) ??
    `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
const allTasks = computed(() => (page.props.tasks as CalendarTask[]) ?? []);
const canViewTasks = computed(() => Boolean(page.props.can_view_tasks));

const [year, monthNum] = month.split('-').map(Number);
const firstOfMonth = new Date(year, monthNum - 1, 1);
const lastOfMonth = new Date(year, monthNum, 0);

const today = new Date();
today.setHours(0, 0, 0, 0);

function pad(value: number): string {
    return String(value).padStart(2, '0');
}

function toDateKey(date: Date): string {
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

const todayKey = toDateKey(today);

const monthLabel = computed(() =>
    firstOfMonth.toLocaleDateString('id-ID', {
        month: 'long',
        year: 'numeric',
    }),
);

const monthShort = computed(() =>
    firstOfMonth.toLocaleDateString('id-ID', { month: 'short' }),
);

const todayLabel = computed(() =>
    today.toLocaleDateString('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    }),
);

const isCurrentMonth = computed(() => month === todayKey.slice(0, 7));

const viewMode = ref<'month' | 'agenda'>('month');
const selectedDate = ref(todayKey);
const selectedPriority = ref('all');

const currentTime = ref('');
let timeTimer: any = null;

function updateCurrentTime() {
    const d = new Date();
    currentTime.value =
        d.toLocaleTimeString('id-ID', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
        }) + ' WIB';
}

onMounted(() => {
    updateCurrentTime();
    timeTimer = setInterval(updateCurrentTime, 1000);
});

onBeforeUnmount(() => {
    if (timeTimer) clearInterval(timeTimer);
});

function shiftMonth(offset: number): string {
    const shifted = new Date(year, monthNum - 1 + offset, 1);

    return `${shifted.getFullYear()}-${pad(shifted.getMonth() + 1)}`;
}

const prevMonth = computed(() => shiftMonth(-1));
const nextMonth = computed(() => shiftMonth(1));

const filteredTasks = computed(() => {
    if (selectedPriority.value === 'all') return allTasks.value;
    return allTasks.value.filter((t) => t.priority === selectedPriority.value);
});

const tasksByDate = computed(() => {
    const map: Record<string, CalendarTask[]> = {};

    for (const task of filteredTasks.value) {
        (map[task.due_date] ??= []).push(task);
    }

    return map;
});

const selectedDateTasks = computed(() => {
    return tasksByDate.value[selectedDate.value] ?? [];
});

const days = computed<DayCell[]>(() => {
    const cells: DayCell[] = [];
    const leading = (firstOfMonth.getDay() + 6) % 7; // Grid dimulai hari Senin
    const daysInMonth = lastOfMonth.getDate();
    const prevMonthDays = new Date(year, monthNum - 1, 0).getDate();

    for (let i = leading; i > 0; i--) {
        const date = new Date(year, monthNum - 2, prevMonthDays - i + 1);

        cells.push({
            date: date.getDate(),
            iso: toDateKey(date),
            isCurrentMonth: false,
            isToday: false,
            isWeekend: date.getDay() === 0 || date.getDay() === 6,
            tasks: [],
        });
    }

    for (let d = 1; d <= daysInMonth; d++) {
        const date = new Date(year, monthNum - 1, d);
        const iso = toDateKey(date);

        cells.push({
            date: d,
            iso,
            isCurrentMonth: true,
            isToday: iso === todayKey,
            isWeekend: date.getDay() === 0 || date.getDay() === 6,
            tasks: tasksByDate.value[iso] ?? [],
        });
    }

    const remainder = (7 - (cells.length % 7)) % 7;

    for (let d = 1; d <= remainder; d++) {
        const date = new Date(year, monthNum, d);

        cells.push({
            date: d,
            iso: toDateKey(date),
            isCurrentMonth: false,
            isToday: false,
            isWeekend: date.getDay() === 0 || date.getDay() === 6,
            tasks: [],
        });
    }

    return cells;
});

const weekDays: WeekDay[] = [
    { label: 'Sen', isWeekend: false },
    { label: 'Sel', isWeekend: false },
    { label: 'Rab', isWeekend: false },
    { label: 'Kam', isWeekend: false },
    { label: 'Jum', isWeekend: false },
    { label: 'Sab', isWeekend: true },
    { label: 'Min', isWeekend: true },
];

const totalTasks = computed(() => allTasks.value.length);
const completedTasks = computed(
    () => allTasks.value.filter((task) => task.status === 'done').length,
);
const highPriorityTasks = computed(
    () =>
        allTasks.value.filter(
            (task) => task.priority === 'urgent' || task.priority === 'high',
        ).length,
);
const overdueTasks = computed(
    () =>
        allTasks.value.filter(
            (task) => task.status !== 'done' && task.due_date < todayKey,
        ).length,
);
const completionRate = computed(() =>
    totalTasks.value === 0
        ? 0
        : Math.round((completedTasks.value / totalTasks.value) * 100),
);

const stats = computed<StatItem[]>(() => [
    {
        label: 'Tenggat Bulan Ini',
        value: totalTasks.value,
        hint: `${completedTasks.value} tugas selesai`,
        icon: 'Calendar',
        tone: 'primary',
    },
    {
        label: 'Prioritas Tinggi',
        value: highPriorityTasks.value,
        hint: 'Mendesak & tinggi',
        icon: 'AlertTriangle',
        tone: 'accent',
    },
    {
        label: 'Selesai',
        value: completedTasks.value,
        hint: `${completionRate.value}% dari bulan ini`,
        icon: 'CheckCircle2',
        tone: 'primary',
    },
    {
        label: 'Lewat Tenggat',
        value: overdueTasks.value,
        hint: 'Belum diselesaikan',
        icon: 'Clock',
        tone: 'accent',
    },
]);

const prioritySummary = computed(() => [
    {
        label: 'Mendesak',
        count: allTasks.value.filter((task) => task.priority === 'urgent')
            .length,
        dot: 'bg-accent-600',
    },
    {
        label: 'Tinggi',
        count: allTasks.value.filter((task) => task.priority === 'high').length,
        dot: 'bg-accent-400',
    },
    {
        label: 'Sedang',
        count: allTasks.value.filter((task) => task.priority === 'medium')
            .length,
        dot: 'bg-primary-500',
    },
    {
        label: 'Rendah',
        count: allTasks.value.filter((task) => task.priority === 'low').length,
        dot: 'bg-surface-400 dark:bg-surface-500',
    },
]);

const agendaTasks = computed(() => {
    const sorted = allTasks.value
        .slice()
        .sort((a, b) => a.due_date.localeCompare(b.due_date));

    if (!isCurrentMonth.value) {
        return sorted.slice(0, 6);
    }

    const upcoming = sorted.filter((task) => task.due_date >= todayKey);

    return (upcoming.length > 0 ? upcoming : sorted).slice(0, 6);
});

const agendaTitle = computed(() =>
    isCurrentMonth.value ? 'Tenggat Terdekat' : 'Tenggat Bulan Ini',
);

const priorityChip: Record<string, string> = {
    low: 'bg-surface-100 text-surface-700 hover:bg-surface-200 dark:bg-surface-800 dark:text-surface-200 dark:hover:bg-surface-700',
    medium: 'bg-primary-50 text-primary-800 hover:bg-primary-100 dark:bg-primary-900/30 dark:text-primary-200 dark:hover:bg-primary-900/50',
    high: 'bg-accent-50 text-accent-800 hover:bg-accent-100 dark:bg-accent-900/20 dark:text-accent-200 dark:hover:bg-accent-900/35',
    urgent: 'bg-accent-100 text-accent-900 hover:bg-accent-200 dark:bg-accent-900/35 dark:text-accent-100 dark:hover:bg-accent-900/50',
};

const priorityDot: Record<string, string> = {
    low: 'bg-surface-400 dark:bg-surface-500',
    medium: 'bg-primary-500',
    high: 'bg-accent-400',
    urgent: 'bg-accent-600',
};

const toneClasses: Record<'primary' | 'accent', { icon: string; bar: string }> =
    {
        primary: {
            icon: 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-200',
            bar: 'bg-primary-500',
        },
        accent: {
            icon: 'bg-accent-50 text-accent-700 dark:bg-accent-900/20 dark:text-accent-200',
            bar: 'bg-accent-500',
        },
    };

function dayNumberClass(cell: DayCell): string {
    if (cell.iso === selectedDate.value) {
        return 'bg-[#0F5132] text-white shadow-md ring-2 ring-[#0F5132]/40 dark:bg-[#0F5132]';
    }

    if (cell.isToday) {
        return 'bg-primary-600 text-white shadow-sm shadow-primary-600/30';
    }

    if (!cell.isCurrentMonth) {
        return 'text-surface-300 dark:text-surface-700';
    }

    if (cell.isWeekend) {
        return 'text-accent-700 dark:text-accent-300';
    }

    return 'text-surface-700 dark:text-surface-200';
}

function formatDate(value: string): string {
    return new Date(value + 'T00:00:00').toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

function dateParts(value: string): { day: string; month: string } {
    const date = new Date(value + 'T00:00:00');

    return {
        day: String(date.getDate()),
        month: date.toLocaleDateString('id-ID', { month: 'short' }),
    };
}

function relativeDueLabel(value: string): string {
    const due = new Date(value + 'T00:00:00');
    const diffDays = Math.round((due.getTime() - today.getTime()) / 86_400_000);

    if (diffDays === 0) return 'Hari ini';
    if (diffDays === 1) return 'Besok';
    if (diffDays === -1) return 'Kemarin';
    if (diffDays < 0) return `Terlambat ${Math.abs(diffDays)} hari`;
    if (diffDays <= 7) return `${diffDays} hari lagi`;

    return formatDate(value);
}

function isOverdue(task: CalendarTask): boolean {
    return task.status !== 'done' && task.due_date < todayKey;
}

function dateBadgeClass(task: CalendarTask): string {
    if (isOverdue(task)) {
        return 'border-accent-300 bg-accent-50 text-accent-800 dark:border-accent-700/40 dark:bg-accent-900/20 dark:text-accent-200';
    }

    if (task.due_date === todayKey) {
        return 'border-primary-300 bg-primary-50 text-primary-700 dark:border-primary-700/50 dark:bg-primary-900/30 dark:text-primary-200';
    }

    if (task.status === 'done') {
        return 'border-surface-200 bg-surface-50 text-surface-500 dark:border-surface-700 dark:bg-surface-800/60 dark:text-surface-400';
    }

    return 'border-surface-200 bg-white text-surface-700 dark:border-surface-700 dark:bg-surface-800 dark:text-surface-200';
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
                    <component :is="getIcon('Calendar')" class="h-3 w-3" />
                    Kalender Tim
                </span>
                <h1
                    class="mt-2 text-2xl font-semibold tracking-tight text-surface-950 sm:text-3xl dark:text-surface-50"
                >
                    Kalender
                </h1>
                <p
                    class="mt-1 max-w-2xl text-sm leading-6 text-surface-700 dark:text-surface-200"
                >
                    Pantau jadwal, tenggat, dan ritme kerja tim dalam satu
                    tampilan.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span
                    class="hidden items-center gap-2 rounded-xl border border-surface-200 bg-white px-3 py-2 text-xs font-semibold text-surface-600 sm:inline-flex dark:border-surface-700 dark:bg-surface-900 dark:text-surface-300"
                >
                    <span class="h-1.5 w-1.5 rounded-full bg-primary-500" />
                    {{ todayLabel }}
                </span>
                <Link
                    v-if="!isCurrentMonth"
                    :href="calendar.url()"
                    class="btn btn-secondary btn-sm"
                >
                    <component :is="getIcon('Calendar')" class="h-3.5 w-3.5" />
                    Hari Ini
                </Link>
                <Link
                    v-if="canViewTasks"
                    :href="taskRoutes.create.url()"
                    class="btn btn-primary"
                >
                    <component :is="getIcon('Plus')" class="h-4 w-4" />
                    Buat Tugas
                </Link>
            </div>
        </div>

        <!-- Stat cards -->
        <section class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
            <div
                v-for="stat in stats"
                :key="stat.label"
                class="group relative min-w-0 overflow-hidden rounded-2xl border border-surface-200 bg-white p-3.5 shadow-xs transition-colors hover:border-primary-300/80 sm:p-5 dark:border-surface-700 dark:bg-surface-900 dark:hover:border-primary-500/40"
            >
                <div
                    class="pointer-events-none absolute -top-8 -right-8 h-24 w-24 rounded-full opacity-0 blur-2xl transition-opacity duration-300 group-hover:opacity-100"
                    :class="
                        stat.tone === 'primary'
                            ? 'bg-primary-500/10'
                            : 'bg-accent-500/10'
                    "
                />
                <div class="relative flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p
                            class="text-[9px] leading-tight font-bold tracking-wide text-surface-500 uppercase sm:text-[11px] sm:tracking-wider dark:text-surface-400"
                        >
                            {{ stat.label }}
                        </p>
                        <p
                            class="mt-2 text-2xl font-black tracking-tight text-surface-950 sm:text-3xl dark:text-surface-50"
                        >
                            {{ stat.value }}
                        </p>
                        <p
                            class="mt-1 text-[10px] leading-tight font-medium text-surface-500 sm:text-[11px] dark:text-surface-400"
                        >
                            {{ stat.hint }}
                        </p>
                    </div>
                    <span
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl sm:h-10 sm:w-10"
                        :class="toneClasses[stat.tone].icon"
                    >
                        <component
                            :is="getIcon(stat.icon)"
                            class="h-4 w-4 sm:h-[18px] sm:w-[18px]"
                        />
                    </span>
                </div>
            </div>
        </section>

        <!-- Content -->
        <div
            class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_21rem] xl:items-start xl:gap-6"
        >
            <!-- Calendar -->
            <section
                class="min-w-0 overflow-hidden rounded-2xl border border-surface-200 bg-white shadow-xs dark:border-surface-700 dark:bg-surface-900"
            >
                <!-- Toolbar -->
                <div
                    class="flex flex-wrap items-center justify-between gap-3 border-b border-surface-200 px-4 py-4 sm:px-5 dark:border-surface-800"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-11 w-11 flex-col items-center justify-center rounded-xl bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-200"
                        >
                            <span
                                class="text-[9px] leading-none font-black tracking-wider uppercase"
                            >
                                {{ monthShort }}
                            </span>
                            <span class="mt-1 text-sm leading-none font-black">
                                {{ year }}
                            </span>
                        </div>
                        <div>
                            <h2
                                class="text-base font-bold tracking-tight text-surface-950 capitalize sm:text-lg dark:text-surface-50"
                            >
                                {{ monthLabel }}
                            </h2>
                            <p
                                class="mt-0.5 text-[11px] font-medium text-surface-500 dark:text-surface-400"
                            >
                                {{ totalTasks }} tenggat ·
                                {{ completedTasks }} selesai
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <!-- View mode toggle -->
                        <div
                            class="flex items-center rounded-xl border border-surface-200 bg-surface-50 p-0.5 dark:border-surface-700 dark:bg-surface-800/60"
                        >
                            <button
                                type="button"
                                class="rounded-lg px-2.5 py-1 text-xs font-semibold transition"
                                :class="
                                    viewMode === 'month'
                                        ? 'bg-[#0F5132] text-white shadow-2xs'
                                        : 'text-surface-600 hover:text-surface-900 dark:text-surface-300 dark:hover:text-white'
                                "
                                @click="viewMode = 'month'"
                            >
                                Bulan
                            </button>
                            <button
                                type="button"
                                class="rounded-lg px-2.5 py-1 text-xs font-semibold transition"
                                :class="
                                    viewMode === 'agenda'
                                        ? 'bg-[#0F5132] text-white shadow-2xs'
                                        : 'text-surface-600 hover:text-surface-900 dark:text-surface-300 dark:hover:text-white'
                                "
                                @click="viewMode = 'agenda'"
                            >
                                Agenda
                            </button>
                        </div>

                        <!-- Prev / Next Month Navigator -->
                        <div
                            class="flex items-center rounded-xl border border-surface-200 bg-surface-50 p-0.5 dark:border-surface-700 dark:bg-surface-800/60"
                        >
                            <Link
                                :href="
                                    calendar.url({
                                        query: { month: prevMonth },
                                    })
                                "
                                class="flex h-8 w-8 items-center justify-center rounded-lg text-surface-600 transition-colors hover:bg-white hover:text-primary-700 hover:shadow-xs dark:text-surface-300 dark:hover:bg-surface-700 dark:hover:text-primary-200"
                                aria-label="Bulan sebelumnya"
                            >
                                <component
                                    :is="getIcon('ChevronLeft')"
                                    class="h-4 w-4"
                                />
                            </Link>
                            <Link
                                :href="
                                    calendar.url({
                                        query: { month: nextMonth },
                                    })
                                "
                                class="flex h-8 w-8 items-center justify-center rounded-lg text-surface-600 transition-colors hover:bg-white hover:text-primary-700 hover:shadow-xs dark:text-surface-300 dark:hover:bg-surface-700 dark:hover:text-primary-200"
                                aria-label="Bulan berikutnya"
                            >
                                <component
                                    :is="getIcon('ChevronRight')"
                                    class="h-4 w-4"
                                />
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Priority Filter Strip -->
                <div
                    class="flex flex-wrap items-center justify-between gap-2 border-b border-surface-100 bg-[#FAF9F4]/70 px-4 py-2 sm:px-5 dark:border-surface-800 dark:bg-surface-950/40"
                >
                    <div class="flex flex-wrap items-center gap-1.5">
                        <span
                            class="mr-1 text-[11px] font-semibold tracking-wider text-surface-500 uppercase"
                        >
                            Prioritas:
                        </span>
                        <button
                            v-for="p in [
                                { val: 'all', label: 'Semua' },
                                { val: 'urgent', label: 'Mendesak' },
                                { val: 'high', label: 'Tinggi' },
                                { val: 'medium', label: 'Sedang' },
                                { val: 'low', label: 'Rendah' },
                            ]"
                            :key="p.val"
                            type="button"
                            class="rounded-full px-2.5 py-0.5 text-[11px] font-medium transition active:scale-95"
                            :class="
                                selectedPriority === p.val
                                    ? 'bg-[#0F5132] text-white shadow-2xs'
                                    : 'bg-surface-100 text-surface-600 hover:bg-surface-200 dark:bg-surface-800 dark:text-surface-300'
                            "
                            @click="selectedPriority = p.val"
                        >
                            {{ p.label }}
                        </button>
                    </div>

                    <div class="text-[11px] font-medium text-surface-500">
                        {{ filteredTasks.length }} tugas ditampilkan
                    </div>
                </div>

                <!-- Weekday header -->
                <div
                    class="grid grid-cols-7 border-b border-surface-200 bg-surface-50/80 dark:border-surface-800 dark:bg-surface-950/30"
                >
                    <div
                        v-for="day in weekDays"
                        :key="day.label"
                        class="py-2.5 text-center text-[10px] font-bold tracking-wider uppercase sm:text-[11px]"
                        :class="
                            day.isWeekend
                                ? 'text-accent-700 dark:text-accent-300'
                                : 'text-surface-500 dark:text-surface-400'
                        "
                    >
                        {{ day.label }}
                    </div>
                </div>

                <!-- Day grid -->
                <div
                    class="grid grid-cols-7 gap-px bg-surface-200 dark:bg-surface-800"
                >
                    <div
                        v-for="cell in days"
                        :key="cell.iso"
                        class="relative min-h-[4.75rem] p-1.5 transition-colors sm:min-h-[6.75rem] sm:p-2 xl:min-h-[7.25rem]"
                        :class="[
                            cell.isCurrentMonth
                                ? 'bg-white hover:bg-surface-50 dark:bg-surface-900 dark:hover:bg-surface-800/60'
                                : 'bg-surface-50/70 dark:bg-surface-950/40',
                            cell.isToday
                                ? 'bg-primary-50/50 ring-1 ring-primary-500/40 ring-inset dark:bg-primary-900/15'
                                : '',
                        ]"
                    >
                        <div class="flex items-center justify-between gap-1">
                            <span
                                class="flex h-6 w-6 items-center justify-center rounded-full text-[11px] font-bold sm:h-7 sm:w-7 sm:text-xs"
                                :class="dayNumberClass(cell)"
                            >
                                {{ cell.date }}
                            </span>
                            <span
                                v-if="cell.isToday"
                                class="hidden rounded-full bg-primary-600 px-1.5 py-0.5 text-[8px] font-black tracking-wide text-white uppercase lg:inline-flex"
                            >
                                Hari ini
                            </span>
                        </div>

                        <!-- Desktop event chips -->
                        <div
                            v-if="cell.tasks.length"
                            class="mt-1.5 hidden space-y-1 sm:block"
                        >
                            <component
                                :is="canViewTasks ? Link : 'div'"
                                v-for="task in cell.tasks.slice(0, 3)"
                                :key="task.id"
                                v-bind="
                                    canViewTasks
                                        ? {
                                              href: taskRoutes.show.url(
                                                  task.id,
                                              ),
                                          }
                                        : {}
                                "
                                class="flex items-center gap-1.5 rounded-md px-1.5 py-1 text-[10px] leading-tight font-semibold transition-colors sm:text-[11px]"
                                :class="[
                                    priorityChip[task.priority],
                                    task.status === 'done' ? 'opacity-60' : '',
                                ]"
                                :title="`${task.title} · ${task.project_name} · ${task.priority_label}`"
                            >
                                <span
                                    class="h-1.5 w-1.5 shrink-0 rounded-full"
                                    :class="priorityDot[task.priority]"
                                />
                                <span
                                    class="truncate"
                                    :class="
                                        task.status === 'done'
                                            ? 'line-through decoration-1'
                                            : ''
                                    "
                                >
                                    {{ task.title }}
                                </span>
                            </component>
                            <p
                                v-if="cell.tasks.length > 3"
                                class="px-1.5 text-[10px] font-semibold text-surface-500 dark:text-surface-400"
                            >
                                +{{ cell.tasks.length - 3 }} tugas lain
                            </p>
                        </div>

                        <!-- Mobile dots -->
                        <div
                            v-if="cell.tasks.length"
                            class="mt-1.5 flex flex-wrap items-center gap-1 sm:hidden"
                        >
                            <span
                                v-for="task in cell.tasks.slice(0, 4)"
                                :key="task.id"
                                class="h-1.5 w-1.5 rounded-full"
                                :class="priorityDot[task.priority]"
                            />
                            <span
                                v-if="cell.tasks.length > 4"
                                class="text-[9px] leading-none font-bold text-surface-500 dark:text-surface-400"
                            >
                                +{{ cell.tasks.length - 4 }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div
                    class="flex flex-wrap items-center justify-between gap-2 border-t border-surface-200 px-4 py-3 sm:px-5 dark:border-surface-800"
                >
                    <p
                        class="text-[11px] font-medium text-surface-500 dark:text-surface-400"
                    >
                        {{
                            canViewTasks
                                ? 'Klik tugas pada kalender untuk membuka detailnya.'
                                : 'Warna penanda menunjukkan prioritas tugas.'
                        }}
                    </p>
                    <div class="flex flex-wrap items-center gap-3">
                        <span
                            v-for="item in prioritySummary"
                            :key="item.label"
                            class="inline-flex items-center gap-1.5 text-[10px] font-semibold text-surface-500 dark:text-surface-400"
                        >
                            <span
                                class="h-1.5 w-1.5 rounded-full"
                                :class="item.dot"
                            />
                            {{ item.label }}
                        </span>
                    </div>
                </div>
            </section>

            <!-- Right rail -->
            <aside class="min-w-0 space-y-4">
                <!-- Agenda -->
                <section
                    class="overflow-hidden rounded-2xl border border-surface-200 bg-white shadow-xs dark:border-surface-700 dark:bg-surface-900"
                >
                    <div
                        class="flex items-center justify-between border-b border-surface-200 px-4 py-4 dark:border-surface-800"
                    >
                        <div class="flex items-center gap-3">
                            <span
                                class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-200"
                            >
                                <component
                                    :is="getIcon('Clock')"
                                    class="h-4 w-4"
                                />
                            </span>
                            <div>
                                <h2
                                    class="text-sm font-bold text-surface-950 dark:text-surface-50"
                                >
                                    {{ agendaTitle }}
                                </h2>
                                <p
                                    class="text-[11px] text-surface-500 dark:text-surface-400"
                                >
                                    {{ agendaTasks.length }} tugas terdekat
                                </p>
                            </div>
                        </div>
                        <span
                            v-if="totalTasks"
                            class="rounded-full bg-surface-100 px-2 py-0.5 text-[10px] font-bold text-surface-600 dark:bg-surface-800 dark:text-surface-300"
                        >
                            {{ totalTasks }}
                        </span>
                    </div>

                    <div
                        v-if="agendaTasks.length"
                        class="divide-y divide-surface-100 dark:divide-surface-800/70"
                    >
                        <component
                            :is="canViewTasks ? Link : 'div'"
                            v-for="task in agendaTasks"
                            :key="task.id"
                            v-bind="
                                canViewTasks
                                    ? {
                                          href: taskRoutes.show.url(task.id),
                                      }
                                    : {}
                            "
                            class="group flex gap-3 px-4 py-3.5 transition-colors hover:bg-surface-50 dark:hover:bg-surface-800/40"
                        >
                            <div
                                class="flex h-11 w-11 shrink-0 flex-col items-center justify-center rounded-xl border"
                                :class="dateBadgeClass(task)"
                            >
                                <span class="text-sm leading-none font-black">
                                    {{ dateParts(task.due_date).day }}
                                </span>
                                <span
                                    class="mt-1 text-[9px] leading-none font-bold tracking-wide uppercase"
                                >
                                    {{ dateParts(task.due_date).month }}
                                </span>
                            </div>

                            <div class="min-w-0 flex-1">
                                <div
                                    class="flex items-start justify-between gap-2"
                                >
                                    <p
                                        class="truncate text-sm font-semibold text-surface-900 group-hover:text-primary-700 dark:text-surface-100 dark:group-hover:text-primary-300"
                                    >
                                        {{ task.title }}
                                    </p>
                                    <Avatar
                                        v-if="task.assignee"
                                        :src="task.assignee.avatar"
                                        :initials="task.assignee.initials"
                                        :alt="task.assignee.name"
                                        size="sm"
                                        class="!h-6 !w-6 shrink-0 text-[9px]"
                                    />
                                </div>
                                <p
                                    class="mt-0.5 truncate text-[11px] text-surface-500 dark:text-surface-400"
                                >
                                    {{ task.project_name }}
                                </p>
                                <div
                                    class="mt-1.5 flex flex-wrap items-center gap-1.5"
                                >
                                    <span
                                        class="inline-flex items-center rounded-full px-1.5 py-0.5 text-[9px] font-bold tracking-wide uppercase"
                                        :class="task.priority_color"
                                    >
                                        {{ task.priority_label }}
                                    </span>
                                    <span
                                        class="text-[10px] font-medium"
                                        :class="
                                            isOverdue(task)
                                                ? 'text-accent-700 dark:text-accent-300'
                                                : 'text-surface-500 dark:text-surface-400'
                                        "
                                    >
                                        {{ relativeDueLabel(task.due_date) }}
                                    </span>
                                </div>
                            </div>
                        </component>
                    </div>

                    <div
                        v-else
                        class="flex flex-col items-center px-6 py-10 text-center"
                    >
                        <div
                            class="flex h-11 w-11 items-center justify-center rounded-xl bg-surface-100 text-surface-400 dark:bg-surface-800 dark:text-surface-500"
                        >
                            <component
                                :is="getIcon('Calendar')"
                                class="h-5 w-5"
                            />
                        </div>
                        <p
                            class="mt-3 text-sm font-semibold text-surface-800 dark:text-surface-200"
                        >
                            Belum ada tenggat
                        </p>
                        <p
                            class="mt-1 text-xs leading-5 text-surface-500 dark:text-surface-400"
                        >
                            Tenggat tugas bulan ini akan muncul di sini.
                        </p>
                        <Link
                            v-if="canViewTasks"
                            :href="taskRoutes.create.url()"
                            class="btn btn-primary btn-sm mt-4"
                        >
                            <component
                                :is="getIcon('Plus')"
                                class="h-3.5 w-3.5"
                            />
                            Buat Tugas
                        </Link>
                    </div>

                    <div
                        v-if="canViewTasks && agendaTasks.length"
                        class="border-t border-surface-100 px-4 py-3 dark:border-surface-800/70"
                    >
                        <Link
                            :href="taskRoutes.index.url()"
                            class="flex items-center justify-center gap-1.5 text-[11px] font-bold text-primary-700 transition-colors hover:text-primary-800 dark:text-primary-300 dark:hover:text-primary-200"
                        >
                            Lihat semua tugas
                            <component
                                :is="getIcon('ArrowRight')"
                                class="h-3.5 w-3.5"
                            />
                        </Link>
                    </div>
                </section>

                <!-- Month summary -->
                <section
                    class="rounded-2xl border border-surface-200 bg-white p-4 shadow-xs dark:border-surface-700 dark:bg-surface-900"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span
                                class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-200"
                            >
                                <component
                                    :is="getIcon('TrendingUp')"
                                    class="h-4 w-4"
                                />
                            </span>
                            <h2
                                class="text-sm font-bold text-surface-950 dark:text-surface-50"
                            >
                                Ringkasan Bulan
                            </h2>
                        </div>
                        <span
                            class="rounded-full bg-surface-100 px-2 py-0.5 text-[10px] font-bold text-surface-600 dark:bg-surface-800 dark:text-surface-300"
                        >
                            {{ totalTasks }}
                        </span>
                    </div>

                    <div class="mt-4 flex items-center gap-4">
                        <div class="relative h-24 w-24 shrink-0">
                            <svg
                                viewBox="0 0 36 36"
                                class="h-24 w-24 -rotate-90"
                                aria-hidden="true"
                            >
                                <circle
                                    cx="18"
                                    cy="18"
                                    r="15.9155"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="3.5"
                                    class="text-surface-100 dark:text-surface-800"
                                />
                                <circle
                                    cx="18"
                                    cy="18"
                                    r="15.9155"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="3.5"
                                    stroke-linecap="round"
                                    :stroke-dasharray="`${completionRate} 100`"
                                    class="text-primary-500 transition-all duration-500"
                                />
                            </svg>
                            <div
                                class="absolute inset-0 flex flex-col items-center justify-center"
                            >
                                <span
                                    class="text-xl font-black text-surface-950 dark:text-surface-50"
                                >
                                    {{ completionRate }}%
                                </span>
                                <span
                                    class="text-[9px] font-bold tracking-wide text-surface-500 uppercase dark:text-surface-400"
                                >
                                    selesai
                                </span>
                            </div>
                        </div>
                        <div class="min-w-0">
                            <p
                                class="text-sm font-bold text-surface-950 dark:text-surface-50"
                            >
                                Progres bulan ini
                            </p>
                            <p
                                class="mt-1 text-xs leading-5 text-surface-500 dark:text-surface-400"
                            >
                                {{ completedTasks }} dari {{ totalTasks }} tugas
                                telah diselesaikan.
                            </p>
                        </div>
                    </div>

                    <div class="mt-5 grid grid-cols-2 gap-2">
                        <div
                            v-for="item in prioritySummary"
                            :key="item.label"
                            class="flex items-center justify-between rounded-xl border border-surface-100 bg-surface-50/70 px-2.5 py-2 dark:border-surface-800 dark:bg-surface-800/40"
                        >
                            <span
                                class="flex items-center gap-1.5 text-[11px] font-semibold text-surface-600 dark:text-surface-300"
                            >
                                <span
                                    class="h-1.5 w-1.5 rounded-full"
                                    :class="item.dot"
                                />
                                {{ item.label }}
                            </span>
                            <span
                                class="text-xs font-black text-surface-900 dark:text-surface-100"
                            >
                                {{ item.count }}
                            </span>
                        </div>
                    </div>
                </section>
            </aside>
        </div>
    </div>
</template>
