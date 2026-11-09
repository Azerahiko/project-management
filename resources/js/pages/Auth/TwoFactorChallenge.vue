<script setup lang="ts">
import { ref, computed, nextTick } from 'vue';
import { useForm, Link, Head } from '@inertiajs/vue3';
import AuthLayout from '@/layouts/AuthLayout.vue';

defineOptions({ layout: AuthLayout });

const recovery = ref(false);

const form = useForm({
    code: '',
    recovery_code: '',
});

const codeInput = ref<HTMLInputElement | null>(null);
const recoveryInput = ref<HTMLInputElement | null>(null);

function toggleRecovery(): void {
    recovery.value = !recovery.value;
    form.clearErrors();
    form.code = '';
    form.recovery_code = '';

    nextTick(() => {
        if (recovery.value) {
            recoveryInput.value?.focus();
        } else {
            codeInput.value?.focus();
        }
    });
}

function handleSubmit(): void {
    form.post('/two-factor-challenge', {
        onFinish: () => {
            form.code = '';
            form.recovery_code = '';
        },
    });
}

const errorMessage = computed(() => {
    const errs = form.errors as Record<string, string | undefined>;
    return errs.code ?? errs.recovery_code ?? null;
});
</script>

<template>
    <Head title="Verifikasi Dua Faktor - Dijital Code" />

    <div class="relative w-full max-w-[420px]">
        <div
            class="pointer-events-none absolute -inset-2 -z-10 rounded-3xl border border-primary-300/40 bg-primary-500/10 blur-lg"
            aria-hidden="true"
        />

        <div
            class="auth-card relative w-full rounded-2xl border border-surface-200 bg-white p-8 shadow-lg sm:p-10"
        >
            <div class="flex flex-col items-center">
                <img
                    src="/images/logo.png"
                    alt="Dijital Code"
                    class="h-10 w-auto"
                />

                <div class="mt-6 flex h-12 w-12 items-center justify-center rounded-full bg-primary-50 text-primary-600">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>

                <h1 class="mt-4 text-center text-xl font-bold tracking-tight text-surface-900">
                    Verifikasi Dua Faktor
                </h1>
                <p class="mt-2 text-center text-sm text-surface-600">
                    <template v-if="!recovery">
                        Konfirmasikan akses ke akun Anda dengan memasukkan kode autentikasi yang diberikan oleh aplikasi authenticator Anda.
                    </template>
                    <template v-else>
                        Konfirmasikan akses ke akun Anda dengan memasukkan salah satu kode pemulihan darurat Anda.
                    </template>
                </p>
            </div>

            <div
                v-if="errorMessage"
                role="alert"
                class="mt-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700"
            >
                {{ errorMessage }}
            </div>

            <form @submit.prevent="handleSubmit" class="mt-6 space-y-5">
                <div v-if="!recovery">
                    <label for="code" class="block text-xs font-semibold uppercase tracking-wider text-surface-700">
                        Kode Autentikasi
                    </label>
                    <input
                        id="code"
                        ref="codeInput"
                        v-model="form.code"
                        type="text"
                        inputmode="numeric"
                        autofocus
                        autocomplete="one-time-code"
                        placeholder="000000"
                        maxlength="6"
                        class="mt-2 block w-full rounded-xl border border-surface-200 bg-surface-50/50 px-4 py-3 text-center font-mono text-2xl tracking-widest text-surface-900 focus:border-primary-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-primary-500/10"
                    />
                </div>

                <div v-else>
                    <label for="recovery_code" class="block text-xs font-semibold uppercase tracking-wider text-surface-700">
                        Kode Pemulihan
                    </label>
                    <input
                        id="recovery_code"
                        ref="recoveryInput"
                        v-model="form.recovery_code"
                        type="text"
                        autocomplete="one-time-code"
                        placeholder="xxxx-xxxx-xxxx"
                        class="mt-2 block w-full rounded-xl border border-surface-200 bg-surface-50/50 px-4 py-3 text-center font-mono text-base text-surface-900 focus:border-primary-500 focus:bg-white focus:outline-none focus:ring-4 focus:ring-primary-500/10"
                    />
                </div>

                <button
                    type="submit"
                    :disabled="form.processing"
                    class="w-full rounded-xl bg-primary-600 px-4 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-700 focus:outline-none focus:ring-4 focus:ring-primary-500/20 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {{ form.processing ? 'Memverifikasi...' : 'Masuk' }}
                </button>

                <div class="flex flex-col items-center gap-3 pt-2 text-center text-sm">
                    <button
                        type="button"
                        class="font-medium text-primary-600 hover:text-primary-700 hover:underline"
                        @click="toggleRecovery"
                    >
                        <template v-if="!recovery">
                            Gunakan kode pemulihan darurat
                        </template>
                        <template v-else>
                            Gunakan kode autentikasi
                        </template>
                    </button>

                    <Link
                        href="/login"
                        class="text-xs text-surface-500 hover:text-surface-700 hover:underline"
                    >
                        Kembali ke halaman masuk
                    </Link>
                </div>
            </form>
        </div>
    </div>
</template>
