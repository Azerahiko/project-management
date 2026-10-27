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
        class="min-h-screen bg-[#F4F7FB] p-2.5 font-sans transition-colors duration-300 sm:p-4 lg:p-6 dark:bg-[#0D0F18]"
    >
        <!-- Mobile Sidebar Backdrop -->
        <div
            v-if="sidebarOpen"
            class="animate-fade-in fixed inset-0 z-40 bg-slate-900/50 backdrop-blur-xs lg:hidden"
            @click="closeSidebar"
            aria-hidden="true"
        />

        <!-- App Window Container (Clean Neo-SaaS Window with rounded-[36px]) -->
        <div
            class="relative mx-auto flex min-h-[95vh] w-full max-w-[1780px] overflow-hidden rounded-[28px] border border-slate-200/70 bg-white shadow-[0_20px_50px_-20px_rgba(15,23,42,0.06)] sm:rounded-[36px] dark:border-white/[0.06] dark:bg-[#151829] dark:shadow-[0_25px_60px_-15px_rgba(0,0,0,0.5)]"
        >
            <!-- White Sidebar (Vendify Aesthetic with Pill Badges) -->
            <Sidebar :open="sidebarOpen" @close="closeSidebar" />

            <!-- Main Content Canvas (Soft #F4F7FB tint) -->
            <div
                class="flex min-w-0 flex-1 flex-col overflow-y-auto bg-[#F4F7FB] dark:bg-[#0E101D]"
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
