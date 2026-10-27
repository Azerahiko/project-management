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
            { opacity: 0, y: 12 },
            { opacity: 1, y: 0, duration: 0.35, ease: 'power2.out' },
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
        class="relative min-h-screen bg-[#F0F2F9] p-2 transition-colors duration-300 sm:p-4 lg:p-6 dark:bg-[#0B0D19]"
    >
        <!-- Background subtle decorative ambient curved shapes -->
        <div
            class="pointer-events-none fixed inset-0 overflow-hidden opacity-40 dark:opacity-20"
            aria-hidden="true"
        >
            <div
                class="absolute -top-[20%] -left-[10%] h-[600px] w-[600px] rounded-full bg-gradient-to-br from-indigo-300/30 to-purple-200/0 blur-3xl"
            />
            <div
                class="absolute -bottom-[20%] -right-[10%] h-[700px] w-[700px] rounded-full bg-gradient-to-tl from-indigo-400/20 to-blue-200/0 blur-3xl"
            />
        </div>

        <!-- Sidebar backdrop (mobile) -->
        <div
            v-if="sidebarOpen"
            class="animate-fade-in fixed inset-0 z-40 bg-slate-900/50 backdrop-blur-xs lg:hidden"
            @click="closeSidebar"
            aria-hidden="true"
        />

        <!-- Bento Canvas Container -->
        <div
            class="bento-canvas relative mx-auto flex min-h-[96vh] w-full max-w-[1720px] overflow-hidden shadow-2xl transition-all"
        >
            <!-- Curved Indigo Dock Sidebar -->
            <Sidebar :open="sidebarOpen" @close="closeSidebar" />

            <!-- Main Content Canvas Area -->
            <div class="flex min-w-0 flex-1 flex-col overflow-y-auto">
                <!-- Top Navigation Bar -->
                <TopBar
                    :sidebar-open="sidebarOpen"
                    @toggle-sidebar="toggleSidebar"
                />

                <!-- Page Content Body -->
                <main ref="main" class="flex-1 p-4 sm:p-6 lg:p-8">
                    <slot />
                </main>
            </div>
        </div>

        <ToastContainer />
    </div>
</template>
