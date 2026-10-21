<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    variant?: 'primary' | 'secondary' | 'destructive' | 'ghost';
    size?: 'sm' | 'md' | 'lg';
    loading?: boolean;
    disabled?: boolean;
    type?: 'button' | 'submit' | 'reset';
}

const props = withDefaults(defineProps<Props>(), {
    variant: 'primary',
    size: 'md',
    loading: false,
    disabled: false,
    type: 'button',
});

const isDisabled = computed(() => props.loading || props.disabled);
</script>

<template>
    <button
        :class="[
            'btn',
            {
                'btn-primary': variant === 'primary',
                'btn-secondary': variant === 'secondary',
                'btn-destructive': variant === 'destructive',
                'btn-ghost': variant === 'ghost',
            },
            { 'btn-sm': size === 'sm', 'btn-lg': size === 'lg' },
        ]"
        :disabled="isDisabled"
        :type="type"
        v-bind="$attrs"
    >
        <svg
            v-if="loading"
            class="animate-spin h-4 w-4"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
        >
            <circle
                class="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                stroke-width="4"
            />
            <path
                class="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
            />
        </svg>
        <slot v-else />
    </button>
</template>

<style>
.btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.75rem;
    gap: 0.375rem;
}

.btn-lg {
    padding: 0.75rem 1.5rem;
    font-size: 1rem;
    gap: 0.5rem;
}
</style>