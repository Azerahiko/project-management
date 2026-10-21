<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { getIcon } from '@/components/sidebar/icons';

const page = usePage();
</script>

<template>
    <div
        class="flex min-h-screen items-center justify-center bg-surface-50 px-4 py-12 dark:bg-surface-950"
    >
        <div class="w-full max-w-md">
            <div class="glass-strong rounded-2xl p-8 shadow-xl">
                <div class="mb-8 text-center">
                    <div
                        class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-primary-600 shadow-lg"
                    >
                        <component
                            :is="getIcon('CheckSquare')"
                            class="h-7 w-7 text-white"
                        />
                    </div>
                    <h1
                        class="text-2xl font-bold text-surface-900 dark:text-surface-100"
                    >
                        {{ page.props.app.shortName }}
                    </h1>
                    <p
                        class="mt-2 text-sm text-surface-600 dark:text-surface-400"
                    >
                        Masuk ke akun Anda
                    </p>
                </div>
                <slot />
            </div>
            <p
                class="mt-6 text-center text-sm text-surface-500 dark:text-surface-400"
            >
                &copy; {{ new Date().getFullYear() }} {{ page.props.app.name }}.
                Hak cipta dilindungi.
            </p>
        </div>
    </div>
</template>
