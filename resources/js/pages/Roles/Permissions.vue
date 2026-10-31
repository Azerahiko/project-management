<script setup lang="ts">
import { ref, computed, watch, reactive } from 'vue';
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

// State
const selectedRole = ref<string | null>(null);
const rolePermissions = ref<Record<string, boolean>>({});
const originalPermissions = ref<Record<string, boolean>>({});
const hasUnsavedChanges = ref(false);
const saving = ref(false);
const confirmSaveModal = ref(false);

// Type helper for accessing rolePermissions
const getPermission = (name: string): boolean =>
    rolePermissions.value[name] ?? false;
const setPermission = (name: string, value: boolean): void => {
    rolePermissions.value = { ...rolePermissions.value, [name]: value };
};

// Initialize from props
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

// Computed
const currentRole = computed(() => {
    if (!selectedRole.value) return null;
    return page.props.roles.find((r) => r.name === selectedRole.value) || null;
});

const currentRolePermissions = computed(() => {
    if (!selectedRole.value) return [];
    return page.props.role_permissions[selectedRole.value] || [];
});

const groupedPermissionsWithState = computed(() => {
    if (!selectedRole.value) return {};

    const result: Record<
        string,
        {
            permissions: { name: string; label: string; checked: boolean }[];
            activeCount: number;
        }
    > = {};

    for (const [category, permissions] of Object.entries(
        page.props.grouped_permissions,
    )) {
        const categoryPermissions = Object.entries(permissions).map(
            ([name, label]) => ({
                name,
                label,
                checked: getPermission(name),
            }),
        );

        const activeCount = categoryPermissions.filter((p) => p.checked).length;

        result[category] = {
            permissions: categoryPermissions,
            activeCount,
        };
    }

    return result;
});

const allPermissionsSelected = computed(() => {
    if (!selectedRole.value) return false;
    const allPerms = Object.keys(page.props.grouped_permissions).flatMap(
        (cat) => Object.keys(page.props.grouped_permissions[cat]),
    );
    return allPerms.every((p) => getPermission(p));
});

const somePermissionsSelected = computed(() => {
    if (!selectedRole.value) return false;
    const allPerms = Object.keys(page.props.grouped_permissions).flatMap(
        (cat) => Object.keys(page.props.grouped_permissions[cat]),
    );
    return allPerms.some((p) => getPermission(p));
});

const categoryAllSelected = (category: string) => {
    const perms = Object.keys(page.props.grouped_permissions[category] || {});
    return perms.every((p) => getPermission(p));
};

const categorySomeSelected = (category: string) => {
    const perms = Object.keys(page.props.grouped_permissions[category] || {});
    return perms.some((p) => getPermission(p));
};

// Watch for changes
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
    if (hasUnsavedChanges.value) {
        // Could show a confirmation modal here, but for simplicity we just switch
        // The user can use Reset button to discard changes
    }
    selectedRole.value = roleName;
}

function togglePermission(permissionName: string): void {
    rolePermissions.value = {
        ...rolePermissions.value,
        [permissionName]: !rolePermissions.value[permissionName],
    };
}

function toggleCategory(category: string): void {
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

        // Find the role ID from the roles list
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
                    // Update original permissions to match current
                    originalPermissions.value = { ...rolePermissions.value };
                    hasUnsavedChanges.value = false;
                    success('Berhasil', 'Permission berhasil diperbarui.');
                },
                onError: (errors) => {
                    error(
                        'Gagal',
                        errors.permissions?.[0] ||
                            'Gagal memperbarui permission.',
                    );
                },
            },
        );
    } catch {
        // Error handled by onError callback
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

function isAdministratorRole(roleName: string): boolean {
    return roleName === 'administrator';
}
</script>

<template>
    <div class="space-y-6">
        <!-- Page Header -->
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <div
                    class="flex items-center gap-2 text-xs font-medium text-surface-500"
                >
                    <span
                        class="h-1.5 w-1.5 rounded-full bg-[#0F5132] dark:bg-[#b7d9c1]"
                    />
                    <span>Administrasi Sistem</span>
                    <span class="text-surface-300 dark:text-surface-700"
                        >·</span
                    >
                    <span>Roles & Permissions</span>
                </div>
                <h1
                    class="mt-1 font-serif text-2xl font-normal tracking-tight text-surface-950 sm:text-3xl dark:text-surface-50"
                >
                    Role & Permission Management
                </h1>
                <p
                    class="mt-1 max-w-xl text-xs text-surface-600 sm:text-sm dark:text-surface-400"
                >
                    Kelola hak akses untuk setiap role. Perubahan hanya disimpan
                    saat tombol <strong>Save Changes</strong> ditekan.
                </p>
            </div>

            <div class="flex items-center gap-2 sm:ml-auto">
                <Button
                    v-if="hasUnsavedChanges"
                    variant="ghost"
                    size="sm"
                    @click="resetChanges"
                >
                    <component :is="getIcon('RotateCcw')" class="h-4 w-4" />
                    Reset Changes
                </Button>
                <Button
                    v-if="hasUnsavedChanges && selectedRole"
                    variant="primary"
                    size="sm"
                    :loading="saving"
                    @click="openConfirmSave"
                >
                    <component :is="getIcon('Save')" class="h-4 w-4" />
                    Save Changes
                </Button>
            </div>
        </div>

        <!-- Error/Success Messages from Session -->
        <div
            v-if="page.props.flash?.error"
            class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-xs font-medium text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/20 dark:text-rose-300"
        >
            {{ page.props.flash.error }}
        </div>
        <div
            v-if="page.props.flash?.success"
            class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-xs font-medium text-emerald-700 dark:border-emerald-900/40 dark:bg-emerald-950/20 dark:text-emerald-300"
        >
            {{ page.props.flash.success }}
        </div>

        <div class="grid gap-6 lg:grid-cols-[280px_minmax(0,1fr)]">
            <!-- Role Selection Sidebar -->
            <aside class="space-y-4">
                <div
                    class="rounded-3xl border border-surface-200/80 bg-white p-4 shadow-xs dark:border-surface-800 dark:bg-surface-900"
                >
                    <h2
                        class="text-xs font-semibold tracking-wider text-surface-400 uppercase dark:text-surface-500"
                    >
                        Daftar Role
                    </h2>
                    <p
                        class="mt-1 text-[11px] text-surface-500 dark:text-surface-400"
                    >
                        Pilih role untuk mengelola permission
                    </p>

                    <div class="mt-4 max-h-[600px] space-y-2 overflow-y-auto">
                        <button
                            v-for="role in page.props.roles"
                            :key="role.id"
                            type="button"
                            class="flex w-full items-center gap-3 rounded-xl px-3 py-3 text-left transition-all duration-200"
                            :class="
                                selectedRole === role.name
                                    ? 'bg-primary-50 text-primary-700 ring-2 ring-primary-500/50 dark:bg-primary-900/20 dark:text-primary-200 dark:ring-primary-500/30'
                                    : 'text-surface-600 hover:bg-surface-50 hover:text-surface-900 dark:text-surface-400 dark:hover:bg-surface-800/50 dark:hover:text-surface-100'
                            "
                            @click="selectRole(role.name)"
                        >
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium">
                                    {{ role.label }}
                                </p>
                                <p
                                    class="truncate text-[11px] text-surface-500"
                                >
                                    {{ role.name }}
                                </p>
                            </div>
                            <div
                                class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-[11px] font-medium"
                                :class="
                                    selectedRole === role.name
                                        ? 'bg-primary-100 text-primary-700 dark:bg-primary-800/50 dark:text-primary-200'
                                        : 'bg-surface-100 text-surface-500 dark:bg-surface-800 dark:text-surface-400'
                                "
                            >
                                {{ role.permissions_count }}
                            </div>
                            <span
                                v-if="isAdministratorRole(role.name)"
                                class="ml-auto inline-flex items-center rounded-full bg-accent-100 px-2 py-0.5 text-[10px] font-semibold text-accent-700 dark:bg-accent-900/30 dark:text-accent-300"
                            >
                                System
                            </span>
                        </button>
                    </div>
                </div>
            </aside>

            <!-- Permission Management Panel -->
            <section class="min-w-0">
                <div
                    v-if="!selectedRole"
                    class="flex flex-col items-center justify-center rounded-3xl border border-dashed border-surface-300 bg-white p-12 text-center dark:border-surface-700 dark:bg-surface-900"
                >
                    <div
                        class="flex h-14 w-14 items-center justify-center rounded-2xl bg-surface-100 text-surface-500 dark:bg-surface-800 dark:text-surface-400"
                    >
                        <component :is="getIcon('Shield')" class="h-7 w-7" />
                    </div>
                    <h3
                        class="mt-4 font-serif text-lg font-normal text-surface-950 dark:text-surface-50"
                    >
                        Pilih Role
                    </h3>
                    <p class="mt-1 max-w-sm text-xs text-surface-500">
                        Klik salah satu role di sebelah kiri untuk mulai
                        mengelola permission-nya.
                    </p>
                </div>

                <div v-else class="space-y-6">
                    <!-- Role Header -->
                    <div
                        class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div>
                            <div
                                class="flex items-center gap-2 text-xs font-medium text-surface-500"
                            >
                                <span
                                    class="h-1.5 w-1.5 rounded-full bg-[#0F5132] dark:bg-[#b7d9c1]"
                                />
                                <span>{{ currentRole?.label }}</span>
                                <span
                                    v-if="isAdministratorRole(selectedRole)"
                                    class="inline-flex items-center rounded-full bg-accent-100 px-2 py-0.5 text-[10px] font-semibold text-accent-700 dark:bg-accent-900/30 dark:text-accent-300"
                                >
                                    System Role (Tidak dapat diubah)
                                </span>
                            </div>
                            <h2
                                class="mt-1 font-serif text-xl font-normal text-surface-950 dark:text-surface-50"
                            >
                                Permission untuk {{ currentRole?.label }}
                            </h2>
                            <p
                                class="mt-1 text-xs text-surface-600 dark:text-surface-400"
                            >
                                {{ currentRolePermissions.length }} dari
                                {{ page.props.permissions.length }} permission
                                aktif
                            </p>
                        </div>

                        <div class="flex items-center gap-2 sm:ml-auto">
                            <Button
                                variant="ghost"
                                size="sm"
                                @click="resetChanges"
                                :disabled="!hasUnsavedChanges"
                            >
                                <component
                                    :is="getIcon('RotateCcw')"
                                    class="h-4 w-4"
                                />
                                Reset
                            </Button>
                            <Button
                                variant="primary"
                                size="sm"
                                :loading="saving"
                                :disabled="
                                    !hasUnsavedChanges ||
                                    isAdministratorRole(selectedRole)
                                "
                                @click="openConfirmSave"
                            >
                                <component
                                    :is="getIcon('Save')"
                                    class="h-4 w-4"
                                />
                                Save Changes
                            </Button>
                        </div>
                    </div>

                    <!-- Permission Groups -->
                    <div class="space-y-6">
                        <div
                            v-for="(
                                group, category
                            ) in groupedPermissionsWithState"
                            :key="category"
                            class="rounded-3xl border border-surface-200/80 bg-white shadow-xs dark:border-surface-800 dark:bg-surface-900"
                        >
                            <!-- Category Header -->
                            <div
                                class="flex flex-col gap-3 border-b border-surface-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-surface-800"
                            >
                                <div>
                                    <h3
                                        class="font-semibold text-surface-950 dark:text-surface-50"
                                    >
                                        {{ category }}
                                    </h3>
                                    <p class="mt-0.5 text-xs text-surface-500">
                                        {{ group.activeCount }} dari
                                        {{ group.permissions.length }}
                                        permission aktif
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <label
                                        class="relative inline-flex cursor-pointer items-center"
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="
                                                categoryAllSelected(category)
                                            "
                                            :indeterminate="
                                                categorySomeSelected(
                                                    category,
                                                ) &&
                                                !categoryAllSelected(category)
                                            "
                                            @change="toggleCategory(category)"
                                            :disabled="
                                                isAdministratorRole(
                                                    selectedRole,
                                                ) || saving
                                            "
                                            class="peer sr-only"
                                        />
                                        <div
                                            class="h-4 w-4 rounded border-2 border-surface-300 transition-colors peer-checked:border-primary-600 peer-checked:bg-primary-600 peer-indeterminate:border-primary-600 peer-indeterminate:bg-primary-600 peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-primary-600 peer-disabled:cursor-not-allowed peer-disabled:opacity-50 dark:border-surface-600"
                                        >
                                            <component
                                                v-if="
                                                    categoryAllSelected(
                                                        category,
                                                    )
                                                "
                                                :is="getIcon('Check')"
                                                class="h-3 w-3 text-white"
                                            />
                                            <component
                                                v-else-if="
                                                    categorySomeSelected(
                                                        category,
                                                    )
                                                "
                                                :is="getIcon('Minus')"
                                                class="h-1 w-3 text-white"
                                            />
                                        </div>
                                        <span
                                            class="ml-2 text-xs font-medium text-surface-700 dark:text-surface-300"
                                        >
                                            {{
                                                categoryAllSelected(category)
                                                    ? 'Deselect All'
                                                    : 'Select All'
                                            }}
                                        </span>
                                    </label>
                                </div>
                            </div>

                            <!-- Permissions List -->
                            <div
                                class="divide-y divide-surface-100 dark:divide-surface-800"
                            >
                                <label
                                    v-for="perm in group.permissions"
                                    :key="perm.name"
                                    class="flex items-center justify-between px-5 py-3.5 transition-colors hover:bg-surface-50/50 dark:hover:bg-surface-800/30"
                                >
                                    <div
                                        class="flex min-w-0 flex-1 items-center gap-3"
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="perm.checked"
                                            @change="
                                                togglePermission(perm.name)
                                            "
                                            :disabled="
                                                isAdministratorRole(
                                                    selectedRole,
                                                ) || saving
                                            "
                                            class="h-4 w-4 rounded border-surface-300 text-primary-600 focus:ring-2 focus:ring-primary-500/20 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 dark:border-surface-600 dark:bg-surface-800 dark:focus:ring-primary-500/20"
                                        />
                                        <div class="min-w-0">
                                            <p
                                                class="truncate text-sm font-medium text-surface-900 dark:text-surface-100"
                                            >
                                                {{
                                                    formatPermissionName(
                                                        perm.name,
                                                    )
                                                }}
                                            </p>
                                            <p
                                                class="truncate font-mono text-[11px] text-surface-500 dark:text-surface-400"
                                            >
                                                {{ perm.name }}
                                            </p>
                                        </div>
                                    </div>
                                    <span
                                        v-if="perm.checked"
                                        class="ml-3 inline-flex items-center rounded-full bg-primary-100 px-2 py-0.5 text-[10px] font-semibold text-primary-700 dark:bg-primary-900/30 dark:text-primary-300"
                                    >
                                        Active
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Empty state for administrator role -->
                    <div
                        v-if="isAdministratorRole(selectedRole)"
                        class="rounded-3xl border border-surface-200/80 bg-white p-8 text-center dark:border-surface-800 dark:bg-surface-900"
                    >
                        <div
                            class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-accent-100 text-accent-600 dark:bg-accent-900/30 dark:text-accent-400"
                        >
                            <component :is="getIcon('Lock')" class="h-7 w-7" />
                        </div>
                        <h3
                            class="mt-4 font-serif text-lg font-normal text-surface-950 dark:text-surface-50"
                        >
                            Role Administrator Tidak Dapat Diubah
                        </h3>
                        <p
                            class="mx-auto mt-2 max-w-md text-sm text-surface-600 dark:text-surface-400"
                        >
                            Role
                            <code
                                class="rounded bg-surface-100 px-1.5 py-0.5 dark:bg-surface-800"
                                >administrator</code
                            >
                            memiliki akses penuh ke semua permission dan tidak
                            dapat dimodifikasi melalui halaman ini untuk menjaga
                            keamanan sistem.
                        </p>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <!-- Confirm Save Modal -->
    <Modal
        :open="confirmSaveModal"
        title="Konfirmasi Simpan"
        size="sm"
        @close="confirmSaveModal = false"
    >
        <p class="text-sm text-surface-600 dark:text-surface-300">
            Apakah Anda yakin ingin menyimpan perubahan permission untuk role
            <strong>{{ currentRole?.label }}</strong
            >?
        </p>
        <p class="mt-2 text-xs text-surface-500 dark:text-surface-400">
            Perubahan akan langsung diterapkan dan cache permission akan
            dibersihkan.
        </p>

        <template #footer>
            <Button variant="secondary" @click="confirmSaveModal = false">
                Batal
            </Button>
            <Button variant="primary" :loading="saving" @click="saveChanges">
                Ya, Simpan Perubahan
            </Button>
        </template>
    </Modal>
</template>
