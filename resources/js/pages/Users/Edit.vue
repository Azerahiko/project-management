<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { usePage, Link } from '@inertiajs/vue3';
import users from '@/routes/users';
import api from '@/lib/api';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Select from '@/components/ui/Select.vue';
import { useToast } from '@/composables/useToast';
import type { User } from '@/types';

defineOptions({
    layout: AppLayout,
});

const { success, error: toastError } = useToast();

const page = usePage();
const initialUser = page.props.user as User;

const roleOptions = [
    { value: 'administrator', label: 'Administrator' },
    { value: 'project_manager', label: 'Project Manager' },
    { value: 'team_leader', label: 'Team Leader' },
    { value: 'member', label: 'Member' },
    { value: 'reviewer', label: 'Reviewer' },
    { value: 'client', label: 'Client' },
];

const form = reactive({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: 'member' as string,
    is_active: true,
    avatar: '',
});

const originalData = reactive<Record<string, any>>({});

const errors = ref<Record<string, string[]>>({});
const processing = ref(false);
const isAdmin = page.props.auth.user?.roles.includes('administrator') ?? false;

function loadUser(): void {
    form.name = initialUser.name;
    form.email = initialUser.email;
    form.role = initialUser.roles[0] || 'member';
    form.is_active = initialUser.is_active;
    form.avatar = initialUser.avatar || '';

    originalData.name = initialUser.name;
    originalData.email = initialUser.email;
    originalData.role = initialUser.roles[0] || 'member';
    originalData.is_active = initialUser.is_active;
    originalData.avatar = initialUser.avatar || '';
}

onMounted(loadUser);

function setErrors(err: Record<string, string[]>): void {
    errors.value = err;
}

function clearError(field: string): void {
    if (errors.value[field]) {
        delete errors.value[field];
    }
}

function isDirty(): boolean {
    return (
        form.name !== originalData.name ||
        form.email !== originalData.email ||
        form.role !== originalData.role ||
        form.is_active !== originalData.is_active ||
        form.avatar !== originalData.avatar ||
        form.password !== ''
    );
}

async function handleSubmit(): Promise<void> {
    if (!isDirty()) {
        router.visit(users.index.url());
        return;
    }

    processing.value = true;
    errors.value = {};

    try {
        const payload: Record<string, any> = {
            name: form.name,
            email: form.email,
            role: form.role,
            is_active: form.is_active,
            avatar: form.avatar,
        };

        if (form.password) {
            payload.password = form.password;
            payload.password_confirmation = form.password_confirmation;
        }

        await api.put(`/users/${initialUser.id}`, payload);
        success('Pengguna diperbarui', 'Perubahan telah disimpan.');
        router.visit(users.index.url());
    } catch (err: any) {
        if (err.response?.data?.errors) {
            setErrors(err.response.data.errors);
        }
    } finally {
        processing.value = false;
    }
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
                    Edit Pengguna
                </h1>
                <p
                    class="mt-1 text-sm leading-6 text-surface-700 dark:text-surface-200"
                >
                    Perbarui informasi pengguna
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
                    <Input
                        v-model="form.password"
                        type="password"
                        label="Kata Sandi Baru (Kosongkan jika tidak diubah)"
                        placeholder="Masukkan kata sandi baru"
                        :error="errors.password?.[0]"
                        autocomplete="new-password"
                        @input="clearError('password')"
                    />

                    <Input
                        v-model="form.password_confirmation"
                        type="password"
                        label="Konfirmasi Kata Sandi Baru"
                        placeholder="Masukkan ulang kata sandi baru"
                        :error="errors.password_confirmation?.[0]"
                        autocomplete="new-password"
                        @input="clearError('password_confirmation')"
                    />
                </div>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <Select
                        v-model="form.role"
                        :options="roleOptions"
                        label="Peran"
                        required
                        :disabled="!isAdmin"
                        :error="errors.role?.[0]"
                        @change="clearError('role')"
                    />

                    <Input
                        v-model="form.avatar"
                        type="url"
                        label="URL Avatar (Opsional)"
                        placeholder="https://example.com/avatar.png"
                        :error="errors.avatar?.[0]"
                        @input="clearError('avatar')"
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

                <p
                    v-if="!isAdmin"
                    class="text-sm text-surface-500 dark:text-surface-400"
                >
                    Hanya Administrator yang dapat mengubah peran.
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
                    :loading="processing"
                    :disabled="!isDirty()"
                >
                    Simpan Perubahan
                </Button>
            </div>
        </form>
    </div>
</template>
