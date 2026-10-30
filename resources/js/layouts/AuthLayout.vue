<script setup lang="ts">
import ToastContainer from '@/components/ui/ToastContainer.vue';
</script>

<template>
    <div
        class="relative min-h-screen w-full overflow-x-hidden bg-surface-950 bg-cover bg-[center_left] bg-no-repeat select-none lg:bg-center"
        style="background-image: url('/images/auth-bg.png')"
    >
        <!-- Keep the photo secondary to the form. -->
        <div
            class="pointer-events-none absolute inset-0 bg-gradient-to-r from-surface-950/85 via-surface-950/65 to-surface-950/40 lg:to-surface-950/20"
            aria-hidden="true"
        />

        <!-- Content Area: Left-aligned on desktop screens, centered on mobile -->
        <div
            class="relative z-10 flex min-h-screen w-full items-center justify-center px-4 py-10 sm:px-8 lg:justify-start lg:pl-16 xl:pl-24 2xl:pl-32"
        >
            <slot />
        </div>

        <ToastContainer />
    </div>
</template>
