<script setup lang="ts">
import { ref, reactive, computed } from 'vue';
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
});

const errors = ref<Record<string, string[]>>({});
const processing = ref(false);
const activationCode = ref<string>('');
const showCode = ref(false);

function setErrors(err: Record<string, string[]>): void {
    errors.value = err;
}

function clearError(field: string): void {
    if (errors.value[field]) {
        delete errors.value[field];
    }
}

async function handleSubmit(): Promise<void> {
    processing.value = true;
    errors.value = {};

    try {
        const response = await api.post('/users', form);
        activationCode.value = response.data.activation_code;
        showCode.value = true;
        success(
            'Pengguna dibuat',
            'Pengguna baru telah ditambahkan. Bagikan kode aktivasi ke pengguna.',
        );
    } catch (err: any) {
        if (err.response?.data?.errors) {
            setErrors(err.response.data.errors);
        }
    } finally {
        processing.value = false;
    }
}

async function regenerateCode(): Promise<void> {
    if (!activationCode.value) return;
    processing.value = true;
    try {
        const response = await api.post(
            `/users/${activationCode.value}/regenerate-code`,
        );
        activationCode.value = response.data.activation_code;
        success('Kode diperbarui', 'Kode aktivasi baru telah dibuat.');
    } catch (err: any) {
        if (err.response?.data?.errors) {
            setErrors(err.response.data.errors);
        }
    } finally {
        processing.value = false;
    }
}

async function copyCode(): Promise<void> {
    await navigator.clipboard.writeText(activationCode.value);
    success('Disalin', 'Kode aktivasi disalin ke papan klip.');
}

function goBack(): void {
    router.visit(users.index.url());
}
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

            <div
                v-if="showCode"
                class="card space-y-4 border-emerald-200 bg-emerald-50 dark:border-emerald-800 dark:bg-emerald-950/30"
            >
                <h3
                    class="text-lg font-semibold text-emerald-900 dark:text-emerald-100"
                >
                    Kode Aktivasi Pengguna
                </h3>
                <p class="text-sm text-emerald-700 dark:text-emerald-300">
                    Bagikan kode ini kepada pengguna. Kode ini hanya berlaku
                    sekali dan akan kedaluwarsa dalam 72 jam.
                </p>
                <div class="flex items-center gap-3">
                    <div
                        class="flex-1 rounded-lg border border-emerald-300 bg-white p-4 font-mono text-2xl tracking-widest text-emerald-900 select-all dark:border-emerald-700 dark:bg-surface-900 dark:text-emerald-100"
                        :class="{ 'opacity-50': !showCode }"
                    >
                        {{ activationCode }}
                    </div>
                    <Button
                        type="button"
                        variant="secondary"
                        @click="copyCode"
                        :disabled="!showCode"
                    >
                        Salin
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        @click="regenerateCode"
                        :disabled="processing"
                    >
                        Buat Ulang
                    </Button>
                </div>
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
                    :loading="processing"
                    :disabled="showCode"
                >
                    {{ showCode ? 'Pengguna Dibuat' : 'Buat Pengguna' }}
                </Button>
            </div>
        </form>
    </div>
</template>
