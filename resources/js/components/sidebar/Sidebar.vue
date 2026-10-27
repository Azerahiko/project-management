<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, watch } from 'vue';
import { usePage, Link, router } from '@inertiajs/vue3';
import { getIcon } from './icons';
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

const unreadNotifications = computed(() => {
    return (page.props.stats as any)?.notifications_unread ?? 0;
});

// The exact 8 icon items matching the reference photo
const sidebarItems = computed(() => [
    {
        label: 'Dashboard',
        href: '/dashboard',
        icon: 'LayoutDashboard',
        permission: 'dashboard.view',
    },
    {
        label: 'Users / Teams',
        href: can('users.view') ? users.index.url() : '/teams',
        icon: 'User',
        permission: can('users.view') ? 'users.view' : 'teams.view',
    },
    {
        label: 'Reports & KPI',
        href: '/reports',
        icon: 'BarChart3',
        permission: 'reports.view',
        isPro: true,
    },
    {
        label: 'Notifications',
        href: '/notifications',
        icon: 'Bell',
        permission: 'notifications.view',
        hasDot: unreadNotifications.value > 0,
    },
    {
        label: 'Calendar',
        href: '/calendar',
        icon: 'Calendar',
        permission: 'calendar.view',
    },
    {
        label: 'Documents',
        href: '/documents',
        icon: 'FileText',
        permission: 'documents.view',
    },
    {
        label: 'Tasks',
        href: tasks.index.url(),
        icon: 'CheckSquare',
        permission: 'tasks.view',
    },
    {
        label: 'Activity Log',
        href: '/activity-log',
        icon: 'Activity',
        permission: 'activity-log.view',
    },
]);

const allowedItems = computed(() =>
    sidebarItems.value.filter((item) => can(item.permission)),
);

const MOBILE_BREAKPOINT = 1024;
const SIDEBAR_WIDTH = 260;

let drawerCtx: gsap.Context | null = null;

function drawer(): HTMLElement | null {
    return document.querySelector<HTMLElement>('[data-sidebar]');
}

function isMobile(): boolean {
    return typeof window !== 'undefined' && window.innerWidth < MOBILE_BREAKPOINT;
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
    <!-- Curved Purple Dock Sidebar (Exact Match to Reference Photo) -->
    <aside
        data-sidebar
        class="dock-indigo fixed inset-y-0 left-0 z-50 flex h-full w-[260px] flex-col justify-between py-6 px-3 shadow-2xl transition-transform duration-300 lg:static lg:z-30 lg:h-auto lg:w-[84px] lg:translate-x-0 lg:rounded-r-[36px] lg:shadow-none"
        aria-label="Navigasi Utama"
    >
        <!-- Top Dock Container -->
        <div class="flex flex-col items-center w-full">
            <!-- Mobile Close Button -->
            <div class="flex w-full items-center justify-between pb-4 lg:hidden">
                <span class="text-xs font-bold text-white tracking-wider uppercase">Menu</span>
                <button
                    type="button"
                    class="rounded-xl p-1.5 text-white/80 hover:bg-white/10 hover:text-white"
                    @click="$emit('close')"
                >
                    <component :is="getIcon('X')" class="h-5 w-5" />
                </button>
            </div>

            <!-- Vertical Stack of 8 Iconic Buttons (Matches Photo Exactly) -->
            <nav class="flex w-full flex-col items-center gap-3 pt-2">
                <Link
                    v-for="item in allowedItems"
                    :key="item.href"
                    :href="item.href"
                    class="group relative flex w-full items-center gap-3.5 rounded-2xl p-3 transition-all duration-200 lg:w-12 lg:h-12 lg:justify-center lg:p-0"
                    :class="
                        isActive(item.href)
                            ? 'bg-white text-[#5B50E5] shadow-lg shadow-indigo-950/20 font-bold dark:bg-[#1E2245] dark:text-[#8D82FF] scale-105'
                            : 'text-white/70 hover:bg-white/15 hover:text-white dark:hover:bg-white/10'
                    "
                    @click="isMobile() ? $emit('close') : undefined"
                >
                    <div class="relative flex items-center justify-center">
                        <component
                            :is="getIcon(item.icon)"
                            class="h-5 w-5 transition-transform duration-200 group-hover:scale-110"
                        />

                        <!-- PRO Badge (Green pill matching reference) -->
                        <span
                            v-if="item.isPro"
                            class="absolute -top-2 -right-2.5 rounded-full bg-emerald-400 px-1 py-0.2 text-[8px] font-black tracking-wider text-slate-900 uppercase shadow-xs"
                        >
                            PRO
                        </span>

                        <!-- Notification Amber Dot -->
                        <span
                            v-if="item.hasDot"
                            class="absolute -top-1 -right-1 flex h-2.5 w-2.5 rounded-full bg-amber-400 ring-2 ring-[#5B50E5] dark:ring-[#2A2375]"
                        />
                    </div>

                    <!-- Label (Visible on mobile, floating tooltip on desktop) -->
                    <span class="text-sm font-medium lg:hidden">{{ item.label }}</span>

                    <!-- Desktop Floating Pill Tooltip -->
                    <div
                        class="pointer-events-none absolute left-full ml-3 hidden whitespace-nowrap rounded-xl bg-slate-900/90 px-3 py-1.5 text-xs font-semibold text-white shadow-xl backdrop-blur-sm transition-all group-hover:block dark:bg-slate-800/95 z-50"
                    >
                        {{ item.label }}
                    </div>
                </Link>
            </nav>
        </div>

        <!-- Bottom: Quick Logout / Refresh -->
        <div class="flex flex-col items-center w-full pt-4 border-t border-white/15 lg:border-t-0">
            <button
                type="button"
                class="group relative flex w-full items-center gap-3.5 rounded-2xl p-3 text-white/70 transition-all duration-200 hover:bg-rose-500/20 hover:text-rose-200 lg:w-12 lg:h-12 lg:justify-center lg:p-0"
                title="Keluar"
                @click="handleLogout"
            >
                <component
                    :is="getIcon('LogOut')"
                    class="h-5 w-5 transition-transform duration-200 group-hover:scale-110"
                />
                <span class="text-sm font-medium lg:hidden">Keluar</span>

                <!-- Desktop Tooltip -->
                <div
                    class="pointer-events-none absolute left-full ml-3 hidden whitespace-nowrap rounded-xl bg-rose-900/90 px-3 py-1.5 text-xs font-semibold text-white shadow-xl backdrop-blur-sm transition-all group-hover:block z-50"
                >
                    Keluar
                </div>
            </button>
        </div>
    </aside>
</template>
