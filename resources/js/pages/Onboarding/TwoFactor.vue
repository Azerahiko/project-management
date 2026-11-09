<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { usePage, router, useForm } from '@inertiajs/vue3';
import { useToast } from '@/composables/useToast';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import OnboardingLayout from './Layout.vue';

defineOptions({
    layout: OnboardingLayout,
});

const page = usePage<{
    step: string;
    nextStep: string;
    qrCodeSvg?: string;
    qrCodeUrl: string;
    secret: string;
    twoFactorEnabled: boolean;
    recoveryCodes?: string[];
}>();

const { success, error: toastError } = useToast();

const form = useForm({
    code: '',
});

const stepState = ref<'setup' | 'confirm' | 'recovery'>('setup');
const showManualSecret = ref(false);

const recoveryCodes = computed<string[]>(() => {
    return page.props.recoveryCodes || [];
});

watch(
    () => page.props.recoveryCodes,
    (codes) => {
        if (codes && codes.length > 0) {
            stepState.value = 'recovery';
        }
    },
    { immediate: true },
);

function confirmTwoFactor(): void {
    form.clearErrors();
    form.post('/onboarding/two-factor/confirmed', {
        onSuccess: () => {
            stepState.value = 'recovery';
            success('2FA Berhasil Dikonfirmasi', 'Simpan kode pemulihan Anda.');
        },
    });
}

function proceedToPasskey(): void {
    router.visit('/onboarding/passkey');
}

function completeOnboarding(): void {
    router.post('/onboarding/complete');
}

function copyRecoveryCodes(): void {
    const codes = recoveryCodes.value.join('\n');
    navigator.clipboard.writeText(codes);
    success('Tersalin', 'Kode pemulihan disalin ke papan klip.');
}
</script>

<template>
    <div class="space-y-6">
        <!-- Setup State -->
        <div v-if="stepState === 'setup'" class="text-center">
            <div
                class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-purple-100 dark:bg-purple-900"
            >
                <svg
                    class="h-6 w-6 text-purple-600 dark:text-purple-400"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                    />
                </svg>
            </div>
            <h2
                class="mt-4 text-lg font-semibold text-surface-900 dark:text-surface-50"
            >
                Aktifkan Autentikasi Dua Faktor
            </h2>
            <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">
                Pindai kode QR dengan aplikasi authenticator (Google Authenticator, Authy,
                dll).
            </p>

            <div class="mt-6 flex flex-col items-center">
                <div
                    v-if="page.props.qrCodeSvg"
                    class="rounded-xl border border-surface-200 bg-white p-4 shadow-sm dark:border-surface-700 dark:bg-white [&>svg]:mx-auto [&>svg]:h-48 [&>svg]:w-48"
                    v-html="page.props.qrCodeSvg"
                />

                <div class="mt-4 text-center">
                    <button
                        type="button"
                        class="text-xs font-medium text-primary-600 underline hover:text-primary-700 dark:text-primary-400"
                        @click="showManualSecret = !showManualSecret"
                    >
                        {{ showManualSecret ? 'Sembunyikan kunci manual' : 'Tampilkan kunci rahasia manual' }}
                    </button>

                    <div v-if="showManualSecret" class="mt-2">
                        <code
                            class="rounded bg-surface-100 px-3 py-1 font-mono text-sm tracking-wider select-all dark:bg-surface-800"
                        >
                            {{ page.props.secret }}
                        </code>
                    </div>
                </div>
            </div>

            <Button
                variant="primary"
                class="mt-6 w-full"
                @click="stepState = 'confirm'"
            >
                Lanjut ke Verifikasi
            </Button>
        </div>

        <!-- Confirm State -->
        <div v-if="stepState === 'confirm'" class="space-y-4">
            <div class="text-center">
                <h2
                    class="text-lg font-semibold text-surface-900 dark:text-surface-50"
                >
                    Verifikasi Kode
                </h2>
                <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">
                    Masukkan kode 6 digit dari aplikasi authenticator Anda.
                </p>
            </div>

            <form @submit.prevent="confirmTwoFactor" class="space-y-4">
                <Input
                    v-model="form.code"
                    type="text"
                    label="Kode Verifikasi"
                    placeholder="000000"
                    required
                    maxlength="6"
                    :error="form.errors.code"
                    autocomplete="one-time-code"
                    class="text-center font-mono text-2xl tracking-widest"
                />

                <Button
                    type="submit"
                    variant="primary"
                    class="w-full"
                    :loading="form.processing"
                >
                    Verifikasi & Aktifkan 2FA
                </Button>

                <button
                    type="button"
                    class="w-full text-sm text-surface-500 hover:text-surface-700"
                    @click="stepState = 'setup'"
                >
                    Kembali ke Kode QR
                </button>
            </form>
        </div>

        <!-- Recovery Codes State -->
        <div v-if="stepState === 'recovery'" class="space-y-4">
            <div class="text-center">
                <div
                    class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-green-100 dark:bg-green-900"
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
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                </div>
                <h2
                    class="mt-4 text-lg font-semibold text-surface-900 dark:text-surface-50"
                >
                    2FA Berhasil Diaktifkan!
                </h2>
                <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">
                    Simpan kode pemulihan berikut di tempat yang aman. Anda
                    membutuhkannya jika kehilangan akses ke authenticator.
                </p>
            </div>

            <div
                class="rounded-lg bg-yellow-50 p-4 text-sm text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300"
            >
                <p class="font-medium">Peringatan:</p>
                <p>
                    Kode ini hanya ditampilkan sekarang. Pastikan menyimpannya di
                    tempat yang aman sebelum melanjutkan.
                </p>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <code
                    v-for="(code, index) in recoveryCodes"
                    :key="index"
                    class="rounded bg-surface-100 px-3 py-2 text-center font-mono text-xs select-all dark:bg-surface-800"
                >
                    {{ code }}
                </code>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row">
                <Button
                    variant="secondary"
                    class="flex-1"
                    @click="copyRecoveryCodes"
                >
                    Salin Semua Kode
                </Button>
                <Button
                    variant="secondary"
                    class="flex-1"
                    @click="proceedToPasskey"
                >
                    Atur Passkey (Opsional)
                </Button>
                <Button
                    variant="primary"
                    class="flex-1"
                    @click="completeOnboarding"
                >
                    Selesaikan Onboarding
                </Button>
            </div>
        </div>
    </div>
</template>
