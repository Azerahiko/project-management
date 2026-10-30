<script setup lang="ts">
interface Props {
    label?: string;
    error?: string;
    hint?: string;
    id?: string;
    modelValue?: string | number;
    type?: string;
    placeholder?: string;
    required?: boolean;
    disabled?: boolean;
    autocomplete?: string;
    rows?: number | string;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    type: 'text',
    rows: 3,
    loading: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: string];
    input: [value: string];
}>();

const inputId = props.id || `input-${Math.random().toString(36).slice(2, 9)}`;
const errorId = props.error ? `${inputId}-error` : undefined;
const hintId = props.hint ? `${inputId}-hint` : undefined;

function onInput(event: Event): void {
    const value = (event.target as HTMLInputElement | HTMLTextAreaElement)
        .value;
    emit('update:modelValue', value);
    emit('input', value);
}
</script>

<template>
    <div class="w-full">
        <label v-if="label" :for="inputId" class="label">
            {{ label }}
        </label>
        <textarea
            v-if="type === 'textarea'"
            :id="inputId"
            :value="modelValue"
            :rows="rows"
            :placeholder="placeholder"
            :required="required"
            :disabled="disabled || loading"
            :aria-invalid="!!error"
            :aria-describedby="
                [errorId, hintId].filter(Boolean).join(' ') || undefined
            "
            :class="['input', { 'input-error': error }]"
            @input="onInput"
        />
        <input
            v-else
            :id="inputId"
            :value="modelValue"
            :type="type"
            :placeholder="placeholder"
            :required="required"
            :disabled="disabled || loading"
            :autocomplete="autocomplete"
            :aria-invalid="!!error"
            :aria-describedby="
                [errorId, hintId].filter(Boolean).join(' ') || undefined
            "
            :class="['input', { 'input-error': error }]"
            @input="onInput"
        />
        <p v-if="error" :id="errorId" class="form-error" role="alert">
            {{ error }}
        </p>
        <p
            v-else-if="hint"
            :id="hintId"
            class="text-sm text-surface-500 dark:text-surface-400 mt-1"
        >
            {{ hint }}
        </p>
    </div>
</template>
