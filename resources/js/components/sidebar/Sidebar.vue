<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { usePage, Link, router } from '@inertiajs/vue3';
import { getIcon } from './icons';
import { LogOut, X, Sparkles } from '@lucide/vue';
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

// Reactive URL tracking for instant active state switching without page reload
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

// Organized categories with useful features (active features + upcoming placeholder features)
const menuSections = computed<MenuSection[]>(() => [
    {
        title: 'MANAJEMEN & ALUR KERJA',
        items: [
            {
                label: 'Overview',
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
                label: 'Timeline Gantt',
                href: '/timeline',
                icon: 'TrendingUp',
                permission: 'projects.view',
            },
        ],
    },
    {
        title: 'KOLABORASI & ANGGOTA',
        items: [
            {
                label: 'Anggota Tim',
                href: can('users.view') ? users.index.url() : '/teams',
                icon: 'Users',
                permission: can('users.view') ? 'users.view' : 'teams.view',
                badge: stats.value.team_members_count,
            },
            {
                label: 'Pesan & Notifikasi',
                href: '/notifications',
                icon: 'Bell',
                permission: 'notifications.view',
                badge: stats.value.notifications_unread,
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
        title: 'LAPORAN & DOKUMEN',
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
        title: 'SISTEM & BANTUAN',
        items: [
            {
                label: 'Pengaturan Akun',
                href: '/settings',
                icon: 'Settings',
                permission: 'settings.view',
            },
            {
                label: 'Integrasi API',
                icon: 'Shield',
                isComingSoon: true,
                badgeLabel: 'Segera',
                comingSoonDesc:
                    'Koneksi webhook dan integrasi dengan Slack, GitHub, dan Google Workspace akan hadir segera.',
            },
            {
                label: 'Bantuan & Support',
                icon: 'HelpCircle',
                isSupport: true,
            },
        ],
    },
]);

// Modal states for upcoming features and support
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

const showSupportModal = ref(false);

function openSupport(): void {
    showSupportModal.value = true;
}

function closeSupport(): void {
    showSupportModal.value = false;
}

const openSupportModal = openSupport;
const closeSupportModal = closeSupport;

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
        class="fixed inset-y-0 left-0 z-50 flex h-full w-[265px] shrink-0 flex-col justify-between overflow-y-auto border-r border-surface-200 bg-white px-3.5 py-5 transition-transform duration-300 select-none lg:sticky lg:top-0 lg:z-30 lg:h-screen lg:translate-x-0 dark:border-surface-700 dark:bg-surface-950"
        aria-label="Navigasi Utama"
    >
        <!-- Top Section: Natural Brand Logo & Categorized Menus -->
        <div class="flex flex-col gap-5">
            <!-- Brand Logo with Tasks Flow branding -->
            <div class="flex items-center justify-between px-2 pt-1 pb-2">
                <Link
                    href="/dashboard"
                    class="group flex items-center gap-2.5 transition-opacity hover:opacity-90"
                >
                    <div
                        class="relative flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary-700/10 p-1 ring-1 ring-primary-700/20 dark:bg-primary-300/10 dark:ring-primary-300/20"
                    >
                        <img
                            src="/images/logo-mark.png"
                            alt="Tasks Flow"
                            class="h-7 w-7 object-contain transition-transform duration-200 group-hover:scale-105"
                        />
                    </div>
                    <div class="flex flex-col">
                        <span
                            class="font-serif text-lg font-normal tracking-tight text-surface-950 dark:text-surface-50"
                        >
                            Tasks Flow
                        </span>
                        <span
                            class="text-[10px] font-medium tracking-wider text-surface-500 uppercase dark:text-surface-400"
                        >
                            Workspace
                        </span>
                    </div>
                </Link>

                <!-- Mobile Close Button (Only visible on small mobile screens) -->
                <button
                    type="button"
                    class="rounded-lg p-1.5 text-surface-400 hover:bg-surface-200/60 hover:text-surface-700 lg:hidden dark:text-surface-300 dark:hover:bg-white/10 dark:hover:text-white"
                    @click="$emit('close')"
                    aria-label="Tutup menu"
                >
                    <X class="h-4 w-4 stroke-[2]" />
                </button>
            </div>

            <!-- Categorized Navigation Groups -->
            <div class="flex flex-col gap-4">
                <div
                    v-for="section in menuSections"
                    :key="section.title"
                    class="flex flex-col gap-1"
                >
                    <!-- Category Title Header -->
                    <div
                        class="px-3 pb-1 text-[11px] font-semibold tracking-wider text-surface-600 uppercase dark:text-surface-300"
                    >
                        {{ section.title }}
                    </div>

                    <!-- Items inside this Category -->
                    <template v-for="item in section.items" :key="item.label">
                        <!-- 1. Support Action Item -->
                        <button
                            v-if="item.isSupport"
                            type="button"
                            class="group flex w-full items-center justify-between rounded-xl px-3 py-2.5 text-sm font-medium text-surface-700 transition-colors hover:bg-surface-100 hover:text-surface-900 dark:text-surface-200 dark:hover:bg-white/5 dark:hover:text-white"
                            @click="openSupport"
                        >
                            <div class="flex items-center gap-3">
                                <component
                                    :is="getIcon(item.icon)"
                                    class="h-4.5 w-4.5 stroke-[2] text-surface-400 group-hover:text-surface-700 dark:text-surface-300 dark:group-hover:text-surface-200"
                                />
                                <span>{{ item.label }}</span>
                            </div>
                        </button>

                        <!-- 2. Upcoming Feature (Placeholder with informative popup) -->
                        <button
                            v-else-if="item.isComingSoon"
                            type="button"
                            class="group flex w-full items-center justify-between rounded-xl px-3 py-2.5 text-sm font-medium text-surface-700 transition-colors hover:bg-surface-100 hover:text-surface-900 dark:text-surface-200 dark:hover:bg-white/5 dark:hover:text-white"
                            @click="openComingSoon(item)"
                        >
                            <div class="flex items-center gap-3">
                                <component
                                    :is="getIcon(item.icon)"
                                    class="h-4.5 w-4.5 stroke-[2] text-surface-400 group-hover:text-surface-700 dark:text-surface-300 dark:group-hover:text-surface-200"
                                />
                                <span>{{ item.label }}</span>
                            </div>
                            <!-- "Segera" Badge (Sleek rounded-md, no pill) -->
                            <span
                                class="rounded-md bg-surface-200/70 px-1.5 py-0.5 text-[9.5px] font-bold text-surface-600 dark:bg-white/10 dark:text-surface-300"
                            >
                                {{ item.badgeLabel || 'Segera' }}
                            </span>
                        </button>

                        <!-- 3. Active Real Routes -->
                        <Link
                            v-else-if="can(item.permission)"
                            :href="item.href!"
                            class="group relative flex items-center justify-between rounded-xl px-3 py-2.5 text-sm transition-all duration-150"
                            :class="
                                isActive(item.href)
                                    ? 'bg-primary-50/90 font-semibold text-primary-900 shadow-2xs ring-1 ring-primary-200/80 dark:bg-primary-950/40 dark:text-primary-100 dark:ring-primary-700/40'
                                    : 'font-medium text-surface-700 hover:bg-surface-100 hover:text-surface-900 dark:text-surface-200 dark:hover:bg-white/5 dark:hover:text-white'
                            "
                            @click="isMobile() ? $emit('close') : undefined"
                        >
                            <!-- Active Accent Bar -->
                            <span
                                v-if="isActive(item.href)"
                                class="absolute top-2 bottom-2 left-0 w-1 rounded-r-full bg-primary-600 dark:bg-primary-400"
                            />

                            <div class="flex items-center gap-3">
                                <component
                                    :is="getIcon(item.icon)"
                                    class="h-4.5 w-4.5 stroke-[2] transition-colors"
                                    :class="
                                        isActive(item.href)
                                            ? 'text-primary-700 dark:text-primary-300'
                                            : 'text-surface-400 group-hover:text-surface-700 dark:text-surface-400 dark:group-hover:text-surface-200'
                                    "
                                />
                                <span class="tracking-tight">{{
                                    item.label
                                }}</span>
                            </div>

                            <!-- Real Item Count Badge (Sleek rounded-md, no pill) -->
                            <div
                                v-if="
                                    item.badge !== undefined &&
                                    item.badge !== null &&
                                    item.badge > 0
                                "
                                class="flex items-center"
                            >
                                <span
                                    class="rounded-md px-1.5 py-0.5 text-[10px] font-bold transition-colors"
                                    :class="
                                        isActive(item.href)
                                            ? 'bg-primary-600 text-white shadow-2xs dark:bg-primary-500 dark:text-surface-950'
                                            : 'bg-surface-200/80 text-surface-700 dark:bg-white/10 dark:text-surface-300'
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

        <!-- Bottom: User Account Footer -->
        <div
            class="mt-6 border-t border-surface-200/70 pt-3 dark:border-surface-800/10"
        >
            <div
                class="px-3 pb-1.5 text-[11px] font-semibold tracking-wider text-surface-500 uppercase dark:text-surface-400"
            >
                AKUN
            </div>

            <div
                v-if="user"
                class="flex items-center justify-between gap-2 rounded-2xl border border-surface-200/70 bg-surface-50/70 p-2 transition-all hover:border-surface-300 hover:bg-surface-100/70 dark:border-surface-800/20 dark:bg-surface-900/40 dark:hover:border-surface-700 dark:hover:bg-surface-900/80"
            >
                <div class="flex min-w-0 items-center gap-2.5">
                    <!-- Circular Avatar with Online Dot -->
                    <div class="relative shrink-0">
                        <img
                            v-if="user.avatar"
                            :src="user.avatar"
                            :alt="user.name"
                            class="h-8.5 w-8.5 rounded-full object-cover ring-1 ring-surface-200 dark:ring-surface-800"
                        />
                        <div
                            v-else
                            class="flex h-8.5 w-8.5 items-center justify-center rounded-full bg-gradient-to-br from-primary-600 to-primary-700 text-[11px] font-bold text-white shadow-2xs"
                        >
                            {{
                                user.initials ||
                                user.name.slice(0, 2).toUpperCase()
                            }}
                        </div>
                        <span
                            class="absolute -right-0.5 -bottom-0.5 h-2.5 w-2.5 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-surface-950"
                            title="Aktif"
                        />
                    </div>

                    <!-- Name & Role -->
                    <div class="flex min-w-0 flex-col leading-tight">
                        <span
                            class="truncate text-xs font-bold text-surface-900 dark:text-white"
                        >
                            {{ user.name }}
                        </span>
                        <span
                            class="truncate text-[10.5px] font-medium text-surface-500 dark:text-surface-400"
                        >
                            {{ user.roles?.[0] || 'Team Member' }}
                        </span>
                    </div>
                </div>

                <!-- Clean Logout Action -->
                <button
                    type="button"
                    class="rounded-xl p-1.5 text-surface-400 transition hover:bg-white hover:text-rose-600 hover:shadow-2xs dark:hover:bg-white/10 dark:hover:text-rose-400"
                    title="Keluar"
                    @click="handleLogout"
                >
                    <LogOut class="h-4 w-4 stroke-[2]" />
                </button>
            </div>
        </div>

        <!-- Upcoming Feature Information Modal -->
        <div
            v-if="comingSoonModal.isOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-surface-900/50 p-4 backdrop-blur-xs"
            @click.self="closeComingSoon"
        >
            <div
                class="w-full max-w-sm rounded-3xl bg-white p-6 text-surface-900 shadow-2xl dark:bg-surface-900 dark:text-white"
            >
                <div class="flex items-center justify-between pb-3">
                    <div class="flex items-center gap-2">
                        <span
                            class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-950/20 dark:text-primary-400"
                        >
                            <Sparkles class="h-4 w-4" />
                        </span>
                        <h3 class="text-base font-bold">
                            {{ comingSoonModal.title }}
                        </h3>
                    </div>
                    <button
                        type="button"
                        class="rounded-xl p-1.5 text-surface-400 hover:bg-surface-100 dark:hover:bg-white/10"
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
                <div class="mt-5 flex justify-end">
                    <button
                        type="button"
                        class="rounded-xl bg-surface-900 px-4 py-2 text-xs font-bold text-white transition hover:bg-black dark:bg-white dark:text-surface-900"
                        @click="closeComingSoon"
                    >
                        Mengerti
                    </button>
                </div>
            </div>
        </div>

        <!-- Support Modal Popup -->
        <div
            v-if="showSupportModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-surface-900/50 p-4 backdrop-blur-xs"
            @click.self="closeSupportModal"
        >
            <div
                class="w-full max-w-sm rounded-3xl bg-white p-6 text-surface-900 shadow-2xl dark:bg-surface-900 dark:text-white"
            >
                <div class="flex items-center justify-between pb-3">
                    <h3 class="text-base font-bold">Pusat Bantuan & Support</h3>
                    <button
                        type="button"
                        class="rounded-xl p-1.5 text-surface-400 hover:bg-surface-100 dark:hover:bg-white/10"
                        @click="closeSupportModal"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>
                <p
                    class="text-xs leading-relaxed text-surface-600 dark:text-surface-300"
                >
                    Butuh panduan atau mengalami kendala? Hubungi tim support
                    DigitalCode melalui email resmi:
                    <a
                        href="mailto:support@project.local"
                        class="font-semibold text-primary-600 underline dark:text-primary-400"
                    >
                        support@project.local </a
                    >.
                </p>
                <div class="mt-5 flex justify-end">
                    <button
                        type="button"
                        class="rounded-xl bg-surface-900 px-4 py-2 text-xs font-bold text-white transition hover:bg-black dark:bg-white dark:text-surface-900"
                        @click="closeSupportModal"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </aside>
</template>
