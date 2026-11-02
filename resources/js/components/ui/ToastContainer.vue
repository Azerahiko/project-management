<script setup lang="ts">
import {
    computed,
    nextTick,
    onMounted,
    onUnmounted,
    ref,
    watch,
    type Component,
} from 'vue';
import { gsap } from 'gsap';
import {
    AlertTriangle,
    CheckCircle2,
    Info,
    X,
    XCircle,
} from '@lucide/vue';
import { useToast, type Toast } from '@/composables/useToast';

const { toasts, dismiss, pause, resume } = useToast();

const toastContainer = ref<HTMLElement | null>(null);
const isMobile = ref(false);
const hoveredId = ref<number | null>(null);

let ctx: gsap.Context | null = null;

const icons: Record<Toast['type'], Component> = {
    success: CheckCircle2,
    error: XCircle,
    warning: AlertTriangle,
    info: Info,
};

const themes: Record<
    Toast['type'],
    { border: string; tile: string; bar: string }
> = {
    success: {
        border: 'border-primary-200/80 dark:border-primary-800/70',
        tile: 'bg-primary-50 text-primary-700 ring-primary-100 dark:bg-primary-900/40 dark:text-primary-300 dark:ring-primary-800/60',
        bar: 'bg-primary-500',
    },
    error: {
        border: 'border-rose-200/80 dark:border-rose-900/70',
        tile: 'bg-rose-50 text-rose-600 ring-rose-100 dark:bg-rose-950/40 dark:text-rose-300 dark:ring-rose-900/60',
        bar: 'bg-rose-500',
    },
    warning: {
        border: 'border-accent-200/80 dark:border-accent-800/70',
        tile: 'bg-accent-50 text-accent-700 ring-accent-100 dark:bg-accent-900/40 dark:text-accent-300 dark:ring-accent-800/60',
        bar: 'bg-accent-500',
    },
    info: {
        border: 'border-surface-200/80 dark:border-surface-700/80',
        tile: 'bg-surface-100 text-surface-600 ring-surface-200 dark:bg-surface-800 dark:text-surface-300 dark:ring-surface-700',
        bar: 'bg-surface-400 dark:bg-surface-500',
    },
};

function checkMobile(): void {
    isMobile.value = window.innerWidth < 640;
}

function themeFor(type: Toast['type']) {
    return themes[type] ?? themes.info;
}

function onEnter(id: number): void {
    hoveredId.value = id;
    pause(id);
}

function onLeave(id: number): void {
    if (hoveredId.value === id) {
        hoveredId.value = null;
        resume(id);
    }
}

watch(
    toasts,
    (newToasts) => {
        if (newToasts.length === 0) {
            ctx?.revert();
            ctx = null;
            return;
        }

        nextTick(() => {
            if (!toastContainer.value) return;

            ctx?.revert();
            ctx = gsap.context(() => {
                newToasts.forEach((toast, i) => {
                    const el = toastContainer.value?.querySelector(
                        `[data-toast-id="${toast.id}"]`,
                    );

                    if (el) {
                        gsap.fromTo(
                            el,
                            {
                                opacity: 0,
                                x: isMobile.value ? 0 : 40,
                                y: isMobile.value ? 18 : 0,
                                scale: 0.97,
                            },
                            {
                                opacity: 1,
                                x: 0,
                                y: 0,
                                scale: 1,
                                duration: 0.32,
                                ease: 'power3.out',
                                delay: i * 0.06,
                            },
                        );
                    }
                });
            });
        });
    },
    { deep: true, immediate: true },
);

const containerClass = computed(() =>
    isMobile.value
        ? 'bottom-4 left-4 right-4'
        : 'top-4 right-4 sm:w-[24rem]',
);

onMounted(() => {
    checkMobile();
    window.addEventListener('resize', checkMobile);
});

onUnmounted(() => {
    window.removeEventListener('resize', checkMobile);
    ctx?.revert();
    ctx = null;
});
</script>

<template>
    <Teleport to="body">
        <div
            ref="toastContainer"
            class="pointer-events-none fixed z-[100] flex flex-col gap-2.5"
            :class="containerClass"
            aria-live="polite"
            aria-label="Notifikasi"
        >
            <div
                v-for="toast in toasts"
                :key="toast.id"
                :data-toast-id="toast.id"
                class="pointer-events-auto relative w-full overflow-hidden rounded-2xl border bg-white/95 p-3.5 pr-10 shadow-xl shadow-surface-950/5 backdrop-blur-xl dark:bg-surface-900/95 dark:shadow-black/20"
                :class="themeFor(toast.type).border"
                :role="
                    toast.type === 'error' || toast.type === 'warning'
                        ? 'alert'
                        : 'status'
                "
                @mouseenter="onEnter(toast.id)"
                @mouseleave="onLeave(toast.id)"
            >
                <div class="flex items-start gap-3">
                    <span
                        class="grid h-9 w-9 shrink-0 place-items-center rounded-xl ring-1"
                        :class="themeFor(toast.type).tile"
                    >
                        <component
                            :is="icons[toast.type]"
                            class="h-4 w-4"
                        />
                    </span>

                    <div class="min-w-0 flex-1">
                        <p
                            class="text-sm leading-5 font-semibold text-surface-950 dark:text-surface-50"
                        >
                            {{ toast.title }}
                        </p>
                        <p
                            v-if="toast.message"
                            class="mt-0.5 text-xs leading-5 text-surface-500 dark:text-surface-400"
                        >
                            {{ toast.message }}
                        </p>
                        <button
                            v-if="toast.action"
                            type="button"
                            class="mt-2 inline-flex items-center gap-1 text-[11px] font-semibold text-primary-700 transition hover:underline dark:text-primary-300"
                            @click="
                                toast.action.onClick();
                                dismiss(toast.id);
                            "
                        >
                            {{ toast.action.label }}
                        </button>
                    </div>
                </div>

                <button
                    type="button"
                    class="absolute top-2 right-2 grid h-6 w-6 place-items-center rounded-lg text-surface-400 transition hover:bg-surface-100 hover:text-surface-700 dark:hover:bg-surface-800 dark:hover:text-surface-200"
                    aria-label="Tutup notifikasi"
                    @click="dismiss(toast.id)"
                >
                    <X class="h-3.5 w-3.5" />
                </button>

                <div
                    v-if="toast.duration && toast.duration > 0"
                    class="absolute inset-x-0 bottom-0 h-0.5 bg-surface-100 dark:bg-surface-800"
                    aria-hidden="true"
                >
                    <div
                        class="toast-progress h-full rounded-full"
                        :class="themeFor(toast.type).bar"
                        :style="{
                            animationDuration: `${toast.duration}ms`,
                            animationPlayState:
                                hoveredId === toast.id ? 'paused' : 'running',
                        }"
                    />
                </div>
            </div>
        </div>
    </Teleport>
</template>
