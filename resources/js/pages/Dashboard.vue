<script setup lang="ts">
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';
import { usePage, Link, router } from '@inertiajs/vue3';
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
    () => page.props.tasks_by_status as Partial<Record<TaskStatus, number>> | undefined,
);
const upcomingDeadlines = computed(
    () => (page.props.upcoming_deadlines as UpcomingDeadline[] | undefined) ?? [],
);
const recentActivities = computed(
    () => (page.props.recent_activities as RecentActivity[] | undefined) ?? [],
);
const projectProgress = computed(
    () => (page.props.project_progress as ProjectProgress[] | undefined) ?? [],
);

// Active calendar day
const activeDayIndex = ref(4); // Default to Friday / middle day
const calendarDays = [
    { day: 'Mon', date: 26 },
    { day: 'Tue', date: 27 },
    { day: 'Wed', date: 28 },
    { day: 'Thr', date: 29 },
    { day: 'Fr', date: 30 },
    { day: 'Sat', date: 31 },
    { day: 'Sun', date: 1 },
];

// Active assignment filter
const activeFilter = ref<'all' | 'in_progress' | 'review' | 'done'>('all');

const heroRef = ref<HTMLElement | null>(null);
const gridRef = ref<HTMLElement | null>(null);
let ctx: gsap.Context | null = null;

onMounted(() => {
    if (prefersReducedMotion()) return;

    ctx = gsap.context(() => {
        gsap.from('.bento-animate-item', {
            opacity: 0,
            y: 20,
            duration: 0.45,
            stagger: 0.06,
            ease: 'power2.out',
        });
    });
});

onBeforeUnmount(() => {
    ctx?.revert();
    ctx = null;
});

// Calculations for circular gauges
const completedTasksRatio = computed(() => {
    const total =
        (tasksByStatus.value?.todo ?? 0) +
        (tasksByStatus.value?.in_progress ?? 0) +
        (tasksByStatus.value?.review ?? 0) +
        (tasksByStatus.value?.done ?? 0);
    if (total === 0) return 85;
    return Math.round(((tasksByStatus.value?.done ?? 0) / total) * 100);
});

const inProgressRatio = computed(() => {
    const total =
        (tasksByStatus.value?.todo ?? 0) +
        (tasksByStatus.value?.in_progress ?? 0) +
        (tasksByStatus.value?.review ?? 0) +
        (tasksByStatus.value?.done ?? 0);
    if (total === 0) return 65;
    return Math.round(((tasksByStatus.value?.in_progress ?? 0) / total) * 100);
});
</script>

<template>
    <div class="space-y-8">
        <!-- 1. Hero Welcome & Quick Feature Carousel Section -->
        <section
            ref="heroRef"
            class="bento-animate-item flex flex-col justify-between gap-6 lg:flex-row lg:items-center"
        >
            <!-- Left: Personalized Greeting -->
            <div class="max-w-xl">
                <div class="flex items-center gap-3">
                    <h1
                        class="text-3xl font-extrabold tracking-tight text-surface-900 sm:text-4xl dark:text-surface-50"
                    >
                        Hi, {{ user?.name || 'James' }}!
                    </h1>
                    <!-- Greeting Avatar Badges -->
                    <div class="flex -space-x-1.5 overflow-hidden">
                        <span
                            class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-indigo-500 text-xs font-bold text-white shadow-xs"
                        >
                            👋
                        </span>
                        <span
                            class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-violet-600 text-xs font-bold text-white shadow-xs"
                        >
                            ✨
                        </span>
                    </div>
                </div>

                <p
                    class="mt-2 text-2xl font-bold tracking-tight text-surface-800 sm:text-3xl dark:text-surface-100"
                >
                    What are your plans for today?
                </p>
                <p class="mt-2 text-sm text-surface-500 sm:text-base dark:text-surface-400">
                    This platform is designed to revolutionize the way you organize, track, and collaborate on your projects and tasks.
                </p>
            </div>

            <!-- Right: Quick Action Cards (Reference Design Matching) -->
            <div class="flex flex-wrap items-center gap-3 sm:gap-4">
                <!-- Dotted Quick Create Box -->
                <Link
                    :href="tasks.create.url()"
                    class="group flex h-32 w-24 flex-col items-center justify-center rounded-3xl border-2 border-dashed border-indigo-200 bg-indigo-50/50 p-3 transition-all hover:border-indigo-500 hover:bg-indigo-100/60 dark:border-indigo-800/80 dark:bg-indigo-950/20 dark:hover:border-indigo-400"
                    title="Add new task or project"
                >
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-2xl bg-white text-indigo-600 shadow-sm transition-transform duration-200 group-hover:scale-110 dark:bg-[#1E2245] dark:text-indigo-400"
                    >
                        <component :is="getIcon('Plus')" class="h-5 w-5" />
                    </div>
                </Link>

                <!-- Card 1: Stay Organized -->
                <Link
                    :href="projects.index.url()"
                    class="bento-card group flex h-32 w-44 flex-col justify-between p-4 transition-all hover:-translate-y-1 sm:w-48"
                >
                    <div class="flex items-center justify-between">
                        <!-- Mini Illustration Icon -->
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-2xl bg-amber-50 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400"
                        >
                            <component :is="getIcon('FolderKanban')" class="h-5 w-5" />
                        </div>
                        <span
                            class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                        >
                            {{ stats?.projects_count ?? 12 }}
                        </span>
                    </div>
                    <div>
                        <h2 class="text-xs font-bold text-surface-900 dark:text-surface-100">
                            Stay organized
                        </h2>
                        <p class="text-[11px] text-surface-500 dark:text-surface-400">
                            A clear structure for projects
                        </p>
                    </div>
                </Link>

                <!-- Card 2: Sync Your Tasks -->
                <Link
                    :href="tasks.index.url()"
                    class="bento-card group flex h-32 w-44 flex-col justify-between p-4 transition-all hover:-translate-y-1 sm:w-48"
                >
                    <div class="flex items-center justify-between">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400"
                        >
                            <component :is="getIcon('CheckSquare')" class="h-5 w-5" />
                        </div>
                        <span
                            class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                        >
                            {{ stats?.my_tasks_count ?? 8 }}
                        </span>
                    </div>
                    <div>
                        <h2 class="text-xs font-bold text-surface-900 dark:text-surface-100">
                            Sync your tasks
                        </h2>
                        <p class="text-[11px] text-surface-500 dark:text-surface-400">
                            Ensure tasks are on track
                        </p>
                    </div>
                </Link>

                <!-- Card 3: Collaborate & Share -->
                <Link
                    :href="'/collaboration'"
                    class="bento-card group flex h-32 w-44 flex-col justify-between p-4 transition-all hover:-translate-y-1 sm:w-48"
                >
                    <div class="flex items-center justify-between">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950/40 dark:text-indigo-400"
                        >
                            <component :is="getIcon('Users')" class="h-5 w-5" />
                        </div>
                        <span
                            class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                        >
                            {{ stats?.team_members_count ?? 15 }}
                        </span>
                    </div>
                    <div>
                        <h2 class="text-xs font-bold text-surface-900 dark:text-surface-100">
                            Collaborate & share
                        </h2>
                        <p class="text-[11px] text-surface-500 dark:text-surface-400">
                            Share updates with team
                        </p>
                    </div>
                </Link>
            </div>
        </section>

        <!-- 2. Main 3-Column Bento-Grid Modular Layout -->
        <div ref="gridRef" class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <!-- COLUMN 1: Notifications & Today Tasks -->
            <div class="space-y-6">
                <!-- Notifications Card -->
                <div class="bento-card bento-animate-item p-6">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-bold text-surface-900 dark:text-surface-100">
                            Notifications
                        </h2>
                        <Link
                            :href="'/notifications'"
                            class="text-xs font-semibold text-primary-600 hover:underline dark:text-primary-400"
                        >
                            Clear / All
                        </Link>
                    </div>

                    <!-- Floating Pill Card for Urgent / Upcoming Event -->
                    <div
                        class="mt-4 rounded-2xl border border-gray-100 bg-surface-50/80 p-4 shadow-sm transition-all hover:bg-white hover:shadow-md dark:border-white/5 dark:bg-[#202444]/60 dark:hover:bg-[#202444]"
                    >
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-2">
                                <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse" />
                                <span class="text-xs font-bold text-surface-900 dark:text-surface-100">
                                    Upcoming deadline
                                </span>
                            </div>
                            <div class="flex items-center gap-1 text-surface-400">
                                <button
                                    type="button"
                                    class="rounded-lg p-1 hover:bg-surface-200 dark:hover:bg-surface-700"
                                    aria-label="Options"
                                >
                                    <component :is="getIcon('MoreHorizontal')" class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>

                        <p class="mt-2 text-sm font-semibold text-surface-800 dark:text-surface-200">
                            {{ upcomingDeadlines[0]?.title ?? 'Landing design review & deployment' }}
                        </p>

                        <div class="mt-3 flex items-center gap-4 text-xs text-surface-500 dark:text-surface-400">
                            <span class="flex items-center gap-1.5">
                                <component :is="getIcon('Calendar')" class="h-3.5 w-3.5" />
                                {{ upcomingDeadlines[0]?.due_date ? new Date(upcomingDeadlines[0].due_date).toLocaleDateString() : 'Sat, 10 May' }}
                            </span>
                            <span class="flex items-center gap-1.5">
                                <component :is="getIcon('Clock')" class="h-3.5 w-3.5" />
                                11 AM - 11:45 AM
                            </span>
                        </div>
                    </div>

                    <!-- Message Preview Item -->
                    <div class="mt-4 rounded-2xl border border-dashed border-gray-200/80 p-3.5 dark:border-white/10">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-surface-700 dark:text-surface-300">
                                Message | {{ recentActivities[0]?.description ? 'Activity Update' : 'Product Design' }}
                            </span>
                            <span class="text-[10px] text-surface-400">Just now</span>
                        </div>
                        <p class="mt-1 line-clamp-1 text-xs text-surface-500 dark:text-surface-400">
                            {{ recentActivities[0]?.description ?? 'Hey team, just wanted to check in on the sprint status...' }}
                        </p>
                    </div>
                </div>

                <!-- Today Tasks Card -->
                <div class="bento-card bento-animate-item p-6">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <h2 class="text-lg font-bold text-surface-900 dark:text-surface-100">
                                Today tasks
                            </h2>
                            <!-- Assignees avatars stack -->
                            <div class="flex -space-x-2">
                                <div
                                    class="h-6 w-6 rounded-full border-2 border-white bg-indigo-500 text-[10px] font-bold text-white flex items-center justify-center dark:border-surface-900"
                                >
                                    JD
                                </div>
                                <div
                                    class="h-6 w-6 rounded-full border-2 border-white bg-amber-500 text-[10px] font-bold text-white flex items-center justify-center dark:border-surface-900"
                                >
                                    RL
                                </div>
                                <div
                                    class="h-6 w-6 rounded-full border-2 border-white bg-emerald-500 text-[10px] font-bold text-white flex items-center justify-center dark:border-surface-900"
                                >
                                    +3
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <Link
                                :href="tasks.index.url()"
                                class="text-xs font-semibold text-primary-600 hover:underline dark:text-primary-400"
                            >
                                Edit / All
                            </Link>
                        </div>
                    </div>

                    <!-- Task Items with Duration & Progress Bars -->
                    <div class="mt-5 space-y-4">
                        <!-- Task 1 -->
                        <div class="space-y-1.5 border-b border-gray-100 pb-3 dark:border-white/5">
                            <div class="flex items-center justify-between text-xs font-semibold text-surface-800 dark:text-surface-200">
                                <span>Conduct UX research & interviews</span>
                                <span class="text-surface-500">90%</span>
                            </div>
                            <div class="h-1.5 w-full rounded-full bg-slate-100 dark:bg-slate-800">
                                <div class="h-1.5 rounded-full bg-[#5B50E5]" style="width: 90%" />
                            </div>
                            <div class="flex items-center justify-between text-[11px] text-surface-400">
                                <span>Duration: 02h 45m</span>
                                <span>4 comments • 16 assets</span>
                            </div>
                        </div>

                        <!-- Task 2 -->
                        <div class="space-y-1.5 border-b border-gray-100 pb-3 dark:border-white/5">
                            <div class="flex items-center justify-between text-xs font-semibold text-surface-800 dark:text-surface-200">
                                <span>Refactor authentication controller</span>
                                <span class="text-surface-500">50%</span>
                            </div>
                            <div class="h-1.5 w-full rounded-full bg-slate-100 dark:bg-slate-800">
                                <div class="h-1.5 rounded-full bg-amber-500" style="width: 50%" />
                            </div>
                            <div class="flex items-center justify-between text-[11px] text-surface-400">
                                <span>Duration: 06h 55m</span>
                                <span>Due: Tomorrow</span>
                            </div>
                        </div>

                        <!-- Task 3 -->
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between text-xs font-semibold text-surface-800 dark:text-surface-200">
                                <span>Deploy staging environment</span>
                                <span class="text-surface-500">10%</span>
                            </div>
                            <div class="h-1.5 w-full rounded-full bg-slate-100 dark:bg-slate-800">
                                <div class="h-1.5 rounded-full bg-emerald-500" style="width: 10%" />
                            </div>
                            <div class="flex items-center justify-between text-[11px] text-surface-400">
                                <span>Duration: 01h 30m</span>
                                <span>Status: In Progress</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- COLUMN 2: Assignments & Purple Feature Card -->
            <div class="space-y-6">
                <!-- Assignments / Active Tasks Card -->
                <div class="bento-card bento-animate-item p-6">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-bold text-surface-900 dark:text-surface-100">
                            Assignments
                        </h2>
                        <Link
                            :href="tasks.index.url()"
                            class="text-xs font-semibold text-primary-600 hover:underline dark:text-primary-400"
                        >
                            Edit
                        </Link>
                    </div>

                    <!-- Category Filter Tags -->
                    <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold">
                        <button
                            type="button"
                            class="rounded-xl px-3 py-1 transition-all"
                            :class="
                                activeFilter === 'all'
                                    ? 'bg-primary-600 text-white shadow-xs'
                                    : 'bg-surface-100 text-surface-600 hover:bg-surface-200 dark:bg-[#202444] dark:text-surface-300'
                            "
                            @click="activeFilter = 'all'"
                        >
                            Motion design
                        </button>
                        <button
                            type="button"
                            class="rounded-xl px-3 py-1 transition-all"
                            :class="
                                activeFilter === 'in_progress'
                                    ? 'bg-primary-600 text-white shadow-xs'
                                    : 'bg-surface-100 text-surface-600 hover:bg-surface-200 dark:bg-[#202444] dark:text-surface-300'
                            "
                            @click="activeFilter = 'in_progress'"
                        >
                            Logo
                        </button>
                    </div>

                    <!-- Featured Task Card -->
                    <div
                        class="mt-4 rounded-2xl border border-gray-100 bg-surface-50/80 p-5 shadow-xs transition-all hover:bg-white hover:shadow-md dark:border-white/5 dark:bg-[#202444]/60 dark:hover:bg-[#202444]"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="text-sm font-bold text-surface-900 dark:text-surface-100">
                                Design a packaging concept for a new product
                            </h3>
                            <span
                                class="rounded-full bg-rose-50 px-2.5 py-0.5 text-[11px] font-bold text-rose-600 dark:bg-rose-950/40 dark:text-rose-400"
                            >
                                High
                            </span>
                        </div>

                        <div class="mt-4 flex items-center justify-between">
                            <span
                                class="rounded-lg bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400"
                            >
                                Package design
                            </span>

                            <div class="flex items-center gap-2">
                                <span class="text-xs font-medium text-surface-600 dark:text-surface-300">
                                    Rachel Lee
                                </span>
                                <div
                                    class="h-6 w-6 rounded-full bg-indigo-500 text-[10px] font-bold text-white flex items-center justify-center"
                                >
                                    RL
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Soft Purple Button: + Add New Assignment -->
                    <Link
                        :href="tasks.create.url()"
                        class="mt-4 flex w-full items-center justify-center gap-2 rounded-2xl bg-[#EEEDFC] py-3 text-xs font-bold text-[#5B50E5] transition-all hover:bg-[#E2E0FA] dark:bg-[#202444] dark:text-[#8D82FF] dark:hover:bg-[#282E56]"
                    >
                        <div class="flex h-5 w-5 items-center justify-center rounded-md bg-[#5B50E5] text-white dark:bg-[#8D82FF] dark:text-slate-900">
                            <component :is="getIcon('Plus')" class="h-3.5 w-3.5" />
                        </div>
                        <span>Add new assignment</span>
                    </Link>
                </div>

                <!-- Purple Feature Card: Go Premium! / Project Velocity -->
                <div
                    class="bento-animate-item relative flex flex-col items-center justify-between overflow-hidden rounded-[32px] bg-[#5B50E5] p-7 text-center text-white shadow-xl dark:bg-[#2B2577]"
                >
                    <!-- Background ambient ring -->
                    <div
                        class="pointer-events-none absolute -top-10 -right-10 h-40 w-40 rounded-full bg-white/10 blur-xl"
                    />

                    <!-- Illustrated Icon Container -->
                    <div
                        class="flex h-16 w-16 items-center justify-center rounded-3xl bg-white/20 backdrop-blur-md shadow-md"
                    >
                        <span class="text-3xl">🎁</span>
                    </div>

                    <div class="mt-4">
                        <h3 class="text-xl font-extrabold tracking-tight">
                            Go premium!
                        </h3>
                        <p class="mt-2 text-xs text-white/80 leading-relaxed max-w-xs">
                            Gain access to advanced automated reporting, priority team workflows, and custom analytics.
                        </p>
                    </div>

                    <Link
                        :href="'/reports'"
                        class="mt-5 rounded-2xl bg-[#0D0E1A] px-6 py-2.5 text-xs font-bold text-white shadow-md transition-all hover:bg-black hover:scale-105 active:scale-95"
                    >
                        Find out more
                    </Link>
                </div>
            </div>

            <!-- COLUMN 3: Calendar, Circular Progress & Board Meeting -->
            <div class="space-y-6">
                <!-- Interactive Calendar Widget -->
                <div class="bento-card bento-animate-item p-6">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-bold text-surface-900 dark:text-surface-100">
                            May 2026
                        </h2>
                        <div class="flex items-center gap-1 text-surface-400">
                            <button
                                type="button"
                                class="rounded-lg p-1.5 hover:bg-surface-100 dark:hover:bg-surface-800"
                                aria-label="Previous month"
                            >
                                <component :is="getIcon('ChevronLeft')" class="h-4 w-4" />
                            </button>
                            <button
                                type="button"
                                class="rounded-lg p-1.5 hover:bg-surface-100 dark:hover:bg-surface-800"
                                aria-label="Next month"
                            >
                                <component :is="getIcon('ChevronRight')" class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <!-- Days of Week Pills Row -->
                    <div class="mt-4 flex items-center justify-between">
                        <button
                            v-for="(item, idx) in calendarDays"
                            :key="item.day"
                            type="button"
                            class="flex flex-col items-center gap-1 rounded-2xl px-2.5 py-2 transition-all"
                            :class="
                                activeDayIndex === idx
                                    ? 'bg-[#5B50E5] text-white shadow-md'
                                    : 'text-surface-600 hover:bg-surface-100 dark:text-surface-400 dark:hover:bg-surface-800'
                            "
                            @click="activeDayIndex = idx"
                        >
                            <span class="text-[11px] font-medium">{{ item.day }}</span>
                            <span class="text-xs font-extrabold">{{ item.date }}</span>
                        </button>
                    </div>

                    <!-- Schedule Timeline with Dotted Connector -->
                    <div class="mt-5 space-y-4">
                        <!-- Timeline Item 1 -->
                        <div>
                            <div class="text-[11px] font-bold text-surface-400 tracking-wider">
                                04:30 - 05:00 PM
                            </div>
                            <div
                                class="mt-1.5 flex items-center justify-between rounded-xl border border-gray-100 bg-surface-50/60 p-3 dark:border-white/5 dark:bg-[#202444]/40"
                            >
                                <div class="flex items-center gap-2.5">
                                    <div
                                        class="flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-100 text-indigo-600 dark:bg-indigo-950/60 dark:text-indigo-400"
                                    >
                                        <component :is="getIcon('Users')" class="h-4 w-4" />
                                    </div>
                                    <div>
                                        <h4 class="text-xs font-bold text-surface-900 dark:text-surface-100">
                                            Team meeting
                                        </h4>
                                        <span class="text-[10px] text-surface-400">12:00 - 12:30 • UX/UI design</span>
                                    </div>
                                </div>
                                <button type="button" class="text-surface-400 hover:text-surface-600" aria-label="Menu">
                                    <component :is="getIcon('MoreHorizontal')" class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>

                        <!-- Timeline Item 2 -->
                        <div>
                            <div class="text-[11px] font-bold text-surface-400 tracking-wider">
                                11:30 - 12:30 PM
                            </div>
                            <div
                                class="mt-1.5 flex items-center justify-between rounded-xl border border-gray-100 bg-surface-50/60 p-3 dark:border-white/5 dark:bg-[#202444]/40"
                            >
                                <div class="flex items-center gap-2.5">
                                    <div
                                        class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400"
                                    >
                                        <component :is="getIcon('CheckSquare')" class="h-4 w-4" />
                                    </div>
                                    <div>
                                        <h4 class="text-xs font-bold text-surface-900 dark:text-surface-100">
                                            Meeting with new client
                                        </h4>
                                        <span class="text-[10px] text-surface-400">12:30 - 01:30 PM • Project scope</span>
                                    </div>
                                </div>
                                <button type="button" class="text-surface-400 hover:text-surface-600" aria-label="Menu">
                                    <component :is="getIcon('MoreHorizontal')" class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dual Circular Progress Stats Card -->
                <div class="bento-card bento-animate-item p-6">
                    <div class="grid grid-cols-2 gap-4">
                        <!-- Ring 1 -->
                        <div class="flex flex-col items-center text-center">
                            <!-- Circular SVG Meter -->
                            <div class="relative flex h-20 w-20 items-center justify-center">
                                <svg class="h-20 w-20 -rotate-90 transform" viewBox="0 0 36 36">
                                    <path
                                        class="text-slate-100 dark:text-slate-800"
                                        stroke-width="3"
                                        stroke="currentColor"
                                        fill="none"
                                        d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                    />
                                    <path
                                        class="text-emerald-500"
                                        stroke-width="3"
                                        stroke-dasharray="90, 100"
                                        stroke-linecap="round"
                                        stroke="currentColor"
                                        fill="none"
                                        d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                    />
                                </svg>
                                <span class="absolute text-sm font-black text-surface-900 dark:text-surface-100">
                                    90%
                                </span>
                            </div>
                            <span class="mt-2 text-[10px] font-bold text-emerald-600 uppercase tracking-wider">
                                Data Research
                            </span>
                            <span class="text-xs font-bold text-surface-900 dark:text-surface-100">
                                Marketing
                            </span>
                            <span class="mt-1 text-[10px] text-surface-400">
                                5/5 tasks done!
                            </span>
                        </div>

                        <!-- Ring 2 -->
                        <div class="flex flex-col items-center text-center">
                            <div class="relative flex h-20 w-20 items-center justify-center">
                                <svg class="h-20 w-20 -rotate-90 transform" viewBox="0 0 36 36">
                                    <path
                                        class="text-slate-100 dark:text-slate-800"
                                        stroke-width="3"
                                        stroke="currentColor"
                                        fill="none"
                                        d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                    />
                                    <path
                                        class="text-rose-500"
                                        stroke-width="3"
                                        stroke-dasharray="65, 100"
                                        stroke-linecap="round"
                                        stroke="currentColor"
                                        fill="none"
                                        d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                    />
                                </svg>
                                <span class="absolute text-sm font-black text-surface-900 dark:text-surface-100">
                                    65%
                                </span>
                            </div>
                            <span class="mt-2 text-[10px] font-bold text-rose-500 uppercase tracking-wider">
                                UX/UI Design
                            </span>
                            <span class="text-xs font-bold text-surface-900 dark:text-surface-100">
                                Typography
                            </span>
                            <Link
                                :href="tasks.index.url()"
                                class="mt-1.5 rounded-full bg-indigo-600 px-3 py-0.5 text-[10px] font-bold text-white shadow-xs hover:bg-indigo-700 transition"
                            >
                                Check
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Board Meeting / Milestone Card -->
                <div class="bento-card bento-animate-item p-6">
                    <div class="flex items-center justify-between">
                        <h2 class="text-base font-bold text-surface-900 dark:text-surface-100">
                            Board meeting
                        </h2>
                        <button type="button" class="text-xs font-semibold text-primary-600 hover:underline dark:text-primary-400">
                            Edit
                        </button>
                    </div>

                    <div class="mt-2 flex items-center gap-2 text-xs text-surface-400">
                        <span class="h-2 w-2 rounded-full bg-indigo-500" />
                        <span>March 24 at 4:00 PM</span>
                    </div>

                    <p class="mt-2 text-xs text-surface-600 dark:text-surface-300">
                        Meeting with John Smith, 4th floor, room 159
                    </p>

                    <div class="mt-4 flex items-center gap-2">
                        <button
                            type="button"
                            class="flex-1 rounded-xl bg-surface-100 py-2 text-xs font-bold text-surface-700 hover:bg-surface-200 transition dark:bg-surface-800 dark:text-surface-200"
                        >
                            Reschedule
                        </button>
                        <button
                            type="button"
                            class="flex-1 rounded-xl bg-[#5B50E5] py-2 text-xs font-bold text-white shadow-xs hover:bg-[#4D40E3] transition"
                        >
                            Accept invite
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
