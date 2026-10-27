<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { usePage, Link, router } from '@inertiajs/vue3';
import { getIcon } from './icons';
import { LogOut, PanelLeft, X } from '@lucide/vue';
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

interface SidebarItem {
    label: string;
    href: string;
    icon: string;
    permission?: string;
    shortcut?: string;
    badge?: number;
    isBlackBadge?: boolean;
    isSupport?: boolean;
}

interface MenuSection {
    title: string;
    items: SidebarItem[];
}

const props = defineProps<Props>();
defineEmits<Emits>();

const page = usePage();
const user = computed(() => page.props.auth?.user);

// Reactive URL tracking for instant active state switching without page reload
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

// Grouped sections matching reference layout with all user features included
const menuSections = computed<MenuSection[]>(() => [
    {
        title: 'Main menu',
        items: [
            {
                label: 'Overview',
                href: '/dashboard',
                icon: 'Home',
                permission: 'dashboard.view',
                shortcut: '⌘ M',
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
                label: 'Schedule',
                href: '/calendar',
                icon: 'Calendar',
                permission: 'calendar.view',
            },
            {
                label: 'Team Members',
                href: can('users.view') ? users.index.url() : '/teams',
                icon: 'Users',
                permission: can('users.view') ? 'users.view' : 'teams.view',
                badge: stats.value.team_members_count,
            },
            {
                label: 'Messages',
                href: '/notifications',
                icon: 'Bell',
                permission: 'notifications.view',
                badge: stats.value.notifications_unread,
                isBlackBadge: true,
            },
            {
                label: 'Analytics',
                href: '/reports',
                icon: 'BarChart3',
                permission: 'reports.view',
            },
            {
                label: 'Documents',
                href: '/documents',
                icon: 'FileText',
                permission: 'documents.view',
            },
        ],
    },
    {
        title: 'Settings and news',
        items: [
            {
                label: 'Project Activities',
                href: '/activity-log',
                icon: 'Sparkles',
                permission: 'activity-log.view',
            },
            {
                label: "What's New & Help",
                href: '#support',
                icon: 'Radio',
                isSupport: true,
            },
            {
                label: 'Settings',
                href: '/settings',
                icon: 'Settings',
                permission: 'settings.view',
            },
        ],
    },
]);

const showSupportModal = ref(false);

function handleSupportClick(): void {
    showSupportModal.value = true;
}

function closeSupportModal(): void {
    showSupportModal.value = false;
}

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
        class="fixed inset-y-0 left-0 z-50 flex h-full w-[250px] shrink-0 flex-col justify-between overflow-y-auto border-r border-slate-200/60 bg-[#F6F8F9] px-3.5 py-5 transition-transform duration-300 select-none lg:sticky lg:top-0 lg:z-30 lg:h-screen lg:translate-x-0 dark:border-white/5 dark:bg-[#10121D]"
        aria-label="Navigasi Utama"
    >
        <!-- Top Section: Brand Logo & Navigation Groups -->
        <div class="flex flex-col gap-6">
            <!-- Brand Squircle Logo + Collapse Toggle Header (Exact Reference Match) -->
            <div class="flex items-center justify-between px-1.5 pt-1">
                <Link
                    href="/dashboard"
                    class="group flex items-center gap-2.5 transition-opacity hover:opacity-90"
                >
                    <!-- Rounded Dark Squircle Badge with Logo Mark -->
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#0E1017] p-2 shadow-xs transition-transform duration-150 group-hover:scale-105 dark:bg-white"
                    >
                        <img
                            src="/images/logo-mark.png"
                            alt="DigitalCode"
                            class="h-full w-full object-contain"
                        />
                    </div>
                    <span
                        class="text-base font-black tracking-tight text-slate-900 dark:text-white"
                    >
                        DigitalCode
                    </span>
                </Link>

                <div class="flex items-center gap-1">
                    <!-- Subtle Sidebar Toggle Icon (Matching Reference Header Icon) -->
                    <button
                        type="button"
                        class="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-200/60 hover:text-slate-700 dark:text-slate-500 dark:hover:bg-white/10 dark:hover:text-white"
                        aria-label="Toggle sidebar"
                    >
                        <PanelLeft class="h-4 w-4 stroke-[2]" />
                    </button>

                    <!-- Mobile Drawer Close Button -->
                    <button
                        type="button"
                        class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-200/60 hover:text-slate-700 lg:hidden dark:text-slate-500 dark:hover:bg-white/10 dark:hover:text-white"
                        @click="$emit('close')"
                        aria-label="Tutup menu"
                    >
                        <X class="h-4 w-4 stroke-[2]" />
                    </button>
                </div>
            </div>

            <!-- Navigation Sections -->
            <div class="flex flex-col gap-5">
                <div
                    v-for="section in menuSections"
                    :key="section.title"
                    class="flex flex-col gap-1"
                >
                    <!-- Section Title Label (e.g. "Main menu", "Settings and news") -->
                    <div
                        class="px-3 pb-1 text-[11px] font-medium tracking-tight text-slate-400 dark:text-slate-500"
                    >
                        {{ section.title }}
                    </div>

                    <!-- Menu Items -->
                    <template v-for="item in section.items" :key="item.label">
                        <!-- Support Modal Trigger Item -->
                        <button
                            v-if="item.isSupport"
                            type="button"
                            class="group flex w-full items-center justify-between rounded-full px-3 py-2 text-[13px] font-medium text-slate-600 transition-colors hover:bg-white/60 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-white/5 dark:hover:text-white"
                            @click="handleSupportClick"
                        >
                            <div class="flex items-center gap-3">
                                <component
                                    :is="getIcon(item.icon)"
                                    class="h-4.5 w-4.5 stroke-[2] text-slate-400 group-hover:text-slate-700 dark:text-slate-500 dark:group-hover:text-slate-200"
                                />
                                <span>{{ item.label }}</span>
                            </div>
                        </button>

                        <!-- Standard Route Navigation Link -->
                        <Link
                            v-else-if="can(item.permission)"
                            :href="item.href"
                            class="group flex items-center justify-between rounded-full px-3 py-2 text-[13px] transition-all duration-150"
                            :class="
                                isActive(item.href)
                                    ? 'bg-white font-semibold text-slate-900 shadow-xs ring-1 shadow-slate-950/5 ring-slate-900/5 dark:bg-white/10 dark:text-white dark:ring-white/10'
                                    : 'font-medium text-slate-600 hover:bg-white/60 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-white/5 dark:hover:text-white'
                            "
                            @click="isMobile() ? $emit('close') : undefined"
                        >
                            <div class="flex items-center gap-3">
                                <component
                                    :is="getIcon(item.icon)"
                                    class="h-4.5 w-4.5 stroke-[2] transition-colors"
                                    :class="
                                        isActive(item.href)
                                            ? 'text-slate-900 dark:text-white'
                                            : 'text-slate-400 group-hover:text-slate-700 dark:text-slate-500 dark:group-hover:text-slate-200'
                                    "
                                />
                                <span>{{ item.label }}</span>
                            </div>

                            <!-- Right Extras: Keyboard Shortcut Pill or Count Badge -->
                            <div class="flex items-center">
                                <!-- Keyboard shortcut badge (e.g. ⌘ M) -->
                                <span
                                    v-if="item.shortcut"
                                    class="rounded bg-slate-200/60 px-1.5 py-0.5 font-mono text-[9.5px] font-medium text-slate-500 dark:bg-white/10 dark:text-slate-400"
                                >
                                    {{ item.shortcut }}
                                </span>

                                <!-- Solid Black Circle Count Badge (Matching screenshot's "2" badge) -->
                                <span
                                    v-else-if="item.isBlackBadge && item.badge"
                                    class="flex h-4.5 min-w-[18px] items-center justify-center rounded-full bg-[#0E1017] px-1 text-[10px] font-bold text-white shadow-2xs dark:bg-white dark:text-slate-900"
                                >
                                    {{ item.badge }}
                                </span>

                                <!-- Soft Count Badge (for tasks/projects) -->
                                <span
                                    v-else-if="
                                        item.badge !== undefined &&
                                        item.badge !== null &&
                                        item.badge > 0
                                    "
                                    class="py-0.2 rounded-full bg-slate-200/60 px-1.5 text-[10px] font-semibold text-slate-500 dark:bg-white/10 dark:text-slate-400"
                                >
                                    {{ item.badge }}
                                </span>
                            </div>
                        </Link>
                    </template>
                </div>
            </div>
        </div>

        <!-- Bottom: Account Section (Exact Reference Match) -->
        <div
            class="mt-6 border-t border-slate-200/50 pt-3.5 dark:border-white/5"
        >
            <div
                class="px-3 pb-1.5 text-[11px] font-medium tracking-tight text-slate-400 dark:text-slate-500"
            >
                Account
            </div>

            <div
                v-if="user"
                class="flex items-center justify-between gap-2 rounded-xl p-1.5 transition-colors hover:bg-white/60 dark:hover:bg-white/5"
            >
                <div class="flex min-w-0 items-center gap-2.5">
                    <!-- Circular Avatar -->
                    <img
                        v-if="user.avatar"
                        :src="user.avatar"
                        :alt="user.name"
                        class="h-8 w-8 rounded-full object-cover ring-1 ring-slate-200 dark:ring-white/20"
                    />
                    <div
                        v-else
                        class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-900 text-[11px] font-bold text-white shadow-2xs dark:bg-white dark:text-slate-900"
                    >
                        {{
                            user.initials || user.name.slice(0, 2).toUpperCase()
                        }}
                    </div>

                    <!-- Name & Role Stack -->
                    <div class="flex min-w-0 flex-col leading-tight">
                        <span
                            class="truncate text-xs font-bold text-slate-900 dark:text-white"
                        >
                            {{ user.name }}
                        </span>
                        <span
                            class="truncate text-[10.5px] font-medium text-slate-400 dark:text-slate-500"
                        >
                            {{ user.roles?.[0] || 'Team Member' }}
                        </span>
                    </div>
                </div>

                <!-- Clean Logout Action Button -->
                <button
                    type="button"
                    class="rounded-lg p-1.5 text-slate-400 transition hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-500/10 dark:hover:text-rose-400"
                    title="Keluar"
                    @click="handleLogout"
                >
                    <LogOut class="h-4 w-4 stroke-[2]" />
                </button>
            </div>
        </div>

        <!-- What's New & Support Modal Popup -->
        <div
            v-if="showSupportModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-xs"
            @click.self="closeSupportModal"
        >
            <div
                class="w-full max-w-md rounded-3xl bg-white p-6 text-slate-900 shadow-2xl dark:bg-[#1A1D30] dark:text-white"
            >
                <div class="flex items-center justify-between pb-3">
                    <h3 class="text-base font-bold">
                        Pusat Bantuan & Pembaruan DigitalCode
                    </h3>
                    <button
                        type="button"
                        class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-white/10"
                        @click="closeSupportModal"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>
                <p
                    class="text-xs leading-relaxed text-slate-600 dark:text-slate-300"
                >
                    Selamat datang di DigitalCode Project Management! Untuk
                    informasi fitur baru atau kendala akun, silakan hubungi tim
                    kami di:
                    <a
                        href="mailto:support@project.local"
                        class="font-semibold text-indigo-600 underline dark:text-indigo-400"
                    >
                        support@project.local </a
                    >.
                </p>
                <div class="mt-5 flex justify-end">
                    <button
                        type="button"
                        class="rounded-full bg-slate-900 px-4 py-2 text-xs font-bold text-white transition hover:bg-black dark:bg-white dark:text-slate-900"
                        @click="closeSupportModal"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </aside>
</template>
