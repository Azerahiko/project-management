<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, watch } from 'vue';
import { usePage, Link, router } from '@inertiajs/vue3';
import { getIcon } from './icons';
import { ChevronRight, ChevronsUpDown, LogOut, Search } from '@lucide/vue';
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

const stats = computed(() => (page.props.stats as any) || {});

// Professional Grouped Navigation
const navigationSections = computed(() => [
    {
        title: 'OVERVIEW',
        items: [
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
                badge: stats.value.my_tasks_count,
            },
            {
                label: 'Projects',
                href: projects.index.url(),
                icon: 'FolderKanban',
                permission: 'projects.view',
                badge: stats.value.projects_count,
            },
            {
                label: 'Calendar',
                href: '/calendar',
                icon: 'Calendar',
                permission: 'calendar.view',
            },
        ],
    },
    {
        title: 'COLLABORATION',
        items: [
            {
                label: 'Team Members',
                href: can('users.view') ? users.index.url() : '/teams',
                icon: 'Users',
                permission: can('users.view') ? 'users.view' : 'teams.view',
                badge: stats.value.team_members_count,
            },
            {
                label: 'Reports & KPI',
                href: '/reports',
                icon: 'BarChart3',
                permission: 'reports.view',
            },
            {
                label: 'Activity Log',
                href: '/activity-log',
                icon: 'Activity',
                permission: 'activity-log.view',
            },
        ],
    },
    {
        title: 'PREFERENCES',
        items: [
            {
                label: 'Settings',
                href: '/settings',
                icon: 'Settings',
                permission: 'settings.view',
            },
        ],
    },
]);

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
        class="fixed inset-y-0 left-0 z-50 flex h-full w-[260px] shrink-0 flex-col justify-between border-r border-slate-200/70 bg-white px-4 py-5 shadow-2xs transition-transform duration-300 select-none lg:sticky lg:top-0 lg:z-30 lg:h-screen lg:translate-x-0 dark:border-white/10 dark:bg-[#0F111E]"
        aria-label="Navigasi Utama"
    >
        <!-- Top: Workspace Brand Switcher + Search + Navigation Groups -->
        <div class="flex flex-col gap-5">
            <!-- 1. Workspace Header / Brand Switcher (Linear / Stripe Style) -->
            <div
                class="flex items-center justify-between rounded-2xl border border-slate-200/80 bg-slate-50/80 p-2.5 transition-colors hover:border-slate-300 dark:border-white/10 dark:bg-white/[0.04] dark:hover:border-white/20"
            >
                <Link
                    href="/dashboard"
                    class="flex min-w-0 flex-1 items-center gap-2.5"
                >
                    <!-- Brand Logo Tile -->
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-900 p-1.5 shadow-2xs dark:bg-white"
                    >
                        <img
                            src="/images/logo-mark.png"
                            alt="DigitalCode"
                            class="h-full w-full object-contain"
                        />
                    </div>

                    <!-- Brand Details -->
                    <div class="flex min-w-0 flex-1 flex-col">
                        <div class="flex items-center gap-1.5">
                            <span
                                class="truncate text-sm font-black tracking-tight text-slate-900 dark:text-white"
                            >
                                DigitalCode
                            </span>
                            <span
                                class="rounded bg-indigo-50 px-1 py-0.5 text-[9px] font-black tracking-wider text-indigo-600 dark:bg-indigo-500/20 dark:text-indigo-400"
                            >
                                PRO
                            </span>
                        </div>
                        <span
                            class="truncate text-[10.5px] font-medium text-slate-400 dark:text-slate-500"
                        >
                            Project Workspace
                        </span>
                    </div>
                </Link>

                <!-- Subtle Chevrons indicator -->
                <div class="text-slate-400 dark:text-slate-500">
                    <ChevronsUpDown class="h-4 w-4" />
                </div>
            </div>

            <!-- 2. Grouped Navigation Links -->
            <div class="flex flex-col gap-5 overflow-y-auto pr-1">
                <div
                    v-for="section in navigationSections"
                    :key="section.title"
                    class="flex flex-col gap-1"
                >
                    <!-- Section Header Label -->
                    <div
                        class="px-3 pb-1 text-[10px] font-black tracking-wider text-slate-400 uppercase dark:text-slate-500"
                    >
                        {{ section.title }}
                    </div>

                    <!-- Section Items -->
                    <template v-for="item in section.items" :key="item.href">
                        <Link
                            v-if="can(item.permission)"
                            :href="item.href"
                            class="group relative flex items-center justify-between rounded-xl px-3 py-2 text-[13px] font-semibold transition-all duration-150"
                            :class="
                                isActive(item.href)
                                    ? 'bg-[#5B50E5] text-white shadow-sm shadow-indigo-500/25 dark:bg-[#5B50E5]'
                                    : 'text-slate-600 hover:bg-slate-100/90 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-white/5 dark:hover:text-white'
                            "
                            @click="isMobile() ? $emit('close') : undefined"
                        >
                            <div class="flex items-center gap-2.5">
                                <component
                                    :is="getIcon(item.icon)"
                                    class="h-4 w-4 stroke-[2.2] transition-colors"
                                    :class="
                                        isActive(item.href)
                                            ? 'text-white'
                                            : 'text-slate-400 group-hover:text-slate-700 dark:text-slate-500 dark:group-hover:text-slate-200'
                                    "
                                />
                                <span class="tracking-tight">{{
                                    item.label
                                }}</span>
                            </div>

                            <!-- Optional Badge (Counts for tasks/projects) -->
                            <div
                                v-if="
                                    item.badge !== undefined &&
                                    item.badge !== null
                                "
                                class="flex items-center"
                            >
                                <span
                                    class="rounded-full px-2 py-0.5 text-[10px] font-bold"
                                    :class="
                                        isActive(item.href)
                                            ? 'bg-white/20 text-white'
                                            : 'bg-slate-100 text-slate-500 group-hover:bg-slate-200/80 group-hover:text-slate-700 dark:bg-white/10 dark:text-slate-400'
                                    "
                                >
                                    {{ item.badge }}
                                </span>
                            </div>
                        </Link>
                    </template>
                </div>
            </div>
        </div>

        <!-- Bottom: User Profile Section & Quick Actions -->
        <div class="border-slate-150 border-t pt-3 dark:border-white/10">
            <div
                v-if="user"
                class="flex items-center justify-between gap-2 rounded-2xl bg-slate-50/80 p-2 transition-colors dark:bg-white/[0.04]"
            >
                <div class="flex min-w-0 items-center gap-2.5">
                    <!-- User Avatar with Online Dot -->
                    <div class="relative shrink-0">
                        <img
                            v-if="user.avatar"
                            :src="user.avatar"
                            :alt="user.name"
                            class="h-8 w-8 rounded-full object-cover ring-1 ring-slate-200 dark:ring-white/20"
                        />
                        <div
                            v-else
                            class="flex h-8 w-8 items-center justify-center rounded-full bg-[#5B50E5] text-[11px] font-bold text-white shadow-2xs"
                        >
                            {{
                                user.initials ||
                                user.name.slice(0, 2).toUpperCase()
                            }}
                        </div>
                        <span
                            class="absolute -right-0.5 -bottom-0.5 h-2 w-2 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-[#0F111E]"
                        />
                    </div>

                    <!-- User Information -->
                    <div class="flex min-w-0 flex-1 flex-col">
                        <span
                            class="truncate text-xs font-bold text-slate-900 dark:text-white"
                        >
                            {{ user.name }}
                        </span>
                        <span
                            class="truncate text-[10.5px] font-medium text-slate-400 dark:text-slate-500"
                        >
                            {{ user.roles?.[0] || 'Member' }}
                        </span>
                    </div>
                </div>

                <!-- Logout Button -->
                <button
                    type="button"
                    class="rounded-xl p-1.5 text-slate-400 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10 dark:hover:text-rose-400"
                    title="Keluar dari Akun"
                    @click="handleLogout"
                >
                    <LogOut class="h-4 w-4 stroke-[2.2]" />
                </button>
            </div>
        </div>
    </aside>
</template>
