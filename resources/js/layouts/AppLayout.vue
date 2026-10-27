<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Sidebar from '@/components/sidebar/Sidebar.vue';
import TopBar from '@/components/navbar/TopBar.vue';
import ToastContainer from '@/components/ui/ToastContainer.vue';
import { prefersReducedMotion } from '@/lib/motion';
import { getIcon } from '@/components/sidebar/icons';
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

        <!-- App Window Container (Matching Reference Frame) -->
        <div
            class="relative mx-auto flex min-h-[95vh] w-full max-w-[1780px] flex-col overflow-hidden rounded-[28px] border border-slate-200/80 bg-white shadow-[0_20px_50px_-20px_rgba(15,23,42,0.06)] sm:rounded-[36px] dark:border-white/[0.06] dark:bg-[#151829] dark:shadow-[0_25px_60px_-15px_rgba(0,0,0,0.5)]"
        >
            <!-- Browser Chrome Header Bar (Exact Match to Reference Photo: Master.com) -->
            <div
                class="border-slate-150/80 hidden h-10 w-full items-center justify-between border-b bg-white px-4 md:flex dark:border-white/5 dark:bg-[#151829]"
            >
                <!-- Left: 3 macOS style window control dots + Chevrons -->
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-1.5">
                        <span class="h-3 w-3 rounded-full bg-[#FF5F56]" />
                        <span class="h-3 w-3 rounded-full bg-[#FFBD2E]" />
                        <span class="h-3 w-3 rounded-full bg-[#27C93F]" />
                    </div>
                    <div class="flex items-center gap-1 text-slate-400">
                        <component
                            :is="getIcon('ChevronLeft')"
                            class="h-3.5 w-3.5 cursor-pointer hover:text-slate-700"
                        />
                        <component
                            :is="getIcon('ChevronRight')"
                            class="h-3.5 w-3.5 cursor-pointer hover:text-slate-700"
                        />
                    </div>
                </div>

                <!-- Center: Address Bar Pill -->
                <div
                    class="flex h-6.5 min-w-[280px] items-center justify-center gap-2 rounded-lg bg-slate-100/80 px-3 text-[11px] font-medium text-slate-600 dark:bg-white/5 dark:text-slate-300"
                >
                    <component
                        :is="getIcon('Lock')"
                        class="h-3 w-3 text-slate-400"
                    />
                    <span>Master.com</span>
                    <component
                        :is="getIcon('Activity')"
                        class="ml-auto h-3 w-3 cursor-pointer text-slate-400 hover:text-slate-600"
                    />
                </div>

                <!-- Right: Browser action icons placeholder -->
                <div class="flex items-center gap-2 text-slate-400">
                    <component
                        :is="getIcon('FileText')"
                        class="h-3.5 w-3.5 cursor-pointer hover:text-slate-700"
                    />
                    <component
                        :is="getIcon('Plus')"
                        class="h-3.5 w-3.5 cursor-pointer hover:text-slate-700"
                    />
                </div>
            </div>

            <!-- Body Frame: Sidebar + Main Content -->
            <div class="flex min-h-0 flex-1 overflow-hidden">
                <!-- Clean White Sidebar -->
                <Sidebar :open="sidebarOpen" @close="closeSidebar" />

                <!-- Main Content Canvas (Soft #F4F7FB) -->
                <div
                    class="flex min-w-0 flex-1 flex-col overflow-y-auto bg-[#F4F7FB] dark:bg-[#0E101D]"
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
        </div>

        <ToastContainer />
    </div>
</template>
