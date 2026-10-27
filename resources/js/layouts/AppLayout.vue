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
        class="min-h-screen bg-[#F0F2F9] p-3 font-sans transition-colors duration-300 sm:p-5 lg:p-7 dark:bg-[#0B0D19]"
    >
        <!-- Mobile Sidebar Backdrop -->
        <div
            v-if="sidebarOpen"
            class="animate-fade-in fixed inset-0 z-40 bg-slate-900/50 backdrop-blur-xs lg:hidden"
            @click="closeSidebar"
            aria-hidden="true"
        />

        <!-- Main Fullscreen Card Canvas (Rounded [44px] Matching Photo) -->
        <div
            class="relative mx-auto flex min-h-[95vh] w-full max-w-[1780px] rounded-[36px] border border-slate-100/90 bg-white shadow-[0_20px_60px_-15px_rgba(26,32,60,0.06)] sm:rounded-[44px] dark:border-white/[0.05] dark:bg-[#141628] dark:shadow-[0_25px_60px_-15px_rgba(0,0,0,0.6)]"
        >
            <!-- Curved Wave Purple Dock Sidebar -->
            <Sidebar :open="sidebarOpen" @close="closeSidebar" />

            <!-- Main Content Area -->
            <div class="flex min-w-0 flex-1 flex-col overflow-y-auto">
                <TopBar
                    :sidebar-open="sidebarOpen"
                    @toggle-sidebar="toggleSidebar"
                />
                <main ref="main" class="flex-1 p-5 sm:p-7 lg:p-9">
                    <slot />
                </main>
            </div>
        </div>

        <ToastContainer />
    </div>
</template>
