<script setup lang="ts">
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { getIcon } from '@/components/sidebar/icons';
import { prefersReducedMotion } from '@/lib/motion';
import { gsap } from 'gsap';

interface Props {
    open: boolean;
    title: string;
    size?: 'sm' | 'md' | 'lg';
}

const props = withDefaults(defineProps<Props>(), {
    size: 'md',
});

const emit = defineEmits<{ close: [] }>();

const panel = ref<HTMLElement | null>(null);

const sizes = {
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-lg',
};

let ctx: gsap.Context | null = null;

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        emit('close');
    }
}

watch(
    () => props.open,
    async (open) => {
        document.body.style.overflow = open ? 'hidden' : '';

        if (!open) {
            ctx?.revert();
            ctx = null;
            return;
        }

        await nextTick();

        if (!panel.value) return;

        ctx?.revert();

        if (prefersReducedMotion()) {
            gsap.set(panel.value, { opacity: 1, scale: 1, y: 0 });
            return;
        }

        ctx = gsap.context(() => {
            gsap.fromTo(
                panel.value,
                { opacity: 0, scale: 0.96, y: 12 },
                { opacity: 1, scale: 1, y: 0, duration: 0.22, ease: 'power2.out' },
            );
        }, panel.value);
    },
);

onMounted(() => document.addEventListener('keydown', onKeydown));

onUnmounted(() => {
    document.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
    ctx?.revert();
    ctx = null;
});
</script>

<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            :aria-label="title"
        >
            <div
                class="modal-backdrop"
                aria-hidden="true"
                @click="$emit('close')"
            />
            <div
                ref="panel"
                class="modal-content relative w-full"
                :class="sizes[size]"
            >
                <div
                    class="flex items-start justify-between border-b border-white/20 p-5 dark:border-surface-700/30"
                >
                    <h2 class="text-lg font-semibold text-surface-900 dark:text-surface-100">
                        {{ title }}
                    </h2>
                    <button
                        type="button"
                        class="rounded-lg p-1 text-surface-400 transition-colors hover:bg-surface-100 dark:hover:bg-surface-800"
                        aria-label="Tutup"
                        @click="$emit('close')"
                    >
                        <component :is="getIcon('X')" class="h-5 w-5" />
                    </button>
                </div>
                <div class="p-5">
                    <slot />
                </div>
                <div
                    v-if="$slots.footer"
                    class="flex flex-col sm:flex-row justify-end gap-2 border-t border-white/20 p-5 dark:border-surface-700/30"
                >
                    <slot name="footer" />
                </div>
            </div>
        </div>
    </Teleport>
</template>