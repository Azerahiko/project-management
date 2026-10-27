<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
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
const user = computed(() => page.props.auth?.user);

// Fully reactive URL tracking with Inertia usePage()
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

// Menu items matching the reference photo visual rhythm and mapped to real routes
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
        label: 'Order History',
        href: projects.index.url(),
        icon: 'FolderKanban',
        permission: 'projects.view',
        badge: stats.value.projects_count ?? 1,
    },
    {
        label: 'Offers',
        href: can('users.view') ? users.index.url() : '/teams',
        icon: 'Users',
        permission: can('users.view') ? 'users.view' : 'teams.view',
        badge: stats.value.team_members_count ?? 1,
    },
    {
        label: 'Products',
        href: '/documents',
        icon: 'FileText',
        permission: 'documents.view',
        badge: 1,
    },
    {
        label: 'Stock',
        href: '/activity-log',
        icon: 'Activity',
        permission: 'activity-log.view',
        badge: 1,
    },
    {
        label: 'Massage',
        href: '/notifications',
        icon: 'MessageSquare',
        permission: 'notifications.view',
        badge: stats.value.notifications_unread || 1,
    },
    {
        label: 'Settings',
        href: '/settings',
        icon: 'Settings',
        permission: 'settings.view',
        badge: 1,
    },
]);

const allowedItems = computed(() =>
    sidebarItems.value.filter((item) => can(item.permission)),
);

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
const SIDEBAR_WIDTH = 270;

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
        class="fixed inset-y-0 left-0 z-50 flex h-full w-[265px] shrink-0 flex-col justify-between overflow-y-auto border-r border-slate-200/80 bg-white p-5 shadow-[4px_0_24px_-10px_rgba(0,0,0,0.03)] transition-transform duration-300 lg:sticky lg:top-0 lg:z-30 lg:h-screen lg:translate-x-0 dark:border-white/5 dark:bg-[#151829] dark:shadow-none"
        aria-label="Navigasi Utama"
    >
        <!-- Top: Brand Header & Main Menu -->
        <div class="flex flex-col">
            <!-- Brand Logo Header Section -->
            <div
                class="flex items-center justify-between border-b border-slate-100/80 px-1 pt-1 pb-6 dark:border-white/5"
            >
                <Link
                    href="/dashboard"
                    class="group flex items-center gap-3 transition-opacity hover:opacity-90"
                >
                    <!-- Official Logo Mark in Sleek Dark Capsule -->
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-[#0D0E12] p-2 shadow-sm ring-1 shadow-black/15 ring-white/10 transition-transform duration-200 group-hover:scale-105 dark:bg-white dark:ring-black/5"
                    >
                        <img
                            src="/images/logo-mark.png"
                            alt="DigitalCode"
                            class="h-full w-full object-contain"
                        />
                    </div>

                    <!-- Brand Name & Pro Pill -->
                    <div class="flex flex-col">
                        <div class="flex items-center gap-1.5">
                            <span
                                class="text-base font-black tracking-tight text-slate-900 dark:text-white"
                            >
                                DigitalCode
                            </span>
                            <span
                                class="rounded-full bg-[#D2F87A] px-1.5 py-0.5 text-[9px] font-black text-slate-900 shadow-2xs"
                            >
                                PRO
                            </span>
                        </div>
                        <span
                            class="text-[11px] font-medium text-slate-400 dark:text-slate-500"
                        >
                            Task Management
                        </span>
                    </div>
                </Link>

                <!-- Mobile Drawer Close Button -->
                <button
                    type="button"
                    class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 lg:hidden dark:text-slate-500 dark:hover:bg-white/5 dark:hover:text-white"
                    @click="$emit('close')"
                    aria-label="Tutup menu"
                >
                    <component :is="getIcon('X')" class="h-5 w-5" />
                </button>
            </div>

            <!-- Navigation Links List (Matching Reference Pill Format Exactly) -->
            <nav class="flex flex-col gap-1.5 pt-5">
                <Link
                    v-for="item in allowedItems"
                    :key="item.href"
                    :href="item.href"
                    class="group flex items-center justify-between rounded-full px-4 py-2.5 text-[13.5px] transition-all duration-200"
                    :class="
                        isActive(item.href)
                            ? 'scale-[1.01] bg-[#0D0E12] font-semibold text-white shadow-md shadow-black/10 dark:bg-white dark:text-slate-900'
                            : 'font-medium text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-white/5 dark:hover:text-white'
                    "
                    @click="isMobile() ? $emit('close') : undefined"
                >
                    <!-- Left: Icon & Label -->
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
                        <span class="tracking-tight">{{ item.label }}</span>
                    </div>

                    <!-- Right: Badge Count & Chevron (e.g. "1 >") -->
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
                            class="h-3.5 w-3.5 stroke-[2.5] transition-transform duration-150 group-hover:translate-x-0.5"
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

        <!-- Bottom: User Mini Profile + Support & Keluar -->
        <div class="mt-8 flex flex-col gap-3">
            <!-- Active User Mini Profile Card -->
            <div
                v-if="user"
                class="flex items-center gap-3 rounded-2xl bg-slate-50/90 p-2.5 transition-colors dark:bg-white/[0.03]"
            >
                <div class="relative">
                    <img
                        v-if="user.avatar"
                        :src="user.avatar"
                        :alt="user.name"
                        class="h-9 w-9 rounded-full object-cover ring-2 ring-white dark:ring-slate-800"
                    />
                    <div
                        v-else
                        class="flex h-9 w-9 items-center justify-center rounded-full bg-[#0D0E12] text-xs font-bold text-white shadow-xs dark:bg-white dark:text-slate-900"
                    >
                        {{
                            user.initials || user.name.slice(0, 2).toUpperCase()
                        }}
                    </div>
                    <!-- Online Green Pulse Indicator -->
                    <span
                        class="absolute -right-0.5 -bottom-0.5 h-2.5 w-2.5 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-[#151829]"
                    />
                </div>
                <div class="flex min-w-0 flex-1 flex-col">
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

            <!-- Footer Navigation (Support & Help Center) -->
            <div class="border-slate-150/80 border-t pt-3 dark:border-white/5">
                <div class="flex flex-col gap-1">
                    <!-- Support Action Button -->
                    <button
                        type="button"
                        class="group flex w-full items-center justify-between rounded-full px-4 py-2 text-[13px] font-medium text-slate-600 transition-colors hover:bg-slate-100/80 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-white/5 dark:hover:text-white"
                        @click="handleSupportClick"
                    >
                        <div class="flex items-center gap-3">
                            <component
                                :is="getIcon('Headphones')"
                                class="h-4.5 w-4.5 stroke-[2.2] text-slate-400 group-hover:text-slate-700 dark:text-slate-500 dark:group-hover:text-slate-200"
                            />
                            <span>Support</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span
                                class="text-xs font-semibold text-slate-400 group-hover:text-slate-600 dark:text-slate-500"
                            >
                                1
                            </span>
                            <ChevronRight
                                class="h-3.5 w-3.5 stroke-[2.5] text-slate-300 group-hover:text-slate-500 dark:text-slate-600"
                            />
                        </div>
                    </button>

                    <!-- Help Center / Logout Action -->
                    <button
                        type="button"
                        class="group flex w-full items-center justify-between rounded-full px-4 py-2 text-[13px] font-medium text-slate-600 transition-colors hover:bg-rose-50 hover:text-rose-600 dark:text-slate-400 dark:hover:bg-rose-500/10 dark:hover:text-rose-300"
                        @click="handleLogout"
                    >
                        <div class="flex items-center gap-3">
                            <component
                                :is="getIcon('LogOut')"
                                class="h-4.5 w-4.5 stroke-[2.2] text-slate-400 group-hover:text-rose-500 dark:text-slate-500 dark:group-hover:text-rose-400"
                            />
                            <span>Help Center</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <span
                                class="text-xs font-semibold text-slate-400 group-hover:text-rose-500 dark:text-slate-500"
                            >
                                1
                            </span>
                            <ChevronRight
                                class="h-3.5 w-3.5 stroke-[2.5] text-slate-300 group-hover:text-rose-400 dark:text-slate-600"
                            />
                        </div>
                    </button>
                </div>
            </div>
        </div>

        <!-- Clean Modal Support Information -->
        <div
            v-if="showSupportModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4 backdrop-blur-xs"
            @click.self="closeSupportModal"
        >
            <div
                class="w-full max-w-md rounded-3xl bg-white p-6 shadow-2xl dark:bg-[#1A1D30]"
            >
                <div class="flex items-center justify-between pb-3">
                    <h3
                        class="text-lg font-bold text-slate-900 dark:text-white"
                    >
                        Pusat Bantuan & Dukungan DigitalCode
                    </h3>
                    <button
                        type="button"
                        class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-white/10"
                        @click="closeSupportModal"
                    >
                        <component :is="getIcon('X')" class="h-5 w-5" />
                    </button>
                </div>
                <p
                    class="text-sm leading-relaxed text-slate-600 dark:text-slate-300"
                >
                    Butuh bantuan terkait manajemen tugas, proyek, atau akun
                    DigitalCode Anda? Hubungi tim support teknis melalui email:
                    <a
                        href="mailto:support@project.local"
                        class="font-semibold text-indigo-600 underline dark:text-indigo-400"
                    >
                        support@project.local
                    </a>
                </p>
                <div class="mt-6 flex justify-end">
                    <button
                        type="button"
                        class="rounded-full bg-[#0D0E12] px-5 py-2 text-xs font-bold text-white transition hover:bg-black dark:bg-white dark:text-slate-900"
                        @click="closeSupportModal"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </aside>
</template>
