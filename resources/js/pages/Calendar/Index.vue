<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
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

const isCurrentMonth = computed(() => month === todayKey.slice(0, 7));

const viewMode = ref<'month' | 'agenda'>('month');
const selectedDate = ref(
    isCurrentMonth.value ? todayKey : toDateKey(firstOfMonth),
);
const selectedPriority = ref('all');

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

const selectedDateLabel = computed(() => {
    return new Date(selectedDate.value + 'T00:00:00').toLocaleDateString(
        'id-ID',
        {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        },
    );
});

// If selected date has no tasks, find nearest tasks in the month to avoid blank wasted space!
const nearestTasks = computed(() => {
    const target = selectedDate.value;
    return allTasks.value
        .slice()
        .sort((a, b) => {
            const diffA = Math.abs(new Date(a.due_date).getTime() - new Date(target).getTime());
            const diffB = Math.abs(new Date(b.due_date).getTime() - new Date(target).getTime());
            return diffA - diffB;
        })
        .slice(0, 4);
});

const days = computed<DayCell[]>(() => {
    const cells: DayCell[] = [];
    const leading = (firstOfMonth.getDay() + 6) % 7; // Start Monday
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
    { label: 'Senin', isWeekend: false },
    { label: 'Selasa', isWeekend: false },
    { label: 'Rabu', isWeekend: false },
    { label: 'Kamis', isWeekend: false },
    { label: 'Jumat', isWeekend: false },
    { label: 'Sabtu', isWeekend: true },
    { label: 'Minggu', isWeekend: true },
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

interface ProjectDeadlineSummary {
    projectName: string;
    totalTasks: number;
    completedTasks: number;
    completionRate: number;
    earliestDue: string;
    latestDue: string;
    urgentCount: number;
    highCount: number;
}

const projectDeadlinesSummary = computed<ProjectDeadlineSummary[]>(() => {
    const map = new Map<string, ProjectDeadlineSummary>();

    for (const task of allTasks.value) {
        const name = task.project_name || 'Proyek Tanpa Nama';
        if (!map.has(name)) {
            map.set(name, {
                projectName: name,
                totalTasks: 0,
                completedTasks: 0,
                completionRate: 0,
                earliestDue: task.due_date,
                latestDue: task.due_date,
                urgentCount: 0,
                highCount: 0,
            });
        }
        const item = map.get(name)!;
        item.totalTasks++;
        if (task.status === 'done') item.completedTasks++;
        if (task.priority === 'urgent') item.urgentCount++;
        if (task.priority === 'high') item.highCount++;
        if (task.due_date < item.earliestDue) item.earliestDue = task.due_date;
        if (task.due_date > item.latestDue) item.latestDue = task.due_date;
    }

    for (const item of map.values()) {
        item.completionRate =
            item.totalTasks > 0
                ? Math.round((item.completedTasks / item.totalTasks) * 100)
                : 0;
    }

    return Array.from(map.values()).sort((a, b) => b.totalTasks - a.totalTasks);
});

// Upcoming agenda tasks sorted chronologically
const agendaTasks = computed(() => {
    const sorted = allTasks.value
        .slice()
        .sort((a, b) => a.due_date.localeCompare(b.due_date));

    if (!isCurrentMonth.value) {
        return sorted.slice(0, 8);
    }

    const upcoming = sorted.filter((task) => task.due_date >= todayKey);
    return (upcoming.length > 0 ? upcoming : sorted).slice(0, 8);
});

// Grouped by date for Agenda View
const agendaDays = computed(() => {
    const dates = Object.keys(tasksByDate.value).sort();
    return dates.map((date) => ({
        date,
        formattedDate: new Date(date + 'T00:00:00').toLocaleDateString(
            'id-ID',
            {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
                year: 'numeric',
            },
        ),
        isToday: date === todayKey,
        isPast: date < todayKey,
        tasks: tasksByDate.value[date] ?? [],
    }));
});

const priorityPills = [
    { val: 'all', label: 'Semua' },
    { val: 'urgent', label: 'Mendesak', dot: 'bg-rose-500' },
    { val: 'high', label: 'Tinggi', dot: 'bg-amber-500' },
    { val: 'medium', label: 'Sedang', dot: 'bg-primary-600' },
    { val: 'low', label: 'Rendah', dot: 'bg-surface-400' },
];

const priorityDot: Record<string, string> = {
    low: 'bg-surface-400',
    medium: 'bg-primary-600',
    high: 'bg-amber-500',
    urgent: 'bg-rose-500',
};

const priorityBadges: Record<string, string> = {
    low: 'bg-surface-100 text-surface-600 border-surface-200 dark:bg-surface-800 dark:text-surface-300 dark:border-surface-700',
    medium: 'bg-primary-50 text-primary-800 border-primary-200/80 dark:bg-primary-950/40 dark:text-primary-300 dark:border-primary-800/40',
    high: 'bg-amber-50 text-amber-800 border-amber-200/80 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800/40',
    urgent: 'bg-rose-50 text-rose-800 border-rose-200/80 dark:bg-rose-950/40 dark:text-rose-300 dark:border-rose-800/40',
};

function selectDate(cell: DayCell): void {
    if (!cell.isCurrentMonth) return;
    selectedDate.value = cell.iso;
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
</script>

<template>
    <div class="space-y-6 pb-6">
        <!-- 1. Page Header -->
        <header
            class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <span
                    class="inline-flex h-6 items-center gap-1.5 rounded-full border border-primary-200/80 bg-primary-50 px-2.5 text-[10px] font-bold tracking-wider text-primary-800 uppercase dark:border-primary-800/40 dark:bg-primary-950/40 dark:text-primary-300"
                >
                    <component :is="getIcon('Calendar')" class="h-3 w-3" />
                    Jadwal & Kalender Tim
                </span>
                <h1
                    class="mt-2 text-2xl font-semibold tracking-tight text-surface-950 sm:text-3xl dark:text-surface-50"
                >
                    Kalender Kerja
                </h1>
                <p
                    class="mt-1 max-w-2xl text-sm leading-relaxed text-surface-600 dark:text-surface-300"
                >
                    Tinjau jadwal tenggat, distribusi beban kerja harian, dan ritme pengerjaan proyek tim secara terpadu.
                </p>
            </div>

            <!-- Header Quick Actions -->
            <div class="flex items-center gap-2.5">
                <Link
                    v-if="!isCurrentMonth"
                    :href="calendar.url()"
                    class="btn btn-secondary text-xs shadow-2xs"
                >
                    <component :is="getIcon('Calendar')" class="h-3.5 w-3.5" />
                    <span>Kembali ke Bulan Ini</span>
                </Link>

                <Link
                    v-if="canViewTasks"
                    :href="taskRoutes.create.url()"
                    class="btn btn-primary text-xs shadow-2xs"
                >
                    <component :is="getIcon('Plus')" class="h-4 w-4" />
                    <span>Buat Tugas</span>
                </Link>
            </div>
        </header>

        <!-- 2. KPI 4 Metric Cards -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
            <div
                class="rounded-3xl border border-surface-200/80 bg-white p-4.5 shadow-xs dark:border-surface-800 dark:bg-surface-900"
            >
                <div class="flex items-center justify-between text-surface-400">
                    <span class="text-xs font-medium text-surface-500 dark:text-surface-400">Tenggat Bulan Ini</span>
                    <component :is="getIcon('Calendar')" class="h-4 w-4" />
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <p class="font-serif text-2xl font-normal text-surface-950 sm:text-3xl dark:text-surface-50">
                        {{ totalTasks }}
                    </p>
                    <span class="text-xs font-semibold text-surface-500">
                        tugas
                    </span>
                </div>
                <p class="mt-1 text-[11px] text-surface-400">
                    {{ completedTasks }} tugas telah tuntas
                </p>
            </div>

            <div
                class="rounded-3xl border border-surface-200/80 bg-white p-4.5 shadow-xs dark:border-surface-800 dark:bg-surface-900"
            >
                <div class="flex items-center justify-between text-surface-400">
                    <span class="text-xs font-medium text-surface-500 dark:text-surface-400">Prioritas Tinggi</span>
                    <component :is="getIcon('AlertTriangle')" class="h-4 w-4 text-amber-500" />
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <p class="font-serif text-2xl font-normal text-surface-950 sm:text-3xl dark:text-surface-50">
                        {{ highPriorityTasks }}
                    </p>
                    <span class="text-xs font-semibold text-amber-600">
                        fokus
                    </span>
                </div>
                <p class="mt-1 text-[11px] text-surface-400">
                    Mendesak & prioritas tinggi
                </p>
            </div>

            <div
                class="rounded-3xl border border-surface-200/80 bg-white p-4.5 shadow-xs dark:border-surface-800 dark:bg-surface-900"
            >
                <div class="flex items-center justify-between text-surface-400">
                    <span class="text-xs font-medium text-surface-500 dark:text-surface-400">Tingkat Tuntas</span>
                    <component :is="getIcon('CheckCircle2')" class="h-4 w-4 text-emerald-600" />
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <p class="font-serif text-2xl font-normal text-surface-950 sm:text-3xl dark:text-surface-50">
                        {{ completionRate }}%
                    </p>
                </div>
                <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-surface-100 dark:bg-surface-800">
                    <div
                        class="h-full rounded-full bg-emerald-600 transition-all duration-700"
                        :style="{ width: `${completionRate}%` }"
                    />
                </div>
            </div>

            <div
                class="rounded-3xl border border-surface-200/80 bg-white p-4.5 shadow-xs dark:border-surface-800 dark:bg-surface-900"
            >
                <div class="flex items-center justify-between text-surface-400">
                    <span class="text-xs font-medium text-surface-500 dark:text-surface-400">Lewat Tenggat</span>
                    <component :is="getIcon('Clock')" class="h-4 w-4 text-rose-500" />
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <p
                        class="font-serif text-2xl font-normal sm:text-3xl"
                        :class="overdueTasks > 0 ? 'text-rose-600' : 'text-surface-950 dark:text-surface-50'"
                    >
                        {{ overdueTasks }}
                    </p>
                    <span
                        v-if="overdueTasks > 0"
                        class="text-xs font-semibold text-rose-600"
                    >
                        perlu tindak
                    </span>
                </div>
                <p class="mt-1 text-[11px] text-surface-400">
                    {{ overdueTasks === 0 ? 'Semua tugas tepat waktu' : 'Perlu penyelesaian segera' }}
                </p>
            </div>
        </div>

        <!-- 3. Main Content: Calendar Grid + Project Summary (Left) + Unified Schedule Sidebar (Right) -->
        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px] xl:items-start">
            <!-- Left Column: Calendar Grid Card + Distribution Card -->
            <div class="min-w-0 space-y-6">
                <!-- Calendar Grid Card -->
                <section
                    class="min-w-0 overflow-hidden rounded-3xl border border-surface-200/80 bg-white shadow-xs dark:border-surface-800 dark:bg-surface-900"
                >
                    <!-- Calendar Toolbar -->
                    <div
                        class="flex flex-col gap-3.5 border-b border-surface-200/80 p-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 dark:border-surface-800"
                    >
                        <div class="flex items-center gap-3">
                            <div
                                class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-primary-50 font-serif text-primary-800 shadow-2xs dark:bg-primary-950/50 dark:text-primary-300"
                            >
                                <component :is="getIcon('Calendar')" class="h-5 w-5" />
                            </div>
                            <div>
                                <h2 class="text-lg font-bold tracking-tight text-surface-950 sm:text-xl dark:text-surface-50">
                                    {{ monthLabel }}
                                </h2>
                                <p class="text-xs text-surface-500 dark:text-surface-400">
                                    {{ filteredTasks.length }} tugas terdata di bulan ini
                                </p>
                            </div>
                        </div>

                        <!-- Toolbar Controls: View Mode & Month Navigator -->
                        <div class="flex items-center gap-2">
                            <!-- View Toggle -->
                            <div class="flex items-center rounded-xl border border-surface-200 bg-surface-50 p-0.5 dark:border-surface-700 dark:bg-surface-800">
                                <button
                                    type="button"
                                    class="rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                                    :class="viewMode === 'month' ? 'bg-white text-surface-900 shadow-2xs dark:bg-surface-900 dark:text-surface-100' : 'text-surface-500'"
                                    @click="viewMode = 'month'"
                                >
                                    Kalender
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                                    :class="viewMode === 'agenda' ? 'bg-white text-surface-900 shadow-2xs dark:bg-surface-900 dark:text-surface-100' : 'text-surface-500'"
                                    @click="viewMode = 'agenda'"
                                >
                                    Agenda
                                </button>
                            </div>

                            <!-- Month Stepper -->
                            <div class="flex items-center gap-1">
                                <Link
                                    :href="calendar.url({ query: { month: prevMonth } })"
                                    class="flex h-8 w-8 items-center justify-center rounded-xl border border-surface-200 text-surface-600 transition hover:bg-surface-100 dark:border-surface-700 dark:text-surface-300 dark:hover:bg-surface-800"
                                    aria-label="Bulan sebelumnya"
                                >
                                    <component :is="getIcon('ChevronLeft')" class="h-4 w-4" />
                                </Link>
                                <Link
                                    :href="calendar.url({ query: { month: nextMonth } })"
                                    class="flex h-8 w-8 items-center justify-center rounded-xl border border-surface-200 text-surface-600 transition hover:bg-surface-100 dark:border-surface-700 dark:text-surface-300 dark:hover:bg-surface-800"
                                    aria-label="Bulan berikutnya"
                                >
                                    <component :is="getIcon('ChevronRight')" class="h-4 w-4" />
                                </Link>
                            </div>
                        </div>
                    </div>

                    <!-- Priority Filter Chips -->
                    <div
                        class="flex items-center gap-2 border-b border-surface-100 bg-surface-50/50 px-4 py-2.5 sm:px-6 dark:border-surface-800/80 dark:bg-surface-950/25"
                    >
                        <span class="text-xs font-medium text-surface-400">Prioritas:</span>
                        <div class="no-scrollbar flex items-center gap-1.5 overflow-x-auto">
                            <button
                                v-for="p in priorityPills"
                                :key="p.val"
                                type="button"
                                class="inline-flex shrink-0 items-center gap-1.5 rounded-xl px-2.5 py-1 text-xs font-semibold transition"
                                :class="
                                    selectedPriority === p.val
                                        ? 'bg-[#0F5132] text-white shadow-2xs dark:bg-primary-600'
                                        : 'bg-white text-surface-600 hover:bg-surface-100 dark:border-surface-700 dark:bg-surface-800 dark:text-surface-300'
                                "
                                @click="selectedPriority = p.val"
                            >
                                <span
                                    v-if="p.dot"
                                    class="h-1.5 w-1.5 rounded-full"
                                    :class="p.dot"
                                />
                                <span>{{ p.label }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- View 1: Month Grid View -->
                    <div v-if="viewMode === 'month'">
                        <!-- Weekday Headers -->
                        <div class="grid grid-cols-7 border-b border-surface-200/80 bg-surface-50/40 dark:border-surface-800 dark:bg-surface-950/20">
                            <div
                                v-for="day in weekDays"
                                :key="day.label"
                                class="py-2.5 text-center text-[11px] font-bold tracking-wider uppercase"
                                :class="day.isWeekend ? 'text-amber-600 dark:text-amber-400' : 'text-surface-500 dark:text-surface-400'"
                            >
                                {{ day.label }}
                            </div>
                        </div>

                        <!-- Days Grid with generous spacing and beautiful task chips -->
                        <div class="grid grid-cols-7 gap-px bg-surface-200/80 dark:bg-surface-800/80">
                            <div
                                v-for="cell in days"
                                :key="cell.iso"
                                class="group relative flex min-h-[100px] flex-col justify-between p-2 transition-all xl:min-h-[114px]"
                                :class="[
                                    cell.isCurrentMonth
                                        ? 'cursor-pointer bg-white hover:bg-surface-50/90 dark:bg-surface-900 dark:hover:bg-surface-800/40'
                                        : 'bg-surface-50/50 dark:bg-surface-950/40',
                                    cell.isToday
                                        ? 'bg-primary-50/40 ring-1 ring-inset ring-[#0F5132]/30 dark:bg-primary-950/20 dark:ring-primary-500/40'
                                        : '',
                                    cell.iso === selectedDate
                                        ? 'ring-2 ring-inset ring-[#0F5132] dark:ring-primary-400'
                                        : '',
                                ]"
                                @click="selectDate(cell)"
                            >
                                <!-- Date Number & Header inside cell -->
                                <div class="flex items-center justify-between">
                                    <span
                                        class="flex h-6 w-6 items-center justify-center rounded-lg text-xs font-bold"
                                        :class="[
                                            cell.isToday
                                                ? 'bg-[#0F5132] text-white dark:bg-primary-500 dark:text-primary-950'
                                                : cell.iso === selectedDate
                                                  ? 'bg-surface-200 text-surface-900 font-black dark:bg-surface-700 dark:text-white'
                                                  : cell.isCurrentMonth
                                                    ? cell.isWeekend
                                                      ? 'text-amber-700 dark:text-amber-300'
                                                      : 'text-surface-700 dark:text-surface-300'
                                                    : 'text-surface-300 dark:text-surface-600',
                                        ]"
                                    >
                                        {{ cell.date }}
                                    </span>

                                    <span
                                        v-if="cell.isToday"
                                        class="text-[9px] font-bold text-[#0F5132] uppercase dark:text-primary-300"
                                    >
                                        Hari Ini
                                    </span>
                                </div>

                                <!-- Task Chips inside cell -->
                                <div class="mt-1 flex-1 space-y-1 overflow-hidden">
                                    <template v-if="cell.tasks.length > 0">
                                        <div
                                            v-for="task in cell.tasks.slice(0, 2)"
                                            :key="`chip-${task.id}`"
                                            class="flex items-center gap-1.5 rounded-lg border px-1.5 py-0.5 text-[10px] leading-tight font-medium transition"
                                            :class="priorityBadges[task.priority] ?? priorityBadges.medium"
                                            :title="`${task.title} (${task.project_name})`"
                                        >
                                            <span
                                                class="h-1.5 w-1.5 shrink-0 rounded-full"
                                                :class="priorityDot[task.priority]"
                                            />
                                            <span
                                                class="truncate"
                                                :class="task.status === 'done' ? 'line-through opacity-70' : ''"
                                            >
                                                {{ task.title }}
                                            </span>
                                        </div>

                                        <!-- Overflow indicator -->
                                        <span
                                            v-if="cell.tasks.length > 2"
                                            class="block text-[10px] font-bold text-surface-500 dark:text-surface-400"
                                        >
                                            +{{ cell.tasks.length - 2 }} lainnya
                                        </span>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- View 2: Agenda Chronological View -->
                    <div v-else class="divide-y divide-surface-100 p-4 sm:p-6 dark:divide-surface-800">
                        <div
                            v-for="group in agendaDays"
                            :key="group.date"
                            class="py-4 first:pt-0 last:pb-0"
                        >
                            <div class="mb-3 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-xl px-2.5 py-1 text-xs font-bold"
                                        :class="
                                            group.isToday
                                                ? 'bg-[#0F5132] text-white dark:bg-primary-600'
                                                : 'bg-surface-100 text-surface-800 dark:bg-surface-800 dark:text-surface-200'
                                        "
                                    >
                                        <component :is="getIcon('Calendar')" class="h-3.5 w-3.5" />
                                        {{ group.formattedDate }}
                                    </span>
                                    <span
                                        v-if="group.isToday"
                                        class="text-[11px] font-bold text-[#0F5132] uppercase dark:text-primary-400"
                                    >
                                        (Hari Ini)
                                    </span>
                                </div>
                                <span class="text-xs font-semibold text-surface-400">
                                    {{ group.tasks.length }} tugas
                                </span>
                            </div>

                            <div class="grid gap-2.5 sm:grid-cols-2">
                                <div
                                    v-for="task in group.tasks"
                                    :key="task.id"
                                    class="flex items-start gap-3 rounded-2xl border border-surface-200/80 bg-white p-3.5 shadow-2xs transition hover:border-surface-300 dark:border-surface-800 dark:bg-surface-900 dark:hover:border-surface-700"
                                >
                                    <span
                                        class="mt-1 h-2 w-2 shrink-0 rounded-full"
                                        :class="priorityDot[task.priority]"
                                    />
                                    <div class="min-w-0 flex-1">
                                        <Link
                                            v-if="canViewTasks"
                                            :href="taskRoutes.show.url(task.id)"
                                            class="truncate text-xs font-semibold text-surface-900 transition hover:text-primary-700 dark:text-surface-100 dark:hover:text-primary-300"
                                            :class="task.status === 'done' ? 'line-through opacity-60' : ''"
                                        >
                                            {{ task.title }}
                                        </Link>
                                        <p v-else class="truncate text-xs font-semibold text-surface-900 dark:text-surface-100">
                                            {{ task.title }}
                                        </p>
                                        <p class="mt-0.5 truncate text-[11px] text-surface-500 dark:text-surface-400">
                                            {{ task.project_name }}
                                        </p>
                                    </div>
                                    <Avatar
                                        v-if="task.assignee"
                                        :src="task.assignee.avatar"
                                        :initials="task.assignee.initials"
                                        :alt="task.assignee.name"
                                        size="xs"
                                        class="shrink-0"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Ringkasan Distribusi Tenggat Proyek Bulan Ini (Menghilangkan ruang kosong di bawah kalender) -->
                <div
                    v-if="projectDeadlinesSummary.length > 0"
                    class="rounded-3xl border border-surface-200/80 bg-white p-5 shadow-xs sm:p-6 dark:border-surface-800 dark:bg-surface-900"
                >
                    <div class="flex items-center justify-between border-b border-surface-100 pb-3.5 dark:border-surface-800">
                        <div class="flex items-center gap-2.5">
                            <span class="grid h-8 w-8 place-items-center rounded-xl bg-primary-50 text-primary-800 dark:bg-primary-950/50 dark:text-primary-300">
                                <component :is="getIcon('FolderKanban')" class="h-4 w-4" />
                            </span>
                            <div>
                                <h3 class="text-sm font-bold text-surface-950 dark:text-surface-50">
                                    Distribusi Tenggat per Proyek Bulan Ini
                                </h3>
                                <p class="text-[11px] text-surface-400">
                                    {{ projectDeadlinesSummary.length }} proyek memiliki agenda tenggat terdata
                                </p>
                            </div>
                        </div>
                        <span class="text-xs font-medium text-surface-500">
                            {{ allTasks.length }} agenda aktif
                        </span>
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div
                            v-for="proj in projectDeadlinesSummary"
                            :key="proj.projectName"
                            class="flex flex-col justify-between rounded-2xl border border-surface-200/70 bg-surface-50/50 p-4 transition hover:border-primary-600/40 hover:bg-white dark:border-surface-800 dark:bg-surface-950/30 dark:hover:bg-surface-800/40"
                        >
                            <div>
                                <div class="flex items-start justify-between gap-2">
                                    <h4 class="line-clamp-1 text-xs font-bold text-surface-900 dark:text-surface-100">
                                        {{ proj.projectName }}
                                    </h4>
                                    <span class="shrink-0 font-serif text-xs font-normal text-surface-700 dark:text-surface-300">
                                        {{ proj.completionRate }}%
                                    </span>
                                </div>
                                <p class="mt-1 text-[10px] text-surface-400">
                                    Tenggat: {{ formatDate(proj.earliestDue) }} – {{ formatDate(proj.latestDue) }}
                                </p>
                            </div>

                            <div class="mt-3">
                                <div class="h-1.5 w-full overflow-hidden rounded-full bg-surface-200/80 dark:bg-surface-800">
                                    <div
                                        class="h-full rounded-full bg-[#0F5132] transition-all duration-500 dark:bg-[#b7d9c1]"
                                        :style="{ width: `${proj.completionRate}%` }"
                                    />
                                </div>
                                <div class="mt-2 flex items-center justify-between text-[10px] text-surface-500">
                                    <span>{{ proj.completedTasks }} dari {{ proj.totalTasks }} tugas selesai</span>
                                    <span v-if="proj.urgentCount > 0" class="font-semibold text-rose-600">
                                        {{ proj.urgentCount }} mendesak
                                    </span>
                                    <span v-else class="text-emerald-600">
                                        Terkendali
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Unified Schedule & Detail Panel (Sticky, Sejajar & Tanpa Ruang Kosong) -->
            <aside class="space-y-6 sticky top-6 self-start">
                <!-- 1. Selected Date Schedule Details -->
                <div
                    class="rounded-3xl border border-surface-200/80 bg-white p-5 shadow-xs sm:p-6 dark:border-surface-800 dark:bg-surface-900"
                >
                    <div class="flex items-center justify-between border-b border-surface-100 pb-4 dark:border-surface-800">
                        <div>
                            <span class="text-[10px] font-bold tracking-wider text-surface-400 uppercase">
                                Jadwal Tanggal Terpilih
                            </span>
                            <h3 class="mt-0.5 text-base font-bold text-surface-950 capitalize dark:text-surface-50">
                                {{ selectedDateLabel }}
                            </h3>
                        </div>

                        <button
                            v-if="selectedDate !== todayKey"
                            type="button"
                            class="rounded-xl border border-surface-200 px-2.5 py-1 text-xs font-semibold text-surface-600 transition hover:bg-surface-100 dark:border-surface-700 dark:text-surface-300 dark:hover:bg-surface-800"
                            @click="selectedDate = todayKey"
                        >
                            Hari Ini
                        </button>
                    </div>

                    <!-- Tasks for Selected Date -->
                    <div v-if="selectedDateTasks.length > 0" class="mt-4 space-y-2.5">
                        <div
                            v-for="task in selectedDateTasks"
                            :key="task.id"
                            class="flex items-start gap-3 rounded-2xl border border-surface-200/80 bg-surface-50/50 p-3.5 transition hover:border-surface-300 dark:border-surface-800 dark:bg-surface-950/30"
                        >
                            <span
                                class="mt-1 h-2 w-2 shrink-0 rounded-full"
                                :class="priorityDot[task.priority]"
                            />
                            <div class="min-w-0 flex-1">
                                <Link
                                    v-if="canViewTasks"
                                    :href="taskRoutes.show.url(task.id)"
                                    class="truncate text-xs font-semibold text-surface-900 transition hover:text-primary-700 dark:text-surface-100 dark:hover:text-primary-300"
                                    :class="task.status === 'done' ? 'line-through opacity-60' : ''"
                                >
                                    {{ task.title }}
                                </Link>
                                <p v-else class="truncate text-xs font-semibold text-surface-900 dark:text-surface-100">
                                    {{ task.title }}
                                </p>
                                <p class="mt-0.5 text-[11px] text-surface-500 dark:text-surface-400">
                                    {{ task.project_name }}
                                </p>
                                <div class="mt-2 flex items-center gap-2">
                                    <span
                                        class="rounded-full px-2 py-0.5 text-[9px] font-bold uppercase"
                                        :class="priorityBadges[task.priority]"
                                    >
                                        {{ task.priority_label }}
                                    </span>
                                    <span class="text-[10px] text-surface-400">
                                        {{ task.status_label }}
                                    </span>
                                </div>
                            </div>
                            <Avatar
                                v-if="task.assignee"
                                :src="task.assignee.avatar"
                                :initials="task.assignee.initials"
                                :alt="task.assignee.name"
                                size="xs"
                                class="shrink-0"
                            />
                        </div>
                    </div>

                    <!-- If No Tasks on Selected Date: Show nearest schedule instead of empty dead space! -->
                    <div v-else class="mt-4">
                        <div class="rounded-2xl border border-dashed border-surface-200 p-4 text-center dark:border-surface-800">
                            <p class="text-xs font-semibold text-surface-700 dark:text-surface-300">
                                Tidak ada tenggat pada tanggal ini
                            </p>
                            <p class="mt-0.5 text-[11px] text-surface-400">
                                Jadwal terdekat di sekitar tanggal ini:
                            </p>
                        </div>

                        <div class="mt-3 space-y-2">
                            <div
                                v-for="near in nearestTasks"
                                :key="`near-${near.id}`"
                                class="flex items-center justify-between rounded-xl border border-surface-200/70 bg-white p-2.5 transition hover:bg-surface-50 dark:border-surface-800 dark:bg-surface-900 dark:hover:bg-surface-800/40"
                            >
                                <div class="min-w-0 flex-1 pr-2">
                                    <Link
                                        v-if="canViewTasks"
                                        :href="taskRoutes.show.url(near.id)"
                                        class="truncate text-xs font-medium text-surface-900 transition hover:text-primary-700 dark:text-surface-100 dark:hover:text-primary-300"
                                    >
                                        {{ near.title }}
                                    </Link>
                                    <p v-else class="truncate text-xs font-medium text-surface-900 dark:text-surface-100">
                                        {{ near.title }}
                                    </p>
                                    <p class="text-[10px] text-surface-400">
                                        {{ formatDate(near.due_date) }} · {{ relativeDueLabel(near.due_date) }}
                                    </p>
                                </div>
                                <span
                                    class="shrink-0 rounded-full px-2 py-0.5 text-[9px] font-bold uppercase"
                                    :class="priorityBadges[near.priority]"
                                >
                                    {{ near.priority_label }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Upcoming Agenda List across Month -->
                <div
                    class="rounded-3xl border border-surface-200/80 bg-white p-5 shadow-xs sm:p-6 dark:border-surface-800 dark:bg-surface-900"
                >
                    <div class="flex items-center justify-between border-b border-surface-100 pb-3 dark:border-surface-800">
                        <div class="flex items-center gap-2">
                            <component :is="getIcon('Clock')" class="h-4 w-4 text-primary-700 dark:text-primary-400" />
                            <h3 class="text-sm font-bold text-surface-950 dark:text-surface-50">
                                Agenda Mendatang
                            </h3>
                        </div>
                        <span class="rounded-full bg-surface-100 px-2 py-0.5 text-[10px] font-bold text-surface-600 dark:bg-surface-800 dark:text-surface-300">
                            {{ agendaTasks.length }}
                        </span>
                    </div>

                    <div class="mt-3.5 space-y-2.5">
                        <div
                            v-for="task in agendaTasks"
                            :key="`agenda-item-${task.id}`"
                            class="flex items-center gap-3 rounded-2xl border border-surface-200/70 bg-surface-50/40 p-3 transition hover:border-surface-300 dark:border-surface-800 dark:bg-surface-950/20"
                        >
                            <!-- Date badge box -->
                            <div class="flex h-10 w-10 shrink-0 flex-col items-center justify-center rounded-xl bg-white shadow-2xs dark:bg-surface-800">
                                <span class="font-serif text-xs font-bold leading-none text-surface-950 dark:text-surface-50">
                                    {{ dateParts(task.due_date).day }}
                                </span>
                                <span class="text-[9px] font-bold tracking-wider text-surface-400 uppercase">
                                    {{ dateParts(task.due_date).month }}
                                </span>
                            </div>

                            <div class="min-w-0 flex-1">
                                <Link
                                    v-if="canViewTasks"
                                    :href="taskRoutes.show.url(task.id)"
                                    class="truncate text-xs font-semibold text-surface-900 transition hover:text-primary-700 dark:text-surface-100 dark:hover:text-primary-300"
                                    :class="task.status === 'done' ? 'line-through opacity-60' : ''"
                                >
                                    {{ task.title }}
                                </Link>
                                <p v-else class="truncate text-xs font-semibold text-surface-900 dark:text-surface-100">
                                    {{ task.title }}
                                </p>
                                <p class="text-[10px] text-surface-400">
                                    {{ task.project_name }} · {{ relativeDueLabel(task.due_date) }}
                                </p>
                            </div>

                            <Avatar
                                v-if="task.assignee"
                                :src="task.assignee.avatar"
                                :initials="task.assignee.initials"
                                :alt="task.assignee.name"
                                size="xs"
                                class="shrink-0"
                            />
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</template>
