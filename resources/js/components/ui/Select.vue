<script setup lang="ts">
interface Props {
    label?: string;
    error?: string;
    hint?: string;
    id?: string;
    modelValue?: string;
    options: { value: string; label: string }[];
    disabled?: boolean;
}

const props = defineProps<Props>();

const emit = defineEmits<{
    'update:modelValue': [value: string];
}>();

const selectId = props.id || `select-${Math.random().toString(36).slice(2, 9)}`;
const errorId = props.error ? `${selectId}-error` : undefined;
const hintId = props.hint ? `${selectId}-hint` : undefined;
</script>

<template>
    <div class="w-full">
        <label
            v-if="label"
            :for="selectId"
            class="label"
        >
            {{ label }}
        </label>
        <select
            :id="selectId"
            :value="modelValue"
            :disabled="disabled"
            :aria-invalid="!!error"
            :aria-describedby="[errorId, hintId].filter(Boolean).join(' ') || undefined"
            :class="['input', { 'input-error': error }]"
            @change="emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
        >
            <option v-for="opt in options" :key="opt.value" :value="opt.value">
                {{ opt.label }}
            </option>
        </select>
        <p v-if="error" :id="errorId" class="form-error" role="alert">
            {{ error }}
        </p>
        <p v-else-if="hint" :id="hintId" class="text-sm text-surface-500 dark:text-surface-400 mt-1">
            {{ hint }}
        </p>
    </div>
</template>