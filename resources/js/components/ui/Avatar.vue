<script setup lang="ts">
interface Props {
    src?: string | null;
    initials: string;
    size?: 'sm' | 'md' | 'lg';
    alt?: string;
}

const props = withDefaults(defineProps<Props>(), {
    size: 'md',
    alt: '',
});

const sizes = {
    sm: 'h-8 w-8 text-xs',
    md: 'h-10 w-10 text-sm',
    lg: 'h-14 w-14 text-base',
};
</script>

<template>
    <img
        v-if="props.src"
        :src="props.src"
        :alt="props.alt"
        class="avatar object-cover"
        :class="sizes[props.size]"
    />
    <span
        v-else
        class="avatar font-semibold"
        :class="sizes[props.size]"
        aria-hidden="true"
    >
        {{ props.initials }}
    </span>
</template>