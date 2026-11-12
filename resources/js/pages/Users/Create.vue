<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import users from '@/routes/users';
import api from '@/lib/api';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Select from '@/components/ui/Select.vue';
import { useToast } from '@/composables/useToast';

defineOptions({
    layout: AppLayout,
});

const { success, error: toastError } = useToast();

const roleOptions = [
    { value: 'project_manager', label: 'Project Manager' },
    { value: 'team_leader', label: 'Team Leader' },
    { value: 'member', label: 'Member' },
    { value: 'reviewer', label: 'Reviewer' },
    { value: 'client', label: 'Client' },
];

const form = reactive({
    name: '',
    email: '',
    role: 'member' as string,
    is_active: true,
    temporary_password: '',
});

const errors = ref<Record<string, string[]>>({});
const processing = ref(false);
const formSubmitting = ref(false);
const passwordGenerating = ref(false);

function setErrors(err: Record<string, string[]>): void {
    errors.value = err;
}

function clearError(field: string): void {
    if (errors.value[field]) {
        delete errors.value[field];
    }
}

async function generateTemporaryPassword(
    isRegeneration: boolean = false,
): Promise<void> {
    if (passwordGenerating.value) return;

    passwordGenerating.value = true;
    errors.value = {};

    try {
        const response = await api.post('/users/generate-temporary-password');
        form.temporary_password = response.data.temporary_password;

        if (isRegeneration) {
            success(
                'Password regenerated',
                'New temporary password has been generated.',
            );
        }
    } catch (err: any) {
        if (err.response?.data?.errors) {
            setErrors(err.response.data.errors);
        } else {
            toastError('Failed to generate password. Please try again.');
        }
    } finally {
        passwordGenerating.value = false;
    }
}

async function copyTemporaryPassword(): Promise<void> {
    try {
        await navigator.clipboard.writeText(form.temporary_password);
        success('Copied', 'Temporary password copied to clipboard.');
    } catch {
        toastError('Failed to copy. Please select and copy manually.');
    }
}

async function handleSubmit(): Promise<void> {
    if (!form.temporary_password) {
        toastError('Please generate a temporary password before submitting.');
        return;
    }

    formSubmitting.value = true;
    errors.value = {};

    try {
        await api.post('/users', form);
        success(
            'Pengguna dibuat',
            'Pengguna baru telah ditambahkan. Undangan telah dikirim.',
        );
        setTimeout(() => {
            router.visit(users.index.url());
        }, 2000);
    } catch (err: any) {
        if (err.response?.data?.errors) {
            setErrors(err.response.data.errors);
        }
    } finally {
        formSubmitting.value = false;
    }
}

function goBack(): void {
    router.visit(users.index.url());
}

onMounted(async () => {
    await generateTemporaryPassword();
});
</script>

<template>
    <div class="mx-auto max-w-2xl space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1
                    class="text-2xl font-semibold tracking-tight text-surface-950 dark:text-surface-50"
                >
                    Tambah Pengguna Baru
                </h1>
                <p
                    class="mt-1 text-sm leading-6 text-surface-700 dark:text-surface-200"
                >
                    Isi formulir di bawah untuk membuat pengguna baru. Kode
                    aktivasi akan dibuat otomatis.
                </p>
            </div>
            <Link :href="users.index.url()">
                <Button variant="ghost">Kembali</Button>
            </Link>
        </div>

        <form @submit.prevent="handleSubmit" class="space-y-6">
            <div class="card space-y-6">
                <h2
                    class="text-lg font-semibold text-surface-900 dark:text-surface-100"
                >
                    Informasi Pengguna
                </h2>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <Input
                        v-model="form.name"
                        label="Nama Lengkap"
                        placeholder="Masukkan nama lengkap"
                        required
                        :error="errors.name?.[0]"
                        autocomplete="name"
                        @input="clearError('name')"
                    />

                    <Input
                        v-model="form.email"
                        type="email"
                        label="Email"
                        placeholder="Masukkan alamat email"
                        required
                        :error="errors.email?.[0]"
                        autocomplete="email"
                        @input="clearError('email')"
                    />
                </div>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <Select
                        v-model="form.role"
                        :options="roleOptions"
                        label="Peran"
                        required
                        :error="errors.role?.[0]"
                        @change="clearError('role')"
                    />
                </div>

                <div class="flex items-center">
                    <input
                        type="checkbox"
                        v-model="form.is_active"
                        class="h-4 w-4 rounded border-surface-300 text-primary-600 focus:ring-primary-500"
                        id="is_active"
                    />
                    <label
                        for="is_active"
                        class="ml-2 text-sm text-surface-700 dark:text-surface-300"
                    >
                        Pengguna aktif
                    </label>
                </div>
            </div>

            <div class="card space-y-4">
                <div>
                    <h3
                        class="text-lg font-semibold text-surface-900 dark:text-surface-100"
                    >
                        Password Sementara
                    </h3>
                    <p
                        class="mt-1 text-sm leading-6 text-surface-700 dark:text-surface-200"
                    >
                        Password dibuat otomatis oleh server. Bagikan password
                        ini kepada pengguna untuk login pertama.
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <input
                        type="text"
                        readonly
                        :value="form.temporary_password"
                        class="input flex-1 font-mono tracking-wider select-all"
                        placeholder="Membuat password..."
                        aria-label="Password sementara"
                    />
                    <Button
                        type="button"
                        variant="secondary"
                        @click="copyTemporaryPassword"
                        :disabled="!form.temporary_password"
                    >
                        Salin
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        @click="generateTemporaryPassword(true)"
                        :disabled="passwordGenerating"
                    >
                        {{ passwordGenerating ? 'Membuat...' : 'Buat Ulang' }}
                    </Button>
                </div>

                <p
                    v-if="!form.temporary_password && !passwordGenerating"
                    class="text-sm text-accent-700 dark:text-accent-300"
                >
                    Password gagal dibuat.
                    <button
                        type="button"
                        class="underline"
                        @click="generateTemporaryPassword()"
                    >
                        Coba lagi
                    </button>
                </p>

                <p
                    v-if="errors.temporary_password?.[0]"
                    class="form-error"
                    role="alert"
                >
                    {{ errors.temporary_password[0] }}
                </p>

                <p class="text-xs text-surface-500 dark:text-surface-400">
                    20 karakter, kombinasi huruf besar, huruf kecil, angka, dan
                    simbol.
                </p>
            </div>

            <div
                v-if="errors.form"
                class="rounded-lg bg-accent-50 p-4 text-accent-700 dark:bg-accent-900/30 dark:text-accent-300"
            >
                {{ errors.form[0] }}
            </div>

            <div class="flex justify-end gap-3">
                <Link :href="users.index.url()">
                    <Button type="button" variant="secondary">Batal</Button>
                </Link>
                <Button
                    type="submit"
                    variant="primary"
                    :loading="formSubmitting"
                    :disabled="!form.temporary_password || passwordGenerating"
                >
                    Buat Pengguna
                </Button>
            </div>
        </form>
    </div>
</template>
