<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, watch } from 'vue';
import { usePage, Link, router } from '@inertiajs/vue3';
import { getIcon } from './icons';
import { menuSections } from './menu';
import { gsap } from 'gsap';
import { prefersReducedMotion } from '@/lib/motion';
import { logout } from '@/routes';

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

function isActiveRoute(href: string): boolean {
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

function can(perm: string): boolean {
    if (!perm) return true;
    return userPermissions.value.includes(perm);
}

const unreadNotifications = computed(() => {
    return (page.props.stats as any)?.notifications_unread ?? 0;
});

// Flattened list of allowed menu items for dock navigation
const dockItems = computed(() => {
    const items: Array<{
        label: string;
        href: string;
        icon: string;
        permission: string;
        badge?: string;
        isPro?: boolean;
    }> = [];

    menuSections.forEach((section) => {
        section.items.forEach((item) => {
            if (can(item.permission)) {
                items.push({
                    ...item,
                    badge:
                        item.icon === 'Bell' && unreadNotifications.value > 0
                            ? String(unreadNotifications.value)
                            : undefined,
                    isPro: item.icon === 'BarChart3',
                });
            }
        });
    });

    return items;
});

const MOBILE_BREAKPOINT = 1024;
const SIDEBAR_WIDTH = 280;

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
    <!-- Desktop Curved Indigo Dock & Mobile Drawer -->
    <aside
        data-sidebar
        class="dock-indigo fixed inset-y-0 left-0 z-50 flex h-full w-[280px] flex-col justify-between p-4 shadow-2xl transition-transform duration-300 lg:static lg:z-30 lg:h-auto lg:w-[88px] lg:translate-x-0 lg:rounded-r-[36px] lg:px-3 lg:py-6 lg:shadow-none"
        aria-label="Navigasi utama"
    >
        <!-- Top Section: Logo & Mobile Close -->
        <div class="flex flex-col items-center">
            <div class="flex w-full items-center justify-between lg:justify-center">
                <!-- Brand Mark Link -->
                <Link
                    :href="'/dashboard'"
                    class="group relative flex h-12 w-12 items-center justify-center rounded-2xl bg-white p-2 shadow-md transition-all duration-300 hover:scale-105 hover:shadow-lg dark:bg-[#1E2245]"
                    :title="page.props.app.name"
                >
                    <img
                        src="/images/logo-mark.png"
                        :alt="page.props.app.name"
                        class="h-8 w-8 object-contain transition-transform duration-300 group-hover:scale-110"
                    />
                </Link>

                <!-- Mobile Close Button -->
                <button
                    type="button"
                    class="rounded-xl p-2 text-white/80 transition-colors hover:bg-white/10 hover:text-white lg:hidden"
                    aria-label="Tutup menu"
                    @click="$emit('close')"
                >
                    <component :is="getIcon('X')" class="h-6 w-6" />
                </button>
            </div>

            <div class="my-4 h-px w-10 bg-white/15 lg:my-6" />

            <!-- Navigation Dock Icons -->
            <nav class="flex w-full flex-col items-center gap-2.5 overflow-y-auto">
                <Link
                    v-for="item in dockItems"
                    :key="item.href"
                    :href="item.href"
                    class="group relative flex w-full items-center gap-3.5 rounded-2xl p-3 transition-all duration-200 lg:w-12 lg:justify-center lg:p-3"
                    :class="
                        isActiveRoute(item.href)
                            ? 'bg-white text-[#5B50E5] shadow-md shadow-indigo-950/20 font-semibold dark:bg-[#202444] dark:text-[#8D82FF]'
                            : 'text-white/70 hover:bg-white/15 hover:text-white dark:hover:bg-white/10'
                    "
                    @click="isMobile() ? $emit('close') : undefined"
                >
                    <div class="relative flex items-center justify-center">
                        <component
                            :is="getIcon(item.icon)"
                            class="h-5 w-5 transition-transform duration-200 group-hover:scale-110"
                        />

                        <!-- Pro Badge -->
                        <span
                            v-if="item.isPro"
                            class="absolute -top-1.5 -right-2 rounded-full bg-emerald-400 px-1 py-0.2 text-[8px] font-black tracking-wider text-slate-900 uppercase shadow-xs"
                        >
                            PRO
                        </span>

                        <!-- Notification Badge / Dot -->
                        <span
                            v-if="item.badge"
                            class="absolute -top-1 -right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-amber-400 px-1 text-[9px] font-bold text-slate-900 shadow-xs"
                        >
                            {{ item.badge }}
                        </span>
                    </div>

                    <!-- Label (Visible on mobile, floating tooltip on desktop) -->
                    <span class="text-sm lg:hidden">{{ item.label }}</span>

                    <!-- Desktop Floating Tooltip -->
                    <div
                        class="pointer-events-none absolute left-full ml-3 hidden whitespace-nowrap rounded-xl bg-slate-900/90 px-3 py-1.5 text-xs font-medium text-white shadow-lg backdrop-blur-sm transition-all group-hover:block dark:bg-slate-800/95 z-50"
                    >
                        {{ item.label }}
                    </div>
                </Link>
            </nav>
        </div>

        <!-- Bottom Section: Quick Logout / Mobile App Info -->
        <div class="mt-4 flex w-full flex-col items-center gap-3 pt-4 border-t border-white/15 lg:mt-0 lg:pt-0 lg:border-t-0">
            <!-- Logout Button -->
            <button
                type="button"
                class="group relative flex w-full items-center gap-3.5 rounded-2xl p-3 text-white/70 transition-all duration-200 hover:bg-rose-500/20 hover:text-rose-200 lg:w-12 lg:justify-center lg:p-3"
                title="Keluar"
                @click="handleLogout"
            >
                <component
                    :is="getIcon('LogOut')"
                    class="h-5 w-5 transition-transform duration-200 group-hover:scale-110"
                />
                <span class="text-sm lg:hidden">Keluar</span>

                <!-- Desktop Tooltip -->
                <div
                    class="pointer-events-none absolute left-full ml-3 hidden whitespace-nowrap rounded-xl bg-rose-900/90 px-3 py-1.5 text-xs font-medium text-white shadow-lg backdrop-blur-sm transition-all group-hover:block z-50"
                >
                    Keluar
                </div>
            </button>
        </div>
    </aside>
</template>
