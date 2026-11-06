<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import api from '@/lib/api';

defineOptions({
    layout: undefined,
});

const page = usePage<{
    token: string;
    email: string;
    name: string;
}>();

const token = page.props.token;
const email = page.props.email;
const name = page.props.name;

const processing = ref(false);
const error = ref('');
const success = ref(false);

async function acceptInvitation(): Promise<void> {
    processing.value = true;
    error.value = '';

    try {
        await api.post(`/invite/${token}`);
        success.value = true;
    } catch (err: any) {
        if (err.response?.status === 410) {
            error.value = 'Undangan sudah tidak valid atau sudah digunakan.';
        } else if (err.response?.status === 404) {
            error.value = 'Undangan tidak ditemukan.';
        } else {
            error.value =
                err.response?.data?.message ||
                'Terjadi kesalahan. Silakan coba lagi.';
        }
    } finally {
        processing.value = false;
    }
}

onMounted(() => {
    // Auto-accept on page load for smoother UX
    acceptInvitation();
});
</script>

<template>
    <div
        class="flex min-h-screen items-center justify-center bg-surface-50 px-4 py-12 dark:bg-surface-900"
    >
        <div class="w-full max-w-md">
            <!-- Logo/Brand -->
            <div class="mb-8 text-center">
                <div
                    class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-primary-600 text-xl font-bold text-white"
                >
                    {{ name.charAt(0).toUpperCase() }}
                </div>
                <h1
                    class="mt-4 text-2xl font-bold text-surface-900 dark:text-surface-50"
                >
                    Undangan Bergabung
                </h1>
                <p class="mt-2 text-surface-600 dark:text-surface-400">
                    {{ name }} ({{ email }})
                </p>
            </div>

            <!-- Loading State -->
            <div v-if="!success && !error" class="card py-8 text-center">
                <div class="flex flex-col items-center gap-4">
                    <div
                        class="h-8 w-8 animate-spin rounded-full border-4 border-primary-600 border-t-transparent"
                    ></div>
                    <p class="text-surface-600 dark:text-surface-400">
                        Memverifikasi undangan...
                    </p>
                </div>
            </div>

            <!-- Success State -->
            <div v-if="success" class="card py-8 text-center">
                <div class="flex flex-col items-center gap-4">
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-full bg-green-100 dark:bg-green-900"
                    >
                        <svg
                            class="h-6 w-6 text-green-600 dark:text-green-400"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M5 13l4 4L19 7"
                            />
                        </svg>
                    </div>
                    <div>
                        <h2
                            class="text-lg font-semibold text-surface-900 dark:text-surface-50"
                        >
                            Email Diverifikasi!
                        </h2>
                        <p class="mt-2 text-surface-600 dark:text-surface-400">
                            Anda akan diarahkan ke halaman login...
                        </p>
                    </div>
                    <a
                        href="/login"
                        class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-6 py-3 text-sm font-semibold text-white hover:bg-primary-700"
                    >
                        Masuk Sekarang
                    </a>
                </div>
            </div>

            <!-- Error State -->
            <div v-if="error" class="card py-8 text-center">
                <div class="flex flex-col items-center gap-4">
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-full bg-red-100 dark:bg-red-900"
                    >
                        <svg
                            class="h-6 w-6 text-red-600 dark:text-red-400"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </div>
                    <div>
                        <h2
                            class="text-lg font-semibold text-surface-900 dark:text-surface-50"
                        >
                            Undangan Tidak Valid
                        </h2>
                        <p class="mt-2 text-surface-600 dark:text-surface-400">
                            {{ error }}
                        </p>
                    </div>
                    <a
                        href="/login"
                        class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-6 py-3 text-sm font-semibold text-white hover:bg-primary-700"
                    >
                        Ke Halaman Login
                    </a>
                </div>
            </div>
        </div>
    </div>
</template>
