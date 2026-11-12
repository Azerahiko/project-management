<script setup lang="ts">
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { getIcon } from '@/components/sidebar/icons';
import { prefersReducedMotion } from '@/lib/motion';
import { gsap } from 'gsap';
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
const userTimezone = computed(
    () => (page.props.userTimezone as string) ?? 'UTC',
);

function todayInUserTimezone(): Date {
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: userTimezone.value,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(new Date());
    const get = (type: string): number =>
        Number(parts.find((part) => part.type === type)?.value);

    return new Date(Date.UTC(get('year'), get('month') - 1, get('day')));
}
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
        on_time_rate: onTimeRate.value,
        urgent_tasks_count: 0,
        review_tasks_count: 0,
        due_this_week_count: 0,
        completed_tasks_count: 0,
        total_tasks_count: totalPipelineTasks.value,
        completion_rate: overallProgress.value,
        my_tasks_count: stats.value?.my_tasks_count ?? 0,
        overdue_count: stats.value?.overdue_count ?? 0,
    };
});

// Formatted Date & Time Labels
const currentMonthLabel = computed(() => {
    return new Date().toLocaleDateString('id-ID', {
        timeZone: userTimezone.value,
        month: 'long',
        year: 'numeric',
    });
});

const currentDateFormatted = computed(() => {
    return new Date().toLocaleDateString('id-ID', {
        timeZone: userTimezone.value,
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
});

const greetingTime = computed(() => {
    const hour = Number(
        new Intl.DateTimeFormat('en-GB', {
            timeZone: userTimezone.value,
            hour: '2-digit',
            hour12: false,
        }).format(new Date()),
    );

    if (hour < 11) return 'Selamat Pagi';
    if (hour < 15) return 'Selamat Siang';
    if (hour < 18) return 'Selamat Sore';
    return 'Selamat Malam';
});

// Overall completion across active projects
const overallProgress = computed(() => {
    if (!projectProgress.value.length) return 0;
    let total = 0;
    let completed = 0;
    for (const p of projectProgress.value) {
        total += p.tasks_count;
        completed += p.completed_tasks_count;
    }
    return total > 0 ? Math.round((completed / total) * 100) : 0;
});

// Active team users extracted from recent activities
const activeTeamUsers = computed(() => {
    const map = new Map<
        string,
        { name: string; avatar: string | null; initials: string }
    >();
    for (const act of recentActivities.value) {
        if (act.user && !map.has(act.user.name)) {
            map.set(act.user.name, act.user);
        }
    }
    return Array.from(map.values()).slice(0, 4);
});

// Total tasks across all status stages
const totalPipelineTasks = computed(() => {
    const s = tasksByStatus.value || {};
    return (
        (s.todo ?? 0) + (s.in_progress ?? 0) + (s.review ?? 0) + (s.done ?? 0)
    );
});

// On-time execution rate
const onTimeRate = computed(() => {
    const totalActive = stats.value?.active_tasks_count ?? 0;
    const overdue = stats.value?.overdue_count ?? 0;
    if (totalActive === 0) return 100;
    const onTime = Math.max(0, totalActive - overdue);
    return Math.round((onTime / totalActive) * 100);
});

// Stage configuration for breakdown & trend (Calm System style)
const stageMeta: Array<{
    key: TaskStatus;
    short: string;
    label: string;
    description: string;
    color: string;
}> = [
    {
        key: 'todo',
        short: 'Antrean',
        label: 'Belum Mulai',
        description: 'Tugas menunggu dikerjakan',
        color: '#D6D3C8', // Stone
    },
    {
        key: 'in_progress',
        short: 'Proses',
        label: 'Sedang Berjalan',
        description: 'Dalam pengerjaan aktif',
        color: '#88c199', // Sage
    },
    {
        key: 'review',
        short: 'Review',
        label: 'Peninjauan',
        description: 'Menunggu validasi hasil',
        color: '#b7d9c1', // Mint
    },
    {
        key: 'done',
        short: 'Selesai',
        label: 'Selesai',
        description: 'Telah tuntas dikerjakan',
        color: '#0F5132', // Forest
    },
];

const statusDistribution = computed(() => {
    const counts = tasksByStatus.value || {};
    const total = totalPipelineTasks.value;

    return stageMeta.map((st) => {
        const count = counts[st.key] ?? 0;
        const percent = total > 0 ? Math.round((count / total) * 100) : 0;
        return {
            ...st,
            count,
            percent,
        };
    });
});

// Animated gentle ripple wave for living trend line (60fps)
const hoveredStage = ref<string | null>(null);

const hoveredStageInfo = computed(() => {
    if (!hoveredStage.value) return null;
    return (
        statusDistribution.value.find((s) => s.key === hoveredStage.value) ??
        null
    );
});

const wavePhase = ref(0);
let waveAnimFrame: number | null = null;

// Accurate, calm SVG Pipeline Chart calculation with living organic motion
const pipelineTrendData = computed(() => {
    const counts = tasksByStatus.value || {};
    const stages = stageMeta.map((st) => ({
        ...st,
        count: counts[st.key] ?? 0,
    }));

    const rawCounts = stages.map((st) => st.count);
    const maxVal = Math.max(5, ...rawCounts);

    const chartW = 560;
    const chartH = 150;
    const padX = 50;
    const padTop = 25;
    const padBottom = 25;
    const plotH = chartH - padTop - padBottom;
    const stepX = (chartW - padX * 2) / (stages.length - 1);

    const points = stages.map((st, i) => {
        const x = padX + i * stepX;
        const wave = Math.sin(wavePhase.value * 0.8 + i * 1.2) * 2.5;
        const baseY = padTop + plotH - (st.count / maxVal) * plotH;
        const y = Math.max(padTop, Math.min(padTop + plotH, baseY + wave));
        return { ...st, x, y };
    });

    let pathD = '';
    let areaD = '';
    if (points.length > 0) {
        pathD = `M ${points[0].x} ${points[0].y}`;
        for (let i = 0; i < points.length - 1; i++) {
            const p0 = points[i];
            const p1 = points[i + 1];
            const cp1x = p0.x + (p1.x - p0.x) / 2;
            const cp1y = p0.y;
            const cp2x = p0.x + (p1.x - p0.x) / 2;
            const cp2y = p1.y;
            pathD += ` C ${cp1x} ${cp1y}, ${cp2x} ${cp2y}, ${p1.x} ${p1.y}`;
        }
        const bottomY = padTop + plotH + 5;
        areaD = `${pathD} L ${points[points.length - 1].x} ${bottomY} L ${points[0].x} ${bottomY} Z`;
    }

    return {
        stages,
        points,
        pathD,
        areaD,
        chartW,
        chartH,
        maxVal,
    };
});

// Dynamic 7-day current week calendar
const currentWeekDays = computed(() => {
    const today = todayInUserTimezone();
    const dayOfWeek = today.getUTCDay();
    const distanceToMonday = (dayOfWeek + 6) % 7;
    const monday = new Date(today);
    monday.setUTCDate(today.getUTCDate() - distanceToMonday);

    const days = [];
    const dayNames = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];

    for (let i = 0; i < 7; i++) {
        const d = new Date(monday);
        d.setUTCDate(monday.getUTCDate() + i);
        days.push({
            name: dayNames[i],
            date: d.getUTCDate(),
            isToday: d.getTime() === today.getTime(),
        });
    }
    return days;
});

function formatRelativeTime(dateString: string): string {
    if (!dateString) return '';
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMins / 60);
    const diffDays = Math.floor(diffHours / 24);

    if (diffMins < 1) return 'Baru saja';
    if (diffMins < 60) return `${diffMins}m yang lalu`;
    if (diffHours < 24) return `${diffHours}j yang lalu`;
    if (diffDays === 1) return 'Kemarin';
    if (diffDays < 7) return `${diffDays} hari lalu`;
    return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
}

function formatDueDate(dateString: string | null): string {
    if (!dateString) return 'Tanpa tenggat';
    const date = new Date(dateString);
    return date.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

function getProjectTheme(proj: ProjectProgress) {
    const percent = proj.progress_percent ?? 0;
    let statusLabel = 'Tahap Awal';
    if (percent >= 100) statusLabel = 'Selesai';
    else if (percent >= 70) statusLabel = 'Maju Pesat';
    else if (percent >= 30) statusLabel = 'Sedang Berjalan';

    return {
        statusLabel,
    };
}

// Clean status text indicator without badges
function getUrgencyIndicator(urgency: string): {
    text: string;
    dotColor: string;
    textColor: string;
} {
    switch (urgency) {
        case 'overdue':
            return {
                text: 'Lewat tenggat waktu',
                dotColor: 'bg-rose-500',
                textColor: 'text-rose-700 dark:text-rose-400',
            };
        case 'critical':
            return {
                text: 'Mendesak hari ini',
                dotColor: 'bg-amber-500',
                textColor: 'text-amber-800 dark:text-amber-300',
            };
        case 'warning':
            return {
                text: 'Mendekati batas',
                dotColor: 'bg-primary-400',
                textColor: 'text-primary-800 dark:text-primary-300',
            };
        default:
            return {
                text: 'Sesuai jadwal',
                dotColor: 'bg-surface-400',
                textColor: 'text-surface-600 dark:text-surface-400',
            };
    }
}

let ctx: gsap.Context | null = null;

onMounted(() => {
    if (!prefersReducedMotion()) {
        ctx = gsap.context(() => {
            gsap.from('.dash-card', {
                opacity: 0,
                y: 10,
                duration: 0.35,
                stagger: 0.05,
                ease: 'power2.out',
            });
        });

        // Continuous dynamic wave animation for trend curve (60fps)
        const stepWave = () => {
            wavePhase.value += 0.0167;
            waveAnimFrame = requestAnimationFrame(stepWave);
        };
        waveAnimFrame = requestAnimationFrame(stepWave);
    }
});

onBeforeUnmount(() => {
    if (waveAnimFrame) {
        cancelAnimationFrame(waveAnimFrame);
        waveAnimFrame = null;
    }
    ctx?.revert();
    ctx = null;
});
</script>

<template>
    <div class="space-y-6">
        <!-- 1. Header Banner: Clearly Visible, Bright Illustration Background with Calm Aesthetics -->
        <section
            class="dash-card relative overflow-hidden rounded-3xl border border-surface-200/80 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900"
        >
            <!-- Background Illustration Image: Bright, Clear, Not Dark! -->
            <div
                class="absolute inset-0 bg-cover bg-right bg-no-repeat transition-transform duration-700 ease-out hover:scale-[1.01] md:bg-center"
                style="background-image: url('/images/dashboard-hero.png')"
            />

            <!-- Gentle Warm Scrim: Gives text high contrast while keeping the right illustration completely bright and clear -->
            <div
                class="absolute inset-0 bg-gradient-to-r from-[#FAF9F4]/90 via-[#FAF9F4]/60 to-transparent sm:w-2/3 lg:w-3/5 dark:from-[#0D100C]/85 dark:via-[#0D100C]/50 dark:to-transparent"
            />

            <!-- Content Area -->
            <div
                class="relative z-10 flex flex-col justify-between gap-6 p-6 sm:p-8 lg:p-10"
            >
                <div class="max-w-xl">
                    <!-- Clean calm date & workspace metadata (NO badges!) -->
                    <div
                        class="flex items-center gap-2 text-xs font-medium text-surface-600 dark:text-surface-300"
                    >
                        <span
                            class="h-2 w-2 rounded-full bg-[#0F5132] dark:bg-[#b7d9c1]"
                        />
                        <span>{{ currentDateFormatted }}</span>
                        <span class="text-surface-300 dark:text-surface-600"
                            >·</span
                        >
                        <span>Tasks Flow Workspace</span>
                    </div>

                    <!-- Serif Heading inspired by CALM / SYSTEM "Clarity feels good" -->
                    <h1
                        class="mt-3.5 font-serif text-3xl font-normal tracking-tight text-surface-950 sm:text-4xl lg:text-5xl dark:text-surface-50"
                    >
                        {{ greetingTime }},
                        <span
                            class="font-normal text-[#0F5132] italic dark:text-[#b7d9c1]"
                        >
                            {{ user?.name || 'Rekan' }}
                        </span>
                    </h1>

                    <p
                        class="mt-2.5 max-w-lg text-sm leading-relaxed text-surface-600 dark:text-surface-300"
                    >
                        Ruang digital yang tenang untuk mengorganisir alur
                        tugas, memantau progres proyek, dan menjaga fokus tim
                        hari ini.
                    </p>

                    <!-- Buttons: Exact Calm System Reference Style (03 BUTTONS) -->
                    <div class="mt-6 flex flex-wrap items-center gap-3">
                        <Link
                            :href="tasks.create.url()"
                            class="inline-flex items-center gap-2 rounded-full bg-[#0F5132] px-5 py-2.5 text-xs font-medium text-white shadow-xs transition hover:bg-[#0c4329] active:scale-95 sm:text-sm dark:bg-[#0F5132] dark:hover:bg-[#14643f]"
                        >
                            <span>Buat Tugas Baru</span>
                            <span class="text-base leading-none">→</span>
                        </Link>

                        <Link
                            :href="projects.index.url()"
                            class="inline-flex items-center gap-2 rounded-full border border-surface-300 bg-white/90 px-5 py-2.5 text-xs font-medium text-surface-800 shadow-2xs backdrop-blur-xs transition hover:bg-white active:scale-95 sm:text-sm dark:border-surface-700 dark:bg-surface-800/80 dark:text-surface-100 dark:hover:bg-surface-700"
                        >
                            <span>Semua Proyek</span>
                            <span class="text-base leading-none">→</span>
                        </Link>
                    </div>
                </div>

                <!-- Mini Summary Strip (Clean typography, NO status badges!) -->
                <div
                    class="flex flex-wrap items-center gap-4 border-t border-surface-200/80 pt-4 text-xs text-surface-600 sm:gap-6 dark:border-surface-800 dark:text-surface-400"
                >
                    <div class="flex items-center gap-1.5">
                        <strong
                            class="font-serif text-base text-surface-900 dark:text-surface-100"
                        >
                            {{ stats?.projects_count ?? 0 }}
                        </strong>
                        <span>Proyek Aktif</span>
                    </div>
                    <span class="text-surface-300 dark:text-surface-700"
                        >·</span
                    >
                    <div class="flex items-center gap-1.5">
                        <strong
                            class="font-serif text-base text-surface-900 dark:text-surface-100"
                        >
                            {{ stats?.active_tasks_count ?? 0 }}
                        </strong>
                        <span>Tugas Berjalan</span>
                    </div>
                    <span class="text-surface-300 dark:text-surface-700"
                        >·</span
                    >
                    <div class="flex items-center gap-1.5">
                        <strong
                            class="font-serif text-base text-surface-900 dark:text-surface-100"
                        >
                            {{ overallProgress }}%
                        </strong>
                        <span>Selesai</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- 2. Core Workspace Metric Cards (Calm System Style, NO status badges!) -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <!-- Card 1: Proyek Tim -->
            <Link
                :href="projects.index.url()"
                class="dash-card group flex flex-col justify-between rounded-3xl border border-surface-200/80 bg-white p-6 shadow-sm transition hover:border-[#0F5132]/40 hover:shadow-md dark:border-surface-800 dark:bg-surface-900"
            >
                <div>
                    <div class="flex items-start justify-between gap-3">
                        <p
                            class="text-[11px] font-semibold tracking-wider text-surface-400 uppercase"
                        >
                            Proyek Tim
                        </p>
                        <component
                            :is="getIcon('FolderKanban')"
                            class="h-4.5 w-4.5 text-surface-400 transition group-hover:text-[#0F5132] dark:group-hover:text-[#b7d9c1]"
                        />
                    </div>
                    <div
                        class="mt-2 font-serif text-3xl font-normal tracking-tight text-surface-950 sm:text-4xl dark:text-surface-50"
                    >
                        {{ stats?.projects_count ?? 0 }}
                    </div>
                    <p class="mt-1 text-xs text-surface-500">
                        Proyek dalam pengerjaan aktif
                    </p>
                </div>

                <div
                    class="mt-5 flex min-h-[44px] flex-col justify-center space-y-1.5"
                >
                    <div
                        class="flex items-center justify-between text-[11px] font-medium text-surface-500"
                    >
                        <span>Penyelesaian alur tugas</span>
                        <span
                            class="font-serif text-sm font-normal text-surface-900 dark:text-surface-100"
                        >
                            {{ overallProgress }}%
                        </span>
                    </div>
                    <!-- Recessed progress bar (Calm reference 06) -->
                    <div
                        class="h-2 w-full overflow-hidden rounded-full bg-surface-200/70 dark:bg-surface-800"
                    >
                        <div
                            class="h-full rounded-full bg-[#0F5132] transition-all duration-500 dark:bg-[#b7d9c1]"
                            :style="{ width: `${overallProgress}%` }"
                        />
                    </div>
                </div>

                <div
                    class="mt-4 flex items-center justify-between border-t border-surface-100 pt-3 text-xs font-medium text-surface-500 transition group-hover:text-[#0F5132] dark:border-surface-800 dark:group-hover:text-[#b7d9c1]"
                >
                    <span>Kelola proyek</span>
                    <span class="transition group-hover:translate-x-1">→</span>
                </div>
            </Link>

            <!-- Card 2: Tugas Saya -->
            <Link
                :href="tasks.index.url()"
                class="dash-card group flex flex-col justify-between rounded-3xl border border-surface-200/80 bg-white p-6 shadow-sm transition hover:border-[#0F5132]/40 hover:shadow-md dark:border-surface-800 dark:bg-surface-900"
            >
                <div>
                    <div class="flex items-start justify-between gap-3">
                        <p
                            class="text-[11px] font-semibold tracking-wider text-surface-400 uppercase"
                        >
                            Tugas Saya
                        </p>
                        <component
                            :is="getIcon('CheckSquare')"
                            class="h-4.5 w-4.5 text-surface-400 transition group-hover:text-[#0F5132] dark:group-hover:text-[#b7d9c1]"
                        />
                    </div>
                    <div
                        class="mt-2 font-serif text-3xl font-normal tracking-tight text-surface-950 sm:text-4xl dark:text-surface-50"
                    >
                        {{ stats?.my_tasks_count ?? 0 }}
                    </div>
                    <p class="mt-1 text-xs text-surface-500">
                        Tugas dalam tanggung jawab Anda
                    </p>
                </div>

                <!-- Clean dot indicator without status badge -->
                <div
                    class="mt-5 flex min-h-[44px] items-center gap-2 text-xs font-medium text-surface-600 dark:text-surface-400"
                >
                    <span
                        class="h-1.5 w-1.5 rounded-full bg-[#0F5132] dark:bg-[#b7d9c1]"
                    />
                    <span
                        >{{ tasksByStatus?.['in_progress'] ?? 0 }} dalam
                        proses</span
                    >
                    <span class="text-surface-300 dark:text-surface-700"
                        >·</span
                    >
                    <span>{{ tasksByStatus?.['todo'] ?? 0 }} antrean</span>
                </div>

                <div
                    class="mt-4 flex items-center justify-between border-t border-surface-100 pt-3 text-xs font-medium text-surface-500 transition group-hover:text-[#0F5132] dark:border-surface-800 dark:group-hover:text-[#b7d9c1]"
                >
                    <span>Buka papan tugas</span>
                    <span class="transition group-hover:translate-x-1">→</span>
                </div>
            </Link>

            <!-- Card 3: Tenggat Waktu -->
            <Link
                :href="tasks.index.url()"
                class="dash-card group flex flex-col justify-between rounded-3xl border border-surface-200/80 bg-white p-6 shadow-sm transition hover:border-[#0F5132]/40 hover:shadow-md dark:border-surface-800 dark:bg-surface-900"
            >
                <div>
                    <div class="flex items-start justify-between gap-3">
                        <p
                            class="text-[11px] font-semibold tracking-wider text-surface-400 uppercase"
                        >
                            Tenggat Waktu
                        </p>
                        <component
                            :is="getIcon('Clock')"
                            class="h-4.5 w-4.5 text-surface-400 transition group-hover:text-[#0F5132] dark:group-hover:text-[#b7d9c1]"
                        />
                    </div>
                    <div
                        class="mt-2 font-serif text-3xl font-normal tracking-tight sm:text-4xl"
                        :class="
                            (stats?.overdue_count ?? 0) > 0
                                ? 'text-rose-700 dark:text-rose-400'
                                : 'text-surface-950 dark:text-surface-50'
                        "
                    >
                        {{ stats?.overdue_count ?? 0 }}
                    </div>
                    <p class="mt-1 text-xs text-surface-500">
                        {{
                            (stats?.overdue_count ?? 0) > 0
                                ? 'Tugas melewati batas waktu'
                                : 'Semua agenda sesuai jadwal'
                        }}
                    </p>
                </div>

                <!-- Clean dot indicator without badge -->
                <div
                    class="mt-5 flex min-h-[44px] items-center gap-2 text-xs font-medium"
                >
                    <span
                        class="h-1.5 w-1.5 rounded-full"
                        :class="
                            (stats?.overdue_count ?? 0) > 0
                                ? 'bg-rose-500'
                                : 'bg-[#0F5132] dark:bg-[#b7d9c1]'
                        "
                    />
                    <span
                        :class="
                            (stats?.overdue_count ?? 0) > 0
                                ? 'text-rose-700 dark:text-rose-400'
                                : 'text-surface-600 dark:text-surface-400'
                        "
                    >
                        {{
                            (stats?.overdue_count ?? 0) > 0
                                ? 'Perlu tindakan segera'
                                : upcomingDeadlines.length + ' tenggat mendekat'
                        }}
                    </span>
                </div>

                <div
                    class="mt-4 flex items-center justify-between border-t border-surface-100 pt-3 text-xs font-medium text-surface-500 transition group-hover:text-[#0F5132] dark:border-surface-800 dark:group-hover:text-[#b7d9c1]"
                >
                    <span>Periksa jadwal</span>
                    <span class="transition group-hover:translate-x-1">→</span>
                </div>
            </Link>

            <!-- Card 4: Kolaborator Tim -->
            <Link
                :href="'/teams'"
                class="dash-card group flex flex-col justify-between rounded-3xl border border-surface-200/80 bg-white p-6 shadow-sm transition hover:border-[#0F5132]/40 hover:shadow-md dark:border-surface-800 dark:bg-surface-900"
            >
                <div>
                    <div class="flex items-start justify-between gap-3">
                        <p
                            class="text-[11px] font-semibold tracking-wider text-surface-400 uppercase"
                        >
                            Kolaborator
                        </p>
                        <component
                            :is="getIcon('Users')"
                            class="h-4.5 w-4.5 text-surface-400 transition group-hover:text-[#0F5132] dark:group-hover:text-[#b7d9c1]"
                        />
                    </div>
                    <div
                        class="mt-2 font-serif text-3xl font-normal tracking-tight text-surface-950 sm:text-4xl dark:text-surface-50"
                    >
                        {{ stats?.team_members_count ?? 0 }}
                    </div>
                    <p class="mt-1 text-xs text-surface-500">
                        Anggota tim aktif terhubung
                    </p>
                </div>

                <div class="mt-5 flex min-h-[44px] items-center gap-2">
                    <div class="flex -space-x-1.5 overflow-hidden">
                        <div
                            v-for="(u, idx) in activeTeamUsers"
                            :key="idx"
                            class="flex h-6.5 w-6.5 items-center justify-center rounded-full border border-white bg-[#0F5132] text-[9px] font-medium text-white shadow-2xs dark:border-surface-900"
                            :title="u.name"
                        >
                            <img
                                v-if="u.avatar"
                                :src="u.avatar"
                                :alt="u.name"
                                class="h-full w-full rounded-full object-cover"
                            />
                            <span v-else>{{ u.initials }}</span>
                        </div>
                    </div>
                    <span class="text-xs font-medium text-surface-500">
                        {{ stats?.team_members_count ?? 0 }} rekan kerja
                    </span>
                </div>

                <div
                    class="mt-4 flex items-center justify-between border-t border-surface-100 pt-3 text-xs font-medium text-surface-500 transition group-hover:text-[#0F5132] dark:border-surface-800 dark:group-hover:text-[#b7d9c1]"
                >
                    <span>Direktori tim</span>
                    <span class="transition group-hover:translate-x-1">→</span>
                </div>
            </Link>
        </div>

        <!-- 3. Main Workspace Grid: 3 Parallel Aligned Rows -->
        <div class="space-y-6">
            <!-- ================= ROW 1: Pipeline Analytics (8 cols) + Fokus & Efisiensi (4 cols) ================= -->
            <div class="grid grid-cols-1 items-stretch gap-6 xl:grid-cols-12">
                <!-- Card: Tren & Distribusi Alur Kerja (8 cols) -->
                <div
                    class="dash-card flex h-full flex-col justify-between rounded-3xl border border-surface-200/80 bg-white p-6 shadow-sm sm:p-8 xl:col-span-8 dark:border-surface-800 dark:bg-surface-900"
                >
                    <div>
                        <div
                            class="flex flex-col justify-between gap-3 border-b border-surface-100 pb-5 sm:flex-row sm:items-center dark:border-surface-800"
                        >
                            <div>
                                <div class="flex items-center gap-2.5">
                                    <h2
                                        class="font-serif text-xl font-normal text-surface-950 sm:text-2xl dark:text-surface-50"
                                    >
                                        Distribusi Alur Kerja
                                    </h2>
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200/90 bg-emerald-50/80 px-2.5 py-0.5 text-[10px] font-semibold text-emerald-800 dark:border-emerald-800/40 dark:bg-emerald-950/40 dark:text-emerald-300"
                                    >
                                        <span class="relative flex h-1.5 w-1.5">
                                            <span
                                                class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"
                                            />
                                            <span
                                                class="relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500"
                                            />
                                        </span>
                                        Alur Dinamis 60 FPS
                                    </span>
                                </div>
                                <p class="mt-0.5 text-xs text-surface-500">
                                    Kapasitas dan sebaran tugas tim di setiap
                                    fase alur proyek
                                </p>
                            </div>

                            <!-- Text metrics with clean indicator -->
                            <div
                                class="flex items-center gap-3 text-xs text-surface-600 dark:text-surface-400"
                            >
                                <span
                                    ><strong
                                        class="font-serif text-sm font-normal text-surface-900 dark:text-surface-100"
                                        >{{ totalPipelineTasks }}</strong
                                    >
                                    tugas tercatat</span
                                >
                                <span
                                    class="text-surface-300 dark:text-surface-700"
                                    >·</span
                                >
                                <span
                                    ><strong
                                        class="font-serif text-sm font-normal text-surface-900 dark:text-surface-100"
                                        >{{ onTimeRate }}%</strong
                                    >
                                    ketepatan jadwal</span
                                >
                            </div>
                        </div>

                        <!-- Stunning High-Tech Glowing SVG Area & Trend Line Chart -->
                        <div
                            class="relative mt-6 w-full overflow-hidden rounded-2xl border border-surface-200/70 bg-gradient-to-b from-surface-50/60 to-surface-50/20 p-4 dark:border-surface-800/80 dark:from-surface-950/40 dark:to-surface-950/10"
                        >
                            <!-- Interactive Floating Hover Tooltip -->
                            <transition
                                enter-active-class="transition duration-150 ease-out"
                                enter-from-class="opacity-0 translate-y-1 scale-95"
                                enter-to-class="opacity-100 translate-y-0 scale-100"
                                leave-active-class="transition duration-100 ease-in"
                                leave-from-class="opacity-100 translate-y-0 scale-100"
                                leave-to-class="opacity-0 translate-y-1 scale-95"
                            >
                                <div
                                    v-if="hoveredStageInfo"
                                    class="pointer-events-none absolute top-3 right-4 z-10 flex items-center gap-2 rounded-xl border border-surface-200/90 bg-white/95 px-3 py-1.5 shadow-md backdrop-blur-md dark:border-surface-700 dark:bg-surface-800/95"
                                >
                                    <span
                                        class="h-2 w-2 rounded-full"
                                        :style="{
                                            backgroundColor:
                                                hoveredStageInfo.color,
                                        }"
                                    />
                                    <span
                                        class="text-xs font-semibold text-surface-900 dark:text-surface-100"
                                        >{{ hoveredStageInfo.label }}:</span
                                    >
                                    <span
                                        class="font-serif text-xs font-bold text-emerald-700 dark:text-emerald-400"
                                        >{{ hoveredStageInfo.count }} Tugas ({{
                                            hoveredStageInfo.percent
                                        }}%)</span
                                    >
                                </div>
                            </transition>

                            <div
                                class="relative h-44 w-full"
                                role="img"
                                :aria-label="`Distribusi alur kerja tugas: ${pipelineTrendData.stages.map((st) => `${st.label} ${st.count}`).join(', ')}`"
                            >
                                <svg
                                    class="h-full w-full overflow-visible"
                                    :viewBox="`0 0 ${pipelineTrendData.chartW} ${pipelineTrendData.chartH}`"
                                    preserveAspectRatio="none"
                                >
                                    <defs>
                                        <!-- Multi-stop glowing line gradient -->
                                        <linearGradient
                                            id="trendStrokeGradient"
                                            x1="0%"
                                            y1="0%"
                                            x2="100%"
                                            y2="0%"
                                        >
                                            <stop
                                                offset="0%"
                                                stop-color="#10b981"
                                            />
                                            <stop
                                                offset="30%"
                                                stop-color="#059669"
                                            />
                                            <stop
                                                offset="70%"
                                                stop-color="#0d9488"
                                            />
                                            <stop
                                                offset="100%"
                                                stop-color="#0284c7"
                                            />
                                        </linearGradient>

                                        <!-- Multi-stop glowing area fill -->
                                        <linearGradient
                                            id="calmAreaGradient"
                                            x1="0"
                                            y1="0"
                                            x2="0"
                                            y2="1"
                                        >
                                            <stop
                                                offset="0%"
                                                stop-color="#10b981"
                                                stop-opacity="0.36"
                                            />
                                            <stop
                                                offset="45%"
                                                stop-color="#0d9488"
                                                stop-opacity="0.14"
                                            />
                                            <stop
                                                offset="100%"
                                                stop-color="#059669"
                                                stop-opacity="0.00"
                                            />
                                        </linearGradient>

                                        <!-- Ethereal Neon Glow Filter -->
                                        <filter
                                            id="neonGlow"
                                            x="-20%"
                                            y="-20%"
                                            width="140%"
                                            height="140%"
                                        >
                                            <feGaussianBlur
                                                stdDeviation="3.5"
                                                result="coloredBlur"
                                            />
                                            <feMerge>
                                                <feMergeNode in="coloredBlur" />
                                                <feMergeNode
                                                    in="SourceGraphic"
                                                />
                                            </feMerge>
                                        </filter>

                                        <!-- Particle Glow Filter -->
                                        <filter
                                            id="particleGlow"
                                            x="-50%"
                                            y="-50%"
                                            width="200%"
                                            height="200%"
                                        >
                                            <feGaussianBlur
                                                stdDeviation="2.5"
                                                result="blur"
                                            />
                                            <feMerge>
                                                <feMergeNode in="blur" />
                                                <feMergeNode
                                                    in="SourceGraphic"
                                                />
                                            </feMerge>
                                        </filter>
                                    </defs>

                                    <!-- Subtle Horizontal Guidelines -->
                                    <line
                                        :x1="30"
                                        :y1="25"
                                        :x2="pipelineTrendData.chartW - 30"
                                        :y2="25"
                                        stroke="#EAE6DB"
                                        stroke-opacity="0.5"
                                        stroke-dasharray="3 3"
                                        class="dark:stroke-surface-800"
                                    />
                                    <line
                                        :x1="30"
                                        :y1="75"
                                        :x2="pipelineTrendData.chartW - 30"
                                        :y2="75"
                                        stroke="#EAE6DB"
                                        stroke-opacity="0.5"
                                        stroke-dasharray="3 3"
                                        class="dark:stroke-surface-800"
                                    />
                                    <line
                                        :x1="30"
                                        :y1="125"
                                        :x2="pipelineTrendData.chartW - 30"
                                        :y2="125"
                                        stroke="#EAE6DB"
                                        stroke-opacity="0.7"
                                        class="dark:stroke-surface-800"
                                    />

                                    <!-- Vertical Laser Drop Guidelines -->
                                    <line
                                        v-for="pt in pipelineTrendData.points"
                                        :key="`guide-${pt.key}`"
                                        :x1="pt.x"
                                        :y1="pt.y"
                                        :x2="pt.x"
                                        :y2="pipelineTrendData.chartH - 22"
                                        stroke="#10b981"
                                        stroke-width="1.5"
                                        stroke-dasharray="2 3"
                                        :opacity="
                                            hoveredStage === pt.key
                                                ? 0.85
                                                : 0.22
                                        "
                                        class="transition-opacity duration-200"
                                    />

                                    <!-- Area Fill with Rich Glowing Gradient -->
                                    <path
                                        v-if="pipelineTrendData.areaD"
                                        :d="pipelineTrendData.areaD"
                                        fill="url(#calmAreaGradient)"
                                        class="transition-all duration-150"
                                    />

                                    <!-- Ambient Glow Under-Layer -->
                                    <path
                                        v-if="pipelineTrendData.pathD"
                                        :d="pipelineTrendData.pathD"
                                        fill="none"
                                        stroke="url(#trendStrokeGradient)"
                                        stroke-width="7"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        filter="url(#neonGlow)"
                                        class="opacity-45 transition-all duration-150"
                                    />

                                    <!-- Crisp Foreground Curve Line -->
                                    <path
                                        v-if="pipelineTrendData.pathD"
                                        id="trendCurvePath"
                                        :d="pipelineTrendData.pathD"
                                        fill="none"
                                        stroke="url(#trendStrokeGradient)"
                                        stroke-width="3.5"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        class="transition-all duration-150"
                                    />

                                    <!-- Animated Traveling Comet Light Beam -->
                                    <g
                                        v-if="
                                            pipelineTrendData.pathD &&
                                            !prefersReducedMotion()
                                        "
                                    >
                                        <circle
                                            r="7"
                                            fill="#34d399"
                                            opacity="0.45"
                                            filter="url(#particleGlow)"
                                        >
                                            <animateMotion
                                                dur="4.5s"
                                                repeatCount="indefinite"
                                                :path="pipelineTrendData.pathD"
                                            />
                                        </circle>
                                        <circle
                                            r="3.5"
                                            fill="#ffffff"
                                            stroke="#059669"
                                            stroke-width="1.5"
                                            filter="url(#particleGlow)"
                                        >
                                            <animateMotion
                                                dur="4.5s"
                                                repeatCount="indefinite"
                                                :path="pipelineTrendData.pathD"
                                            />
                                        </circle>
                                    </g>

                                    <!-- Stage Node Points with Interactive Halo -->
                                    <g
                                        v-for="pt in pipelineTrendData.points"
                                        :key="pt.key"
                                        class="cursor-pointer"
                                        @mouseenter="hoveredStage = pt.key"
                                        @mouseleave="hoveredStage = null"
                                    >
                                        <!-- Outer Pulsing Halo -->
                                        <circle
                                            :cx="pt.x"
                                            :cy="pt.y"
                                            :r="
                                                hoveredStage === pt.key ? 13 : 9
                                            "
                                            fill="#10b981"
                                            :fill-opacity="
                                                hoveredStage === pt.key
                                                    ? 0.3
                                                    : 0.16
                                            "
                                            class="transition-all duration-200"
                                            :class="
                                                hoveredStage === pt.key
                                                    ? ''
                                                    : 'animate-pulse'
                                            "
                                        />
                                        <!-- Node Core -->
                                        <circle
                                            :cx="pt.x"
                                            :cy="pt.y"
                                            :r="
                                                hoveredStage === pt.key
                                                    ? 6.5
                                                    : 5
                                            "
                                            fill="#ffffff"
                                            stroke="url(#trendStrokeGradient)"
                                            stroke-width="2.5"
                                            class="transition-all duration-200 dark:fill-surface-900"
                                        />
                                        <!-- Count label above node -->
                                        <text
                                            :x="pt.x"
                                            :y="pt.y - 14"
                                            text-anchor="middle"
                                            class="font-sans text-[12px] font-bold transition-all duration-200"
                                            :class="
                                                hoveredStage === pt.key
                                                    ? 'fill-emerald-600 font-extrabold dark:fill-emerald-400'
                                                    : 'fill-surface-900 dark:fill-surface-100'
                                            "
                                        >
                                            {{ pt.count }}
                                        </text>
                                    </g>
                                </svg>
                            </div>
                        </div>

                        <!-- 4 Stage Progress Modules with Interactive Hover Sync -->
                        <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
                            <div
                                v-for="st in statusDistribution"
                                :key="st.key"
                                class="group relative flex cursor-pointer flex-col justify-between overflow-hidden rounded-2xl border p-4 transition-all duration-300 hover:-translate-y-0.5 hover:shadow-md"
                                :class="
                                    hoveredStage === st.key
                                        ? 'border-emerald-500 bg-white shadow-sm ring-1 ring-emerald-400/40 dark:border-emerald-500 dark:bg-surface-800'
                                        : 'border-surface-200/70 bg-[#FAF9F4]/70 hover:border-surface-300 dark:border-surface-800 dark:bg-surface-900/60'
                                "
                                @mouseenter="hoveredStage = st.key"
                                @mouseleave="hoveredStage = null"
                            >
                                <!-- Top Accent Indicator Bar -->
                                <div
                                    class="absolute inset-x-0 top-0 h-1 transition-opacity duration-300"
                                    :class="
                                        hoveredStage === st.key
                                            ? 'opacity-100'
                                            : 'opacity-40 group-hover:opacity-100'
                                    "
                                    :style="{ backgroundColor: st.color }"
                                />

                                <div class="flex items-center justify-between">
                                    <span
                                        class="text-xs font-medium text-surface-600 dark:text-surface-300"
                                    >
                                        {{ st.label }}
                                    </span>
                                    <span
                                        class="font-serif text-xs font-semibold text-surface-500"
                                    >
                                        {{ st.percent }}%
                                    </span>
                                </div>

                                <div class="mt-2 flex items-baseline gap-1.5">
                                    <span
                                        class="font-serif text-2xl font-normal text-surface-950 dark:text-surface-50"
                                    >
                                        {{ st.count }}
                                    </span>
                                    <span class="text-[11px] text-surface-400"
                                        >tugas</span
                                    >
                                </div>

                                <div
                                    class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-surface-200/80 dark:bg-surface-800"
                                >
                                    <div
                                        class="h-full rounded-full transition-all duration-500"
                                        :style="{
                                            width: `${st.percent}%`,
                                            backgroundColor: st.color,
                                        }"
                                    />
                                </div>

                                <p class="mt-2 text-[11px] text-surface-400">
                                    {{ st.description }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        class="mt-6 flex items-center justify-between border-t border-surface-100 pt-4 text-xs font-medium text-surface-500 dark:border-surface-800"
                    >
                        <span
                            >Pembaruan alur tugas tersinkronisasi otomatis</span
                        >
                        <Link
                            :href="tasks.index.url()"
                            class="inline-flex items-center gap-1 text-[#0F5132] transition hover:underline dark:text-[#b7d9c1]"
                        >
                            <span>Buka Papan Tugas</span>
                            <span>→</span>
                        </Link>
                    </div>
                </div>

                <!-- Card: Fokus & Efisiensi Tim (4 cols) -->
                <div
                    class="dash-card flex h-full flex-col justify-between rounded-3xl border border-surface-200/80 bg-white p-6 shadow-sm xl:col-span-4 dark:border-surface-800 dark:bg-surface-900"
                >
                    <div>
                        <div
                            class="flex items-center justify-between gap-3 border-b border-surface-100 pb-4 dark:border-surface-800"
                        >
                            <div>
                                <h2
                                    class="font-serif text-lg font-normal text-surface-950 dark:text-surface-50"
                                >
                                    Fokus & Efisiensi
                                </h2>
                                <p class="mt-0.5 text-xs text-surface-500">
                                    Ketepatan jadwal dan prioritas tindakan tim
                                </p>
                            </div>
                            <div
                                class="flex items-center gap-1.5 text-xs font-medium"
                            >
                                <span
                                    class="h-1.5 w-1.5 rounded-full"
                                    :class="
                                        focusMetrics.overdue_count === 0
                                            ? 'bg-[#0F5132] dark:bg-[#b7d9c1]'
                                            : 'bg-rose-500'
                                    "
                                />
                                <span
                                    :class="
                                        focusMetrics.overdue_count === 0
                                            ? 'text-[#0F5132] dark:text-[#b7d9c1]'
                                            : 'text-rose-700 dark:text-rose-400'
                                    "
                                >
                                    {{
                                        focusMetrics.overdue_count === 0
                                            ? 'Ritme Optimal'
                                            : 'Perlu Fokus'
                                    }}
                                </span>
                            </div>
                        </div>

                        <div class="mt-4 space-y-3.5">
                            <!-- Progress Rate Score -->
                            <div
                                class="rounded-2xl border border-surface-200/60 bg-[#FAF9F4]/70 p-4 dark:border-surface-800 dark:bg-surface-900/60"
                            >
                                <div
                                    class="flex items-center justify-between text-xs font-medium text-surface-600 dark:text-surface-300"
                                >
                                    <span>Ketepatan Jadwal Eksekusi</span>
                                    <span
                                        class="font-serif text-base font-normal text-surface-900 dark:text-surface-100"
                                    >
                                        {{ focusMetrics.on_time_rate }}%
                                    </span>
                                </div>
                                <div
                                    class="mt-2.5 h-2 w-full overflow-hidden rounded-full bg-surface-200/80 dark:bg-surface-800"
                                >
                                    <div
                                        class="h-full rounded-full bg-[#0F5132] transition-all duration-500 dark:bg-[#b7d9c1]"
                                        :style="{
                                            width: `${focusMetrics.on_time_rate}%`,
                                        }"
                                    />
                                </div>
                                <p class="mt-2 text-[11px] text-surface-500">
                                    {{
                                        focusMetrics.on_time_rate >= 80
                                            ? 'Ritme alur kerja tim berada di dalam target penyelesaian yang optimal.'
                                            : 'Terdapat tugas melewati batas waktu yang memerlukan perhatian segera.'
                                    }}
                                </p>
                            </div>

                            <!-- 4-Grid Metrik Akurat -->
                            <div class="grid grid-cols-2 gap-2.5">
                                <div
                                    class="rounded-2xl border border-surface-200/60 bg-[#FAF9F4]/70 p-3 dark:border-surface-800 dark:bg-surface-900/60"
                                >
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <span
                                            class="text-[10px] font-semibold text-surface-400 uppercase"
                                        >
                                            Tugas Aktif
                                        </span>
                                        <span
                                            class="h-1.5 w-1.5 rounded-full bg-[#0F5132] dark:bg-[#b7d9c1]"
                                        />
                                    </div>
                                    <div
                                        class="mt-1 font-serif text-xl font-normal text-surface-950 dark:text-surface-50"
                                    >
                                        {{ focusMetrics.my_tasks_count }}
                                    </div>
                                    <span class="text-[11px] text-surface-400">
                                        Tanggung jawab Anda
                                    </span>
                                </div>

                                <div
                                    class="rounded-2xl border border-surface-200/60 bg-[#FAF9F4]/70 p-3 dark:border-surface-800 dark:bg-surface-900/60"
                                >
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <span
                                            class="text-[10px] font-semibold text-surface-400 uppercase"
                                        >
                                            Terlambat
                                        </span>
                                        <span
                                            class="h-1.5 w-1.5 rounded-full"
                                            :class="
                                                focusMetrics.overdue_count > 0
                                                    ? 'bg-rose-500'
                                                    : 'bg-surface-300 dark:bg-surface-700'
                                            "
                                        />
                                    </div>
                                    <div
                                        class="mt-1 font-serif text-xl font-normal"
                                        :class="
                                            focusMetrics.overdue_count > 0
                                                ? 'text-rose-700 dark:text-rose-400'
                                                : 'text-surface-950 dark:text-surface-50'
                                        "
                                    >
                                        {{ focusMetrics.overdue_count }}
                                    </div>
                                    <span class="text-[11px] text-surface-400">
                                        {{
                                            focusMetrics.overdue_count > 0
                                                ? 'Perlu tindakan'
                                                : 'Tepat waktu'
                                        }}
                                    </span>
                                </div>

                                <div
                                    class="rounded-2xl border border-surface-200/60 bg-[#FAF9F4]/70 p-3 dark:border-surface-800 dark:bg-surface-900/60"
                                >
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <span
                                            class="text-[10px] font-semibold text-surface-400 uppercase"
                                        >
                                            Prioritas Tinggi
                                        </span>
                                        <span
                                            class="h-1.5 w-1.5 rounded-full"
                                            :class="
                                                focusMetrics.urgent_tasks_count >
                                                0
                                                    ? 'bg-amber-500'
                                                    : 'bg-surface-300 dark:bg-surface-700'
                                            "
                                        />
                                    </div>
                                    <div
                                        class="mt-1 font-serif text-xl font-normal text-surface-950 dark:text-surface-50"
                                    >
                                        {{ focusMetrics.urgent_tasks_count }}
                                    </div>
                                    <span class="text-[11px] text-surface-400">
                                        Mendesak & penting
                                    </span>
                                </div>

                                <div
                                    class="rounded-2xl border border-surface-200/60 bg-[#FAF9F4]/70 p-3 dark:border-surface-800 dark:bg-surface-900/60"
                                >
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <span
                                            class="text-[10px] font-semibold text-surface-400 uppercase"
                                        >
                                            Perlu Review
                                        </span>
                                        <span
                                            class="h-1.5 w-1.5 rounded-full"
                                            :class="
                                                focusMetrics.review_tasks_count >
                                                0
                                                    ? 'bg-sky-500'
                                                    : 'bg-surface-300 dark:bg-surface-700'
                                            "
                                        />
                                    </div>
                                    <div
                                        class="mt-1 font-serif text-xl font-normal text-surface-950 dark:text-surface-50"
                                    >
                                        {{ focusMetrics.review_tasks_count }}
                                    </div>
                                    <span class="text-[11px] text-surface-400">
                                        Menunggu validasi
                                    </span>
                                </div>
                            </div>

                            <!-- Progress Global -->
                            <div
                                class="rounded-2xl border border-surface-200/60 bg-[#FAF9F4]/70 p-3.5 dark:border-surface-800 dark:bg-surface-900/60"
                            >
                                <div
                                    class="flex items-center justify-between text-xs text-surface-600 dark:text-surface-300"
                                >
                                    <span class="font-medium"
                                        >Penyelesaian Seluruh Tugas</span
                                    >
                                    <span
                                        class="font-serif text-sm font-normal text-surface-900 dark:text-surface-100"
                                    >
                                        {{ focusMetrics.completed_tasks_count }}
                                        /
                                        {{ focusMetrics.total_tasks_count }} ({{
                                            focusMetrics.completion_rate
                                        }}%)
                                    </span>
                                </div>
                                <div
                                    class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-surface-200/80 dark:bg-surface-800"
                                >
                                    <div
                                        class="h-full rounded-full bg-[#0F5132] transition-all duration-500 dark:bg-[#b7d9c1]"
                                        :style="{
                                            width: `${focusMetrics.completion_rate}%`,
                                        }"
                                    />
                                </div>
                                <div
                                    class="mt-2 flex items-center justify-between text-[11px] text-surface-500"
                                >
                                    <span>Tenggat minggu ini:</span>
                                    <span
                                        class="font-semibold text-surface-800 dark:text-surface-200"
                                    >
                                        {{ focusMetrics.due_this_week_count }}
                                        tugas
                                    </span>
                                </div>
                            </div>

                            <!-- Rekomendasi Alur Tindakan -->
                            <div
                                class="flex items-start gap-2.5 rounded-2xl border p-3 text-xs transition"
                                :class="
                                    focusMetrics.overdue_count > 0
                                        ? 'border-rose-200 bg-rose-50/60 text-rose-900 dark:border-rose-900/40 dark:bg-rose-950/20 dark:text-rose-200'
                                        : focusMetrics.review_tasks_count > 0
                                          ? 'border-sky-200 bg-sky-50/60 text-sky-900 dark:border-sky-900/40 dark:bg-sky-950/20 dark:text-sky-200'
                                          : 'border-emerald-200/80 bg-emerald-50/50 text-emerald-900 dark:border-emerald-900/40 dark:bg-emerald-950/20 dark:text-emerald-200'
                                "
                            >
                                <span class="mt-0.5 shrink-0 text-sm">
                                    {{
                                        focusMetrics.overdue_count > 0
                                            ? '⚠️'
                                            : focusMetrics.review_tasks_count >
                                                0
                                              ? '🔍'
                                              : '✓'
                                    }}
                                </span>
                                <div class="min-w-0 flex-1 leading-relaxed">
                                    <p class="font-semibold">
                                        {{
                                            focusMetrics.overdue_count > 0
                                                ? 'Tindakan Mendesak'
                                                : focusMetrics.review_tasks_count >
                                                    0
                                                  ? 'Validasi Diperlukan'
                                                  : 'Alur Kerja Terkendali'
                                        }}
                                    </p>
                                    <p class="mt-0.5 text-[11px] opacity-90">
                                        {{
                                            focusMetrics.overdue_count > 0
                                                ? `Ada ${focusMetrics.overdue_count} tugas lewat tenggat. Lakukan reskedul atau koordinasi dengan PIC terkait.`
                                                : focusMetrics.review_tasks_count >
                                                    0
                                                  ? `Ada ${focusMetrics.review_tasks_count} tugas menunggu review untuk melanjutkan ke tahap rilis.`
                                                  : 'Semua alur kerja berjalan sesuai rencana tanpa hambatan kritis.'
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Dual Quick Links -->
                    <div
                        class="mt-4 flex items-center justify-between border-t border-surface-100 pt-3.5 text-xs font-medium dark:border-surface-800"
                    >
                        <Link
                            :href="tasks.index.url()"
                            class="inline-flex items-center gap-1 text-[#0F5132] transition hover:underline dark:text-[#b7d9c1]"
                        >
                            <span>Papan Tugas</span>
                            <span>→</span>
                        </Link>
                        <Link
                            :href="'/calendar'"
                            class="inline-flex items-center gap-1 text-surface-500 transition hover:text-surface-900 dark:hover:text-surface-100"
                        >
                            <span>Jadwal Alur</span>
                            <span>→</span>
                        </Link>
                    </div>
                </div>
            </div>

            <!-- ================= ROW 2: Progres Proyek (6 cols) + Tenggat Waktu (6 cols) ================= -->
            <div class="grid grid-cols-1 items-stretch gap-6 xl:grid-cols-12">
                <!-- Card: Progres Proyek Aktif (6 cols) -->
                <div
                    class="dash-card flex h-full flex-col justify-between rounded-3xl border border-surface-200/80 bg-white p-6 shadow-sm sm:p-8 xl:col-span-6 dark:border-surface-800 dark:bg-surface-900"
                >
                    <div>
                        <div
                            class="flex items-start justify-between gap-3 border-b border-surface-100 pb-5 dark:border-surface-800"
                        >
                            <div>
                                <h2
                                    class="font-serif text-xl font-normal text-surface-950 sm:text-2xl dark:text-surface-50"
                                >
                                    Progres Proyek
                                </h2>
                                <p class="mt-0.5 text-xs text-surface-500">
                                    {{ projectProgress.length }} proyek aktif
                                    dalam alur tim
                                </p>
                            </div>
                            <Link
                                :href="projects.index.url()"
                                class="inline-flex items-center gap-1 text-xs font-medium text-[#0F5132] transition hover:underline dark:text-[#b7d9c1]"
                            >
                                <span>Semua Proyek</span>
                                <span>→</span>
                            </Link>
                        </div>

                        <!-- Project Items Grid -->
                        <div
                            v-if="projectProgress.length > 0"
                            class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2"
                        >
                            <div
                                v-for="proj in projectProgress"
                                :key="proj.id"
                                class="group flex flex-col justify-between rounded-2xl border border-surface-200/70 bg-[#FAF9F4]/60 p-5 transition hover:border-[#0F5132]/40 hover:bg-white dark:border-surface-800 dark:bg-surface-900/60 dark:hover:bg-surface-800/40"
                            >
                                <div>
                                    <div
                                        class="flex items-start justify-between gap-3"
                                    >
                                        <div class="min-w-0 flex-1">
                                            <Link
                                                :href="`/projects/${proj.id}`"
                                                class="block truncate font-serif text-base font-normal text-surface-950 transition hover:text-[#0F5132] dark:text-surface-50 dark:hover:text-[#b7d9c1]"
                                                :title="proj.name"
                                            >
                                                {{ proj.name }}
                                            </Link>
                                            <div
                                                class="mt-1 flex items-center gap-1.5 text-xs font-medium text-surface-500"
                                            >
                                                <span
                                                    class="h-1.5 w-1.5 rounded-full bg-[#0F5132] dark:bg-[#b7d9c1]"
                                                />
                                                <span>{{
                                                    getProjectTheme(proj)
                                                        .statusLabel
                                                }}</span>
                                            </div>
                                        </div>

                                        <span
                                            class="font-serif text-lg font-normal text-surface-900 dark:text-surface-100"
                                        >
                                            {{ proj.progress_percent }}%
                                        </span>
                                    </div>

                                    <p
                                        v-if="proj.description"
                                        class="mt-3 line-clamp-2 text-xs leading-relaxed text-surface-500"
                                    >
                                        {{ proj.description }}
                                    </p>
                                </div>

                                <div
                                    class="mt-5 border-t border-surface-200/60 pt-3 dark:border-surface-800"
                                >
                                    <div
                                        class="h-2 w-full overflow-hidden rounded-full bg-surface-200/80 dark:bg-surface-800"
                                    >
                                        <div
                                            class="h-full rounded-full bg-[#0F5132] transition-all duration-500 dark:bg-[#b7d9c1]"
                                            :style="{
                                                width: `${proj.progress_percent}%`,
                                            }"
                                        />
                                    </div>

                                    <div
                                        class="mt-2.5 flex items-center justify-between text-xs text-surface-500"
                                    >
                                        <span
                                            >{{
                                                proj.completed_tasks_count
                                            }}
                                            dari {{ proj.tasks_count }} tugas
                                            selesai</span
                                        >
                                        <Link
                                            :href="`/projects/${proj.id}`"
                                            class="inline-flex items-center gap-1 text-[#0F5132] transition hover:underline dark:text-[#b7d9c1]"
                                        >
                                            <span>Rincian</span>
                                            <span>→</span>
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div
                            v-else
                            class="flex flex-col items-center justify-center py-10 text-center"
                        >
                            <p
                                class="font-serif text-base text-surface-700 dark:text-surface-300"
                            >
                                Belum Ada Proyek Aktif
                            </p>
                            <p class="mt-1 text-xs text-surface-400">
                                Mulai koordinasikan pekerjaan Anda dengan
                                membuat proyek tim.
                            </p>
                            <Link
                                :href="projects.create.url()"
                                class="mt-4 inline-flex items-center gap-2 rounded-full bg-[#0F5132] px-5 py-2 text-xs font-medium text-white shadow-xs"
                            >
                                <span>Buat Proyek Baru</span>
                                <span>→</span>
                            </Link>
                        </div>
                    </div>

                    <div
                        class="mt-6 flex items-center justify-between border-t border-surface-100 pt-4 text-xs font-medium text-surface-500 dark:border-surface-800"
                    >
                        <span>Daftar proyek aktif tim</span>
                        <Link
                            :href="projects.index.url()"
                            class="inline-flex items-center gap-1 text-[#0F5132] transition hover:underline dark:text-[#b7d9c1]"
                        >
                            <span>Kelola Portofolio Proyek</span>
                            <span>→</span>
                        </Link>
                    </div>
                </div>

                <!-- Card: Tenggat Waktu Mendatang (6 cols) -->
                <div
                    class="dash-card flex h-full flex-col justify-between rounded-3xl border border-surface-200/80 bg-white p-6 shadow-sm sm:p-8 xl:col-span-6 dark:border-surface-800 dark:bg-surface-900"
                >
                    <div>
                        <div
                            class="flex items-start justify-between gap-3 border-b border-surface-100 pb-5 dark:border-surface-800"
                        >
                            <div>
                                <h2
                                    class="font-serif text-xl font-normal text-surface-950 sm:text-2xl dark:text-surface-50"
                                >
                                    Tenggat Waktu
                                </h2>
                                <p class="mt-0.5 text-xs text-surface-500">
                                    Agenda tugas yang mendekati batas waktu
                                    penyelesaian
                                </p>
                            </div>
                            <Link
                                :href="tasks.index.url()"
                                class="inline-flex items-center gap-1 text-xs font-medium text-[#0F5132] transition hover:underline dark:text-[#b7d9c1]"
                            >
                                <span>Semua Tugas</span>
                                <span>→</span>
                            </Link>
                        </div>

                        <!-- Upcoming Tasks List -->
                        <div
                            v-if="upcomingDeadlines.length > 0"
                            class="mt-6 space-y-3"
                        >
                            <div
                                v-for="task in upcomingDeadlines"
                                :key="task.id"
                                class="group flex flex-col justify-between gap-3 rounded-2xl border border-surface-200/70 bg-[#FAF9F4]/60 p-4 transition hover:border-[#0F5132]/40 hover:bg-white sm:flex-row sm:items-center dark:border-surface-800 dark:bg-surface-900/60 dark:hover:bg-surface-800/40"
                            >
                                <div class="min-w-0 flex-1">
                                    <div
                                        class="flex items-center gap-2 text-xs font-medium"
                                    >
                                        <span
                                            class="h-1.5 w-1.5 rounded-full"
                                            :class="
                                                getUrgencyIndicator(
                                                    task.deadline_urgency,
                                                ).dotColor
                                            "
                                        />
                                        <span
                                            :class="
                                                getUrgencyIndicator(
                                                    task.deadline_urgency,
                                                ).textColor
                                            "
                                        >
                                            {{
                                                getUrgencyIndicator(
                                                    task.deadline_urgency,
                                                ).text
                                            }}
                                        </span>
                                        <span
                                            v-if="task.project"
                                            class="text-surface-300 dark:text-surface-700"
                                            >·</span
                                        >
                                        <span
                                            v-if="task.project"
                                            class="truncate text-surface-500"
                                        >
                                            {{ task.project.name }}
                                        </span>
                                    </div>

                                    <Link
                                        :href="`/tasks/${task.id}`"
                                        class="mt-1 block truncate text-sm font-medium text-surface-900 transition hover:text-[#0F5132] dark:text-surface-100 dark:hover:text-[#b7d9c1]"
                                    >
                                        {{ task.title }}
                                    </Link>
                                </div>

                                <div
                                    class="flex shrink-0 items-center gap-4 text-xs font-medium text-surface-500"
                                >
                                    <span>{{
                                        formatDueDate(task.due_date)
                                    }}</span>
                                    <Link
                                        :href="`/tasks/${task.id}`"
                                        class="inline-flex items-center gap-1 text-[#0F5132] transition hover:underline dark:text-[#b7d9c1]"
                                    >
                                        <span>Lihat</span>
                                        <span>→</span>
                                    </Link>
                                </div>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div v-else class="py-8 text-center">
                            <p
                                class="font-serif text-sm text-surface-700 dark:text-surface-300"
                            >
                                Semua Tugas Terkendali
                            </p>
                            <p class="mt-0.5 text-xs text-surface-400">
                                Tidak ada tenggat waktu mendesak saat ini.
                            </p>
                        </div>
                    </div>

                    <div
                        class="mt-6 flex items-center justify-between border-t border-surface-100 pt-4 text-xs font-medium text-surface-500 dark:border-surface-800"
                    >
                        <span
                            >Prioritaskan tugas dengan batas waktu
                            terdekat</span
                        >
                        <Link
                            :href="'/calendar'"
                            class="inline-flex items-center gap-1 text-[#0F5132] transition hover:underline dark:text-[#b7d9c1]"
                        >
                            <span>Buka Kalender Tenggat</span>
                            <span>→</span>
                        </Link>
                    </div>
                </div>
            </div>

            <!-- ================= ROW 3: Aktivitas Terkini (7 cols) + Kalender Sepekan & Pintasan (5 cols) ================= -->
            <div class="grid grid-cols-1 items-stretch gap-6 xl:grid-cols-12">
                <!-- Card: Aktivitas Terkini (7 cols) -->
                <div
                    class="dash-card flex h-full flex-col justify-between rounded-3xl border border-surface-200/80 bg-white p-6 shadow-sm sm:p-8 xl:col-span-7 dark:border-surface-800 dark:bg-surface-900"
                >
                    <div>
                        <div
                            class="flex items-center justify-between gap-3 border-b border-surface-100 pb-5 dark:border-surface-800"
                        >
                            <div>
                                <h2
                                    class="font-serif text-xl font-normal text-surface-950 sm:text-2xl dark:text-surface-50"
                                >
                                    Aktivitas Terkini
                                </h2>
                                <p class="mt-0.5 text-xs text-surface-500">
                                    Pembaruan log dan jejak tindakan tim secara
                                    realtime
                                </p>
                            </div>
                            <Link
                                :href="'/collaboration'"
                                class="inline-flex items-center gap-1 text-xs font-medium text-[#0F5132] transition hover:underline dark:text-[#b7d9c1]"
                            >
                                <span>Semua Kolaborasi</span>
                                <span>→</span>
                            </Link>
                        </div>

                        <!-- Activities Stream -->
                        <div
                            v-if="recentActivities.length > 0"
                            class="mt-6 space-y-3.5"
                        >
                            <div
                                v-for="act in recentActivities.slice(0, 6)"
                                :key="act.id"
                                class="flex items-start gap-3 rounded-2xl border border-surface-200/60 bg-[#FAF9F4]/60 p-3.5 text-xs transition hover:bg-white dark:border-surface-800 dark:bg-surface-900/60 dark:hover:bg-surface-800/40"
                            >
                                <div
                                    class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-[#b7d9c1] text-[10px] font-bold text-[#0F5132]"
                                >
                                    {{ act.user?.initials || 'US' }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="line-clamp-2 text-xs leading-relaxed text-surface-800 dark:text-surface-200"
                                    >
                                        {{ act.description }}
                                    </p>
                                    <span
                                        class="mt-1 block text-[10px] text-surface-400"
                                    >
                                        {{ formatRelativeTime(act.created_at) }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div
                            v-else
                            class="py-8 text-center text-xs text-surface-400"
                        >
                            Belum ada aktivitas baru tercatat hari ini.
                        </div>
                    </div>

                    <div
                        class="mt-6 flex items-center justify-between border-t border-surface-100 pt-4 text-xs font-medium text-surface-500 dark:border-surface-800"
                    >
                        <span
                            >Sinkronisasi log aktivitas proyek & tugas tim</span
                        >
                        <Link
                            :href="'/collaboration'"
                            class="inline-flex items-center gap-1 text-[#0F5132] transition hover:underline dark:text-[#b7d9c1]"
                        >
                            <span>Buka Ruang Diskusi</span>
                            <span>→</span>
                        </Link>
                    </div>
                </div>

                <!-- Right Column (5 cols): Kalender Mingguan + Pintasan Ruang Kerja -->
                <div
                    class="flex h-full flex-col justify-between gap-6 xl:col-span-5"
                >
                    <!-- Mini Kalender Interaktif Mingguan -->
                    <div
                        class="dash-card rounded-3xl border border-surface-200/80 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900"
                    >
                        <div
                            class="flex items-center justify-between gap-3 pb-4"
                        >
                            <div>
                                <h2
                                    class="font-serif text-lg font-normal text-surface-950 dark:text-surface-50"
                                >
                                    {{ currentMonthLabel }}
                                </h2>
                                <p class="text-xs text-surface-400">
                                    Kalender sepekan berjalan
                                </p>
                            </div>
                            <Link
                                :href="'/calendar'"
                                class="inline-flex items-center gap-1 text-xs font-medium text-[#0F5132] transition hover:underline dark:text-[#b7d9c1]"
                            >
                                <span>Kalender</span>
                                <span>→</span>
                            </Link>
                        </div>

                        <!-- Weekdays Row -->
                        <div class="mt-2 grid grid-cols-7 gap-1.5 text-center">
                            <div
                                v-for="d in currentWeekDays"
                                :key="d.name"
                                class="flex flex-col items-center gap-1 rounded-2xl py-2.5 transition"
                                :class="[
                                    d.isToday
                                        ? 'bg-[#0F5132] text-white shadow-xs dark:bg-[#b7d9c1] dark:text-surface-950'
                                        : 'text-surface-600 hover:bg-surface-100/60 dark:text-surface-300 dark:hover:bg-surface-800/50',
                                ]"
                            >
                                <span
                                    class="text-[10px] font-medium opacity-80"
                                >
                                    {{ d.name }}
                                </span>
                                <span class="font-serif text-sm font-normal">
                                    {{ d.date }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Pintasan Ruang Kerja (Modern Command Center Bento Grid) -->
                    <div
                        class="dash-card flex-1 rounded-3xl border border-surface-200/80 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900"
                    >
                        <div class="flex items-center justify-between pb-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <div
                                        class="flex h-6 w-6 items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300"
                                    >
                                        <component
                                            :is="getIcon('Sparkles')"
                                            class="h-3.5 w-3.5"
                                        />
                                    </div>
                                    <h2
                                        class="font-serif text-lg font-normal text-surface-950 dark:text-surface-50"
                                    >
                                        Pintasan Cepat
                                    </h2>
                                    <span
                                        class="rounded-full bg-surface-100 px-2 py-0.5 text-[10px] font-semibold text-surface-600 dark:bg-surface-800 dark:text-surface-300"
                                    >
                                        6 Modul
                                    </span>
                                </div>
                                <p class="mt-0.5 text-xs text-surface-400">
                                    Akses instan modul kerja dan navigasi alur
                                    proyek
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                            <!-- 1. Papan Tugas -->
                            <Link
                                :href="tasks.index.url()"
                                class="group relative flex items-center gap-3 overflow-hidden rounded-2xl border border-surface-200/70 bg-[#FAF9F4]/70 p-3 transition-all duration-200 hover:-translate-y-0.5 hover:border-emerald-500/40 hover:bg-white hover:shadow-sm dark:border-surface-800 dark:bg-surface-900/60 dark:hover:border-emerald-500/40 dark:hover:bg-surface-800/80"
                            >
                                <div
                                    class="flex h-8.5 w-8.5 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500/15 to-teal-500/25 text-emerald-700 shadow-2xs transition-transform duration-200 group-hover:scale-105 dark:from-emerald-500/25 dark:to-teal-500/35 dark:text-emerald-300"
                                >
                                    <component
                                        :is="getIcon('CheckSquare')"
                                        class="h-4 w-4"
                                    />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <p
                                            class="truncate text-xs font-semibold text-surface-900 group-hover:text-emerald-700 dark:text-surface-100 dark:group-hover:text-emerald-300"
                                        >
                                            Papan Tugas
                                        </p>
                                        <span
                                            class="text-surface-400 transition-transform duration-200 group-hover:translate-x-0.5 group-hover:text-emerald-600"
                                            >→</span
                                        >
                                    </div>
                                    <p
                                        class="truncate text-[11px] text-surface-400"
                                    >
                                        Kanban & alur kerja
                                    </p>
                                </div>
                            </Link>

                            <!-- 2. Manajemen Proyek -->
                            <Link
                                :href="projects.index.url()"
                                class="group relative flex items-center gap-3 overflow-hidden rounded-2xl border border-surface-200/70 bg-[#FAF9F4]/70 p-3 transition-all duration-200 hover:-translate-y-0.5 hover:border-indigo-500/40 hover:bg-white hover:shadow-sm dark:border-surface-800 dark:bg-surface-900/60 dark:hover:border-indigo-500/40 dark:hover:bg-surface-800/80"
                            >
                                <div
                                    class="flex h-8.5 w-8.5 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500/15 to-blue-500/25 text-indigo-700 shadow-2xs transition-transform duration-200 group-hover:scale-105 dark:from-indigo-500/25 dark:to-blue-500/35 dark:text-indigo-300"
                                >
                                    <component
                                        :is="getIcon('FolderKanban')"
                                        class="h-4 w-4"
                                    />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <p
                                            class="truncate text-xs font-semibold text-surface-900 group-hover:text-indigo-700 dark:text-surface-100 dark:group-hover:text-indigo-300"
                                        >
                                            Daftar Proyek
                                        </p>
                                        <span
                                            class="text-surface-400 transition-transform duration-200 group-hover:translate-x-0.5 group-hover:text-indigo-600"
                                            >→</span
                                        >
                                    </div>
                                    <p
                                        class="truncate text-[11px] text-surface-400"
                                    >
                                        Portofolio & inisiatif
                                    </p>
                                </div>
                            </Link>

                            <!-- 3. Kalender & Tenggat -->
                            <Link
                                :href="'/calendar'"
                                class="group relative flex items-center gap-3 overflow-hidden rounded-2xl border border-surface-200/70 bg-[#FAF9F4]/70 p-3 transition-all duration-200 hover:-translate-y-0.5 hover:border-amber-500/40 hover:bg-white hover:shadow-sm dark:border-surface-800 dark:bg-surface-900/60 dark:hover:border-amber-500/40 dark:hover:bg-surface-800/80"
                            >
                                <div
                                    class="flex h-8.5 w-8.5 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-amber-500/15 to-orange-500/25 text-amber-700 shadow-2xs transition-transform duration-200 group-hover:scale-105 dark:from-amber-500/25 dark:to-orange-500/35 dark:text-amber-300"
                                >
                                    <component
                                        :is="getIcon('Calendar')"
                                        class="h-4 w-4"
                                    />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <p
                                            class="truncate text-xs font-semibold text-surface-900 group-hover:text-amber-700 dark:text-surface-100 dark:group-hover:text-amber-300"
                                        >
                                            Kalender
                                        </p>
                                        <span
                                            class="text-surface-400 transition-transform duration-200 group-hover:translate-x-0.5 group-hover:text-amber-600"
                                            >→</span
                                        >
                                    </div>
                                    <p
                                        class="truncate text-[11px] text-surface-400"
                                    >
                                        Agenda & deadline
                                    </p>
                                </div>
                            </Link>

                            <!-- 4. Ruang Kolaborasi -->
                            <Link
                                :href="'/collaboration'"
                                class="group relative flex items-center gap-3 overflow-hidden rounded-2xl border border-surface-200/70 bg-[#FAF9F4]/70 p-3 transition-all duration-200 hover:-translate-y-0.5 hover:border-purple-500/40 hover:bg-white hover:shadow-sm dark:border-surface-800 dark:bg-surface-900/60 dark:hover:border-purple-500/40 dark:hover:bg-surface-800/80"
                            >
                                <div
                                    class="flex h-8.5 w-8.5 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-purple-500/15 to-pink-500/25 text-purple-700 shadow-2xs transition-transform duration-200 group-hover:scale-105 dark:from-purple-500/25 dark:to-pink-500/35 dark:text-purple-300"
                                >
                                    <component
                                        :is="getIcon('MessageSquare')"
                                        class="h-4 w-4"
                                    />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <p
                                            class="truncate text-xs font-semibold text-surface-900 group-hover:text-purple-700 dark:text-surface-100 dark:group-hover:text-purple-300"
                                        >
                                            Kolaborasi
                                        </p>
                                        <span
                                            class="text-surface-400 transition-transform duration-200 group-hover:translate-x-0.5 group-hover:text-purple-600"
                                            >→</span
                                        >
                                    </div>
                                    <p
                                        class="truncate text-[11px] text-surface-400"
                                    >
                                        Diskusi alur kerja
                                    </p>
                                </div>
                            </Link>

                            <!-- 5. Berkas & Dokumen -->
                            <Link
                                :href="'/documents'"
                                class="group relative flex items-center gap-3 overflow-hidden rounded-2xl border border-surface-200/70 bg-[#FAF9F4]/70 p-3 transition-all duration-200 hover:-translate-y-0.5 hover:border-sky-500/40 hover:bg-white hover:shadow-sm dark:border-surface-800 dark:bg-surface-900/60 dark:hover:border-sky-500/40 dark:hover:bg-surface-800/80"
                            >
                                <div
                                    class="flex h-8.5 w-8.5 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-sky-500/15 to-cyan-500/25 text-sky-700 shadow-2xs transition-transform duration-200 group-hover:scale-105 dark:from-sky-500/25 dark:to-cyan-500/35 dark:text-sky-300"
                                >
                                    <component
                                        :is="getIcon('FileText')"
                                        class="h-4 w-4"
                                    />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <p
                                            class="truncate text-xs font-semibold text-surface-900 group-hover:text-sky-700 dark:text-surface-100 dark:group-hover:text-sky-300"
                                        >
                                            Dokumen
                                        </p>
                                        <span
                                            class="text-surface-400 transition-transform duration-200 group-hover:translate-x-0.5 group-hover:text-sky-600"
                                            >→</span
                                        >
                                    </div>
                                    <p
                                        class="truncate text-[11px] text-surface-400"
                                    >
                                        Perpustakaan berkas
                                    </p>
                                </div>
                            </Link>

                            <!-- 6. Laporan & Evaluasi -->
                            <Link
                                :href="'/reports'"
                                class="group relative flex items-center gap-3 overflow-hidden rounded-2xl border border-surface-200/70 bg-[#FAF9F4]/70 p-3 transition-all duration-200 hover:-translate-y-0.5 hover:border-rose-500/40 hover:bg-white hover:shadow-sm dark:border-surface-800 dark:bg-surface-900/60 dark:hover:border-rose-500/40 dark:hover:bg-surface-800/80"
                            >
                                <div
                                    class="flex h-8.5 w-8.5 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-rose-500/15 to-pink-500/25 text-rose-700 shadow-2xs transition-transform duration-200 group-hover:scale-105 dark:from-rose-500/25 dark:to-pink-500/35 dark:text-rose-300"
                                >
                                    <component
                                        :is="getIcon('BarChart3')"
                                        class="h-4 w-4"
                                    />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <p
                                            class="truncate text-xs font-semibold text-surface-900 group-hover:text-rose-700 dark:text-surface-100 dark:group-hover:text-rose-300"
                                        >
                                            Laporan
                                        </p>
                                        <span
                                            class="text-surface-400 transition-transform duration-200 group-hover:translate-x-0.5 group-hover:text-rose-600"
                                            >→</span
                                        >
                                    </div>
                                    <p
                                        class="truncate text-[11px] text-surface-400"
                                    >
                                        Analitik performa
                                    </p>
                                </div>
                            </Link>
                        </div>

                        <!-- Direct Actions Action Bar -->
                        <div
                            class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-surface-100 pt-3 dark:border-surface-800"
                        >
                            <span
                                class="text-[11px] font-medium text-surface-400"
                            >
                                Aksi Cepat:
                            </span>
                            <div class="flex items-center gap-2">
                                <Link
                                    :href="tasks.index.url()"
                                    class="inline-flex items-center gap-1.5 rounded-full border border-surface-200/80 bg-surface-50 px-3 py-1 text-xs font-medium text-surface-700 transition hover:border-[#0F5132]/40 hover:bg-white hover:text-[#0F5132] dark:border-surface-700 dark:bg-surface-800/60 dark:text-surface-300 dark:hover:text-[#b7d9c1]"
                                >
                                    <component
                                        :is="getIcon('Plus')"
                                        class="h-3 w-3"
                                    />
                                    <span>Buka Tugas</span>
                                </Link>
                                <Link
                                    :href="projects.create.url()"
                                    class="inline-flex items-center gap-1.5 rounded-full bg-[#0F5132] px-3 py-1 text-xs font-medium text-white shadow-2xs transition hover:bg-[#165b3b] dark:bg-[#0F5132]"
                                >
                                    <component
                                        :is="getIcon('FolderPlus')"
                                        class="h-3 w-3"
                                    />
                                    <span>+ Proyek Baru</span>
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
