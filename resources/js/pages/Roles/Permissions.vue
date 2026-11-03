<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import roles from '@/routes/roles';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import Modal from '@/components/ui/Modal.vue';
import { getIcon } from '@/components/sidebar/icons';
import { useToast } from '@/composables/useToast';
import type { PageProps as InertiaPageProps } from '@inertiajs/core';

defineOptions({
    layout: AppLayout,
});

interface Role {
    id: number;
    name: string;
    label: string;
    permissions_count: number;
}

interface Permission {
    id: number;
    name: string;
}

interface GroupedPermission {
    [category: string]: Record<string, string>;
}

interface RolePermissions {
    [roleName: string]: string[];
}

interface PageProps extends InertiaPageProps {
    roles: Role[];
    permissions: Permission[];
    grouped_permissions: GroupedPermission;
    role_permissions: RolePermissions;
}

const page = usePage<PageProps>();
const { success, error } = useToast();

const roleLabels: Record<string, string> = {
    administrator: 'Administrator',
    project_manager: 'Manajer Proyek',
    team_leader: 'Ketua Tim',
    member: 'Anggota',
    reviewer: 'Peninjau',
    client: 'Klien',
};

const roleDescriptions: Record<string, string> = {
    administrator: 'Akses penuh dan kontrol sistem tertinggi',
    project_manager: 'Mengelola proyek, tugas, dan anggota tim',
    team_leader: 'Memimpin koordinasi alur kerja tim',
    member: 'Mengerjakan tugas dan berkolaborasi dalam proyek',
    reviewer: 'Meninjau hasil pekerjaan dan memberikan validasi',
    client: 'Akses terbatas untuk meninjau progres proyek',
};

const permissionLabels: Record<string, string> = {
    'dashboard.view': 'Lihat Dashboard',
    'notifications.view': 'Lihat Notifikasi',
    'projects.view': 'Lihat Proyek',
    'projects.create': 'Buat Proyek Baru',
    'projects.update': 'Ubah Detail Proyek',
    'projects.delete': 'Hapus Proyek',
    'projects.manage_members': 'Kelola Anggota Proyek',
    'tasks.view': 'Lihat Tugas',
    'task-collaboration.view': 'Kolaborasi Tugas',
    'teams.view': 'Lihat Anggota Tim',
    'calendar.view': 'Lihat Jadwal & Kalender',
    'documents.view': 'Lihat & Kelola Dokumen',
    'reports.view': 'Lihat Laporan & KPI',
    'activity-log.view': 'Lihat Log Aktivitas',
    'users.view': 'Lihat Pengguna',
    'users.create': 'Buat Pengguna Baru',
    'users.update': 'Ubah Pengguna',
    'users.delete': 'Hapus Pengguna',
    'roles.manage': 'Kelola Role & Permission',
    'settings.view': 'Lihat Pengaturan',
};

const categoryLabels: Record<string, string> = {
    General: 'Umum & Beranda',
    Workspace: 'Ruang Kerja & Proyek',
    Monitoring: 'Pemantauan & Analitik',
    Administration: 'Administrasi Sistem',
};

const categoryMeta: Record<string, { icon: string }> = {
    General: { icon: 'LayoutGrid' },
    Workspace: { icon: 'FolderKanban' },
    Monitoring: { icon: 'Activity' },
    Administration: { icon: 'Settings' },
};

// State
const firstEditableRole =
    page.props.roles.find((role) => role.name !== 'administrator')?.name ??
    page.props.roles[0]?.name ??
    null;

const selectedRole = ref<string | null>(firstEditableRole);
const rolePermissions = ref<Record<string, boolean>>({});
const originalPermissions = ref<Record<string, boolean>>({});
const hasUnsavedChanges = ref(false);
const saving = ref(false);
const confirmSaveModal = ref(false);
const roleSearch = ref('');
const permissionSearch = ref('');
const permissionStateFilter = ref<'all' | 'active' | 'inactive'>('all');

// Helper for accessing permission state
const getPermission = (name: string): boolean =>
    rolePermissions.value[name] ?? false;

function roleLabel(role: Role | null | undefined): string {
    if (!role) return '';
    return roleLabels[role.name] ?? role.label ?? role.name;
}

function permissionLabel(name: string, fallback?: string): string {
    return permissionLabels[name] ?? fallback ?? formatPermissionName(name);
}

function categoryLabel(category: string): string {
    return categoryLabels[category] ?? category;
}

function categoryIcon(category: string): string {
    return categoryMeta[category]?.icon ?? 'LayoutGrid';
}

function roleDescription(role: Role | null | undefined): string {
    if (!role) return '';
    return roleDescriptions[role.name] ?? 'Peran pengguna ruang kerja';
}

function roleInitials(label: string): string {
    const parts = label.trim().split(/\s+/);
    if (parts.length === 1) {
        return parts[0].slice(0, 2).toUpperCase();
    }
    return parts
        .slice(0, 2)
        .map((part) => part.charAt(0))
        .join('')
        .toUpperCase();
}

const filteredRoles = computed(() => {
    const query = roleSearch.value.trim().toLowerCase();
    if (!query) {
        return page.props.roles;
    }
    return page.props.roles.filter(
        (role) =>
            roleLabel(role).toLowerCase().includes(query) ||
            role.name.toLowerCase().includes(query),
    );
});

// Initialize role permissions
function initializePermissions(roleName: string): void {
    const perms = page.props.role_permissions[roleName] || [];
    const newPermissions: Record<string, boolean> = {};
    for (const perm of perms) {
        newPermissions[perm] = true;
    }
    rolePermissions.value = newPermissions;
    originalPermissions.value = { ...newPermissions };
    hasUnsavedChanges.value = false;
}

// Current Role
const currentRole = computed(() => {
    if (!selectedRole.value) return null;
    return page.props.roles.find((r) => r.name === selectedRole.value) || null;
});

const currentRoleLabel = computed(() => roleLabel(currentRole.value));
const currentRoleInitials = computed(() =>
    roleInitials(currentRoleLabel.value),
);

const isAdministratorSelected = computed(
    () => selectedRole.value === 'administrator',
);

const activePermissionCount = computed(
    () => Object.values(rolePermissions.value).filter(Boolean).length,
);

const totalPermissionCount = computed(() => page.props.permissions.length);

const permissionProgress = computed(() =>
    totalPermissionCount.value > 0
        ? Math.round(
              (activePermissionCount.value / totalPermissionCount.value) * 100,
          )
        : 0,
);

const groupedPermissionsWithState = computed(() => {
    if (!selectedRole.value) return {};

    const result: Record<
        string,
        {
            permissions: { name: string; label: string; checked: boolean }[];
            activeCount: number;
            totalCount: number;
        }
    > = {};

    const query = permissionSearch.value.trim().toLowerCase();

    for (const [category, permissions] of Object.entries(
        page.props.grouped_permissions,
    )) {
        const categoryPermissions = Object.entries(permissions).map(
            ([name, label]) => ({
                name,
                label: permissionLabel(name, label),
                checked: getPermission(name),
            }),
        );

        const activeCount = categoryPermissions.filter((p) => p.checked).length;
        const totalCount = categoryPermissions.length;

        let visible = categoryPermissions;

        // Text query filter
        if (query) {
            visible = visible.filter(
                (p) =>
                    p.label.toLowerCase().includes(query) ||
                    p.name.toLowerCase().includes(query),
            );
        }

        // Active / Inactive state filter
        if (permissionStateFilter.value === 'active') {
            visible = visible.filter((p) => p.checked);
        } else if (permissionStateFilter.value === 'inactive') {
            visible = visible.filter((p) => !p.checked);
        }

        if (visible.length === 0) {
            continue;
        }

        result[category] = {
            permissions: visible,
            activeCount,
            totalCount,
        };
    }

    return result;
});

const hasVisiblePermissions = computed(
    () => Object.keys(groupedPermissionsWithState.value).length > 0,
);

const visiblePermissionCount = computed(() =>
    Object.values(groupedPermissionsWithState.value).reduce(
        (total, group) => total + group.permissions.length,
        0,
    ),
);

const permissionGroupEntries = computed(() =>
    Object.entries(groupedPermissionsWithState.value),
);

const categoryAllSelected = (category: string) => {
    const perms = Object.keys(page.props.grouped_permissions[category] || {});
    return perms.every((p) => getPermission(p));
};

const categorySomeSelected = (category: string) => {
    const perms = Object.keys(page.props.grouped_permissions[category] || {});
    return perms.some((p) => getPermission(p));
};

// Watchers
watch(
    () => selectedRole.value,
    (newRole) => {
        if (newRole) {
            initializePermissions(newRole);
        }
    },
    { immediate: true },
);

watch(
    () => rolePermissions.value,
    () => {
        if (!selectedRole.value) return;
        const current = JSON.stringify(rolePermissions.value);
        const original = JSON.stringify(originalPermissions.value);
        hasUnsavedChanges.value = current !== original;
    },
    { deep: true },
);

// Actions
function selectRole(roleName: string): void {
    selectedRole.value = roleName;
    permissionSearch.value = '';
    permissionStateFilter.value = 'all';
}

function togglePermission(permissionName: string): void {
    if (isAdministratorSelected.value || saving.value) return;
    rolePermissions.value = {
        ...rolePermissions.value,
        [permissionName]: !rolePermissions.value[permissionName],
    };
}

function toggleCategory(category: string): void {
    if (isAdministratorSelected.value || saving.value) return;
    const perms = Object.keys(page.props.grouped_permissions[category] || {});
    const allSelected = categoryAllSelected(category);

    const newPermissions = { ...rolePermissions.value };
    perms.forEach((perm) => {
        newPermissions[perm] = !allSelected;
    });
    rolePermissions.value = newPermissions;
}

function resetChanges(): void {
    if (selectedRole.value) {
        rolePermissions.value = { ...originalPermissions.value };
        hasUnsavedChanges.value = false;
    }
}

function openConfirmSave(): void {
    confirmSaveModal.value = true;
}

async function saveChanges(): Promise<void> {
    if (!selectedRole.value) return;

    saving.value = true;
    confirmSaveModal.value = false;

    try {
        const permissionsToSend = Object.entries(rolePermissions.value)
            .filter(([, checked]) => checked)
            .map(([name]) => name);

        const role = page.props.roles.find(
            (r) => r.name === selectedRole.value,
        );
        const roleId = role?.id;

        await router.put(
            roles.permissions.update.url({ role: roleId ?? 0 }),
            { permissions: permissionsToSend },
            {
                preserveScroll: true,
                onSuccess: () => {
                    originalPermissions.value = { ...rolePermissions.value };
                    hasUnsavedChanges.value = false;
                    success('Berhasil', 'Hak akses role berhasil diperbarui.');
                },
                onError: (errors) => {
                    error(
                        'Gagal',
                        errors.permissions?.[0] ||
                            'Gagal memperbarui hak akses role.',
                    );
                },
            },
        );
    } catch {
        // Handled by onError
    } finally {
        saving.value = false;
    }
}

function formatPermissionName(name: string): string {
    return name
        .split('.')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}
</script>

<template>
    <div class="space-y-6 pb-20">
        <!-- 1. Page Header -->
        <header
            class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <span
                    class="inline-flex h-6 items-center gap-1.5 rounded-full border border-primary-200/80 bg-primary-50 px-2.5 text-[10px] font-bold tracking-wider text-primary-800 uppercase dark:border-primary-800/40 dark:bg-primary-950/40 dark:text-primary-300"
                >
                    <component :is="getIcon('Shield')" class="h-3 w-3" />
                    Manajemen Hak Akses
                </span>
                <h1
                    class="mt-2 text-2xl font-semibold tracking-tight text-surface-950 sm:text-3xl dark:text-surface-50"
                >
                    Role & Hak Akses
                </h1>
                <p
                    class="mt-1 max-w-2xl text-sm leading-relaxed text-surface-600 dark:text-surface-300"
                >
                    Tentukan hak akses permission untuk setiap peran pengguna di seluruh ruang kerja.
                </p>
            </div>

            <!-- Quick Role Counts -->
            <div class="flex items-center gap-2">
                <span
                    class="inline-flex items-center gap-2 rounded-xl border border-surface-200/80 bg-white px-3 py-1.5 text-xs font-semibold text-surface-700 shadow-2xs dark:border-surface-800 dark:bg-surface-900 dark:text-surface-200"
                >
                    <component
                        :is="getIcon('UsersRound')"
                        class="h-3.5 w-3.5 text-surface-400"
                    />
                    {{ page.props.roles.length }} Role
                </span>
                <span
                    class="inline-flex items-center gap-2 rounded-xl border border-surface-200/80 bg-white px-3 py-1.5 text-xs font-semibold text-surface-700 shadow-2xs dark:border-surface-800 dark:bg-surface-900 dark:text-surface-200"
                >
                    <component
                        :is="getIcon('Shield')"
                        class="h-3.5 w-3.5 text-surface-400"
                    />
                    {{ totalPermissionCount }} Permission
                </span>
            </div>
        </header>

        <!-- 2. Mobile Role Switcher -->
        <div
            class="no-scrollbar flex items-center gap-2 overflow-x-auto pb-1 lg:hidden"
        >
            <button
                v-for="role in page.props.roles"
                :key="`mobile-${role.id}`"
                type="button"
                class="inline-flex shrink-0 items-center gap-2 rounded-xl border px-3.5 py-2 text-xs font-semibold transition"
                :class="
                    selectedRole === role.name
                        ? 'border-primary-600 bg-primary-700 text-white shadow-xs dark:bg-primary-600'
                        : 'border-surface-200 bg-white text-surface-700 hover:bg-surface-50 dark:border-surface-800 dark:bg-surface-900 dark:text-surface-300'
                "
                @click="selectRole(role.name)"
            >
                <span>{{ roleLabel(role) }}</span>
                <span
                    class="rounded-full px-1.5 py-0.2 text-[10px] font-bold"
                    :class="
                        selectedRole === role.name
                            ? 'bg-white/20 text-white'
                            : 'bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-400'
                    "
                >
                    {{ role.permissions_count }}
                </span>
            </button>
        </div>

        <!-- 3. Main Grid Layout -->
        <div class="grid gap-6 lg:grid-cols-[300px_minmax(0,1fr)] lg:items-start">
            <!-- Left: Roles List Sidebar -->
            <aside class="space-y-4 lg:sticky lg:top-24">
                <div
                    class="rounded-3xl border border-surface-200/80 bg-white p-4 shadow-xs sm:p-5 dark:border-surface-800 dark:bg-surface-900"
                >
                    <div class="flex items-center justify-between gap-3">
                        <p
                            class="text-[10px] font-bold tracking-[0.16em] text-surface-400 uppercase dark:text-surface-500"
                        >
                            Daftar Role
                        </p>
                        <span class="text-xs font-medium text-surface-500 dark:text-surface-400">
                            {{ filteredRoles.length }} role
                        </span>
                    </div>

                    <!-- Search Input -->
                    <div class="relative mt-3">
                        <span
                            class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-surface-400"
                        >
                            <component :is="getIcon('Search')" class="h-3.5 w-3.5" />
                        </span>
                        <input
                            v-model="roleSearch"
                            type="search"
                            placeholder="Cari role..."
                            class="input py-2 pl-9 text-xs"
                        />
                    </div>

                    <!-- Role Items List -->
                    <div class="mt-3.5 space-y-1.5 max-h-[460px] overflow-y-auto pr-0.5">
                        <button
                            v-for="role in filteredRoles"
                            :key="role.id"
                            type="button"
                            class="flex w-full items-center gap-3 rounded-2xl border p-2.5 text-left transition-all"
                            :class="
                                selectedRole === role.name
                                    ? 'border-[#0F5132] bg-primary-50/50 shadow-2xs ring-1 ring-[#0F5132]/20 dark:border-primary-500 dark:bg-primary-950/30'
                                    : 'border-transparent hover:border-surface-200 hover:bg-surface-50 dark:hover:border-surface-800 dark:hover:bg-surface-800/40'
                            "
                            @click="selectRole(role.name)"
                        >
                            <span
                                class="grid h-10 w-10 shrink-0 place-items-center rounded-xl text-xs font-bold transition-colors"
                                :class="
                                    selectedRole === role.name
                                        ? 'bg-[#0F5132] text-white dark:bg-primary-600'
                                        : 'bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-300'
                                "
                            >
                                {{ roleInitials(roleLabel(role)) }}
                            </span>

                            <div class="min-w-0 flex-1">
                                <p
                                    class="truncate text-xs font-semibold sm:text-sm"
                                    :class="
                                        selectedRole === role.name
                                            ? 'text-surface-950 dark:text-surface-50'
                                            : 'text-surface-800 dark:text-surface-200'
                                    "
                                >
                                    {{ roleLabel(role) }}
                                </p>
                                <p
                                    class="mt-0.5 truncate text-[11px] text-surface-500 dark:text-surface-400"
                                >
                                    {{ roleDescription(role) }}
                                </p>
                            </div>

                            <span
                                class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold"
                                :class="
                                    selectedRole === role.name
                                        ? 'bg-primary-100 text-primary-800 dark:bg-primary-900/60 dark:text-primary-200'
                                        : 'bg-surface-100 text-surface-500 dark:bg-surface-800 dark:text-surface-400'
                                "
                            >
                                {{ role.permissions_count }}
                            </span>
                        </button>

                        <p
                            v-if="filteredRoles.length === 0"
                            class="py-6 text-center text-xs text-surface-400"
                        >
                            Tidak ada role yang sesuai.
                        </p>
                    </div>
                </div>

                <!-- Guidance card -->
                <div
                    class="rounded-3xl border border-primary-200/70 bg-primary-50/50 p-4 text-xs text-primary-900 dark:border-primary-800/40 dark:bg-primary-950/20 dark:text-primary-200"
                >
                    <div class="flex items-center gap-2 font-semibold">
                        <component :is="getIcon('Lock')" class="h-4 w-4 text-primary-700 dark:text-primary-300" />
                        <span>Keamanan Akses Ruang Kerja</span>
                    </div>
                    <p class="mt-1 text-[11px] leading-relaxed text-primary-800/90 dark:text-primary-300/80">
                        Setiap perubahan hak akses langsung mempengaruhi izin seluruh anggota yang memiliki role tersebut.
                    </p>
                </div>
            </aside>

            <!-- Right: Permission Management Panel -->
            <main class="min-w-0">
                <!-- If no role selected -->
                <div
                    v-if="!selectedRole"
                    class="rounded-3xl border border-surface-200/80 bg-white p-12 text-center shadow-xs dark:border-surface-800 dark:bg-surface-900"
                >
                    <component :is="getIcon('Shield')" class="mx-auto h-12 w-12 text-surface-300" />
                    <h3 class="mt-3 text-base font-semibold text-surface-800 dark:text-surface-200">
                        Pilih Role untuk Memulai
                    </h3>
                    <p class="mt-1 text-xs text-surface-500">
                        Pilih salah satu role di sebelah kiri untuk melihat dan menyesuaikan permission.
                    </p>
                </div>

                <!-- Role Permissions Panel -->
                <div
                    v-else
                    class="overflow-hidden rounded-3xl border border-surface-200/80 bg-white shadow-xs dark:border-surface-800 dark:bg-surface-900"
                >
                    <!-- Role Header -->
                    <div
                        class="border-b border-surface-200/80 p-5 sm:p-6 dark:border-surface-800"
                    >
                        <div
                            class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                        >
                            <div class="flex items-start gap-4">
                                <span
                                    class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-primary-50 font-serif text-lg text-primary-800 shadow-2xs dark:bg-primary-950/50 dark:text-primary-300"
                                >
                                    {{ currentRoleInitials }}
                                </span>

                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h2
                                            class="text-xl font-semibold tracking-tight text-surface-950 dark:text-surface-50"
                                        >
                                            {{ currentRoleLabel }}
                                        </h2>
                                        <span
                                            v-if="isAdministratorSelected"
                                            class="inline-flex items-center rounded-full bg-amber-50 border border-amber-200/80 px-2 py-0.5 text-[10px] font-bold text-amber-800 dark:bg-amber-950/40 dark:border-amber-800/40 dark:text-amber-300"
                                        >
                                            Role Sistem Tetap
                                        </span>
                                        <span
                                            v-if="hasUnsavedChanges"
                                            class="inline-flex items-center gap-1.5 rounded-full bg-primary-50 border border-primary-200/80 px-2 py-0.5 text-[10px] font-bold text-primary-800 dark:bg-primary-950/40 dark:border-primary-800/40 dark:text-primary-300"
                                        >
                                            <span class="h-1.5 w-1.5 rounded-full bg-[#0F5132] animate-pulse dark:bg-primary-400" />
                                            Perubahan Belum Disimpan
                                        </span>
                                    </div>

                                    <p class="mt-1 text-xs text-surface-500 dark:text-surface-400">
                                        {{ roleDescription(currentRole) }}
                                        <span class="mx-1 text-surface-300 dark:text-surface-600">·</span>
                                        <code class="font-mono text-[11px] text-surface-400">{{ selectedRole }}</code>
                                    </p>

                                    <!-- Progress indicator -->
                                    <div class="mt-3 flex items-center gap-3">
                                        <div
                                            class="h-1.5 w-32 overflow-hidden rounded-full bg-surface-100 dark:bg-surface-800"
                                        >
                                            <div
                                                class="h-full rounded-full bg-[#0F5132] transition-all duration-500 dark:bg-primary-400"
                                                :style="{ width: `${permissionProgress}%` }"
                                            />
                                        </div>
                                        <span class="text-xs text-surface-500 dark:text-surface-400">
                                            <strong class="font-semibold text-surface-800 dark:text-surface-200">{{ activePermissionCount }}</strong>
                                            dari {{ totalPermissionCount }} permission aktif ({{ permissionProgress }}%)
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Header Save / Status Actions -->
                            <div
                                v-if="!isAdministratorSelected"
                                class="hidden shrink-0 items-center gap-2 sm:flex"
                            >
                                <span
                                    v-if="!hasUnsavedChanges"
                                    class="inline-flex items-center gap-1.5 rounded-xl border border-surface-200 bg-surface-50 px-3 py-1.5 text-xs font-semibold text-surface-500 dark:border-surface-800 dark:bg-surface-900 dark:text-surface-400"
                                >
                                    <component :is="getIcon('Check')" class="h-3.5 w-3.5 text-emerald-600" />
                                    Tersimpan
                                </span>
                                <template v-else>
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        @click="resetChanges"
                                    >
                                        <component :is="getIcon('RotateCcw')" class="h-3.5 w-3.5" />
                                        <span>Reset</span>
                                    </Button>
                                    <Button
                                        variant="primary"
                                        size="sm"
                                        :loading="saving"
                                        @click="openConfirmSave"
                                    >
                                        <component :is="getIcon('Save')" class="h-3.5 w-3.5" />
                                        <span>Simpan Perubahan</span>
                                    </Button>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- Toolbar: Search + Quick Filter Tabs -->
                    <div
                        class="flex flex-col gap-3 border-b border-surface-200/80 p-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 dark:border-surface-800"
                    >
                        <div class="relative w-full sm:max-w-xs">
                            <span
                                class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-surface-400"
                            >
                                <component :is="getIcon('Search')" class="h-3.5 w-3.5" />
                            </span>
                            <input
                                v-model="permissionSearch"
                                type="search"
                                placeholder="Cari permission..."
                                class="input py-2 pl-9 text-xs"
                            />
                        </div>

                        <!-- Filter Status Pills -->
                        <div class="flex items-center gap-1.5">
                            <button
                                type="button"
                                class="rounded-xl px-2.5 py-1 text-xs font-semibold transition"
                                :class="
                                    permissionStateFilter === 'all'
                                        ? 'bg-[#0F5132] text-white dark:bg-primary-600'
                                        : 'bg-surface-100 text-surface-600 hover:bg-surface-200 dark:bg-surface-800 dark:text-surface-300'
                                "
                                @click="permissionStateFilter = 'all'"
                            >
                                Semua
                            </button>
                            <button
                                type="button"
                                class="rounded-xl px-2.5 py-1 text-xs font-semibold transition"
                                :class="
                                    permissionStateFilter === 'active'
                                        ? 'bg-[#0F5132] text-white dark:bg-primary-600'
                                        : 'bg-surface-100 text-surface-600 hover:bg-surface-200 dark:bg-surface-800 dark:text-surface-300'
                                "
                                @click="permissionStateFilter = 'active'"
                            >
                                Aktif
                            </button>
                            <button
                                type="button"
                                class="rounded-xl px-2.5 py-1 text-xs font-semibold transition"
                                :class="
                                    permissionStateFilter === 'inactive'
                                        ? 'bg-[#0F5132] text-white dark:bg-primary-600'
                                        : 'bg-surface-100 text-surface-600 hover:bg-surface-200 dark:bg-surface-800 dark:text-surface-300'
                                "
                                @click="permissionStateFilter = 'inactive'"
                            >
                                Nonaktif
                            </button>
                        </div>
                    </div>

                    <!-- Administrator notice -->
                    <div
                        v-if="isAdministratorSelected"
                        class="m-4 flex items-start gap-3 rounded-2xl border border-amber-200/80 bg-amber-50/60 p-4 sm:m-6 dark:border-amber-800/40 dark:bg-amber-950/20"
                    >
                        <span
                            class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-300"
                        >
                            <component :is="getIcon('Lock')" class="h-4 w-4" />
                        </span>
                        <div>
                            <p class="text-xs font-bold text-amber-900 dark:text-amber-200">
                                Role Administrator Memiliki Akses Penuh Sistem
                            </p>
                            <p class="mt-0.5 text-xs leading-relaxed text-amber-800/80 dark:text-amber-300/80">
                                Hak akses Administrator dikunci secara permanen pada level kode agar sistem selalu dapat dikelola dan tidak mengalami kegagalan akses.
                            </p>
                        </div>
                    </div>

                    <!-- Categories in Clean 2-Column Responsive CSS Grid (NO awkward column breaking!) -->
                    <div
                        v-if="hasVisiblePermissions"
                        class="grid grid-cols-1 gap-4 p-4 sm:p-6 xl:grid-cols-2"
                    >
                        <div
                            v-for="[category, group] in permissionGroupEntries"
                            :key="category"
                            class="flex flex-col overflow-hidden rounded-2xl border border-surface-200/80 bg-white transition hover:border-surface-300 dark:border-surface-800 dark:bg-surface-900 dark:hover:border-surface-700"
                        >
                            <!-- Category Header -->
                            <div
                                class="flex items-center justify-between gap-3 border-b border-surface-100 bg-surface-50/60 px-4 py-3 dark:border-surface-800/80 dark:bg-surface-950/30"
                            >
                                <div class="flex items-center gap-2.5">
                                    <span
                                        class="grid h-7 w-7 place-items-center rounded-lg bg-white text-surface-600 shadow-2xs dark:bg-surface-800 dark:text-surface-300"
                                    >
                                        <component
                                            :is="getIcon(categoryIcon(category))"
                                            class="h-3.5 w-3.5 text-primary-700 dark:text-primary-400"
                                        />
                                    </span>
                                    <div>
                                        <h3
                                            class="text-xs font-semibold text-surface-900 dark:text-surface-100"
                                        >
                                            {{ categoryLabel(category) }}
                                        </h3>
                                        <p class="text-[10px] text-surface-500 dark:text-surface-400">
                                            {{ group.activeCount }} dari {{ group.totalCount }} aktif
                                        </p>
                                    </div>
                                </div>

                                <!-- Toggle all category button -->
                                <button
                                    type="button"
                                    :disabled="isAdministratorSelected || saving"
                                    class="rounded-lg border border-surface-200 bg-white px-2 py-1 text-[11px] font-semibold text-surface-700 transition hover:border-primary-300 hover:text-primary-800 disabled:cursor-not-allowed disabled:opacity-40 dark:border-surface-700 dark:bg-surface-800 dark:text-surface-300 dark:hover:border-primary-700"
                                    @click="toggleCategory(category)"
                                >
                                    {{
                                        categoryAllSelected(category)
                                            ? 'Kosongkan'
                                            : 'Pilih Semua'
                                    }}
                                </button>
                            </div>

                            <!-- Permissions List -->
                            <div class="divide-y divide-surface-100 dark:divide-surface-800/60">
                                <label
                                    v-for="perm in group.permissions"
                                    :key="perm.name"
                                    class="flex items-center gap-3 px-4 py-2.5 transition-colors"
                                    :class="[
                                        isAdministratorSelected
                                            ? 'cursor-not-allowed opacity-80'
                                            : 'cursor-pointer hover:bg-surface-50/70 dark:hover:bg-surface-800/30',
                                        perm.checked
                                            ? 'bg-primary-50/20 dark:bg-primary-950/10'
                                            : '',
                                    ]"
                                >
                                    <input
                                        type="checkbox"
                                        :checked="perm.checked"
                                        :disabled="isAdministratorSelected || saving"
                                        class="h-4 w-4 rounded border-surface-300 text-[#0F5132] focus:ring-2 focus:ring-[#0F5132]/20 disabled:cursor-not-allowed dark:border-surface-600 dark:bg-surface-800"
                                        @change="togglePermission(perm.name)"
                                    />

                                    <div class="min-w-0 flex-1">
                                        <p
                                            class="truncate text-xs font-medium"
                                            :class="
                                                perm.checked
                                                    ? 'text-surface-950 font-semibold dark:text-surface-50'
                                                    : 'text-surface-600 dark:text-surface-400'
                                            "
                                        >
                                            {{ perm.label }}
                                        </p>
                                        <p
                                            class="font-mono text-[10px] text-surface-400 dark:text-surface-500"
                                        >
                                            {{ perm.name }}
                                        </p>
                                    </div>

                                    <span
                                        v-if="perm.checked"
                                        class="h-2 w-2 rounded-full bg-[#0F5132] dark:bg-primary-400"
                                    />
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Empty state if search has no match -->
                    <div
                        v-else
                        class="p-12 text-center"
                    >
                        <component
                            :is="getIcon('Search')"
                            class="mx-auto h-8 w-8 text-surface-300 dark:text-surface-600"
                        />
                        <h4 class="mt-2 text-sm font-semibold text-surface-800 dark:text-surface-200">
                            Tidak Ada Permission yang Sesuai
                        </h4>
                        <p class="mt-1 text-xs text-surface-500">
                            Coba bersihkan kolom pencarian atau ubah filter status aktif/nonaktif.
                        </p>
                        <button
                            type="button"
                            class="btn btn-secondary mt-3 text-xs"
                            @click="
                                permissionSearch = '';
                                permissionStateFilter = 'all';
                            "
                        >
                            Reset Filter
                        </button>
                    </div>
                </div>
            </main>
        </div>

        <!-- 4. Sticky Mobile & Desktop Save Bar when Unsaved Changes -->
        <div
            v-if="selectedRole && hasUnsavedChanges && !isAdministratorSelected"
            class="fixed inset-x-0 bottom-4 z-40 mx-auto max-w-lg px-4"
        >
            <div
                class="flex items-center justify-between gap-3 rounded-2xl border border-surface-200 bg-white/95 p-3.5 shadow-xl backdrop-blur-md dark:border-surface-700 dark:bg-surface-900/95"
            >
                <div class="min-w-0">
                    <p class="truncate text-xs font-bold text-surface-900 dark:text-surface-100">
                        Perubahan Belum Disimpan
                    </p>
                    <p class="truncate text-[11px] text-surface-500 dark:text-surface-400">
                        Role: {{ currentRoleLabel }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Button variant="ghost" size="sm" @click="resetChanges">
                        Batal
                    </Button>
                    <Button
                        variant="primary"
                        size="sm"
                        :loading="saving"
                        @click="openConfirmSave"
                    >
                        <component :is="getIcon('Save')" class="h-3.5 w-3.5" />
                        <span>Simpan</span>
                    </Button>
                </div>
            </div>
        </div>

        <!-- 5. Confirmation Modal -->
        <Modal
            :open="confirmSaveModal"
            title="Konfirmasi Perubahan Hak Akses"
            size="sm"
            @close="confirmSaveModal = false"
        >
            <p class="text-xs leading-relaxed text-surface-600 dark:text-surface-300">
                Apakah Anda yakin ingin memperbarui hak akses untuk role
                <strong class="font-semibold text-surface-900 dark:text-surface-100">{{ currentRoleLabel }}</strong>?
            </p>
            <p class="mt-2 text-[11px] text-surface-500 dark:text-surface-400">
                Perubahan ini akan langsung berlaku untuk seluruh pengguna dengan role tersebut.
            </p>

            <template #footer>
                <Button variant="secondary" size="sm" @click="confirmSaveModal = false">
                    Batal
                </Button>
                <Button
                    variant="primary"
                    size="sm"
                    :loading="saving"
                    @click="saveChanges"
                >
                    Ya, Simpan Perubahan
                </Button>
            </template>
        </Modal>
    </div>
</template>
