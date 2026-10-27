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
const user = computed(() => page.props.auth?.user);

// Reactive URL tracking
const currentPath = computed(() => {
    const rawUrl = page.url || '';
    return rawUrl.split('?')[0].split('#')[0];
});

function isActive(href: string): boolean {
    const path = currentPath.value;
    const cleanHref = href.split('?')[0].split('#')[0];

    if (cleanHref === '/dashboard' || cleanHref === '/') {
        return path === '/dashboard' || path === '/';
    }

    return path === cleanHref || path.startsWith(`${cleanHref}/`);
}

const userPermissions = computed(
    () => (page.props.auth?.user as any)?.permissions || [],
);

function can(perm?: string): boolean {
    if (!perm) return true;
    return userPermissions.value.includes(perm);
}

const navItems = computed(() => [
    {
        label: 'Dashboard',
        href: '/dashboard',
        icon: 'LayoutDashboard',
        permission: 'dashboard.view',
    },
    {
        label: 'Tasks',
        href: tasks.index.url(),
        icon: 'CheckSquare',
        permission: 'tasks.view',
    },
    {
        label: 'Projects',
        href: projects.index.url(),
        icon: 'FolderKanban',
        permission: 'projects.view',
    },
    {
        label: 'Team Members',
        href: can('users.view') ? users.index.url() : '/teams',
        icon: 'Users',
        permission: can('users.view') ? 'users.view' : 'teams.view',
    },
    {
        label: 'Calendar',
        href: '/calendar',
        icon: 'Calendar',
        permission: 'calendar.view',
    },
    {
        label: 'Reports',
        href: '/reports',
        icon: 'BarChart3',
        permission: 'reports.view',
    },
    {
        label: 'Settings',
        href: '/settings',
        icon: 'Settings',
        permission: 'settings.view',
    },
]);

const allowedItems = computed(() =>
    navItems.value.filter((item) => can(item.permission)),
);

function handleLogout(): void {
    router.post(logout.url(), {}, { preserveScroll: true });
}

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
</script>

<template>
    <aside
        data-sidebar
        class="fixed inset-y-0 left-0 z-50 flex h-full w-64 shrink-0 flex-col justify-between border-r border-slate-200/80 bg-white p-6 transition-transform duration-300 lg:sticky lg:top-0 lg:z-30 lg:h-screen lg:translate-x-0 dark:border-white/10 dark:bg-[#121524]"
        aria-label="Navigasi Utama"
    >
        <!-- Top: Brand Header & Menu -->
        <div class="flex flex-col">
            <!-- Brand Logo -->
            <div class="flex items-center justify-between pb-8">
                <Link
                    href="/dashboard"
                    class="flex items-center gap-3 transition hover:opacity-90"
                >
                    <img
                        src="/images/logo-mark.png"
                        alt="DigitalCode"
                        class="h-9 w-9 object-contain drop-shadow-xs"
                    />
                    <div class="flex flex-col">
                        <span
                            class="text-lg font-black tracking-tight text-slate-900 dark:text-white"
                        >
                            DigitalCode
                        </span>
                        <span
                            class="text-[11px] font-medium text-slate-400 dark:text-slate-500"
                        >
                            Project Management
                        </span>
                    </div>
                </Link>

                <!-- Mobile Close Button -->
                <button
                    type="button"
                    class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 lg:hidden dark:text-slate-500 dark:hover:bg-white/5 dark:hover:text-white"
                    @click="$emit('close')"
                    aria-label="Tutup menu"
                >
                    <component :is="getIcon('X')" class="h-5 w-5" />
                </button>
            </div>

            <!-- Navigation Links -->
            <nav class="flex flex-col gap-1.5">
                <Link
                    v-for="item in allowedItems"
                    :key="item.href"
                    :href="item.href"
                    class="group flex items-center gap-3.5 rounded-xl px-3.5 py-2.5 text-sm font-semibold transition-all duration-200"
                    :class="
                        isActive(item.href)
                            ? 'bg-[#5B50E5] text-white shadow-sm shadow-indigo-500/20'
                            : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-white/5 dark:hover:text-white'
                    "
                    @click="isMobile() ? $emit('close') : undefined"
                >
                    <component
                        :is="getIcon(item.icon)"
                        class="h-5 w-5 stroke-[2] transition-colors"
                        :class="
                            isActive(item.href)
                                ? 'text-white'
                                : 'text-slate-400 group-hover:text-slate-700 dark:text-slate-500 dark:group-hover:text-slate-200'
                        "
                    />
                    <span>{{ item.label }}</span>
                </Link>
            </nav>
        </div>

        <!-- Bottom: User Profile & Logout -->
        <div class="border-t border-slate-100 pt-4 dark:border-white/10">
            <div
                v-if="user"
                class="flex items-center justify-between gap-3 rounded-2xl bg-slate-50 p-2.5 dark:bg-white/5"
            >
                <div class="flex min-w-0 items-center gap-2.5">
                    <img
                        v-if="user.avatar"
                        :src="user.avatar"
                        :alt="user.name"
                        class="h-8 w-8 rounded-full object-cover"
                    />
                    <div
                        v-else
                        class="flex h-8 w-8 items-center justify-center rounded-full bg-[#5B50E5] text-xs font-bold text-white"
                    >
                        {{
                            user.initials || user.name.slice(0, 2).toUpperCase()
                        }}
                    </div>
                    <div class="flex min-w-0 flex-col">
                        <span
                            class="truncate text-xs font-bold text-slate-900 dark:text-white"
                        >
                            {{ user.name }}
                        </span>
                        <span
                            class="truncate text-[10px] font-medium text-slate-400 dark:text-slate-500"
                        >
                            {{ user.roles?.[0] || 'Team Member' }}
                        </span>
                    </div>
                </div>

                <!-- Logout Icon Button -->
                <button
                    type="button"
                    class="rounded-xl p-1.5 text-slate-400 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10 dark:hover:text-rose-400"
                    title="Keluar"
                    @click="handleLogout"
                >
                    <component
                        :is="getIcon('LogOut')"
                        class="h-4 w-4 stroke-[2.2]"
                    />
                </button>
            </div>
        </div>
    </aside>
</template>
