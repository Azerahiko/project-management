<script setup lang="ts">
import { getIcon } from '@/components/sidebar/icons';

interface Props {
    icon?: string;
    title: string;
    description: string;
    badge?: string;
    note?: string;
}

const props = withDefaults(defineProps<Props>(), {
    icon: 'FileText',
});
</script>

<template>
    <section class="card overflow-hidden !p-0">
        <div class="grid min-h-72 lg:grid-cols-[1fr_18rem]">
            <div class="flex flex-col items-start justify-center px-6 py-10 sm:px-10 sm:py-12">
                <div class="flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-primary-50 text-primary-700 dark:bg-primary-900/40 dark:text-primary-200">
                        <component :is="getIcon(props.icon)" class="h-5 w-5" />
                    </span>
                    <span v-if="props.badge" class="rounded-md border border-surface-200/80 bg-surface-100 px-2.5 py-0.5 text-xs font-medium text-surface-700 dark:border-surface-700 dark:bg-surface-800 dark:text-surface-300">
                        {{ props.badge }}
                    </span>
                </div>
                <h2 class="mt-6 text-xl font-semibold tracking-tight text-surface-950 dark:text-surface-50 sm:text-2xl">
                    {{ props.title }}
                </h2>
                <p class="mt-2 max-w-lg text-sm leading-7 text-surface-700 dark:text-surface-200">
                    {{ props.description }}
                </p>
                <p v-if="props.note" class="mt-6 border-l-2 border-accent-500 pl-4 text-sm leading-6 text-surface-700 dark:text-surface-200">
                    {{ props.note }}
                </p>
                <slot />
            </div>

            <div class="hidden border-l border-surface-200 bg-surface-50 p-8 lg:flex lg:flex-col lg:justify-center dark:border-surface-700 dark:bg-surface-950/40" aria-hidden="true">
                <div class="rounded-2xl border border-surface-200 bg-white p-5 shadow-xs dark:border-surface-700 dark:bg-surface-900">
                    <div class="flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full bg-accent-500" />
                        <span class="h-2 w-2 rounded-full bg-primary-500/40" />
                        <span class="h-2 w-2 rounded-full bg-primary-500/20" />
                    </div>
                    <div class="mt-6 h-2 w-2/3 rounded-full bg-surface-200 dark:bg-surface-700" />
                    <div class="mt-3 h-2 w-full rounded-full bg-surface-100 dark:bg-surface-800" />
                    <div class="mt-3 h-2 w-4/5 rounded-full bg-surface-100 dark:bg-surface-800" />
                    <div class="mt-6 h-1 w-full rounded-full bg-surface-100 dark:bg-surface-800">
                        <div class="h-full w-2/5 rounded-full bg-primary-600" />
                    </div>
                </div>
                <span class="mt-4 text-center text-xs font-medium tracking-wide text-surface-600 uppercase dark:text-surface-300">Ruang kerja sedang disiapkan</span>
            </div>
        </div>
    </section>
</template>
