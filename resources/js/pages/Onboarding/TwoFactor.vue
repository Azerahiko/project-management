<script setup lang="ts">
import { ref, reactive } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import api from '@/lib/api';
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
    qrCodeUrl: string;
    secret: string;
    twoFactorEnabled: boolean;
}>();

const { success, error: toastError } = useToast();

const form = reactive({
    code: '',
});

const errors = ref<Record<string, string[]>>({});
const processing = ref(false);
const stepState = ref<'setup' | 'confirm' | 'recovery'>('setup');
const recoveryCodes = ref<string[]>([]);

function clearError(field: string): void {
    if (errors.value[field]) {
        delete errors.value[field];
    }
}

async function enableTwoFactor(): Promise<void> {
    processing.value = true;

    try {
        await api.post('/user/two-factor-authentication');
        stepState.value = 'confirm';
        success(
            'Kode QR Generated',
            'Pindai dengan aplikasi authenticator Anda.',
        );
    } catch (err: any) {
        toastError(err.response?.data?.message || 'Gagal mengaktifkan 2FA.');
    } finally {
        processing.value = false;
    }
}

async function confirmTwoFactor(): Promise<void> {
    processing.value = true;
    errors.value = {};

    try {
        await api.post('/user/confirmed-two-factor-authentication', {
            code: form.code,
        });
        // Fetch recovery codes
        const recoveryResponse = await api.get(
            '/user/two-factor-recovery-codes',
        );
        recoveryCodes.value = recoveryResponse.data;
        stepState.value = 'recovery';
    } catch (err: any) {
        if (err.response?.data?.errors?.code) {
            errors.value = err.response.data.errors;
        } else {
            toastError(err.response?.data?.message || 'Kode tidak valid.');
        }
    } finally {
        processing.value = false;
    }
}

function proceedToPasskey(): void {
    router.visit(`/onboarding/${page.props.nextStep}`);
}

function copyRecoveryCodes(): void {
    const codes = recoveryCodes.value.join('\n');
    navigator.clipboard.writeText(codes);
    success('Tersalin', 'Kode pemulihan disalin.');
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
                Anda wajib mengaktifkan 2FA untuk keamanan akun. Pindai kode QR
                dengan aplikasi authenticator (Google Authenticator, Authy,
                dll).
            </p>

            <div
                v-if="page.props.qrCodeUrl"
                class="mt-6 flex flex-col items-center"
            >
                <div class="rounded-lg bg-white p-4">
                    <img
                        :src="`https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(page.props.qrCodeUrl)}`"
                        alt="QR Code"
                        class="h-48 w-48"
                    />
                </div>
                <p class="mt-4 text-xs text-surface-500">
                    atau masukkan kode ini:
                </p>
                <code
                    class="mt-1 rounded bg-surface-100 px-2 py-1 font-mono text-sm select-all dark:bg-surface-800"
                >
                    {{ page.props.secret }}
                </code>
            </div>

            <Button
                v-if="!page.props.twoFactorEnabled && !page.props.qrCodeUrl"
                variant="primary"
                class="mt-6"
                @click="enableTwoFactor"
                :loading="processing"
            >
                Generate Kode QR
            </Button>
            <Button
                v-else-if="!page.props.twoFactorEnabled"
                variant="primary"
                class="mt-6"
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
                    :error="errors.code?.[0]"
                    autocomplete="one-time-code"
                    class="text-center font-mono text-2xl tracking-widest"
                    @input="clearError('code')"
                />

                <Button
                    type="submit"
                    variant="primary"
                    class="w-full"
                    :loading="processing"
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
                    2FA Aktif!
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
                    Kode ini hanya ditampilkan sekali. Pastikan menyimpannya di
                    tempat yang aman.
                </p>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <code
                    v-for="(code, index) in recoveryCodes"
                    :key="index"
                    class="rounded bg-surface-100 px-3 py-2 text-center font-mono text-xs dark:bg-surface-800"
                >
                    {{ code }}
                </code>
            </div>

            <div class="flex gap-3">
                <Button
                    variant="secondary"
                    class="flex-1"
                    @click="copyRecoveryCodes"
                >
                    Salin Kode
                </Button>
                <Button
                    variant="primary"
                    class="flex-1"
                    @click="proceedToPasskey"
                >
                    Lanjut ke Passkey
                </Button>
            </div>
        </div>
    </div>
</template>
