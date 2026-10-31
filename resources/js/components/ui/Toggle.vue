<script setup lang="ts">
interface Props {
    modelValue: boolean;
    label?: string;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    label: '',
    disabled: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: boolean];
}>();

function toggle(): void {
    if (props.disabled) {
        return;
    }

    emit('update:modelValue', !props.modelValue);
}
</script>

<template>
    <button
        type="button"
        role="switch"
        :aria-checked="modelValue"
        :aria-label="label || undefined"
        :disabled="disabled"
        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full transition-colors duration-200 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 disabled:cursor-not-allowed disabled:opacity-50"
        :class="
            modelValue
                ? 'bg-primary-600 dark:bg-primary-500'
                : 'bg-surface-300 dark:bg-surface-600'
        "
        @click="toggle"
    >
        <span
            class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow-sm transition-transform duration-200"
            :class="modelValue ? 'translate-x-6' : 'translate-x-1'"
        />
    </button>
</template>
