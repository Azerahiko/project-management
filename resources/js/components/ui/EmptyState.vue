<script setup lang="ts">
import { getIcon } from '@/components/sidebar/icons';

interface Props {
    icon?: string;
    title: string;
    description: string;
    badge?: string;
}

const props = withDefaults(defineProps<Props>(), {
    icon: 'FileText',
});
</script>

<template>
    <div class="card flex flex-col items-center justify-center px-6 py-16 text-center">
        <div
            class="flex h-14 w-14 items-center justify-center rounded-2xl bg-surface-100 dark:bg-surface-800"
        >
            <component
                :is="getIcon(props.icon)"
                class="h-7 w-7 text-surface-400 dark:text-surface-500"
            />
        </div>

        <span v-if="props.badge" class="badge-neutral mt-5">
            {{ props.badge }}
        </span>

        <h2 class="mt-3 text-lg font-semibold text-surface-900 dark:text-surface-100">
            {{ props.title }}
        </h2>
        <p class="mt-1 max-w-sm text-sm text-surface-500 dark:text-surface-400">
            {{ props.description }}
        </p>
    </div>
</template>