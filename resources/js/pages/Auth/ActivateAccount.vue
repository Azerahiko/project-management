<script setup lang="ts">
import { ref } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { activate } from '@/routes/api/invitations';
import web from '@/lib/web';
import Button from '@/components/ui/Button.vue';
import { useToast } from '@/composables/useToast';

defineOptions({
    layout: undefined,
});

const { success, error: toastError } = useToast();

const page = usePage<{
    token: string;
    email: string;
    name: string;
}>();

const token = page.props.token;
const email = page.props.email;
const name = page.props.name;

const processing = ref(false);
const submitted = ref(false);

async function handleVerify(): Promise<void> {
    processing.value = true;

    try {
        const response = await web.post(activate.url({ token }));
        submitted.value = true;
        success('Verifikasi Berhasil', response.data.message);
        setTimeout(() => {
            router.visit('/login');
        }, 2000);
    } catch (err: any) {
        if (err.response?.data?.message) {
            toastError('Gagal', err.response.data.message);
        } else {
            toastError('Gagal', 'Terjadi kesalahan saat memverifikasi undangan.');
        }
    } finally {
        processing.value = false;
    }
}

function goToLogin(): void {
    router.visit('/login');
}
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
                    Verifikasi Undangan
                </h1>
                <p class="mt-2 text-surface-600 dark:text-surface-400">
                    {{ name }} ({{ email }})
                </p>
            </div>

            <!-- Verification Action Box -->
            <div v-if="!submitted" class="card space-y-6">
                <div class="space-y-2">
                    <h2
                        class="text-lg font-semibold text-surface-900 dark:text-surface-100"
                    >
                        Konfirmasi Email & Undangan
                    </h2>
                    <p class="text-sm text-surface-600 dark:text-surface-400">
                        Klik tombol di bawah untuk memverifikasi email Anda. Setelah terverifikasi, Anda dapat masuk menggunakan kata sandi sementara yang diberikan oleh administrator.
                    </p>
                </div>

                <div class="space-y-4">
                    <Button
                        type="button"
                        variant="primary"
                        class="w-full"
                        :loading="processing"
                        @click="handleVerify"
                    >
                        Konfirmasi & Verifikasi Akun
                    </Button>
                </div>

                <p
                    class="text-center text-sm text-surface-600 dark:text-surface-400"
                >
                    Mengalami kendala? Hubungi administrator untuk informasi lebih lanjut.
                </p>
            </div>

            <!-- Success State -->
            <div v-if="submitted" class="card py-8 text-center">
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
                            class="text-xl font-semibold text-surface-900 dark:text-surface-50"
                        >
                            Email Terverifikasi!
                        </h2>
                        <p
                            class="mt-2 text-sm text-surface-600 dark:text-surface-400"
                        >
                            Undangan Anda telah diverifikasi. Mengalihkan ke halaman masuk...
                        </p>
                    </div>

                    <Button variant="primary" class="w-full" @click="goToLogin">
                        Masuk Sekarang
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
