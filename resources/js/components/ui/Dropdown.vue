<script setup lang="ts">
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { prefersReducedMotion } from '@/lib/motion';
import { gsap } from 'gsap';

interface Props {
    align?: 'left' | 'right';
    label?: string;
    /** Render the trigger slot as-is instead of wrapping it in a button. */
    bare?: boolean;
    triggerClass?: string;
}

const props = withDefaults(defineProps<Props>(), {
    align: 'right',
    bare: false,
    triggerClass: '',
});

const open = ref(false);
const root = ref<HTMLElement | null>(null);
const content = ref<HTMLElement | null>(null);
const isMobile = ref(false);

let ctx: gsap.Context | null = null;

function checkMobile(): void {
    isMobile.value = window.innerWidth < 640;
}

function close(): void {
    open.value = false;
}

function onDocumentClick(event: MouseEvent): void {
    if (root.value && !root.value.contains(event.target as Node)) {
        close();
    }
}

function onEscape(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        close();
    }
}

watch(open, async (isOpen) => {
    if (!isOpen) {
        ctx?.revert();
        ctx = null;
        return;
    }

    await nextTick();

    if (!content.value) return;

    if (prefersReducedMotion()) {
        gsap.set(content.value, { opacity: 1, scale: 1, y: 0 });
        return;
    }

    ctx?.revert();
    ctx = gsap.context(() => {
        gsap.fromTo(
            content.value,
            { opacity: 0, scale: 0.95, y: -6 },
            { opacity: 1, scale: 1, y: 0, duration: 0.18, ease: 'power2.out' },
        );
    }, content.value);
});

onMounted(() => {
    checkMobile();
    window.addEventListener('resize', checkMobile);
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onEscape);
});

onUnmounted(() => {
    window.removeEventListener('resize', checkMobile);
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onEscape);
    ctx?.revert();
    ctx = null;
});
</script>

<template>
    <div ref="root" class="relative">
        <component
            :is="bare ? 'div' : 'button'"
            :type="bare ? undefined : 'button'"
            :aria-label="bare ? undefined : label"
            :aria-expanded="bare ? undefined : open"
            :aria-haspopup="bare ? undefined : 'menu'"
            :class="
                bare
                    ? 'inline-flex'
                    : [
                          'inline-flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm font-medium text-surface-700 transition-colors hover:bg-surface-100 dark:text-surface-300 dark:hover:bg-surface-800',
                          triggerClass,
                      ]
            "
            @click="open = !open"
        >
            <slot name="trigger" :open="open" />
        </component>

        <div
            v-show="open"
            ref="content"
            role="menu"
            :class="[
                'dropdown max-h-[min(70vh,24rem)] overflow-y-auto overscroll-contain',
                isMobile
                    ? 'fixed bottom-20 left-4 right-4 z-50 mx-auto max-w-[calc(100vw-2rem)]'
                    : 'absolute top-full z-50 mt-2',
                align === 'right'
                    ? 'right-0 origin-top-right'
                    : 'left-0 origin-top-left',
            ]"
        >
            <slot />
        </div>
    </div>
</template>