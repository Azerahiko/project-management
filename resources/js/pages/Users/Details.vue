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
    role: 'member' as string,
    is_active: true,
});

const originalData = reactive<Record<string, any>>({});

const errors = ref<Record<string, string[]>>({});
const processing = ref(false);
const isAdmin = page.props.auth.user?.roles.includes('administrator') ?? false;
const isEditing = ref(false);

function loadUser(): void {
    form.name = initialUser.name;
    form.email = initialUser.email;
    form.role = initialUser.roles[0] || 'member';
    form.is_active = initialUser.is_active;

    originalData.name = initialUser.name;
    originalData.email = initialUser.email;
    originalData.role = initialUser.roles[0] || 'member';
    originalData.is_active = initialUser.is_active;
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
        form.is_active !== originalData.is_active
    );
}

function toggleEditMode(): void {
    if (isEditing.value) {
        // Cancel - reset to original data
        form.name = originalData.name;
        form.email = originalData.email;
        form.role = originalData.role;
        form.is_active = originalData.is_active;
        errors.value = {};
    }
    isEditing.value = !isEditing.value;
}

async function handleSubmit(): Promise<void> {
    if (!isDirty()) {
        isEditing.value = false;
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
        };

        await api.put(`/users/${initialUser.id}`, payload);
        success('Pengguna diperbarui', 'Perubahan telah disimpan.');

        // Update original data to current form values
        originalData.name = form.name;
        originalData.email = form.email;
        originalData.role = form.role;
        originalData.is_active = form.is_active;

        isEditing.value = false;
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

function goBack(): void {
    router.visit(users.index.url());
}

function formatDate(dateString: string): string {
    if (!dateString) return '—';
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

function getInvitationStatusLabel(user: User): {
    status: string;
    label: string;
    color: string;
} {
    const inv = (user as any).invitation_status;
    if (!inv) return { status: 'unknown', label: '—', color: 'surface-400' };

    switch (inv.status) {
        case 'accepted':
            return {
                status: 'accepted',
                label: 'Digunakan',
                color: 'emerald-500',
            };
        case 'pending':
            return {
                status: 'pending',
                label: 'Terkirim',
                color: 'primary-500',
            };
        case 'expired':
            return {
                status: 'expired',
                label: 'Kedaluwarsa',
                color: 'amber-500',
            };
        case 'never_sent':
        default:
            return {
                status: 'never_sent',
                label: 'Belum pernah dikirim',
                color: 'surface-400',
            };
    }
}

function getInvitationSentAt(user: User): string | null {
    const inv = (user as any).invitation_status;
    if (!inv || !inv.sent_at) return null;
    return formatDate(inv.sent_at);
}

function getRoleLabel(role: string): string {
    const found = roleOptions.find((r) => r.value === role);
    return found?.label || role;
}
</script>

<template>
    <div class="mx-auto max-w-3xl space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <Link
                    :href="users.index.url()"
                    class="mb-4 inline-flex items-center gap-1.5 text-sm text-surface-500 hover:text-surface-700 dark:text-surface-400 dark:hover:text-surface-200"
                >
                    <svg
                        class="h-4 w-4"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M15 19l-7-7 7-7"
                        />
                    </svg>
                    Kembali ke Daftar Pengguna
                </Link>
                <h1
                    class="text-2xl font-semibold tracking-tight text-surface-950 dark:text-surface-50"
                >
                    Detail Pengguna
                </h1>
                <p
                    class="mt-1 text-sm leading-6 text-surface-700 dark:text-surface-200"
                >
                    Informasi lengkap pengguna {{ initialUser.name }}
                </p>
            </div>
            <div class="flex items-center gap-3">
                <Link :href="users.index.url()">
                    <Button variant="ghost">Kembali</Button>
                </Link>
                <Button
                    variant="ghost"
                    @click="toggleEditMode"
                    :disabled="processing"
                >
                    {{ isEditing ? 'Batal' : 'Mode Edit' }}
                </Button>
            </div>
        </div>

        <!-- User Info Card -->
        <div class="card space-y-6">
            <div class="flex items-center gap-4">
                <div class="relative">
                    <div
                        class="flex h-20 w-20 items-center justify-center rounded-2xl bg-gradient-to-br from-[#0F5132] to-[#14643f] text-2xl font-bold text-white"
                    >
                        {{ initialUser.initials }}
                    </div>
                    <span
                        class="absolute -right-1 -bottom-1 h-5 w-5 rounded-full ring-4 ring-white dark:ring-surface-900"
                        :class="
                            initialUser.is_active
                                ? 'bg-emerald-500'
                                : 'bg-surface-400'
                        "
                        :title="
                            initialUser.is_active
                                ? 'Pengguna Aktif'
                                : 'Nonaktif'
                        "
                    />
                </div>
                <div class="flex-1">
                    <h2
                        class="font-serif text-xl font-normal text-surface-950 dark:text-surface-50"
                    >
                        {{ initialUser.name }}
                    </h2>
                    <p class="mt-1 text-surface-600 dark:text-surface-400">
                        {{ initialUser.email }}
                    </p>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        <span
                            v-for="role in initialUser.roles"
                            :key="role"
                            class="inline-flex items-center rounded-md border border-surface-200/80 bg-surface-50 px-2.5 py-0.5 text-sm font-medium text-surface-700 dark:border-surface-700 dark:bg-surface-800 dark:text-surface-300"
                        >
                            {{ getRoleLabel(role) }}
                        </span>
                    </div>
                </div>
            </div>

            <div
                class="border-t border-surface-100 pt-4 dark:border-surface-800"
            >
                <h3
                    class="mb-4 text-sm font-semibold text-surface-900 dark:text-surface-100"
                >
                    Informasi Akun
                </h3>

                <div
                    class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <!-- Name -->
                    <div class="space-y-1">
                        <label
                            class="text-[11px] font-medium tracking-wider text-surface-500 uppercase"
                        >
                            Nama Lengkap
                        </label>
                        <div
                            v-if="!isEditing"
                            class="text-sm text-surface-900 dark:text-surface-100"
                        >
                            {{ form.name }}
                        </div>
                        <Input
                            v-else
                            v-model="form.name"
                            label="Nama Lengkap"
                            placeholder="Masukkan nama lengkap"
                            required
                            :error="errors.name?.[0]"
                            autocomplete="name"
                            @input="clearError('name')"
                        />
                    </div>

                    <!-- Email -->
                    <div class="space-y-1">
                        <label
                            class="text-[11px] font-medium tracking-wider text-surface-500 uppercase"
                        >
                            Email
                        </label>
                        <div
                            v-if="!isEditing"
                            class="text-sm text-surface-900 dark:text-surface-100"
                        >
                            {{ form.email }}
                        </div>
                        <Input
                            v-else
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

                    <!-- Role -->
                    <div class="space-y-1">
                        <label
                            class="text-[11px] font-medium tracking-wider text-surface-500 uppercase"
                        >
                            Peran
                        </label>
                        <div
                            v-if="!isEditing"
                            class="text-sm text-surface-900 dark:text-surface-100"
                        >
                            {{ getRoleLabel(form.role) }}
                        </div>
                        <Select
                            v-else
                            v-model="form.role"
                            :options="roleOptions"
                            label="Peran"
                            required
                            :disabled="!isAdmin"
                            :error="errors.role?.[0]"
                            @change="clearError('role')"
                        />
                    </div>

                    <!-- Status -->
                    <div class="space-y-1">
                        <label
                            class="text-[11px] font-medium tracking-wider text-surface-500 uppercase"
                        >
                            Status
                        </label>
                        <div v-if="!isEditing" class="flex items-center gap-2">
                            <span
                                class="h-2.5 w-2.5 rounded-full"
                                :class="
                                    form.is_active
                                        ? 'bg-emerald-500'
                                        : 'bg-surface-400'
                                "
                            />
                            <span
                                :class="
                                    form.is_active
                                        ? 'text-surface-800 dark:text-surface-200'
                                        : 'text-surface-400'
                                "
                            >
                                {{ form.is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </div>
                        <div v-else class="flex items-center">
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

                    <!-- Email Verified -->
                    <div class="space-y-1">
                        <label
                            class="text-[11px] font-medium tracking-wider text-surface-500 uppercase"
                        >
                            Email Terverifikasi
                        </label>
                        <div class="flex items-center gap-2">
                            <span
                                class="h-2.5 w-2.5 rounded-full"
                                :class="
                                    initialUser.email_verified_at
                                        ? 'bg-emerald-500'
                                        : 'bg-surface-400'
                                "
                            />
                            <span
                                :class="
                                    initialUser.email_verified_at
                                        ? 'text-surface-800 dark:text-surface-200'
                                        : 'text-surface-400'
                                "
                            >
                                {{
                                    initialUser.email_verified_at
                                        ? 'Ya'
                                        : 'Belum'
                                }}
                            </span>
                        </div>
                    </div>

                    <!-- 2FA Status -->
                    <div class="space-y-1">
                        <label
                            class="text-[11px] font-medium tracking-wider text-surface-500 uppercase"
                        >
                            2FA (TOTP)
                        </label>
                        <div class="flex items-center gap-2">
                            <span
                                class="h-2.5 w-2.5 rounded-full"
                                :class="
                                    initialUser.two_factor_enabled
                                        ? 'bg-emerald-500'
                                        : 'bg-surface-400'
                                "
                            />
                            <span
                                :class="
                                    initialUser.two_factor_enabled
                                        ? 'text-surface-800 dark:text-surface-200'
                                        : 'text-surface-400'
                                "
                            >
                                {{
                                    initialUser.two_factor_enabled
                                        ? 'Aktif'
                                        : 'Nonaktif'
                                }}
                            </span>
                        </div>
                    </div>

                    <!-- Created At -->
                    <div class="space-y-1">
                        <label
                            class="text-[11px] font-medium tracking-wider text-surface-500 uppercase"
                        >
                            Dibuat Pada
                        </label>
                        <div
                            class="text-sm text-surface-900 dark:text-surface-100"
                        >
                            {{ formatDate(initialUser.created_at) }}
                        </div>
                    </div>

                    <!-- Updated At -->
                    <div class="space-y-1">
                        <label
                            class="text-[11px] font-medium tracking-wider text-surface-500 uppercase"
                        >
                            Diperbarui Pada
                        </label>
                        <div
                            class="text-sm text-surface-900 dark:text-surface-100"
                        >
                            {{ formatDate(initialUser.updated_at) }}
                        </div>
                    </div>

                    <!-- Onboarding Status -->
                    <div class="space-y-1">
                        <label
                            class="text-[11px] font-medium tracking-wider text-surface-500 uppercase"
                        >
                            Onboarding
                        </label>
                        <div class="flex items-center gap-2">
                            <span
                                class="h-2.5 w-2.5 rounded-full"
                                :class="
                                    initialUser.onboarding_completed_at
                                        ? 'bg-emerald-500'
                                        : 'bg-amber-500'
                                "
                            />
                            <span
                                :class="
                                    initialUser.onboarding_completed_at
                                        ? 'text-surface-800 dark:text-surface-200'
                                        : 'text-amber-700 dark:text-amber-300'
                                "
                            >
                                {{
                                    initialUser.onboarding_completed_at
                                        ? 'Selesai'
                                        : 'Belum Selesai'
                                }}
                            </span>
                        </div>
                    </div>

                    <!-- Invitation Status -->
                    <div class="space-y-1">
                        <label
                            class="text-[11px] font-medium tracking-wider text-surface-500 uppercase"
                        >
                            Status Invitation
                        </label>
                        <div class="flex items-center gap-2">
                            <span
                                :class="[
                                    'h-2.5 w-2.5 rounded-full',
                                    getInvitationStatusLabel(initialUser).color,
                                ]"
                            />
                            <span
                                :class="[
                                    getInvitationStatusLabel(initialUser)
                                        .color === 'emerald-500'
                                        ? 'text-surface-800 dark:text-surface-200'
                                        : getInvitationStatusLabel(initialUser)
                                                .color === 'primary-500'
                                          ? 'text-primary-700 dark:text-primary-300'
                                          : getInvitationStatusLabel(
                                                  initialUser,
                                              ).color === 'amber-500'
                                            ? 'text-amber-700 dark:text-amber-300'
                                            : 'text-surface-400',
                                ]"
                            >
                                {{
                                    getInvitationStatusLabel(initialUser).label
                                }}
                            </span>
                        </div>
                        <p
                            v-if="getInvitationSentAt(initialUser)"
                            class="text-xs text-surface-500"
                        >
                            Terakhir dikirim:
                            {{ getInvitationSentAt(initialUser) }}
                        </p>
                    </div>
                </div>

                <!-- Security Note -->
                <div
                    class="mt-6 rounded-lg bg-blue-50 p-4 text-sm text-blue-700 dark:bg-blue-900/30 dark:text-blue-300"
                >
                    <p class="font-medium">Catatan Keamanan:</p>
                    <ul class="mt-1 list-inside list-disc space-y-1">
                        <li>
                            Password tidak dapat diubah melalui halaman ini.
                        </li>
                        <li>
                            Pengguna harus mengubah password sendiri melalui
                            halaman Pengaturan atau alur Reset Password.
                        </li>
                        <li>
                            Pengguna wajib mengaktifkan 2FA (TOTP) saat
                            onboarding.
                        </li>
                        <li>Passkey bersifat opsional.</li>
                    </ul>
                </div>

                <!-- Admin-only role change note -->
                <p
                    v-if="!isAdmin && isEditing"
                    class="mt-2 text-sm text-surface-500 dark:text-surface-400"
                >
                    Hanya Administrator yang dapat mengubah peran.
                </p>
            </div>

            <!-- Action Buttons (only in edit mode) -->
            <div
                v-if="isEditing"
                class="flex justify-end gap-3 border-t border-surface-100 pt-4 dark:border-surface-800"
            >
                <Button
                    type="button"
                    variant="secondary"
                    @click="toggleEditMode"
                >
                    Batal
                </Button>
                <Button
                    type="button"
                    variant="primary"
                    :loading="processing"
                    :disabled="!isDirty()"
                    @click="handleSubmit"
                >
                    Simpan Perubahan
                </Button>
            </div>
        </div>

        <!-- Error State -->
        <div
            v-if="errors.form"
            class="rounded-lg bg-accent-50 p-4 text-accent-700 dark:bg-accent-900/30 dark:text-accent-300"
        >
            {{ errors.form[0] }}
        </div>
    </div>
</template>
