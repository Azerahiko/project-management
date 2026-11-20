<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { usePage, Link, router } from '@inertiajs/vue3';
import { getIcon } from './icons';
import { LogOut, X, Sparkles, ChevronDown, Plus, Search } from '@lucide/vue';
import { gsap } from 'gsap';
import { prefersReducedMotion } from '@/lib/motion';
import { useNotifications } from '@/composables/useNotifications';
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
    href?: string;
    icon: string;
    permission?: string;
    badge?: number;
    badgeLabel?: string;
    isComingSoon?: boolean;
    comingSoonDesc?: string;
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

const currentPath = computed(() => {
    const rawUrl = page.url || '';
    return rawUrl.split('?')[0].split('#')[0];
});

function isActive(href?: string): boolean {
    if (!href) return false;
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
const { unreadCount } = useNotifications();

const menuSections = computed<MenuSection[]>(() => [
    {
        title: 'UTAMA',
        items: [
            {
                label: 'Dashboard',
                href: '/dashboard',
                icon: 'Home',
                permission: 'dashboard.view',
            },
            {
                label: 'Tugas Saya',
                href: tasks.index.url(),
                icon: 'CheckSquare',
                permission: 'tasks.view',
                badge: stats.value.my_tasks_count,
            },
            {
                label: 'Proyek Tim',
                href: projects.index.url(),
                icon: 'FolderKanban',
                permission: 'projects.view',
                badge: stats.value.projects_count,
            },
            {
                label: 'Jadwal & Kalender',
                href: '/calendar',
                icon: 'Calendar',
                permission: 'calendar.view',
            },
            {
                label: 'Timeline & Roadmap',
                href: '/timeline',
                icon: 'TrendingUp',
                permission: 'projects.view',
            },
        ],
    },
    {
        title: 'KOLABORASI',
        items: [
            {
                label: 'Anggota Tim',
                href: can('users.view') ? users.index.url() : '/teams',
                icon: 'Users',
                permission: can('users.view') ? 'users.view' : 'teams.view',
                badge: stats.value.team_members_count,
            },
            {
                label: 'Notifikasi',
                href: '/notifications',
                icon: 'Bell',
                permission: 'notifications.view',
                badge: unreadCount.value,
            },
            {
                label: 'Papan Kolaborasi',
                href: '/collaboration',
                icon: 'Activity',
                permission: 'task-collaboration.view',
            },
            {
                label: 'Pengumuman Tim',
                href: '/announcements',
                icon: 'Radio',
                permission: 'dashboard.view',
            },
        ],
    },
    {
        title: 'DOKUMEN & KPI',
        items: [
            {
                label: 'Laporan & KPI',
                href: '/reports',
                icon: 'BarChart3',
                permission: 'reports.view',
            },
            {
                label: 'Berkas Dokumen',
                href: '/documents',
                icon: 'FileText',
                permission: 'documents.view',
            },
            {
                label: 'Log Aktivitas',
                href: '/activity-log',
                icon: 'Sparkles',
                permission: 'activity-log.view',
            },
        ],
    },
    {
        title: 'SISTEM',
        items: [
            {
                label: 'Role & Permissions',
                href: '/roles/permissions',
                icon: 'Shield',
                permission: 'roles.view',
            },
            {
                label: 'Pengaturan Akun',
                href: '/settings',
                icon: 'Settings',
            },
        ],
    },
]);

const comingSoonModal = ref<{
    isOpen: boolean;
    title: string;
    desc: string;
}>({
    isOpen: false,
    title: '',
    desc: '',
});

function openComingSoon(item: SidebarItem): void {
    comingSoonModal.value = {
        isOpen: true,
        title: item.label,
        desc:
            item.comingSoonDesc ||
            'Fitur ini sedang dalam tahap pengembangan dan akan segera siap digunakan.',
    };
}

function closeComingSoon(): void {
    comingSoonModal.value.isOpen = false;
}

function handleLogout(): void {
    router.post(logout.url(), {}, { preserveScroll: true });
}

const MOBILE_BREAKPOINT = 1024;
const SIDEBAR_WIDTH = 250;

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
    syncDrawerPosition();
    window.addEventListener('resize', syncDrawerPosition);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', syncDrawerPosition);
    drawerCtx?.revert();
    drawerCtx = null;
});

function syncDrawerPosition(): void {
    drawerCtx?.revert();
    drawerCtx = null;

    const element = drawer();
    if (!element) return;

    if (!isMobile()) {
        gsap.set(element, { clearProps: 'transform' });
        return;
    }

    gsap.set(element, { x: props.open ? 0 : -SIDEBAR_WIDTH });
}

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
                duration: open ? 0.3 : 0.22,
                ease: open ? 'power3.out' : 'power2.in',
            });
        });
    },
);
</script>

<template>
    <aside
        data-sidebar
        class="fixed inset-y-0 left-0 z-50 flex h-full w-[250px] shrink-0 flex-col justify-between overflow-y-auto border-r border-surface-200/90 bg-white/95 px-3 py-4 backdrop-blur-md transition-transform duration-200 select-none lg:sticky lg:top-0 lg:z-30 lg:h-screen lg:translate-x-0 dark:border-surface-800/80 dark:bg-surface-950/90"
        aria-label="Navigasi Utama"
    >
        <!-- Top: Workspace Header & Navigation Menus -->
        <div class="flex flex-col gap-3.5">
            <!-- Workspace Selector Header (Linear/Vercel style) -->
            <div class="flex items-center justify-between pb-1">
                <Link
                    href="/dashboard"
                    class="group flex w-full items-center justify-between rounded-xl p-1.5 transition-colors hover:bg-surface-200/60 dark:hover:bg-surface-900"
                >
                    <div class="flex min-w-0 items-center gap-2.5">
                        <div
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary-600 text-xs font-semibold tracking-tight text-white shadow-xs dark:bg-primary-500 dark:text-surface-950"
                        >
                            {{
                                (page.props.app.shortName || 'PX')
                                    .slice(0, 2)
                                    .toUpperCase()
                            }}
                        </div>
                        <div class="flex min-w-0 flex-col text-left">
                            <span
                                class="truncate text-xs font-semibold text-surface-900 dark:text-surface-100"
                            >
                                {{ page.props.app.name }}
                            </span>
                            <span
                                class="text-[10px] font-medium tracking-wide text-surface-400"
                            >
                                Workspace Tim
                            </span>
                        </div>
                    </div>
                    <ChevronDown
                        class="h-3.5 w-3.5 text-surface-400 transition-transform group-hover:text-surface-600 dark:group-hover:text-surface-300"
                    />
                </Link>

                <!-- Mobile Close Button -->
                <button
                    type="button"
                    class="rounded-lg p-1.5 text-surface-400 hover:bg-surface-200 hover:text-surface-700 lg:hidden dark:text-surface-400 dark:hover:bg-surface-800 dark:hover:text-white"
                    @click="$emit('close')"
                    aria-label="Tutup menu"
                >
                    <X class="h-4 w-4 stroke-[2]" />
                </button>
            </div>

            <!-- Quick Action Button -->
            <div class="px-0.5">
                <Link
                    :href="tasks.index.url()"
                    class="flex h-8 w-full items-center justify-between rounded-lg border border-surface-200/90 bg-white px-2.5 text-xs font-medium text-surface-600 shadow-2xs transition-all hover:border-primary-300 hover:text-surface-900 dark:border-surface-800 dark:bg-surface-900/60 dark:text-surface-300 dark:hover:border-primary-800 dark:hover:text-white"
                >
                    <div class="flex items-center gap-2">
                        <Search class="h-3.5 w-3.5 text-surface-400" />
                        <span>Cari cepat...</span>
                    </div>
                    <kbd
                        class="py-0.2 rounded border border-surface-200 bg-surface-100 px-1 text-[9px] font-semibold text-surface-400 dark:border-surface-700 dark:bg-surface-800"
                    >
                        ⌘K
                    </kbd>
                </Link>
            </div>

            <!-- Categorized Navigation Groups -->
            <nav class="flex flex-col gap-3.5 pt-1">
                <div
                    v-for="section in menuSections"
                    :key="section.title"
                    class="flex flex-col gap-0.5"
                >
                    <!-- Category Header -->
                    <div
                        class="px-2 pb-1 text-[10px] font-semibold tracking-wider text-surface-400 uppercase dark:text-surface-500"
                    >
                        {{ section.title }}
                    </div>

                    <!-- Items -->
                    <template v-for="item in section.items" :key="item.label">
                        <Link
                            v-if="can(item.permission)"
                            :href="item.href!"
                            class="group relative flex h-8 items-center justify-between rounded-lg px-2 text-xs transition-all duration-150"
                            :class="
                                isActive(item.href)
                                    ? 'bg-primary-600/10 font-semibold text-primary-900 dark:bg-primary-400/15 dark:text-primary-200'
                                    : 'font-medium text-surface-600 hover:bg-surface-200/50 hover:text-surface-900 dark:text-surface-300 dark:hover:bg-surface-900/60 dark:hover:text-white'
                            "
                            @click="isMobile() ? $emit('close') : undefined"
                        >
                            <!-- Active Accent Indicator -->
                            <span
                                v-if="isActive(item.href)"
                                class="absolute top-1.5 bottom-1.5 left-0 w-1 rounded-r-sm bg-primary-600 dark:bg-primary-400"
                            />

                            <div class="flex min-w-0 items-center gap-2.5">
                                <component
                                    :is="getIcon(item.icon)"
                                    class="h-4 w-4 shrink-0 transition-colors"
                                    :class="
                                        isActive(item.href)
                                            ? 'text-primary-700 dark:text-primary-300'
                                            : 'text-surface-400 group-hover:text-surface-700 dark:text-surface-400 dark:group-hover:text-surface-200'
                                    "
                                />
                                <span class="truncate">{{ item.label }}</span>
                            </div>

                            <!-- Count Badge -->
                            <div
                                v-if="
                                    item.badge !== undefined &&
                                    item.badge !== null &&
                                    item.badge > 0
                                "
                                class="flex items-center"
                            >
                                <span
                                    class="py-0.2 rounded px-1.5 text-[10px] font-bold tabular-nums transition-colors"
                                    :class="
                                        isActive(item.href)
                                            ? 'bg-primary-600 text-white dark:bg-primary-400 dark:text-surface-950'
                                            : 'bg-surface-200/70 text-surface-600 dark:bg-surface-800 dark:text-surface-400'
                                    "
                                >
                                    {{ item.badge }}
                                </span>
                            </div>
                        </Link>
                    </template>
                </div>
            </nav>
        </div>

        <!-- Bottom: User Card Footer -->
        <div
            class="mt-4 border-t border-surface-200/80 pt-2.5 dark:border-surface-800/80"
        >
            <div
                v-if="user"
                class="flex items-center justify-between rounded-xl p-1.5 transition-colors hover:bg-surface-200/50 dark:hover:bg-surface-900/60"
            >
                <Link
                    href="/settings"
                    class="flex min-w-0 flex-1 items-center gap-2"
                >
                    <div class="relative shrink-0">
                        <img
                            v-if="user.avatar"
                            :src="user.avatar"
                            :alt="user.name"
                            class="h-7 w-7 rounded-full object-cover ring-1 ring-surface-200 dark:ring-surface-700"
                        />
                        <div
                            v-else
                            class="flex h-7 w-7 items-center justify-center rounded-full bg-primary-700 text-[10px] font-bold text-white shadow-2xs dark:bg-primary-600"
                        >
                            {{
                                user.initials ||
                                user.name.slice(0, 2).toUpperCase()
                            }}
                        </div>
                        <span
                            class="absolute -right-0.5 -bottom-0.5 h-2 w-2 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-surface-950"
                            title="Online"
                        />
                    </div>
                    <div class="flex min-w-0 flex-col leading-tight">
                        <span
                            class="truncate text-xs font-medium text-surface-900 dark:text-surface-100"
                        >
                            {{ user.name }}
                        </span>
                        <span class="truncate text-[10px] text-surface-400">
                            {{ user.roles?.[0] || 'Developer' }}
                        </span>
                    </div>
                </Link>

                <button
                    type="button"
                    class="rounded-lg p-1.5 text-surface-400 transition hover:bg-surface-200 hover:text-rose-600 dark:hover:bg-surface-800 dark:hover:text-rose-400"
                    title="Keluar"
                    @click="handleLogout"
                >
                    <LogOut class="h-3.5 w-3.5 stroke-[2]" />
                </button>
            </div>
        </div>

        <!-- Modal info -->
        <div
            v-if="comingSoonModal.isOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-surface-900/50 p-4 backdrop-blur-xs"
            @click.self="closeComingSoon"
        >
            <div
                class="w-full max-w-sm rounded-2xl bg-white p-5 text-surface-900 shadow-xl dark:bg-surface-900 dark:text-white"
            >
                <div class="flex items-center justify-between pb-3">
                    <div class="flex items-center gap-2">
                        <span
                            class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-950/20 dark:text-primary-400"
                        >
                            <Sparkles class="h-4 w-4" />
                        </span>
                        <h3 class="text-sm font-bold">
                            {{ comingSoonModal.title }}
                        </h3>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg p-1 text-surface-400 hover:bg-surface-100 dark:hover:bg-surface-800"
                        @click="closeComingSoon"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>
                <p
                    class="text-xs leading-relaxed text-surface-600 dark:text-surface-300"
                >
                    {{ comingSoonModal.desc }}
                </p>
            </div>
        </div>
    </aside>
</template>
