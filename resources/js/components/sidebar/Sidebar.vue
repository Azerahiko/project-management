<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { usePage, Link, router } from '@inertiajs/vue3';
import { getIcon } from './icons';
import { ChevronLeft, ChevronRight, MessageCircle } from '@lucide/vue';
import { gsap } from 'gsap';
import { prefersReducedMotion } from '@/lib/motion';
import { logout } from '@/routes';
import tasks from '@/routes/tasks';
import projects from '@/routes/projects';
import users from '@/routes/users';
import { useTheme } from '@/composables/useTheme';

interface Props {
    open: boolean;
}

interface Emits {
    close: [];
}

const props = defineProps<Props>();
defineEmits<Emits>();

const page = usePage();
const { currentTheme, setTheme } = useTheme();

// Collapsed state (Narrow Dock vs Expanded) matching reference image
const isCollapsed = ref(false);

function toggleCollapse(): void {
    isCollapsed.value = !isCollapsed.value;
}

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

const sidebarItems = computed(() => [
    {
        label: 'Dashboard',
        href: '/dashboard',
        icon: 'Home',
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
        label: 'Reports',
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
    {
        label: 'Settings',
        href: '/settings',
        icon: 'Settings',
        permission: 'settings.view',
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

function handleToggleTheme(): void {
    setTheme(currentTheme.value === 'dark' ? 'light' : 'dark');
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
        class="fixed inset-y-0 left-0 z-50 flex h-full flex-col justify-between overflow-y-auto transition-all duration-300 select-none lg:sticky lg:top-4 lg:z-30 lg:h-[calc(100vh-2rem)] lg:translate-x-0"
        :class="[
            isCollapsed ? 'p-3 lg:w-[84px]' : 'p-5 lg:w-[255px]',
            'w-[260px] p-5',
            'rounded-[32px] border shadow-xl',
            'border-slate-200/80 bg-white text-slate-800 shadow-slate-200/50',
            'dark:border-indigo-400/20 dark:bg-[#5B50E5] dark:text-white dark:shadow-indigo-950/40',
        ]"
        aria-label="Navigasi Utama"
    >
        <!-- Top Section: Brand + Collapse Toggle Button -->
        <div class="flex flex-col">
            <div class="relative flex items-center justify-between pt-1 pb-6">
                <!-- Expanded Brand (Logo Mark + DigitalCode) -->
                <Link
                    v-if="!isCollapsed"
                    href="/dashboard"
                    class="group flex items-center gap-3 transition-opacity hover:opacity-90"
                >
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-slate-900 p-2 shadow-sm transition-transform duration-200 group-hover:scale-105 dark:bg-white"
                    >
                        <img
                            src="/images/logo-mark.png"
                            alt="DigitalCode"
                            class="h-full w-full object-contain"
                        />
                    </div>
                    <span
                        class="text-lg font-black tracking-tight text-slate-900 dark:text-white"
                    >
                        DigitalCode
                    </span>
                </Link>

                <!-- Collapsed Brand (Logo Mark only, centered) -->
                <div v-else class="flex w-full items-center justify-center">
                    <Link
                        href="/dashboard"
                        class="flex h-10 w-10 items-center justify-center rounded-2xl bg-slate-900 p-2 shadow-sm dark:bg-white"
                        title="DigitalCode Dashboard"
                    >
                        <img
                            src="/images/logo-mark.png"
                            alt="DigitalCode"
                            class="h-full w-full object-contain"
                        />
                    </Link>
                </div>

                <!-- Desktop Collapse/Expand Circular Toggle Button (Matches Reference Exact Circle) -->
                <button
                    type="button"
                    class="hidden h-6 w-6 items-center justify-center rounded-full border border-slate-200/80 bg-white text-slate-500 shadow-xs transition hover:scale-110 lg:flex dark:border-white/20 dark:bg-white/10 dark:text-white"
                    :class="
                        isCollapsed ? 'absolute top-2.5 -right-1' : 'relative'
                    "
                    :title="isCollapsed ? 'Expand sidebar' : 'Collapse sidebar'"
                    @click="toggleCollapse"
                >
                    <ChevronLeft
                        v-if="!isCollapsed"
                        class="h-3.5 w-3.5 stroke-[2.5]"
                    />
                    <ChevronRight v-else class="h-3.5 w-3.5 stroke-[2.5]" />
                </button>

                <!-- Mobile Drawer Close Button -->
                <button
                    type="button"
                    class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700 lg:hidden dark:text-white/80 dark:hover:bg-white/10 dark:hover:text-white"
                    @click="$emit('close')"
                    aria-label="Tutup menu"
                >
                    <component :is="getIcon('X')" class="h-5 w-5" />
                </button>
            </div>

            <!-- Navigation Links List -->
            <nav class="flex flex-col gap-1.5 pt-1">
                <Link
                    v-for="item in allowedItems"
                    :key="item.href"
                    :href="item.href"
                    class="group relative flex items-center rounded-2xl transition-all duration-200"
                    :class="[
                        isCollapsed
                            ? 'mx-auto h-11 w-11 justify-center p-0'
                            : 'gap-3.5 px-4 py-3',
                        isActive(item.href)
                            ? 'bg-[#5B50E5] font-bold text-white shadow-md shadow-indigo-500/25 dark:bg-[#4338CA] dark:shadow-indigo-950/50'
                            : 'font-medium text-slate-600 hover:bg-slate-100/80 hover:text-slate-900 dark:text-white/80 dark:hover:bg-white/15 dark:hover:text-white',
                    ]"
                    :title="isCollapsed ? item.label : undefined"
                    @click="isMobile() ? $emit('close') : undefined"
                >
                    <!-- Icon -->
                    <component
                        :is="getIcon(item.icon)"
                        class="h-5 w-5 shrink-0 stroke-[2] transition-transform duration-200 group-hover:scale-110"
                        :class="
                            isActive(item.href)
                                ? 'text-white'
                                : 'text-slate-400 group-hover:text-slate-800 dark:text-white/70 dark:group-hover:text-white'
                        "
                    />

                    <!-- Label (Visible when expanded) -->
                    <span
                        v-if="!isCollapsed"
                        class="text-[13.5px] tracking-tight whitespace-nowrap"
                    >
                        {{ item.label }}
                    </span>

                    <!-- Desktop Collapsed Tooltip -->
                    <div
                        v-if="isCollapsed"
                        class="pointer-events-none absolute left-full z-50 ml-3 hidden rounded-xl bg-slate-900 px-3 py-1.5 text-xs font-semibold whitespace-nowrap text-white shadow-xl group-hover:block dark:bg-white dark:text-slate-900"
                    >
                        {{ item.label }}
                    </div>
                </Link>
            </nav>
        </div>

        <!-- Bottom Section: Premium Card + Support Button + Dark Mode Switch -->
        <div class="mt-6 flex flex-col gap-3.5">
            <!-- 1. "Get more power with our Premium features now!" Card (Exact Reference Match) -->
            <div
                v-if="!isCollapsed"
                class="relative overflow-hidden rounded-2xl border p-4 transition-all"
                :class="
                    currentTheme === 'dark'
                        ? 'border-white/20 bg-white text-slate-900 shadow-md'
                        : 'border-slate-100 bg-slate-50 text-slate-800'
                "
            >
                <div class="relative z-10 flex flex-col">
                    <p class="text-[11.5px] leading-relaxed font-semibold">
                        Get more power with our
                        <span class="font-extrabold text-[#5B50E5]"
                            >Premium</span
                        >
                        features now!
                    </p>

                    <!-- Circular Action Arrow Button & Mini Illustration -->
                    <div class="mt-3 flex items-center justify-between">
                        <button
                            type="button"
                            class="flex h-6 w-6 items-center justify-center rounded-full bg-[#5B50E5] text-white shadow-xs transition hover:scale-110"
                            @click="handleSupportClick"
                            title="Learn more"
                        >
                            <ChevronRight class="h-3.5 w-3.5 stroke-[2.5]" />
                        </button>

                        <!-- Isometric Illustration representation -->
                        <div
                            class="flex h-9 w-12 items-center justify-center rounded-xl bg-indigo-50 text-base shadow-2xs dark:bg-indigo-100"
                        >
                            📊🚀
                        </div>
                    </div>
                </div>
            </div>

            <!-- Mini Collapsed Promo Indicator (when collapsed) -->
            <div
                v-else
                class="flex flex-col items-center justify-center rounded-2xl border p-2 text-center"
                :class="
                    currentTheme === 'dark'
                        ? 'border-white/20 bg-white text-slate-900'
                        : 'border-slate-100 bg-slate-50 text-slate-800'
                "
            >
                <span class="text-[9px] font-bold text-[#5B50E5]">PRO</span>
                <button
                    type="button"
                    class="mt-1 flex h-5 w-5 items-center justify-center rounded-full bg-[#5B50E5] text-white"
                    @click="handleSupportClick"
                >
                    <ChevronRight class="h-3 w-3 stroke-[2.5]" />
                </button>
            </div>

            <!-- 2. Support Pill Button (Exact Reference Match) -->
            <button
                type="button"
                class="group flex w-full items-center justify-center gap-2 rounded-full px-4 py-2.5 text-xs font-bold shadow-sm transition-all hover:scale-[1.02] active:scale-98"
                :class="
                    currentTheme === 'dark'
                        ? 'bg-white text-[#5B50E5] shadow-indigo-950/20'
                        : 'bg-[#5B50E5] text-white shadow-indigo-500/20'
                "
                @click="handleSupportClick"
            >
                <MessageCircle class="h-4 w-4 stroke-[2.3]" />
                <span v-if="!isCollapsed">Support</span>
            </button>

            <!-- 3. Dark/Light Mode iOS-style Switch Toggle (Exact Reference Match) -->
            <div
                class="flex items-center justify-between rounded-2xl px-2 py-1.5 transition-colors"
                :class="
                    currentTheme === 'dark'
                        ? 'bg-white/10 text-white'
                        : 'bg-slate-100 text-slate-700'
                "
            >
                <!-- Label with Sun/Moon Icon -->
                <div
                    v-if="!isCollapsed"
                    class="flex items-center gap-2 text-xs font-bold"
                >
                    <component
                        :is="getIcon(currentTheme === 'dark' ? 'Moon' : 'Sun')"
                        class="h-4 w-4"
                        :class="
                            currentTheme === 'dark'
                                ? 'text-amber-300'
                                : 'text-amber-500'
                        "
                    />
                    <span>{{
                        currentTheme === 'dark' ? 'Light Mode' : 'Dark Mode'
                    }}</span>
                </div>

                <!-- Toggle Switch Control -->
                <button
                    type="button"
                    role="switch"
                    :aria-checked="currentTheme === 'dark'"
                    class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                    :class="[
                        currentTheme === 'dark'
                            ? 'bg-white dark:bg-white'
                            : 'bg-slate-300',
                        isCollapsed ? 'mx-auto' : '',
                    ]"
                    @click="handleToggleTheme"
                >
                    <span
                        class="pointer-events-none inline-block h-4 w-4 transform rounded-full shadow-md ring-0 transition duration-200 ease-in-out"
                        :class="
                            currentTheme === 'dark'
                                ? 'translate-x-4 bg-[#5B50E5]'
                                : 'translate-x-0 bg-white'
                        "
                    />
                </button>
            </div>
        </div>

        <!-- Support Modal Popup -->
        <div
            v-if="showSupportModal"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4 backdrop-blur-xs"
            @click.self="closeSupportModal"
        >
            <div
                class="w-full max-w-md rounded-3xl bg-white p-6 text-slate-900 shadow-2xl dark:bg-[#1A1D30] dark:text-white"
            >
                <div class="flex items-center justify-between pb-3">
                    <h3 class="text-lg font-bold">
                        Pusat Bantuan & Fitur Premium
                    </h3>
                    <button
                        type="button"
                        class="rounded-xl p-1.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-white/10"
                        @click="closeSupportModal"
                    >
                        <component :is="getIcon('X')" class="h-5 w-5" />
                    </button>
                </div>
                <p
                    class="text-sm leading-relaxed text-slate-600 dark:text-slate-300"
                >
                    Nikmati kemampuan penuh DigitalCode dengan fitur manajemen
                    proyek, alur kerja otomatis, dan analitik real-time. Hubungi
                    support kami di
                    <a
                        href="mailto:support@project.local"
                        class="font-semibold text-indigo-600 underline dark:text-indigo-400"
                    >
                        support@project.local </a
                    >.
                </p>
                <div class="mt-6 flex justify-end">
                    <button
                        type="button"
                        class="rounded-full bg-[#5B50E5] px-5 py-2 text-xs font-bold text-white transition hover:bg-[#4338CA]"
                        @click="closeSupportModal"
                    >
                        Tutup
                    </button>
                </div>
            </div>
        </div>
    </aside>
</template>
