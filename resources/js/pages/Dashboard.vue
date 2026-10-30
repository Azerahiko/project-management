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

// Formatted Date & Time Labels
const currentMonthLabel = computed(() => {
    return new Date().toLocaleDateString('id-ID', {
        month: 'long',
        year: 'numeric',
    });
});

const currentDateFormatted = computed(() => {
    return new Date().toLocaleDateString('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
});

const greetingTime = computed(() => {
    const hour = new Date().getHours();
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

// Animated gentle ripple wave for living trend line
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
        const wave = Math.sin(wavePhase.value + i * 1.5) * 3.5;
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
    const today = new Date();
    const dayOfWeek = today.getDay();
    const distanceToMonday = (dayOfWeek + 6) % 7;
    const monday = new Date(today);
    monday.setDate(today.getDate() - distanceToMonday);

    const days = [];
    const dayNames = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];

    for (let i = 0; i < 7; i++) {
        const d = new Date(monday);
        d.setDate(monday.getDate() + i);
        days.push({
            name: dayNames[i],
            date: d.getDate(),
            isToday: d.toDateString() === today.toDateString(),
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

        // Continuous gentle breathing wave animation for trend curve
        const stepWave = () => {
            wavePhase.value += 0.03;
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

                <div class="mt-5 space-y-1.5">
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
                    class="mt-5 flex items-center gap-2 text-xs font-medium text-surface-600 dark:text-surface-400"
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
                <div class="mt-5 flex items-center gap-2 text-xs font-medium">
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

                <div class="mt-5 flex items-center gap-2">
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

        <!-- 3. Main Workspace Grid: Left Column (8 cols) & Right Column (4 cols) -->
        <div class="grid grid-cols-1 items-stretch gap-6 xl:grid-cols-12">
            <!-- ================= LEFT COLUMN: Pipeline Analytics, Active Projects, Upcoming Deadlines (8 Cols) ================= -->
            <div class="flex flex-col gap-6 xl:col-span-8">
                <!-- Card: Tren & Distribusi Alur Kerja (Calm Style, NO status badges!) -->
                <div
                    class="dash-card rounded-3xl border border-surface-200/80 bg-white p-6 shadow-sm sm:p-8 dark:border-surface-800 dark:bg-surface-900"
                >
                    <div
                        class="flex flex-col justify-between gap-3 border-b border-surface-100 pb-5 sm:flex-row sm:items-center dark:border-surface-800"
                    >
                        <div>
                            <h2
                                class="font-serif text-xl font-normal text-surface-950 sm:text-2xl dark:text-surface-50"
                            >
                                Distribusi Alur Kerja
                            </h2>
                            <p class="mt-0.5 text-xs text-surface-500">
                                Kapasitas dan sebaran tugas tim di setiap fase
                                alur
                            </p>
                        </div>

                        <!-- Clean text metrics without badges -->
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
                            <span class="text-surface-300 dark:text-surface-700"
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

                    <!-- Clean SVG Area Chart -->
                    <div
                        class="mt-6 w-full overflow-hidden rounded-2xl bg-surface-50/50 p-4 dark:bg-surface-950/30"
                    >
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
                                    <linearGradient
                                        id="calmAreaGradient"
                                        x1="0"
                                        y1="0"
                                        x2="0"
                                        y2="1"
                                    >
                                        <stop
                                            offset="0%"
                                            stop-color="#0F5132"
                                            stop-opacity="0.25"
                                        />
                                        <stop
                                            offset="70%"
                                            stop-color="#0F5132"
                                            stop-opacity="0.05"
                                        />
                                        <stop
                                            offset="100%"
                                            stop-color="#0F5132"
                                            stop-opacity="0.00"
                                        />
                                    </linearGradient>
                                    <linearGradient
                                        id="calmBeamGradient"
                                        x1="0%"
                                        y1="0%"
                                        x2="100%"
                                        y2="0%"
                                    >
                                        <stop
                                            offset="0%"
                                            stop-color="#0F5132"
                                        />
                                        <stop
                                            offset="50%"
                                            stop-color="#55a26c"
                                        />
                                        <stop
                                            offset="100%"
                                            stop-color="#b7d9c1"
                                        />
                                    </linearGradient>
                                </defs>

                                <!-- Horizontal Guidelines (Clean solid subtle lines, no dotted patterns) -->
                                <line
                                    :x1="30"
                                    :y1="25"
                                    :x2="pipelineTrendData.chartW - 30"
                                    :y2="25"
                                    stroke="#EAE6DB"
                                    stroke-opacity="0.6"
                                    class="dark:stroke-surface-800"
                                />
                                <line
                                    :x1="30"
                                    :y1="75"
                                    :x2="pipelineTrendData.chartW - 30"
                                    :y2="75"
                                    stroke="#EAE6DB"
                                    stroke-opacity="0.6"
                                    class="dark:stroke-surface-800"
                                />
                                <line
                                    :x1="30"
                                    :y1="125"
                                    :x2="pipelineTrendData.chartW - 30"
                                    :y2="125"
                                    stroke="#EAE6DB"
                                    stroke-opacity="0.8"
                                    class="dark:stroke-surface-800"
                                />

                                <!-- Translucent Area Fill (Breathes dynamically) -->
                                <path
                                    v-if="pipelineTrendData.areaD"
                                    :d="pipelineTrendData.areaD"
                                    fill="url(#calmAreaGradient)"
                                    class="transition-all duration-75"
                                />

                                <!-- Base Smooth Curve Line -->
                                <path
                                    v-if="pipelineTrendData.pathD"
                                    :d="pipelineTrendData.pathD"
                                    fill="none"
                                    stroke="#0F5132"
                                    stroke-width="3"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    class="opacity-75 dark:stroke-[#b7d9c1]"
                                />

                                <!-- Flowing Wave Pulse Line (Moving along curve) -->
                                <path
                                    v-if="pipelineTrendData.pathD"
                                    :d="pipelineTrendData.pathD"
                                    fill="none"
                                    stroke="url(#calmBeamGradient)"
                                    stroke-width="3.5"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    class="drop-shadow-xs filter"
                                />

                                <!-- Stage Node Points with Pulsing Rings & Clean Numbers -->
                                <g
                                    v-for="pt in pipelineTrendData.points"
                                    :key="pt.key"
                                >
                                    <!-- Subtle Breathing Aura -->
                                    <circle
                                        :cx="pt.x"
                                        :cy="pt.y"
                                        r="9"
                                        fill="#0F5132"
                                        class="animate-pulse opacity-20 dark:fill-[#b7d9c1]"
                                    />
                                    <!-- Center Node -->
                                    <circle
                                        :cx="pt.x"
                                        :cy="pt.y"
                                        r="5"
                                        fill="#0F5132"
                                        class="stroke-white stroke-2 dark:fill-[#b7d9c1] dark:stroke-surface-900"
                                    />
                                    <text
                                        :x="pt.x"
                                        :y="pt.y - 12"
                                        text-anchor="middle"
                                        class="fill-surface-900 font-serif text-[12px] font-normal dark:fill-surface-100"
                                    >
                                        {{ pt.count }}
                                    </text>
                                </g>
                            </svg>
                        </div>
                    </div>

                    <!-- 4 Stage Progress Modules (matching Calm reference 06 PROGRESS BAR, NO status badges!) -->
                    <div class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <div
                            v-for="st in statusDistribution"
                            :key="st.key"
                            class="flex flex-col justify-between rounded-2xl border border-surface-200/70 bg-[#FAF9F4]/70 p-4 dark:border-surface-800 dark:bg-surface-900/60"
                        >
                            <div class="flex items-center justify-between">
                                <span
                                    class="text-xs font-medium text-surface-600 dark:text-surface-300"
                                >
                                    {{ st.label }}
                                </span>
                                <span
                                    class="font-serif text-xs text-surface-500"
                                >
                                    {{ st.percent }}%
                                </span>
                            </div>

                            <div
                                class="mt-2 font-serif text-2xl font-normal text-surface-950 dark:text-surface-50"
                            >
                                {{ st.count }}
                            </div>

                            <!-- Calm Recessed Progress Bar -->
                            <div
                                class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-surface-200/80 dark:bg-surface-800"
                            >
                                <div
                                    class="h-full rounded-full bg-[#0F5132] transition-all duration-500 dark:bg-[#b7d9c1]"
                                    :style="{ width: `${st.percent}%` }"
                                />
                            </div>

                            <p class="mt-2 text-[11px] text-surface-400">
                                {{ st.description }}
                            </p>
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

                <!-- Card: Progres Proyek Aktif (NO status badges!) -->
                <div
                    class="dash-card rounded-3xl border border-surface-200/80 bg-white p-6 shadow-sm sm:p-8 dark:border-surface-800 dark:bg-surface-900"
                >
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
                                {{ projectProgress.length }} proyek aktif dalam
                                alur tim
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
                        class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2"
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
                                        <!-- Simple dot status indicator, NO badge -->
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
                                <!-- Recessed Progress Bar (Calm System) -->
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
                                        >{{ proj.completed_tasks_count }} dari
                                        {{ proj.tasks_count }} tugas
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
                            Mulai koordinasikan pekerjaan Anda dengan membuat
                            proyek tim.
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

                <!-- Card: Tenggat Waktu Mendatang (NO status badges!) -->
                <div
                    class="dash-card rounded-3xl border border-surface-200/80 bg-white p-6 shadow-sm sm:p-8 dark:border-surface-800 dark:bg-surface-900"
                >
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
                                <!-- Clean dot urgency indicator, NO badge -->
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
                                <span>{{ formatDueDate(task.due_date) }}</span>
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
            </div>

            <!-- ================= RIGHT COLUMN: Mini Kalender, Aktivitas Terkini, Pintasan & Efisiensi (4 Cols) ================= -->
            <div class="flex flex-col gap-6 xl:col-span-4">
                <!-- Mini Kalender Interaktif Mingguan (Calm System Tabs style) -->
                <div
                    class="dash-card rounded-3xl border border-surface-200/80 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900"
                >
                    <div class="flex items-center justify-between gap-3 pb-4">
                        <h2
                            class="font-serif text-lg font-normal text-surface-950 dark:text-surface-50"
                        >
                            {{ currentMonthLabel }}
                        </h2>
                        <Link
                            :href="'/calendar'"
                            class="inline-flex items-center gap-1 text-xs font-medium text-[#0F5132] transition hover:underline dark:text-[#b7d9c1]"
                        >
                            <span>Kalender</span>
                            <span>→</span>
                        </Link>
                    </div>

                    <!-- Weekdays Row -->
                    <div class="mt-2 grid grid-cols-7 gap-1 text-center">
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
                            <span class="text-[10px] font-medium opacity-80">
                                {{ d.name }}
                            </span>
                            <span class="font-serif text-sm font-normal">
                                {{ d.date }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Aktivitas Terkini Tim (matching Calm reference 08 NOTIFICATION) -->
                <div
                    class="dash-card rounded-3xl border border-surface-200/80 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900"
                >
                    <div class="flex items-center justify-between gap-3 pb-4">
                        <h2
                            class="font-serif text-lg font-normal text-surface-950 dark:text-surface-50"
                        >
                            Aktivitas Terkini
                        </h2>
                        <Link
                            :href="'/collaboration'"
                            class="inline-flex items-center gap-1 text-xs font-medium text-[#0F5132] transition hover:underline dark:text-[#b7d9c1]"
                        >
                            <span>Semua</span>
                            <span>→</span>
                        </Link>
                    </div>

                    <!-- Activities Stream -->
                    <div
                        v-if="recentActivities.length > 0"
                        class="mt-2 space-y-3.5"
                    >
                        <div
                            v-for="act in recentActivities.slice(0, 5)"
                            :key="act.id"
                            class="flex items-start gap-3 rounded-2xl border border-surface-200/60 bg-[#FAF9F4]/60 p-3 text-xs dark:border-surface-800 dark:bg-surface-900/60"
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
                        class="py-6 text-center text-xs text-surface-400"
                    >
                        Belum ada aktivitas baru tercatat hari ini.
                    </div>
                </div>

                <!-- Pintasan Ruang Kerja (NO badges!) -->
                <div
                    class="dash-card rounded-3xl border border-surface-200/80 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900"
                >
                    <h2
                        class="pb-4 font-serif text-lg font-normal text-surface-950 dark:text-surface-50"
                    >
                        Pintasan Cepat
                    </h2>

                    <div class="space-y-2.5">
                        <Link
                            :href="'/collaboration'"
                            class="group flex items-center justify-between rounded-2xl border border-surface-200/70 bg-[#FAF9F4]/70 p-3.5 transition hover:border-[#0F5132]/40 hover:bg-white dark:border-surface-800 dark:bg-surface-900/60"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-8 w-8 items-center justify-center rounded-full bg-[#b7d9c1]/50 text-[#0F5132] dark:bg-[#0F5132]/50 dark:text-[#b7d9c1]"
                                >
                                    <component
                                        :is="getIcon('MessageSquare')"
                                        class="h-4 w-4"
                                    />
                                </div>
                                <div>
                                    <p
                                        class="text-xs font-medium text-surface-900 group-hover:text-[#0F5132] dark:text-surface-100 dark:group-hover:text-[#b7d9c1]"
                                    >
                                        Papan Kolaborasi
                                    </p>
                                    <p class="text-[11px] text-surface-400">
                                        Diskusi alur kerja tim
                                    </p>
                                </div>
                            </div>
                            <span
                                class="text-surface-400 transition group-hover:translate-x-1 group-hover:text-[#0F5132] dark:group-hover:text-[#b7d9c1]"
                                >→</span
                            >
                        </Link>

                        <Link
                            :href="'/notifications'"
                            class="group flex items-center justify-between rounded-2xl border border-surface-200/70 bg-[#FAF9F4]/70 p-3.5 transition hover:border-[#0F5132]/40 hover:bg-white dark:border-surface-800 dark:bg-surface-900/60"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-8 w-8 items-center justify-center rounded-full bg-[#b7d9c1]/50 text-[#0F5132] dark:bg-[#0F5132]/50 dark:text-[#b7d9c1]"
                                >
                                    <component
                                        :is="getIcon('Bell')"
                                        class="h-4 w-4"
                                    />
                                </div>
                                <div>
                                    <p
                                        class="text-xs font-medium text-surface-900 group-hover:text-[#0F5132] dark:text-surface-100 dark:group-hover:text-[#b7d9c1]"
                                    >
                                        Pusat Notifikasi
                                    </p>
                                    <p class="text-[11px] text-surface-400">
                                        Pemberitahuan & update
                                    </p>
                                </div>
                            </div>
                            <span
                                class="text-surface-400 transition group-hover:translate-x-1 group-hover:text-[#0F5132] dark:group-hover:text-[#b7d9c1]"
                                >→</span
                            >
                        </Link>

                        <Link
                            :href="'/calendar'"
                            class="group flex items-center justify-between rounded-2xl border border-surface-200/70 bg-[#FAF9F4]/70 p-3.5 transition hover:border-[#0F5132]/40 hover:bg-white dark:border-surface-800 dark:bg-surface-900/60"
                        >
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-8 w-8 items-center justify-center rounded-full bg-[#b7d9c1]/50 text-[#0F5132] dark:bg-[#0F5132]/50 dark:text-[#b7d9c1]"
                                >
                                    <component
                                        :is="getIcon('Calendar')"
                                        class="h-4 w-4"
                                    />
                                </div>
                                <div>
                                    <p
                                        class="text-xs font-medium text-surface-900 group-hover:text-[#0F5132] dark:text-surface-100 dark:group-hover:text-[#b7d9c1]"
                                    >
                                        Kalender Jadwal
                                    </p>
                                    <p class="text-[11px] text-surface-400">
                                        Timeline tenggat waktu
                                    </p>
                                </div>
                            </div>
                            <span
                                class="text-surface-400 transition group-hover:translate-x-1 group-hover:text-[#0F5132] dark:group-hover:text-[#b7d9c1]"
                                >→</span
                            >
                        </Link>
                    </div>
                </div>

                <!-- Fokus & Efisiensi Tim (NO status badges!) -->
                <div
                    class="dash-card flex flex-1 flex-col justify-between rounded-3xl border border-surface-200/80 bg-white p-6 shadow-sm dark:border-surface-800 dark:bg-surface-900"
                >
                    <div>
                        <div
                            class="flex items-center justify-between gap-3 pb-4"
                        >
                            <h2
                                class="font-serif text-lg font-normal text-surface-950 dark:text-surface-50"
                            >
                                Fokus & Efisiensi
                            </h2>
                            <!-- Simple dot status indicator, NO badge -->
                            <div
                                class="flex items-center gap-1.5 text-xs font-medium"
                            >
                                <span
                                    class="h-1.5 w-1.5 rounded-full"
                                    :class="
                                        (stats?.overdue_count ?? 0) === 0
                                            ? 'bg-[#0F5132] dark:bg-[#b7d9c1]'
                                            : 'bg-rose-500'
                                    "
                                />
                                <span
                                    :class="
                                        (stats?.overdue_count ?? 0) === 0
                                            ? 'text-[#0F5132] dark:text-[#b7d9c1]'
                                            : 'text-rose-700 dark:text-rose-400'
                                    "
                                >
                                    {{
                                        (stats?.overdue_count ?? 0) === 0
                                            ? 'Ritme Optimal'
                                            : 'Perlu Fokus'
                                    }}
                                </span>
                            </div>
                        </div>

                        <!-- Progress Rate Score (Calm System 06 PROGRESS BAR) -->
                        <div
                            class="rounded-2xl border border-surface-200/60 bg-[#FAF9F4]/70 p-4 dark:border-surface-800 dark:bg-surface-900/60"
                        >
                            <div
                                class="flex items-center justify-between text-xs font-medium text-surface-600 dark:text-surface-300"
                            >
                                <span>Ketepatan Jadwal</span>
                                <span
                                    class="font-serif text-base font-normal text-surface-900 dark:text-surface-100"
                                >
                                    {{ onTimeRate }}%
                                </span>
                            </div>
                            <div
                                class="mt-2.5 h-2 w-full overflow-hidden rounded-full bg-surface-200/80 dark:bg-surface-800"
                            >
                                <div
                                    class="h-full rounded-full bg-[#0F5132] transition-all duration-500 dark:bg-[#b7d9c1]"
                                    :style="{ width: `${onTimeRate}%` }"
                                />
                            </div>
                            <p class="mt-2 text-[11px] text-surface-400">
                                {{
                                    onTimeRate >= 80
                                        ? 'Ritme alur kerja tim berada di dalam target penyelesaian.'
                                        : 'Perhatikan beberapa tugas yang mendekati batas tenggat.'
                                }}
                            </p>
                        </div>

                        <!-- 2 Stats Mini Grid -->
                        <div class="mt-3.5 grid grid-cols-2 gap-3">
                            <div
                                class="rounded-2xl border border-surface-200/60 bg-[#FAF9F4]/70 p-3.5 dark:border-surface-800 dark:bg-surface-900/60"
                            >
                                <span
                                    class="text-[10px] font-semibold text-surface-400 uppercase"
                                >
                                    Tugas Aktif
                                </span>
                                <div
                                    class="mt-1 font-serif text-xl font-normal text-surface-950 dark:text-surface-50"
                                >
                                    {{ stats?.my_tasks_count ?? 0 }}
                                </div>
                                <span class="text-[11px] text-surface-400"
                                    >Tanggung jawab Anda</span
                                >
                            </div>
                            <div
                                class="rounded-2xl border border-surface-200/60 bg-[#FAF9F4]/70 p-3.5 dark:border-surface-800 dark:bg-surface-900/60"
                            >
                                <span
                                    class="text-[10px] font-semibold text-surface-400 uppercase"
                                >
                                    Terlambat
                                </span>
                                <div
                                    class="mt-1 font-serif text-xl font-normal"
                                    :class="
                                        (stats?.overdue_count ?? 0) > 0
                                            ? 'text-rose-700 dark:text-rose-400'
                                            : 'text-surface-950 dark:text-surface-50'
                                    "
                                >
                                    {{ stats?.overdue_count ?? 0 }}
                                </div>
                                <span class="text-[11px] text-surface-400">
                                    {{
                                        (stats?.overdue_count ?? 0) > 0
                                            ? 'Perlu tindakan'
                                            : 'Nihil'
                                    }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div
                        class="mt-5 border-t border-surface-100 pt-3.5 dark:border-surface-800"
                    >
                        <Link
                            :href="tasks.index.url()"
                            class="flex items-center justify-between text-xs font-medium text-[#0F5132] transition hover:underline dark:text-[#b7d9c1]"
                        >
                            <span>Buka Papan Tugas</span>
                            <span>→</span>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
