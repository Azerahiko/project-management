<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

defineOptions({
    layout: undefined,
});

const page = usePage<{
    step: string;
    nextStep: string;
}>();

const step = computed(() => page.props.step);

const steps = [
    { key: 'password', label: 'Kata Sandi', icon: 'lock' },
    { key: 'two_factor', label: '2FA', icon: 'shield' },
];

const currentStepIndex = computed(() => {
    const idx = steps.findIndex((s) => s.key === step.value);
    return idx === -1 ? steps.length : idx;
});
</script>

<template>
    <div class="min-h-screen bg-surface-50 px-4 py-8 dark:bg-surface-900">
        <div class="mx-auto max-w-2xl">
            <!-- Logo -->
            <div class="mb-8 text-center">
                <div
                    class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-primary-600 text-xl font-bold text-white"
                >
                    P
                </div>
                <h1
                    class="mt-4 text-2xl font-bold text-surface-900 dark:text-surface-50"
                >
                    Setup Akun
                </h1>
                <p class="mt-1 text-surface-600 dark:text-surface-400">
                    Selesaikan langkah berikut untuk mengaktifkan akun Anda
                </p>
            </div>

            <!-- Stepper -->
            <nav aria-label="Progress" class="mb-8">
                <ol class="flex items-center justify-center">
                    <li
                        v-for="(s, index) in steps"
                        :key="s.key"
                        class="relative flex items-center"
                    >
                        <!-- Step circle -->
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-full border-2 text-sm font-semibold"
                            :class="{
                                'border-primary-600 bg-primary-600 text-white':
                                    index < currentStepIndex || step === s.key,
                                'border-surface-300 bg-white text-surface-500 dark:border-surface-600 dark:bg-surface-800':
                                    index > currentStepIndex && step !== s.key,
                            }"
                        >
                            <svg
                                v-if="index < currentStepIndex"
                                class="h-5 w-5"
                                fill="currentColor"
                                viewBox="0 0 20 20"
                            >
                                <path
                                    fill-rule="evenodd"
                                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                    clip-rule="evenodd"
                                />
                            </svg>
                            <span v-else>{{ index + 1 }}</span>
                        </div>

                        <!-- Step label (hidden on mobile) -->
                        <span
                            class="ml-3 hidden text-sm font-medium sm:block"
                            :class="
                                step === s.key
                                    ? 'text-primary-600 dark:text-primary-400'
                                    : 'text-surface-500'
                            "
                        >
                            {{ s.label }}
                        </span>

                        <!-- Connector -->
                        <div
                            v-if="index < steps.length - 1"
                            class="hidden h-0.5 w-8 sm:block"
                            :class="
                                index < currentStepIndex
                                    ? 'bg-primary-600'
                                    : 'bg-surface-300 dark:bg-surface-600'
                            "
                        ></div>
                    </li>
                </ol>
            </nav>

            <!-- Content -->
            <div class="card">
                <slot />
            </div>
        </div>
    </div>
</template>
