<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { getIcon } from '@/components/sidebar/icons';
import { prefersReducedMotion } from '@/lib/motion';
import { gsap } from 'gsap';
import projects from '@/routes/projects';
import tasks from '@/routes/tasks';
import ProjectProgressCard from '@/components/dashboard/ProjectProgressCard.vue';
import TasksStatusChart from '@/components/dashboard/TasksStatusChart.vue';
import UpcomingDeadlines from '@/components/dashboard/UpcomingDeadlines.vue';
import RecentActivityFeed from '@/components/dashboard/RecentActivityFeed.vue';
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
const user = page.props.auth?.user;
const stats = page.props.stats as DashboardStats | undefined;
const tasksByStatus = page.props.tasks_by_status as
    | Partial<Record<TaskStatus, number>>
    | undefined;
const upcomingDeadlines = page.props.upcoming_deadlines as
    | UpcomingDeadline[]
    | undefined;
const recentActivities = page.props.recent_activities as
    | RecentActivity[]
    | undefined;
const projectProgress = page.props.project_progress as
    | ProjectProgress[]
    | undefined;

const cardsRef = ref<HTMLElement[]>([]);

let ctx: gsap.Context | null = null;

function animateEntrance(): void {
    if (!cardsRef.value.length) return;

    ctx?.revert();

    if (prefersReducedMotion()) {
        cardsRef.value.forEach((el) => {
            gsap.set(el, { opacity: 1, y: 0 });
        });
        return;
    }

    ctx = gsap.context(() => {
        gsap.fromTo(
            cardsRef.value,
            { opacity: 0, y: 16 },
            {
                opacity: 1,
                y: 0,
                duration: 0.4,
                ease: 'power2.out',
                stagger: 0.08,
            },
        );
    }, cardsRef.value);
}

onMounted(() => {
    animateEntrance();
});

onBeforeUnmount(() => {
    ctx?.revert();
    ctx = null;
});

const statCards = [
    {
        label: 'Proyek Aktif',
        icon: 'FolderKanban',
        iconBg: 'bg-primary-100 dark:bg-primary-900/30',
        iconColor: 'text-primary-600 dark:text-primary-400',
        value: stats?.projects_count ?? 0,
        url: projects.index.url(),
    },
    {
        label: 'Tugas Saya',
        icon: 'CheckSquare',
        iconBg: 'bg-yellow-100 dark:bg-yellow-900/30',
        iconColor: 'text-yellow-600 dark:text-yellow-400',
        value: stats?.my_tasks_count ?? 0,
        url: tasks.index.url(),
    },
    {
        label: 'Terlewat',
        icon: 'AlertTriangle',
        iconBg: 'bg-red-100 dark:bg-red-900/30',
        iconColor: 'text-red-600 dark:text-red-400',
        value: stats?.overdue_count ?? 0,
        url: tasks.index.url({ query: { status: 'todo' } }),
    },
    {
        label: 'Anggota Tim',
        icon: 'Users',
        iconBg: 'bg-green-100 dark:bg-green-900/30',
        iconColor: 'text-green-600 dark:text-green-400',
        value: stats?.team_members_count ?? 0,
        url: null,
    },
    {
        label: 'Notifikasi',
        icon: 'Bell',
        iconBg: 'bg-purple-100 dark:bg-purple-900/30',
        iconColor: 'text-purple-600 dark:text-purple-400',
        value: stats?.notifications_unread ?? 0,
        url: '/notifications', // No typed route for notifications in index.ts
    },
];

function navigateTo(url: string | null): void {
    if (url) {
        router.visit(url);
    }
}
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1
                    class="text-2xl font-bold text-surface-900 dark:text-surface-100"
                >
                    Selamat datang, {{ user?.name }}
                </h1>
                <p class="mt-1 text-surface-600 dark:text-surface-400">
                    Ringkasan aktivitas proyek Anda
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-5">
            <div
                v-for="card in statCards"
                :key="card.label"
                ref="cardsRef"
                class="card-hover cursor-pointer"
                @click="navigateTo(card.url)"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <p
                            class="text-sm font-medium text-surface-500 dark:text-surface-400"
                        >
                            {{ card.label }}
                        </p>
                        <p
                            class="mt-1 text-2xl font-bold text-surface-900 dark:text-surface-100"
                        >
                            {{ card.value }}
                        </p>
                    </div>
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-lg"
                        :class="card.iconBg"
                    >
                        <component
                            :is="getIcon(card.icon)"
                            class="h-6 w-6"
                            :class="card.iconColor"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <TasksStatusChart :tasks_by_status="tasksByStatus ?? {}" />

            <UpcomingDeadlines :upcoming_deadlines="upcomingDeadlines ?? []" />
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <ProjectProgressCard :project_progress="projectProgress ?? []" />

            <RecentActivityFeed :recent_activities="recentActivities ?? []" />
        </div>
    </div>
</template>
