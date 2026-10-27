<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, watch } from 'vue';
import { usePage, Link, router } from '@inertiajs/vue3';
import { getIcon } from './icons';
import { ChevronRight } from '@lucide/vue';
import { gsap } from 'gsap';
import { prefersReducedMotion } from '@/lib/motion';
import { logout } from '@/routes';
import tasks from '@/routes/tasks';
import projects from '@/routes/projects';
import users from '@/routes/users';

interface Props {
    open: boolean;
}

interface Emits {
    close: [];
}

const props = defineProps<Props>();
defineEmits<Emits>();

const page = usePage();

const currentPath = computed(() => {
    if (typeof window !== 'undefined') {
        return window.location.pathname;
    }
    return new URL(page.url).pathname;
});

function isActive(href: string): boolean {
    if (href === '/dashboard') {
        return currentPath.value === '/dashboard';
    }
    return (
        currentPath.value === href || currentPath.value.startsWith(`${href}/`)
    );
}

const userPermissions = computed(
    () => (page.props.auth?.user as any)?.permissions || [],
);

function can(perm?: string): boolean {
    if (!perm) return true;
    return userPermissions.value.includes(perm);
}

const stats = computed(() => (page.props.stats as any) || {});

// Nav items matching the reference aesthetic with our application routes
const sidebarItems = computed(() => [
    {
        label: 'Home',
        href: '/dashboard',
        icon: 'Home',
        permission: 'dashboard.view',
        badge: 1,
    },
    {
        label: 'Live Orders',
        href: tasks.index.url(),
        icon: 'CheckSquare',
        permission: 'tasks.view',
        badge: stats.value.my_tasks_count ?? 1,
    },
    {
        label: 'Projects',
        href: projects.index.url(),
        icon: 'FolderKanban',
        permission: 'projects.view',
        badge: stats.value.projects_count ?? 1,
    },
    {
        label: 'Team Members',
        href: can('users.view') ? users.index.url() : '/teams',
        icon: 'Users',
        permission: can('users.view') ? 'users.view' : 'teams.view',
        badge: stats.value.team_members_count ?? 1,
    },
    {
        label: 'Calendar',
        href: '/calendar',
        icon: 'Calendar',
        permission: 'calendar.view',
        badge: 1,
    },
    {
        label: 'Stock / Activity',
        href: '/activity-log',
        icon: 'Activity',
        permission: 'activity-log.view',
        badge: 1,
    },
    {
        label: 'Reports',
        href: '/reports',
        icon: 'BarChart3',
        permission: 'reports.view',
        badge: 1,
    },
    {
        label: 'Settings',
        href: '/profile',
        icon: 'Settings',
        badge: 1,
    },
]);

const allowedItems = computed(() =>
    sidebarItems.value.filter((item) => can(item.permission)),
);

const bottomItems = computed(() => [
    {
        label: 'Support',
        href: '#support',
        icon: 'Headphones',
        badge: 1,
        isAction: true,
    },
    {
        label: 'Keluar',
        href: '#logout',
        icon: 'LogOut',
        badge: 1,
        isLogout: true,
    },
]);

const MOBILE_BREAKPOINT = 1024;
const SIDEBAR_WIDTH = 260;

let drawerCtx: gsap.Context | null = null;

function drawer(): HTMLElement | null {
    return document.querySelector<HTMLElement>('[data-sidebar]');
}

function isMobile(): boolean {
    return (
        typeof window !== 'undefined' && window.innerWidth < MOBILE_BREAKPOINT
    );
}

onMounted(() => {
    drawerCtx = gsap.context(() => {
        if (isMobile()) {
            gsap.set(drawer(), { x: -SIDEBAR_WIDTH });
        }
    });
});

onBeforeUnmount(() => {
    drawerCtx?.revert();
    drawerCtx = null;
});

watch(
    () => props.open,
    (open) => {
        if (!isMobile()) return;

        drawerCtx?.revert();

        if (prefersReducedMotion()) {
            gsap.set(drawer(), { x: open ? 0 : -SIDEBAR_WIDTH });
            return;
        }

        drawerCtx = gsap.context(() => {
            gsap.to(drawer(), {
                x: open ? 0 : -SIDEBAR_WIDTH,
                duration: open ? 0.32 : 0.24,
                ease: open ? 'power3.out' : 'power2.in',
            });
        });
    },
);

function handleLogout(): void {
    router.post(logout.url(), {}, { preserveScroll: true });
}
</script>

<template>
    <aside
        data-sidebar
        class="fixed inset-y-0 left-0 z-50 flex h-full w-[260px] flex-col justify-between border-r border-slate-100 bg-white p-5 transition-transform duration-300 lg:static lg:z-10 lg:w-[250px] lg:shrink-0 lg:translate-x-0 dark:border-white/5 dark:bg-[#151829]"
        aria-label="Navigasi Utama"
    >
        <!-- Top: Brand Logo Section -->
        <div>
            <div class="flex items-center justify-between pb-6">
                <Link
                    href="/dashboard"
                    class="flex items-center gap-3 transition hover:opacity-90"
                >
                    <!-- Two-tone Pie / Circle Logo (Exact Reference Match) -->
                    <div
                        class="relative flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-[#0D0E12] shadow-xs dark:bg-white"
                    >
                        <!-- Top-Right Neon Lime Wedge -->
                        <div
                            class="absolute top-0 right-0 h-4.5 w-4.5 rounded-bl-full bg-[#D2F87A]"
                        />
                    </div>
                    <div class="flex flex-col">
                        <span
                            class="text-xl font-black tracking-tight text-slate-900 dark:text-white"
                        >
                            {{ page.props.app?.name || 'Vendify' }}
                        </span>
                    </div>
                </Link>

                <!-- Mobile Close Button -->
                <button
                    type="button"
                    class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-600 lg:hidden dark:text-slate-500 dark:hover:bg-white/5"
                    @click="$emit('close')"
                    aria-label="Tutup menu"
                >
                    <component :is="getIcon('X')" class="h-5 w-5" />
                </button>
            </div>

            <!-- Navigation Items List -->
            <nav class="flex flex-col gap-1 pt-1">
                <Link
                    v-for="item in allowedItems"
                    :key="item.href"
                    :href="item.href"
                    class="group flex items-center justify-between rounded-full px-4 py-2.5 text-[13px] transition-all duration-200"
                    :class="
                        isActive(item.href)
                            ? 'bg-[#0D0E12] font-bold text-white shadow-sm dark:bg-white dark:text-slate-900'
                            : 'font-medium text-slate-600 hover:bg-slate-100/70 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-white/5 dark:hover:text-white'
                    "
                    @click="isMobile() ? $emit('close') : undefined"
                >
                    <div class="flex items-center gap-3">
                        <component
                            :is="getIcon(item.icon)"
                            class="h-4.5 w-4.5 stroke-[2.2] transition-colors"
                            :class="
                                isActive(item.href)
                                    ? 'text-white dark:text-slate-900'
                                    : 'text-slate-400 group-hover:text-slate-700 dark:text-slate-500 dark:group-hover:text-slate-200'
                            "
                        />
                        <span>{{ item.label }}</span>
                    </div>

                    <!-- Right Count & Chevron (e.g. "1 >") -->
                    <div class="flex items-center gap-1.5">
                        <span
                            class="text-xs font-semibold"
                            :class="
                                isActive(item.href)
                                    ? 'text-white/80 dark:text-slate-700'
                                    : 'text-slate-400 group-hover:text-slate-600 dark:text-slate-500'
                            "
                        >
                            {{ item.badge }}
                        </span>
                        <ChevronRight
                            class="h-3.5 w-3.5 stroke-[2.5]"
                            :class="
                                isActive(item.href)
                                    ? 'text-white/70 dark:text-slate-500'
                                    : 'text-slate-300 group-hover:text-slate-500 dark:text-slate-600'
                            "
                        />
                    </div>
                </Link>
            </nav>
        </div>

        <!-- Bottom: Support & Keluar Items -->
        <div class="border-t border-slate-100 pt-4 dark:border-white/5">
            <div class="flex flex-col gap-1">
                <template v-for="item in bottomItems" :key="item.label">
                    <button
                        v-if="item.isLogout"
                        type="button"
                        class="group flex w-full items-center justify-between rounded-full px-4 py-2.5 text-[13px] font-medium text-slate-600 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:text-slate-400 dark:hover:bg-rose-500/10 dark:hover:text-rose-300"
                        @click="handleLogout"
                    >
                        <div class="flex items-center gap-3">
                            <component
                                :is="getIcon(item.icon)"
                                class="h-4.5 w-4.5 stroke-[2.2] text-slate-400 group-hover:text-rose-500 dark:text-slate-500 dark:group-hover:text-rose-400"
                            />
                            <span>{{ item.label }}</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span
                                class="text-xs font-semibold text-slate-400 group-hover:text-rose-500 dark:text-slate-500"
                            >
                                {{ item.badge }}
                            </span>
                            <ChevronRight
                                class="h-3.5 w-3.5 stroke-[2.5] text-slate-300 group-hover:text-rose-400 dark:text-slate-600"
                            />
                        </div>
                    </button>

                    <a
                        v-else
                        :href="item.href"
                        class="group flex items-center justify-between rounded-full px-4 py-2.5 text-[13px] font-medium text-slate-600 transition-colors hover:bg-slate-100/70 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-white/5 dark:hover:text-white"
                    >
                        <div class="flex items-center gap-3">
                            <component
                                :is="getIcon(item.icon)"
                                class="h-4.5 w-4.5 stroke-[2.2] text-slate-400 group-hover:text-slate-700 dark:text-slate-500 dark:group-hover:text-slate-200"
                            />
                            <span>{{ item.label }}</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span
                                class="text-xs font-semibold text-slate-400 group-hover:text-slate-600 dark:text-slate-500"
                            >
                                {{ item.badge }}
                            </span>
                            <ChevronRight
                                class="h-3.5 w-3.5 stroke-[2.5] text-slate-300 group-hover:text-slate-500 dark:text-slate-600"
                            />
                        </div>
                    </a>
                </template>
            </div>
        </div>
    </aside>
</template>
