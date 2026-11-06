<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import { activate } from '@/routes/api/invitations';
import api from '@/lib/api';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
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

const form = reactive({
    activation_code: '',
    password: '',
    password_confirmation: '',
});

const errors = ref<Record<string, string[]>>({});
const processing = ref(false);
const submitted = ref(false);

function setErrors(err: Record<string, string[]>): void {
    errors.value = err;
}

function clearError(field: string): void {
    if (errors.value[field]) {
        delete errors.value[field];
    }
}

async function handleActivate(): Promise<void> {
    processing.value = true;
    errors.value = {};

    try {
        const response = await api.post(activate.url({ token }), {
            email,
            activation_code: form.activation_code,
            password: form.password,
            password_confirmation: form.password_confirmation,
        });
        submitted.value = true;
        success('Aktivasi Berhasil', response.data.message);
        setTimeout(() => {
            router.visit('/login');
        }, 2000);
    } catch (err: any) {
        if (err.response?.data?.errors) {
            setErrors(err.response.data.errors);
        }
        if (err.response?.data?.message) {
            toastError('Gagal', err.response.data.message);
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
                    Aktivasi Akun
                </h1>
                <p class="mt-2 text-surface-600 dark:text-surface-400">
                    {{ name }} ({{ email }})
                </p>
            </div>

            <!-- Activation Form -->
            <div v-if="!submitted" class="card space-y-6">
                <div class="space-y-2">
                    <h2
                        class="text-lg font-semibold text-surface-900 dark:text-surface-100"
                    >
                        Masukkan Kode Aktivasi
                    </h2>
                    <p class="text-sm text-surface-600 dark:text-surface-400">
                        Kode aktivasi 8 digit telah dikirim ke email Anda.
                        Masukkan kode tersebut di bawah.
                    </p>
                </div>

                <form @submit.prevent="handleActivate" class="space-y-4">
                    <Input
                        v-model="form.activation_code"
                        label="Kode Aktivasi"
                        placeholder="Masukkan 8 digit kode aktivasi"
                        required
                        :error="errors.activation_code?.[0]"
                        autocomplete="one-time-code"
                        maxlength="8"
                        @input="clearError('activation_code')"
                    />

                    <Input
                        v-model="form.password"
                        type="password"
                        label="Kata Sandi Baru"
                        placeholder="Masukkan kata sandi baru"
                        required
                        :error="errors.password?.[0]"
                        autocomplete="new-password"
                        @input="clearError('password')"
                    />

                    <Input
                        v-model="form.password_confirmation"
                        type="password"
                        label="Konfirmasi Kata Sandi"
                        placeholder="Masukkan ulang kata sandi baru"
                        required
                        :error="errors.password_confirmation?.[0]"
                        autocomplete="new-password"
                        @input="clearError('password_confirmation')"
                    />

                    <div
                        v-if="errors.form"
                        class="rounded-lg bg-accent-50 p-4 text-accent-700 dark:bg-accent-900/30 dark:text-accent-300"
                    >
                        {{ errors.form[0] }}
                    </div>

                    <Button
                        type="submit"
                        variant="primary"
                        class="w-full"
                        :loading="processing"
                    >
                        Aktifkan Akun
                    </Button>
                </form>

                <p
                    class="text-center text-sm text-surface-600 dark:text-surface-400"
                >
                    Belum menerima kode? Hubungi administrator untuk mengirim
                    ulang undangan.
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
                            class="text-lg font-semibold text-surface-900 dark:text-surface-50"
                        >
                            Aktivasi Berhasil!
                        </h2>
                        <p class="mt-2 text-surface-600 dark:text-surface-400">
                            Anda akan diarahkan ke halaman login...
                        </p>
                    </div>
                    <Button variant="primary" @click="goToLogin" class="w-full">
                        Masuk Sekarang
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
