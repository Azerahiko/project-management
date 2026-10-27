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
        class="min-h-screen bg-[#F4F7FB] font-sans text-slate-900 transition-colors duration-300 dark:bg-[#0D0F18] dark:text-slate-100"
    >
        <!-- Mobile Sidebar Backdrop -->
        <div
            v-if="sidebarOpen"
            class="animate-fade-in fixed inset-0 z-40 bg-slate-900/50 backdrop-blur-xs lg:hidden"
            @click="closeSidebar"
            aria-hidden="true"
        />

        <!-- App Layout Structure (Sidebar Left + Main Content Canvas) -->
        <div class="flex min-h-screen w-full">
            <!-- Clean White Sidebar (Pinned & Sticky on Desktop) -->
            <Sidebar :open="sidebarOpen" @close="closeSidebar" />

            <!-- Main Content Canvas (Soft #F4F7FB) -->
            <div
                class="flex min-w-0 flex-1 flex-col bg-[#F4F7FB] dark:bg-[#0E101D]"
            >
                <TopBar
                    :sidebar-open="sidebarOpen"
                    @toggle-sidebar="toggleSidebar"
                />
                <main ref="main" class="flex-1 px-4 pb-8 sm:px-6 lg:px-8">
                    <slot />
                </main>
            </div>
        </div>

        <ToastContainer />
    </div>
</template>
