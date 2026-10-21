<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Sidebar from '@/components/sidebar/Sidebar.vue';
import TopBar from '@/components/navbar/TopBar.vue';
import ToastContainer from '@/components/ui/ToastContainer.vue';
import { prefersReducedMotion } from '@/lib/motion';
import { gsap } from 'gsap';

const page = usePage();
const sidebarOpen = ref(false);
const main = ref<HTMLElement | null>(null);

let ctx: gsap.Context | null = null;

function toggleSidebar(): void {
    sidebarOpen.value = !sidebarOpen.value;
}

function closeSidebar(): void {
    sidebarOpen.value = false;
}

function animateEnter(): void {
    if (!main.value) return;

    ctx?.revert();

    if (prefersReducedMotion()) {
        gsap.set(main.value, { opacity: 1, y: 0 });
        return;
    }

    ctx = gsap.context(() => {
        gsap.fromTo(
            main.value,
            { opacity: 0, y: 8 },
            { opacity: 1, y: 0, duration: 0.3, ease: 'power2.out' },
        );
    }, main.value);
}

const pageKey = computed(() => (page as any).key);

watch(
    pageKey,
    async () => {
        await nextTick();
        animateEnter();
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    ctx?.revert();
    ctx = null;
});
</script>

<template>
    <div class="min-h-screen bg-surface-50 dark:bg-surface-950">
        <!-- Sidebar backdrop (mobile) -->
        <div
            v-if="sidebarOpen"
            class="animate-fade-in fixed inset-0 z-40 bg-black/50 backdrop-blur-sm lg:hidden"
            @click="closeSidebar"
            aria-hidden="true"
        />

        <!-- Sidebar -->
        <Sidebar :open="sidebarOpen" @close="closeSidebar" />

        <!-- Main content area -->
        <div class="lg:pl-64">
            <!-- Top Bar -->
            <TopBar
                :sidebar-open="sidebarOpen"
                @toggle-sidebar="toggleSidebar"
            />

            <!-- Page Content -->
            <main ref="main" class="p-4 sm:p-6 lg:p-8">
                <slot />
            </main>
        </div>

        <ToastContainer />
    </div>
</template>
