<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, watch } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import SidebarMenu from './SidebarMenu.vue';
import { getIcon } from './icons';
import { menuSections } from './menu';
import { gsap } from 'gsap';
import { prefersReducedMotion } from '@/lib/motion';

interface Props {
    open: boolean;
}

interface Emits {
    close: [];
}

const props = defineProps<Props>();
defineEmits<Emits>();

const page = usePage();

const currentPath = computed(() => window.location.pathname);

function isActiveRoute(href: string): boolean {
    return (
        currentPath.value === href || currentPath.value.startsWith(`${href}/`)
    );
}

const MOBILE_BREAKPOINT = 1024;
const SIDEBAR_WIDTH = 256; // w-64 = 16rem = 256px

let drawerCtx: gsap.Context | null = null;

function drawer(): HTMLElement | null {
    return document.querySelector<HTMLElement>('[data-sidebar]');
}

function isMobile(): boolean {
    return window.innerWidth < MOBILE_BREAKPOINT;
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
        class="glass fixed top-0 left-0 z-50 h-screen w-64 border-r border-white/20 lg:translate-x-0 dark:border-surface-700/30"
        aria-label="Navigasi utama"
    >
        <!-- Brand -->
        <div
            class="flex h-16 items-center gap-3 border-b border-white/20 px-4 dark:border-surface-700/30"
        >
            <div
                class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-lg bg-primary-600 shadow-sm"
            >
                <component
                    :is="getIcon('CheckSquare')"
                    class="h-5 w-5 text-white"
                />
            </div>
            <span
                class="truncate text-lg font-semibold text-surface-900 dark:text-surface-100"
            >
                {{ page.props.app.shortName }}
            </span>
            <button
                type="button"
                class="ml-auto rounded-lg p-2 text-surface-500 hover:bg-surface-100 lg:hidden dark:text-surface-400 dark:hover:bg-surface-800"
                aria-label="Tutup menu"
                @click="$emit('close')"
            >
                <component :is="getIcon('X')" class="h-5 w-5" />
            </button>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 space-y-6 overflow-y-auto px-3 py-4">
            <SidebarMenu
                v-for="section in menuSections"
                :key="section.label"
                :section="section"
                :is-active-route="isActiveRoute"
            />
        </nav>

        <!-- Footer -->
        <div class="border-t border-white/20 p-4 dark:border-surface-700/30">
            <Link
                :href="'/dashboard'"
                class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm text-surface-600 transition-colors duration-150 hover:bg-surface-100 dark:text-surface-400 dark:hover:bg-surface-800"
            >
                <component :is="getIcon('Settings')" class="h-5 w-5" />
                <span>{{ page.props.app.name }}</span>
            </Link>
        </div>
    </aside>
</template>
