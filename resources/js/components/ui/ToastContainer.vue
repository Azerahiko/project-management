<script setup lang="ts">
import { onMounted, onUnmounted, ref, watch, nextTick } from 'vue';
import { gsap } from 'gsap';
import { useToast, type Toast } from '@/composables/useToast';
import { getIcon } from '@/components/sidebar/icons';

const { toasts, dismiss } = useToast();

let ctx: gsap.Context | null = null;
const isMobile = ref(false);

function checkMobile(): void {
    isMobile.value = window.innerWidth < 640;
}

function getIconName(type: Toast['type']): string {
    switch (type) {
        case 'success':
            return 'CheckCircle2';
        case 'error':
            return 'X';
        case 'warning':
            return 'AlertTriangle';
        default:
            return 'FileText';
    }
}

function getToastClass(type: Toast['type']): string {
    switch (type) {
        case 'success':
            return 'toast-success';
        case 'error':
            return 'toast-error';
        case 'warning':
            return 'toast-warning';
        default:
            return 'toast-info';
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
                    const el = toastContainer.value?.querySelector(`[data-toast-id="${toast.id}"]`);
                    if (el) {
                        gsap.fromTo(
                            el,
                            { opacity: 0, x: isMobile.value ? 0 : 300, y: isMobile.value ? 20 : 0 },
                            { opacity: 1, x: 0, y: 0, duration: 0.3, ease: 'power2.out', delay: i * 0.08 },
                        );
                    }
                });
            });
        });
    },
    { deep: true, immediate: true },
);

const toastContainer = ref<HTMLElement | null>(null);

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
            :class="[
                'fixed z-[100] flex flex-col gap-2 pointer-events-none',
                isMobile
                    ? 'bottom-4 left-4 right-4'
                    : 'top-4 right-4',
            ]"
            aria-live="polite"
            aria-label="Notifikasi"
        >
            <div
                v-for="toast in toasts"
                :key="toast.id"
                :data-toast-id="toast.id"
                :class="[getToastClass(toast.type), 'pointer-events-auto']"
            >
                <div class="flex items-start gap-3">
                    <component
                        :is="getIcon(getIconName(toast.type))"
                        class="h-5 w-5 flex-shrink-0 mt-0.5"
                        :class="{
                            'text-primary-500': toast.type === 'success' || toast.type === 'info',
                            'text-accent-700 dark:text-accent-300': toast.type === 'error' || toast.type === 'warning',
                        }"
                    />
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-surface-900 dark:text-surface-100">{{ toast.title }}</p>
                        <p v-if="toast.message" class="mt-0.5 text-sm text-surface-600 dark:text-surface-400">
                            {{ toast.message }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="flex-shrink-0 rounded-lg p-1 text-surface-400 hover:bg-surface-100 dark:hover:bg-surface-800"
                        @click="dismiss(toast.id)"
                        aria-label="Tutup"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
