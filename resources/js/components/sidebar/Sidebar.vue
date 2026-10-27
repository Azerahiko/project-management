<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, watch } from 'vue';
import { usePage, Link, router } from '@inertiajs/vue3';
import { getIcon } from './icons';
import { gsap } from 'gsap';
import { prefersReducedMotion } from '@/lib/motion';
import { logout } from '@/routes';
import tasks from '@/routes/tasks';
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

// The exact 8 icon buttons matching the reference photo
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
    <!-- Desktop: Floating Curved Wave Wing Dock (Matching Reference Photo Pixel-for-Pixel) -->
    <!-- Mobile: Slide-over Drawer -->
    <aside
        data-sidebar
        class="fixed inset-y-0 left-0 z-50 flex h-full w-[260px] flex-col justify-between bg-[#5B50E5] p-5 shadow-2xl transition-transform duration-300 lg:sticky lg:top-24 lg:z-30 lg:h-[530px] lg:w-[84px] lg:translate-x-0 lg:bg-transparent lg:p-0 lg:shadow-none"
        aria-label="Navigasi Utama"
    >
        <!-- Desktop SVG Curved Wave Background (S-curves matching photo) -->
        <svg
            class="pointer-events-none absolute inset-0 -z-10 hidden h-full w-full drop-shadow-[0_10px_25px_rgba(91,80,229,0.35)] lg:block"
            viewBox="0 0 88 530"
            fill="none"
            preserveAspectRatio="none"
            aria-hidden="true"
        >
            <path
                d="M 0 0 C 0 35, 30 55, 88 80 L 88 450 C 30 475, 0 495, 0 530 Z"
                fill="#5B50E5"
                class="dark:fill-[#2D257A]"
            />
        </svg>

        <!-- Mobile Drawer Header -->
        <div class="flex w-full items-center justify-between pb-4 lg:hidden">
            <span class="text-xs font-black tracking-wider text-white uppercase"
                >Menu</span
            >
            <button
                type="button"
                class="rounded-xl p-1.5 text-white/80 hover:bg-white/10 hover:text-white"
                @click="$emit('close')"
            >
                <component :is="getIcon('X')" class="h-5 w-5" />
            </button>
        </div>

        <!-- 8 Iconic Buttons (Centered in the vertical dock) -->
        <nav
            class="flex flex-1 flex-col items-center justify-center gap-2.5 px-2 py-4 lg:gap-2.5 lg:pt-[76px] lg:pb-[76px]"
        >
            <Link
                v-for="item in allowedItems"
                :key="item.href"
                :href="item.href"
                class="group relative flex w-full items-center gap-3.5 rounded-2xl p-2.5 transition-all duration-200 lg:h-11 lg:w-11 lg:justify-center lg:p-0"
                :class="
                    isActive(item.href)
                        ? 'scale-105 bg-white font-bold text-[#5B50E5] shadow-lg shadow-indigo-950/25 dark:bg-[#1E2245] dark:text-[#8D82FF]'
                        : 'text-white/80 hover:bg-white/15 hover:text-white dark:hover:bg-white/10'
                "
                @click="isMobile() ? $emit('close') : undefined"
            >
                <div class="relative flex items-center justify-center">
                    <component
                        :is="getIcon(item.icon)"
                        class="h-5 w-5 transition-transform duration-200 group-hover:scale-110"
                    />

                    <!-- Green PRO Pill Badge -->
                    <span
                        v-if="item.isPro"
                        class="py-0.2 absolute -top-1.5 -right-2.5 rounded-full bg-emerald-400 px-1 text-[8px] font-black tracking-wider text-slate-900 uppercase shadow-xs"
                    >
                        PRO
                    </span>

                    <!-- Amber Notification Dot -->
                    <span
                        v-if="item.hasDot"
                        class="absolute -top-0.5 -right-0.5 flex h-2.5 w-2.5 rounded-full bg-amber-400 ring-2 ring-[#5B50E5] dark:ring-[#2D257A]"
                    />
                </div>

                <!-- Label (Visible on mobile drawer) -->
                <span class="text-sm font-semibold text-white lg:hidden">{{
                    item.label
                }}</span>

                <!-- Desktop Floating Pill Tooltip -->
                <div
                    class="pointer-events-none absolute left-full z-50 ml-3 hidden rounded-xl bg-slate-900/90 px-3 py-1.5 text-xs font-semibold whitespace-nowrap text-white shadow-xl backdrop-blur-sm transition-all group-hover:block dark:bg-slate-800/95"
                >
                    {{ item.label }}
                </div>
            </Link>
        </nav>

        <!-- Mobile Drawer Bottom Logout -->
        <div class="border-t border-white/15 pt-4 lg:hidden">
            <button
                type="button"
                class="flex w-full items-center gap-3 rounded-2xl p-2.5 text-white/80 transition-colors hover:bg-rose-500/20 hover:text-rose-200"
                @click="handleLogout"
            >
                <component :is="getIcon('LogOut')" class="h-5 w-5" />
                <span class="text-sm font-semibold">Keluar</span>
            </button>
        </div>
    </aside>
</template>
