<script setup lang="ts">
import { ref, reactive } from 'vue';
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
    role: 'member' as string,
});

const errors = ref<Record<string, string[]>>({});
const processing = ref(false);
const inviteResult = ref<{
    activation_code: string;
    invitation_url: string;
    user: { name: string; email: string; role: string };
} | null>(null);
const showResult = ref(false);

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
    showResult.value = false;
    inviteResult.value = null;

    try {
        const response = await api.post('/invitations', form);
        inviteResult.value = response.data;
        showResult.value = true;
        success(
            'Undangan berhasil dikirim',
            'Kode aktivasi ditampilkan di bawah dan dikirim via email.',
        );
    } catch (err: any) {
        if (err.response?.data?.errors) {
            setErrors(err.response.data.errors);
        } else if (err.response?.data?.message) {
            toastError(err.response.data.message);
        }
    } finally {
        processing.value = false;
    }
}

function resetForm(): void {
    form.name = '';
    form.email = '';
    form.role = 'member';
    showResult.value = false;
    inviteResult.value = null;
}

function copyToClipboard(text: string): void {
    navigator.clipboard.writeText(text);
    success('Tersalin', 'Teks berhasil disalin ke clipboard.');
}
</script>

<template>
    <div class="mx-auto max-w-2xl space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1
                    class="text-2xl font-semibold tracking-tight text-surface-950 dark:text-surface-50"
                >
                    Undang Pengguna Baru
                </h1>
                <p
                    class="mt-1 text-sm leading-6 text-surface-700 dark:text-surface-200"
                >
                    Kirim undangan melalui email untuk mendaftarkan pengguna
                    baru. Kode aktivasi akan dikirim via email dan ditampilkan
                    di sini.
                </p>
            </div>
            <Link :href="users.index.url()">
                <Button variant="ghost">Kembali</Button>
            </Link>
        </div>

        <!-- Success/Result Card -->
        <div
            v-if="showResult && inviteResult"
            class="rounded-lg border border-green-200 bg-green-50 p-6 dark:border-green-800 dark:bg-green-900/20"
        >
            <div class="flex items-start gap-4">
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-green-100 dark:bg-green-900"
                >
                    <svg
                        class="h-5 w-5 text-green-600 dark:text-green-400"
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
                <div class="flex-1 space-y-4">
                    <div>
                        <h3
                            class="font-semibold text-green-800 dark:text-green-200"
                        >
                            Undangan Berhasil Dikirim
                        </h3>
                        <p
                            class="mt-1 text-sm text-green-700 dark:text-green-300"
                        >
                            Kode aktivasi untuk
                            <strong>{{ inviteResult.user.name }}</strong> ({{
                                inviteResult.user.email
                            }})
                        </p>
                    </div>

                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <code
                                class="flex-1 rounded bg-white px-3 py-2 font-mono text-2xl tracking-widest text-green-800 dark:bg-green-950 dark:text-green-200"
                            >
                                {{ inviteResult.activation_code }}
                            </code>
                            <Button
                                variant="secondary"
                                size="sm"
                                @click="
                                    copyToClipboard(
                                        inviteResult.activation_code,
                                    )
                                "
                            >
                                Salin
                            </Button>
                        </div>
                        <p class="text-xs text-green-600 dark:text-green-400">
                            Simpan kode ini! Kode hanya berlaku sekali dan akan
                            kedaluwarsa dalam 72 jam.
                        </p>
                    </div>

                    <div class="space-y-2">
                        <label
                            class="text-sm font-medium text-green-800 dark:text-green-200"
                        >
                            Tautan Undangan:
                        </label>
                        <div class="flex items-center gap-2">
                            <code
                                class="flex-1 truncate rounded bg-white px-3 py-2 font-mono text-xs dark:bg-green-950"
                            >
                                {{ inviteResult.invitation_url }}
                            </code>
                            <Button
                                variant="secondary"
                                size="sm"
                                @click="
                                    copyToClipboard(inviteResult.invitation_url)
                                "
                            >
                                Salin
                            </Button>
                        </div>
                    </div>

                    <div class="pt-2">
                        <Button variant="primary" size="sm" @click="resetForm">
                            Undang Pengguna Lain
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Invite Form -->
        <form
            v-if="!showResult"
            @submit.prevent="handleSubmit"
            class="space-y-6"
        >
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
                <Button type="submit" variant="primary" :loading="processing">
                    Kirim Undangan
                </Button>
            </div>
        </form>
    </div>
</template>
