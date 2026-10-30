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
            { opacity: 0, y: 10 },
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
    <div
        class="min-h-screen bg-surface-50 font-sans text-surface-900 transition-colors duration-300 dark:bg-surface-950 dark:text-surface-50"
    >
        <!-- Mobile Sidebar Backdrop -->
        <div
            v-if="sidebarOpen"
            class="animate-fade-in fixed inset-0 z-40 bg-surface-950/50 backdrop-blur-xs lg:hidden"
            @click="closeSidebar"
            aria-hidden="true"
        />

        <div class="flex min-h-screen w-full">
            <!-- Modern Frosted Sidebar -->
            <Sidebar :open="sidebarOpen" @close="closeSidebar" />

            <!-- Main Content Canvas -->
            <div
                class="flex min-w-0 flex-1 flex-col bg-surface-50 dark:bg-surface-950"
            >
                <TopBar
                    :sidebar-open="sidebarOpen"
                    @toggle-sidebar="toggleSidebar"
                />
                <main ref="main" class="flex-1 p-4 sm:p-6 lg:p-8">
                    <slot />
                </main>
            </div>
        </div>

        <ToastContainer />
    </div>
</template>
