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

// Active calendar day matching reference photo
const activeDayIndex = ref(4); // Default to Friday (Fr 18)
const calendarDays = [
    { day: 'Mon', date: 14 },
    { day: 'Tue', date: 15 },
    { day: 'Wed', date: 16 },
    { day: 'Thr', date: 17 },
    { day: 'Fr', date: 18 },
    { day: 'Sat', date: 19 },
    { day: 'Sun', date: 20 },
];

// Active assignment category filter
const activeFilter = ref<'motion' | 'logo'>('motion');

let ctx: gsap.Context | null = null;

onMounted(() => {
    if (prefersReducedMotion()) return;

    ctx = gsap.context(() => {
        gsap.from('.bento-item', {
            opacity: 0,
            y: 12,
            duration: 0.35,
            stagger: 0.04,
            ease: 'power2.out',
        });
    });
});

onBeforeUnmount(() => {
    ctx?.revert();
    ctx = null;
});
</script>

<template>
    <div class="space-y-8">
        <!-- 1. Hero Section & Quick Action Cards Carousel (Exact Match to Reference Photo) -->
        <section
            class="bento-item flex flex-col justify-between gap-6 xl:flex-row xl:items-center"
        >
            <!-- Left: Greeting with Avatars & Display Heading -->
            <div class="max-w-xl">
                <div class="flex items-center gap-3">
                    <h1
                        class="text-3xl font-black tracking-tight text-slate-900 sm:text-4xl dark:text-white"
                    >
                        Hi, {{ user?.name || 'James' }}!
                    </h1>
                    <!-- Greeting Avatars Stack -->
                    <div class="flex -space-x-1.5 overflow-hidden">
                        <span
                            class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-indigo-500 text-xs font-bold text-white shadow-xs"
                        >
                            👨‍💻
                        </span>
                        <span
                            class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-violet-600 text-xs font-bold text-white shadow-xs"
                        >
                            🚀
                        </span>
                    </div>
                </div>

                <p
                    class="mt-2 text-2xl font-extrabold tracking-tight text-slate-900 sm:text-3xl dark:text-white"
                >
                    What are your plans for today?
                </p>
                <p
                    class="mt-2 max-w-lg text-xs leading-relaxed font-medium text-slate-500 sm:text-sm dark:text-slate-400"
                >
                    This platform is designed to revolutionize the way you
                    organize, track, and access your projects and tasks.
                </p>
            </div>

            <!-- Right: 4 Carousel Cards in a Row -->
            <div class="flex flex-wrap items-center gap-3 sm:gap-4">
                <!-- 1. Dotted Quick Create Box with '+' -->
                <Link
                    :href="tasks.create.url()"
                    class="group flex h-36 w-28 flex-col items-center justify-center rounded-3xl border-2 border-dashed border-indigo-200 bg-indigo-50/50 p-3 transition-all hover:border-[#5B50E5] hover:bg-indigo-100/60 dark:border-indigo-800/80 dark:bg-indigo-950/20"
                    title="Add new task"
                >
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-2xl bg-[#5B50E5] text-white shadow-md transition-transform duration-200 group-hover:scale-110"
                    >
                        <component :is="getIcon('Plus')" class="h-5 w-5" />
                    </div>
                </Link>

                <!-- 2. Stay organized -->
                <div
                    class="flex h-36 w-44 flex-col justify-between rounded-3xl border border-slate-100 bg-white p-4 shadow-sm transition-all hover:-translate-y-1 hover:shadow-md sm:w-48 dark:border-white/10 dark:bg-[#1B1E36]"
                >
                    <!-- Illustration Icon -->
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-50 text-amber-600 dark:bg-amber-950/40 dark:text-amber-400"
                    >
                        <component :is="getIcon('Calendar')" class="h-6 w-6" />
                    </div>
                    <div>
                        <h2
                            class="text-xs font-bold text-slate-900 dark:text-white"
                        >
                            Stay organized
                        </h2>
                        <p
                            class="mt-0.5 text-[11px] font-medium text-slate-400"
                        >
                            A clear structure for notes
                        </p>
                    </div>
                </div>

                <!-- 3. Sync your notes -->
                <div
                    class="flex h-36 w-44 flex-col justify-between rounded-3xl border border-slate-100 bg-white p-4 shadow-sm transition-all hover:-translate-y-1 hover:shadow-md sm:w-48 dark:border-white/10 dark:bg-[#1B1E36]"
                >
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 text-[#5B50E5] dark:bg-indigo-950/40 dark:text-indigo-400"
                    >
                        <component
                            :is="getIcon('CheckSquare')"
                            class="h-6 w-6"
                        />
                    </div>
                    <div>
                        <h2
                            class="text-xs font-bold text-slate-900 dark:text-white"
                        >
                            Sync your notes
                        </h2>
                        <p
                            class="mt-0.5 text-[11px] font-medium text-slate-400"
                        >
                            Ensure that notes are synced
                        </p>
                    </div>
                </div>

                <!-- 4. Collaborate and share -->
                <div
                    class="flex h-36 w-44 flex-col justify-between rounded-3xl border border-slate-100 bg-white p-4 shadow-sm transition-all hover:-translate-y-1 hover:shadow-md sm:w-48 dark:border-white/10 dark:bg-[#1B1E36]"
                >
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-400"
                    >
                        <component :is="getIcon('Users')" class="h-6 w-6" />
                    </div>
                    <div>
                        <h2
                            class="text-xs font-bold text-slate-900 dark:text-white"
                        >
                            Collaborate and share
                        </h2>
                        <p
                            class="mt-0.5 text-[11px] font-medium text-slate-400"
                        >
                            Share notes with colleagues
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- 2. Main 3-Column Bento Grid Matching Photo Pixel-for-Pixel -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 xl:grid-cols-3">
            <!-- ================= COLUMN 1: Notifications & Today Tasks ================= -->
            <div class="space-y-6">
                <!-- Notifications Card -->
                <div
                    class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-[#1B1E36]"
                >
                    <div class="flex items-center justify-between">
                        <h2
                            class="text-base font-bold text-slate-900 dark:text-white"
                        >
                            Notifications
                        </h2>
                        <Link
                            :href="'/notifications'"
                            class="text-xs font-semibold text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                        >
                            Clear
                        </Link>
                    </div>

                    <!-- Floating White Pill Card for Upcoming Event -->
                    <div
                        class="mt-4 rounded-3xl border border-slate-100 bg-slate-50/90 p-4 shadow-sm transition-all hover:bg-white hover:shadow-md dark:border-white/10 dark:bg-[#202444]/80 dark:hover:bg-[#202444]"
                    >
                        <div class="flex items-start justify-between">
                            <div class="flex items-center gap-2">
                                <span
                                    class="h-2 w-2 animate-pulse rounded-full bg-emerald-500"
                                />
                                <span
                                    class="text-xs font-bold text-slate-900 dark:text-white"
                                >
                                    Upcoming event •
                                </span>
                            </div>
                            <div
                                class="flex items-center gap-1.5 text-slate-400"
                            >
                                <button
                                    type="button"
                                    class="p-1 hover:text-slate-600 dark:hover:text-slate-200"
                                    aria-label="Menu"
                                >
                                    <component
                                        :is="getIcon('MoreHorizontal')"
                                        class="h-3.5 w-3.5"
                                    />
                                </button>
                                <button
                                    type="button"
                                    class="p-1 hover:text-slate-600 dark:hover:text-slate-200"
                                    aria-label="Delete"
                                >
                                    <component
                                        :is="getIcon('Trash2')"
                                        class="h-3.5 w-3.5"
                                    />
                                </button>
                                <button
                                    type="button"
                                    class="p-1 hover:text-slate-600 dark:hover:text-slate-200"
                                    aria-label="Edit"
                                >
                                    <component
                                        :is="getIcon('Pencil')"
                                        class="h-3.5 w-3.5"
                                    />
                                </button>
                            </div>
                        </div>

                        <p
                            class="mt-1 text-xs font-medium text-slate-500 dark:text-slate-400"
                        >
                            Landing design meeting | Time: 120 min
                        </p>

                        <div
                            class="mt-3 flex items-center gap-4 text-xs font-semibold text-slate-700 dark:text-slate-300"
                        >
                            <span class="flex items-center gap-1.5">
                                <component
                                    :is="getIcon('Calendar')"
                                    class="h-3.5 w-3.5 text-slate-400"
                                />
                                Sat, 10 May
                            </span>
                            <span class="flex items-center gap-1.5">
                                <component
                                    :is="getIcon('Clock')"
                                    class="h-3.5 w-3.5 text-slate-400"
                                />
                                11 AM - 11:45 AM
                            </span>
                        </div>
                    </div>

                    <!-- Message Preview Item -->
                    <div
                        class="mt-4 rounded-2xl border border-dashed border-slate-200/90 p-3.5 dark:border-white/10"
                    >
                        <div class="flex items-center justify-between">
                            <span
                                class="text-xs font-bold text-slate-800 dark:text-slate-200"
                            >
                                Message | Product design
                            </span>
                            <button
                                type="button"
                                class="text-slate-400 hover:text-slate-600"
                                aria-label="More"
                            >
                                <component
                                    :is="getIcon('MoreHorizontal')"
                                    class="h-3.5 w-3.5"
                                />
                            </button>
                        </div>
                        <p class="mt-1 text-[11px] font-medium text-slate-400">
                            Message from Ken Smith
                        </p>
                        <p
                            class="mt-1 line-clamp-1 text-xs text-slate-600 dark:text-slate-300"
                        >
                            Hey team, just wanted to check in and see how is...
                        </p>
                    </div>
                </div>

                <!-- Today Tasks Card -->
                <div
                    class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-[#1B1E36]"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <h2
                                class="text-base font-bold text-slate-900 dark:text-white"
                            >
                                Today tasks
                            </h2>
                            <!-- Avatars Stack -->
                            <div class="flex -space-x-1.5">
                                <div
                                    class="flex h-6 w-6 items-center justify-center rounded-full border-2 border-white bg-indigo-500 text-[10px] font-bold text-white dark:border-slate-800"
                                >
                                    JD
                                </div>
                                <div
                                    class="flex h-6 w-6 items-center justify-center rounded-full border-2 border-white bg-amber-500 text-[10px] font-bold text-white dark:border-slate-800"
                                >
                                    RL
                                </div>
                                <div
                                    class="flex h-6 w-6 items-center justify-center rounded-full border-2 border-white bg-emerald-500 text-[10px] font-bold text-white dark:border-slate-800"
                                >
                                    +3
                                </div>
                            </div>
                        </div>

                        <div
                            class="flex items-center gap-2 text-xs font-semibold text-slate-400"
                        >
                            <span
                                class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-600 dark:bg-slate-800 dark:text-slate-300"
                            >
                                (88)
                            </span>
                            <button
                                type="button"
                                class="hover:text-slate-600 dark:hover:text-slate-200"
                            >
                                Edit
                            </button>
                            <button
                                type="button"
                                class="hover:text-slate-600 dark:hover:text-slate-200"
                            >
                                Share
                            </button>
                        </div>
                    </div>

                    <!-- Task Progress Rows (Exact Reference Design) -->
                    <div class="mt-5 space-y-4">
                        <!-- Task 1 -->
                        <div
                            class="space-y-1.5 border-b border-slate-100 pb-3 dark:border-white/5"
                        >
                            <div
                                class="flex items-center justify-between text-xs font-bold text-slate-800 dark:text-slate-200"
                            >
                                <div>
                                    <p
                                        class="font-bold text-slate-900 dark:text-white"
                                    >
                                        Conduct research
                                    </p>
                                    <span
                                        class="text-[10px] font-normal text-slate-400"
                                        >4 May, 09:20 AM</span
                                    >
                                </div>
                                <div class="text-right">
                                    <span
                                        class="text-[10px] font-normal text-slate-400"
                                        >Duration</span
                                    >
                                    <p class="text-xs font-bold">02 h 45 m</p>
                                </div>
                                <div class="w-24">
                                    <div
                                        class="flex justify-end text-[10px] font-bold text-slate-600 dark:text-slate-300"
                                    >
                                        90%
                                    </div>
                                    <div
                                        class="h-1.5 w-full rounded-full bg-slate-100 dark:bg-slate-800"
                                    >
                                        <div
                                            class="h-1.5 rounded-full bg-[#5B50E5]"
                                            style="width: 90%"
                                        />
                                    </div>
                                </div>
                                <span
                                    class="text-[11px] font-medium text-slate-400"
                                    >📎 4 • 💬 16</span
                                >
                            </div>
                        </div>

                        <!-- Task 2 -->
                        <div
                            class="space-y-1.5 border-b border-slate-100 pb-3 dark:border-white/5"
                        >
                            <div
                                class="flex items-center justify-between text-xs font-bold text-slate-800 dark:text-slate-200"
                            >
                                <div>
                                    <p
                                        class="font-bold text-slate-900 dark:text-white"
                                    >
                                        Schedule a meeting
                                    </p>
                                    <span
                                        class="text-[10px] font-normal text-slate-400"
                                        >14 May, 12:45 AM</span
                                    >
                                </div>
                                <div class="text-right">
                                    <span
                                        class="text-[10px] font-normal text-slate-400"
                                        >Duration</span
                                    >
                                    <p class="text-xs font-bold">06 h 55 m</p>
                                </div>
                                <div class="w-24">
                                    <div
                                        class="flex justify-end text-[10px] font-bold text-slate-600 dark:text-slate-300"
                                    >
                                        50%
                                    </div>
                                    <div
                                        class="h-1.5 w-full rounded-full bg-slate-100 dark:bg-slate-800"
                                    >
                                        <div
                                            class="h-1.5 rounded-full bg-[#5B50E5]"
                                            style="width: 50%"
                                        />
                                    </div>
                                </div>
                                <span
                                    class="text-[11px] font-medium text-slate-400"
                                    >📎 4 • 📅 3 June</span
                                >
                            </div>
                        </div>

                        <!-- Task 3 -->
                        <div class="space-y-1.5">
                            <div
                                class="flex items-center justify-between text-xs font-bold text-slate-800 dark:text-slate-200"
                            >
                                <div>
                                    <p
                                        class="font-bold text-slate-900 dark:text-white"
                                    >
                                        Send out reminders
                                    </p>
                                    <span
                                        class="text-[10px] font-normal text-slate-400"
                                        >21 May, 10:30 AM</span
                                    >
                                </div>
                                <div class="text-right">
                                    <span
                                        class="text-[10px] font-normal text-slate-400"
                                        >Duration</span
                                    >
                                    <p class="text-xs font-bold">01 h 30 m</p>
                                </div>
                                <div class="w-24">
                                    <div
                                        class="flex justify-end text-[10px] font-bold text-slate-600 dark:text-slate-300"
                                    >
                                        10%
                                    </div>
                                    <div
                                        class="h-1.5 w-full rounded-full bg-slate-100 dark:bg-slate-800"
                                    >
                                        <div
                                            class="h-1.5 rounded-full bg-[#5B50E5]"
                                            style="width: 10%"
                                        />
                                    </div>
                                </div>
                                <span
                                    class="text-[11px] font-medium text-slate-400"
                                    >📎 16 • 📅 3 June</span
                                >
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ================= COLUMN 2: Assignments & Sprint Velocity KPI ================= -->
            <div class="space-y-6">
                <!-- Assignments Card -->
                <div
                    class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-[#1B1E36]"
                >
                    <div class="flex items-center justify-between">
                        <h2
                            class="text-base font-bold text-slate-900 dark:text-white"
                        >
                            Assignments
                        </h2>
                        <Link
                            :href="tasks.index.url()"
                            class="text-xs font-semibold text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
                        >
                            Edit
                        </Link>
                    </div>

                    <!-- Category Filter Links -->
                    <div class="mt-4 flex items-center gap-3 text-xs font-bold">
                        <button
                            type="button"
                            class="transition-colors"
                            :class="
                                activeFilter === 'motion'
                                    ? 'font-black text-[#5B50E5] dark:text-[#8D82FF]'
                                    : 'text-slate-400 hover:text-slate-600'
                            "
                            @click="activeFilter = 'motion'"
                        >
                            Motion design
                        </button>
                        <button
                            type="button"
                            class="transition-colors"
                            :class="
                                activeFilter === 'logo'
                                    ? 'font-black text-[#5B50E5] dark:text-[#8D82FF]'
                                    : 'text-slate-400 hover:text-slate-600'
                            "
                            @click="activeFilter = 'logo'"
                        >
                            Logo
                        </button>
                    </div>

                    <!-- Featured Task Card -->
                    <div
                        class="mt-4 rounded-3xl border border-slate-100 bg-slate-50/80 p-5 shadow-xs dark:border-white/5 dark:bg-[#202444]/70"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <h3
                                class="text-sm font-bold text-slate-900 dark:text-white"
                            >
                                Design a packaging concept for a new product
                            </h3>
                            <span
                                class="rounded-full bg-rose-50 px-3 py-0.5 text-[11px] font-bold text-rose-600 dark:bg-rose-950/40 dark:text-rose-400"
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
                                <span
                                    class="text-xs font-semibold text-slate-700 dark:text-slate-300"
                                >
                                    Rachel Lee
                                </span>
                                <div
                                    class="flex h-6 w-6 items-center justify-center rounded-full bg-indigo-500 text-[10px] font-bold text-white"
                                >
                                    RL
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- + Add New Assignment Soft Lavender Pill Button -->
                    <Link
                        :href="tasks.create.url()"
                        class="mt-4 flex w-full items-center justify-center gap-2 rounded-2xl bg-[#EEEDFC] py-3 text-xs font-bold text-[#5B50E5] transition-all hover:bg-[#E2E0FA] dark:bg-[#202444] dark:text-[#8D82FF]"
                    >
                        <div
                            class="flex h-5 w-5 items-center justify-center rounded-md bg-[#5B50E5] text-white dark:bg-[#8D82FF] dark:text-slate-900"
                        >
                            <component
                                :is="getIcon('Plus')"
                                class="h-3.5 w-3.5"
                            />
                        </div>
                        <span>Add new assignment</span>
                    </Link>
                </div>

                <!-- KPI & PERFORMANCE VELOCITY CARD (Replaces Go Premium!) -->
                <div
                    class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-[#1B1E36]"
                >
                    <div class="flex items-center justify-between">
                        <h2
                            class="text-base font-bold text-slate-900 dark:text-white"
                        >
                            Sprint Velocity & KPI
                        </h2>
                        <Link
                            :href="'/reports'"
                            class="text-xs font-semibold text-[#5B50E5] hover:underline dark:text-[#8D82FF]"
                        >
                            View Reports
                        </Link>
                    </div>

                    <!-- 3 KPI Metric Cards -->
                    <div class="mt-4 grid grid-cols-3 gap-3">
                        <div
                            class="rounded-2xl border border-slate-100 bg-slate-50/80 p-3 text-center dark:border-white/5 dark:bg-[#202444]/60"
                        >
                            <span
                                class="text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                                >Velocity</span
                            >
                            <p
                                class="mt-1 text-lg font-black text-indigo-600 dark:text-indigo-400"
                            >
                                84 pts
                            </p>
                            <span class="text-[10px] font-bold text-emerald-500"
                                >↑ +12%</span
                            >
                        </div>
                        <div
                            class="rounded-2xl border border-slate-100 bg-slate-50/80 p-3 text-center dark:border-white/5 dark:bg-[#202444]/60"
                        >
                            <span
                                class="text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                                >On-Time</span
                            >
                            <p
                                class="mt-1 text-lg font-black text-emerald-600 dark:text-emerald-400"
                            >
                                92%
                            </p>
                            <span class="text-[10px] font-bold text-emerald-500"
                                >Target 90%</span
                            >
                        </div>
                        <div
                            class="rounded-2xl border border-slate-100 bg-slate-50/80 p-3 text-center dark:border-white/5 dark:bg-[#202444]/60"
                        >
                            <span
                                class="text-[10px] font-bold tracking-wider text-slate-400 uppercase"
                                >Active</span
                            >
                            <p
                                class="mt-1 text-lg font-black text-amber-600 dark:text-amber-400"
                            >
                                {{ stats?.my_tasks_count ?? 8 }}
                            </p>
                            <span
                                class="text-[10px] font-semibold text-slate-400"
                                >In Sprint</span
                            >
                        </div>
                    </div>

                    <!-- Visual Progress Distribution Bar -->
                    <div class="mt-5 space-y-2">
                        <div
                            class="flex items-center justify-between text-xs font-bold text-slate-800 dark:text-slate-200"
                        >
                            <span>Sprint Completion Rate</span>
                            <span class="text-[#5B50E5] dark:text-[#8D82FF]"
                                >85%</span
                            >
                        </div>
                        <div
                            class="flex h-3 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                        >
                            <div
                                class="bg-[#5B50E5]"
                                style="width: 60%"
                                title="Completed (60%)"
                            />
                            <div
                                class="bg-amber-400"
                                style="width: 25%"
                                title="In Progress (25%)"
                            />
                            <div
                                class="bg-indigo-300"
                                style="width: 15%"
                                title="Review (15%)"
                            />
                        </div>
                        <div
                            class="flex items-center justify-between pt-1 text-[10px] font-medium text-slate-500 dark:text-slate-400"
                        >
                            <span class="flex items-center gap-1.5"
                                ><span
                                    class="h-2 w-2 rounded-full bg-[#5B50E5]"
                                />
                                Done (60%)</span
                            >
                            <span class="flex items-center gap-1.5"
                                ><span
                                    class="h-2 w-2 rounded-full bg-amber-400"
                                />
                                Progress (25%)</span
                            >
                            <span class="flex items-center gap-1.5"
                                ><span
                                    class="h-2 w-2 rounded-full bg-indigo-300"
                                />
                                Review (15%)</span
                            >
                        </div>
                    </div>
                </div>
            </div>

            <!-- ================= COLUMN 3: Calendar, Dual Circular Gauges & Board Meeting ================= -->
            <div class="space-y-6">
                <!-- Interactive Calendar Widget (May 2021) -->
                <div
                    class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-[#1B1E36]"
                >
                    <div class="flex items-center justify-between">
                        <h2
                            class="text-base font-bold text-slate-900 dark:text-white"
                        >
                            May 2021
                        </h2>
                        <div class="flex items-center gap-1 text-slate-400">
                            <button
                                type="button"
                                class="rounded-lg p-1 hover:bg-slate-100 dark:hover:bg-slate-800"
                                aria-label="Previous"
                            >
                                <component
                                    :is="getIcon('ChevronLeft')"
                                    class="h-3.5 w-3.5"
                                />
                            </button>
                            <button
                                type="button"
                                class="rounded-lg p-1 hover:bg-slate-100 dark:hover:bg-slate-800"
                                aria-label="Next"
                            >
                                <component
                                    :is="getIcon('ChevronRight')"
                                    class="h-3.5 w-3.5"
                                />
                            </button>
                        </div>
                    </div>

                    <!-- Days Row (Active Blue Circle on Fr 18) -->
                    <div class="mt-4 flex items-center justify-between">
                        <button
                            v-for="(item, idx) in calendarDays"
                            :key="item.day"
                            type="button"
                            class="flex flex-col items-center gap-1 rounded-2xl px-2 py-1.5 transition-all"
                            :class="
                                activeDayIndex === idx
                                    ? 'bg-[#2563EB] font-bold text-white shadow-md'
                                    : 'text-slate-600 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800'
                            "
                            @click="activeDayIndex = idx"
                        >
                            <span class="text-[10px] font-medium">{{
                                item.day
                            }}</span>
                            <span class="text-xs font-bold">{{
                                item.date
                            }}</span>
                        </button>
                    </div>

                    <!-- Timeline Schedule with Dotted Connecting Line -->
                    <div class="mt-5 space-y-4">
                        <!-- Timeline Item 1 -->
                        <div>
                            <span class="text-[11px] font-bold text-slate-400"
                                >04:30-05:00 PM</span
                            >
                            <div
                                class="mt-1.5 flex items-center justify-between rounded-2xl border border-slate-100 bg-slate-50/70 p-3 dark:border-white/5 dark:bg-[#202444]/60"
                            >
                                <div class="flex items-center gap-2.5">
                                    <div
                                        class="flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-100 text-[#5B50E5] dark:bg-indigo-950 dark:text-indigo-400"
                                    >
                                        <component
                                            :is="getIcon('Users')"
                                            class="h-4 w-4"
                                        />
                                    </div>
                                    <div>
                                        <h4
                                            class="text-xs font-bold text-slate-900 dark:text-white"
                                        >
                                            Team meeting
                                        </h4>
                                        <span
                                            class="text-[10px] font-medium text-slate-400"
                                            >12:00 - 12:30 • UX/UI design</span
                                        >
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    class="text-slate-400 hover:text-slate-600"
                                    aria-label="Menu"
                                >
                                    <component
                                        :is="getIcon('MoreHorizontal')"
                                        class="h-3.5 w-3.5"
                                    />
                                </button>
                            </div>
                        </div>

                        <!-- Timeline Item 2 -->
                        <div>
                            <span class="text-[11px] font-bold text-slate-400"
                                >11:30-12:30 PM</span
                            >
                            <div
                                class="mt-1.5 flex items-center justify-between rounded-2xl border border-slate-100 bg-slate-50/70 p-3 dark:border-white/5 dark:bg-[#202444]/60"
                            >
                                <div class="flex items-center gap-2.5">
                                    <div
                                        class="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-100 text-blue-600 dark:bg-blue-950 dark:text-blue-400"
                                    >
                                        <component
                                            :is="getIcon('Calendar')"
                                            class="h-4 w-4"
                                        />
                                    </div>
                                    <div>
                                        <h4
                                            class="text-xs font-bold text-slate-900 dark:text-white"
                                        >
                                            Meeting with new client
                                        </h4>
                                        <span
                                            class="text-[10px] font-medium text-slate-400"
                                            >12:30 - 01:30 PM • Job
                                            interview</span
                                        >
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    class="text-slate-400 hover:text-slate-600"
                                    aria-label="Menu"
                                >
                                    <component
                                        :is="getIcon('MoreHorizontal')"
                                        class="h-3.5 w-3.5"
                                    />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Dual Circular Progress KPI Gauges -->
                <div
                    class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-[#1B1E36]"
                >
                    <div class="grid grid-cols-2 gap-4">
                        <!-- Ring 1 -->
                        <div class="flex flex-col items-center text-center">
                            <div
                                class="relative flex h-20 w-20 items-center justify-center"
                            >
                                <svg
                                    class="h-20 w-20 -rotate-90 transform"
                                    viewBox="0 0 36 36"
                                >
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
                                <span
                                    class="absolute text-sm font-black text-slate-900 dark:text-white"
                                >
                                    90%
                                </span>
                            </div>
                            <span
                                class="mt-2 text-[9px] font-bold tracking-wider text-emerald-600 uppercase"
                            >
                                DATA RESEARCH
                            </span>
                            <span
                                class="text-xs font-bold text-slate-900 dark:text-white"
                            >
                                Marketing
                            </span>
                            <span
                                class="mt-1 text-[10px] leading-tight font-medium text-slate-400"
                            >
                                You marked 5/5<br />All assignments are done!
                            </span>
                        </div>

                        <!-- Ring 2 -->
                        <div class="flex flex-col items-center text-center">
                            <div
                                class="relative flex h-20 w-20 items-center justify-center"
                            >
                                <svg
                                    class="h-20 w-20 -rotate-90 transform"
                                    viewBox="0 0 36 36"
                                >
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
                                <span
                                    class="absolute text-sm font-black text-slate-900 dark:text-white"
                                >
                                    65%
                                </span>
                            </div>
                            <span
                                class="mt-2 text-[9px] font-bold tracking-wider text-rose-500 uppercase"
                            >
                                UX/UI DESIGN
                            </span>
                            <span
                                class="text-xs font-bold text-slate-900 dark:text-white"
                            >
                                Typography
                            </span>
                            <span
                                class="mt-1 text-[10px] leading-tight font-medium text-slate-400"
                            >
                                You marked 3/5<br />2 assignments left
                            </span>
                            <button
                                type="button"
                                class="mt-2 rounded-full bg-[#5B50E5] px-3.5 py-0.5 text-[10px] font-bold text-white shadow-xs transition hover:bg-[#4D40E3]"
                            >
                                Check
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Board Meeting Card -->
                <div
                    class="rounded-3xl border border-slate-100 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-[#1B1E36]"
                >
                    <div class="flex items-center justify-between">
                        <h2
                            class="text-base font-bold text-slate-900 dark:text-white"
                        >
                            Board meeting
                        </h2>
                        <button
                            type="button"
                            class="text-xs font-semibold text-slate-400 hover:text-slate-600"
                        >
                            Edit
                        </button>
                    </div>

                    <div
                        class="mt-2 flex items-center gap-2 text-xs font-semibold text-slate-500 dark:text-slate-400"
                    >
                        <span class="h-2 w-2 rounded-full bg-[#5B50E5]" />
                        <span>March 24 at 4:00 PM</span>
                    </div>

                    <p
                        class="mt-1 text-xs font-medium text-slate-600 dark:text-slate-300"
                    >
                        Meeting with John Smith, 4th floor, room 159
                    </p>

                    <div class="mt-4 flex items-center gap-2">
                        <button
                            type="button"
                            class="flex-1 rounded-xl bg-slate-100 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200"
                        >
                            Reschedule
                        </button>
                        <button
                            type="button"
                            class="flex-1 rounded-xl bg-[#5B50E5] py-2 text-xs font-bold text-white shadow-xs transition hover:bg-[#4D40E3]"
                        >
                            Accept invite
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
