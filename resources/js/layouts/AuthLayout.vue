<script setup lang="ts">
import ToastContainer from '@/components/ui/ToastContainer.vue';
</script>

<template>
    <div
        class="relative min-h-screen w-full overflow-x-hidden bg-[#101214] bg-cover bg-[center_left] bg-no-repeat select-none lg:bg-center"
        style="background-image: url('/images/auth-bg.png')"
    >
        <!-- Soft subtle dark vignette gradient: deepens the left wood area while keeping the right-hand laptop, coffee, and plant bright and visible -->
        <div
            class="pointer-events-none absolute inset-0 bg-gradient-to-r from-black/65 via-black/30 to-black/40 lg:to-transparent"
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
