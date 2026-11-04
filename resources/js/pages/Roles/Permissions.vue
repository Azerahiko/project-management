<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import roles from '@/routes/roles';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import Modal from '@/components/ui/Modal.vue';
import { getIcon } from '@/components/sidebar/icons';
import { useToast } from '@/composables/useToast';
import type {
    PageProps as InertiaPageProps,
    PendingVisit,
} from '@inertiajs/core';

defineOptions({
    layout: AppLayout,
});

interface Role {
    id: number;
    name: string;
    label: string;
    permissions_count: number;
    users_count: number;
    is_system: boolean;
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

interface Summary {
    roles_count: number;
    permissions_count: number;
    assignments_count: number;
    members_count: number;
}

interface PageProps extends InertiaPageProps {
    roles: Role[];
    permissions: Permission[];
    grouped_permissions: GroupedPermission;
    role_permissions: RolePermissions;
    summary: Summary;
}

type ViewMode = 'manage' | 'matrix';

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

const viewMode = ref<ViewMode>('manage');
const firstEditableRole =
    page.props.roles.find((role) => role.name !== 'administrator')?.name ??
    page.props.roles[0]?.name ??
    null;

const selectedRole = ref<string | null>(firstEditableRole);
const rolePermissions = ref<Record<string, boolean>>({});
const originalPermissionNames = ref<string[]>([]);
const saving = ref(false);
const confirmSaveModal = ref(false);
const roleSearch = ref('');
const permissionSearch = ref('');
const permissionStateFilter = ref<'all' | 'active' | 'inactive'>('all');
const matrixSearch = ref('');
const matrixCategoryFilter = ref<string>('all');

const pendingRole = ref<string | null>(null);
const switchRoleModalOpen = ref(false);

const pendingNavigation = ref<PendingVisit | null>(null);
const leaveModalOpen = ref(false);
let allowNavigation = false;
let removeBeforeListener: (() => void) | null = null;

const summary = computed<Summary>(() => ({
    roles_count: page.props.summary?.roles_count ?? page.props.roles.length,
    permissions_count:
        page.props.summary?.permissions_count ?? page.props.permissions.length,
    assignments_count:
        page.props.summary?.assignments_count ??
        page.props.roles.reduce(
            (total, role) => total + role.permissions_count,
            0,
        ),
    members_count:
        page.props.summary?.members_count ??
        page.props.roles.reduce((total, role) => total + role.users_count, 0),
}));

const totalPermissionCount = computed(() => page.props.permissions.length);

const enabledPermissionNames = computed(() =>
    Object.entries(rolePermissions.value)
        .filter(([, checked]) => checked)
        .map(([name]) => name)
        .sort(),
);

const hasUnsavedChanges = computed(
    () =>
        JSON.stringify(enabledPermissionNames.value) !==
        JSON.stringify(originalPermissionNames.value),
);

const currentRole = computed(() => {
    if (!selectedRole.value) return null;

    return (
        page.props.roles.find((role) => role.name === selectedRole.value) ??
        null
    );
});

const isAdministratorSelected = computed(
    () => selectedRole.value === 'administrator',
);

const activePermissionCount = computed(
    () => enabledPermissionNames.value.length,
);

const permissionProgress = computed(() =>
    totalPermissionCount.value > 0
        ? Math.round(
              (activePermissionCount.value / totalPermissionCount.value) * 100,
          )
        : 0,
);

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

        if (query) {
            visible = visible.filter(
                (p) =>
                    p.label.toLowerCase().includes(query) ||
                    p.name.toLowerCase().includes(query),
            );
        }

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

const permissionGroupEntries = computed(() =>
    Object.entries(groupedPermissionsWithState.value),
);

const hasVisiblePermissions = computed(
    () => permissionGroupEntries.value.length > 0,
);

const matrixCategories = computed(() =>
    Object.keys(page.props.grouped_permissions),
);

const matrixGroups = computed(() => {
    const query = matrixSearch.value.trim().toLowerCase();

    return Object.entries(page.props.grouped_permissions)
        .filter(
            ([category]) =>
                matrixCategoryFilter.value === 'all' ||
                matrixCategoryFilter.value === category,
        )
        .map(([category, permissions]) => ({
            category,
            permissions: Object.entries(permissions)
                .map(([name, label]) => ({
                    name,
                    label: permissionLabel(name, label),
                }))
                .filter(
                    (permission) =>
                        !query ||
                        permission.label.toLowerCase().includes(query) ||
                        permission.name.toLowerCase().includes(query),
                ),
        }))
        .filter((group) => group.permissions.length > 0);
});

const matrixCellCount = computed(() =>
    matrixGroups.value.reduce(
        (total, group) => total + group.permissions.length,
        0,
    ),
);

function getPermission(name: string): boolean {
    return rolePermissions.value[name] ?? false;
}

function roleLabel(role: Role | null | undefined): string {
    if (!role) return '';

    return roleLabels[role.name] ?? role.label ?? role.name;
}

function roleDescription(role: Role | null | undefined): string {
    if (!role) return '';

    return roleDescriptions[role.name] ?? 'Peran pengguna ruang kerja';
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

function formatPermissionName(name: string): string {
    return name
        .split('.')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
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

function roleHasPermission(roleName: string, permissionName: string): boolean {
    return (
        page.props.role_permissions[roleName]?.includes(permissionName) ?? false
    );
}

function initializePermissions(roleName: string): void {
    const permissions = page.props.role_permissions[roleName] ?? [];
    const state: Record<string, boolean> = {};

    for (const permission of permissions) {
        state[permission] = true;
    }

    rolePermissions.value = state;
    originalPermissionNames.value = [...permissions].sort();
}

function applyRoleSelection(roleName: string): void {
    selectedRole.value = roleName;
    permissionSearch.value = '';
    permissionStateFilter.value = 'all';
    initializePermissions(roleName);
}

function selectRole(roleName: string): void {
    if (roleName === selectedRole.value) {
        return;
    }

    if (hasUnsavedChanges.value) {
        pendingRole.value = roleName;
        switchRoleModalOpen.value = true;
        return;
    }

    applyRoleSelection(roleName);
}

function confirmRoleSwitch(): void {
    const roleName = pendingRole.value;
    switchRoleModalOpen.value = false;
    pendingRole.value = null;

    if (roleName) {
        applyRoleSelection(roleName);
    }
}

function togglePermission(permissionName: string): void {
    if (isAdministratorSelected.value || saving.value) return;

    rolePermissions.value = {
        ...rolePermissions.value,
        [permissionName]: !rolePermissions.value[permissionName],
    };
}

function categoryAllSelected(category: string): boolean {
    const permissions = Object.keys(
        page.props.grouped_permissions[category] ?? {},
    );

    return permissions.every((permission) => getPermission(permission));
}

function toggleCategory(category: string): void {
    if (isAdministratorSelected.value || saving.value) return;

    const permissions = Object.keys(
        page.props.grouped_permissions[category] ?? {},
    );
    const allSelected = categoryAllSelected(category);
    const newPermissions = { ...rolePermissions.value };

    for (const permission of permissions) {
        newPermissions[permission] = !allSelected;
    }

    rolePermissions.value = newPermissions;
}

function resetChanges(): void {
    if (!selectedRole.value) return;

    rolePermissions.value = {};
    for (const permission of originalPermissionNames.value) {
        rolePermissions.value[permission] = true;
    }
}

function openConfirmSave(): void {
    confirmSaveModal.value = true;
}

function saveChanges(): void {
    if (!selectedRole.value || !currentRole.value) return;

    saving.value = true;
    confirmSaveModal.value = false;

    router.put(
        roles.permissions.update.url({ role: currentRole.value.id }),
        { permissions: enabledPermissionNames.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                originalPermissionNames.value = [
                    ...enabledPermissionNames.value,
                ];
                success(
                    'Hak Akses Diperbarui',
                    `Permission role ${roleLabel(currentRole.value)} berhasil disimpan.`,
                );
            },
            onError: (errors) => {
                error(
                    'Gagal Menyimpan',
                    errors.permissions?.[0] ||
                        'Perubahan hak akses tidak dapat disimpan.',
                );
            },
            onFinish: () => {
                saving.value = false;
            },
        },
    );
}

function switchView(mode: ViewMode): void {
    viewMode.value = mode;

    if (mode === 'matrix') {
        matrixSearch.value = '';
        matrixCategoryFilter.value = 'all';
    }
}

function openRoleFromMatrix(roleName: string): void {
    selectRole(roleName);
    if (!hasUnsavedChanges.value || roleName === selectedRole.value) {
        viewMode.value = 'manage';
    }
}

function exportMatrix(): void {
    const header = [
        'Permission',
        'Kode',
        ...page.props.roles.map((role) => roleLabel(role)),
    ];
    const rows: string[][] = [header];

    for (const [category, permissions] of Object.entries(
        page.props.grouped_permissions,
    )) {
        rows.push([
            `— ${categoryLabel(category)} —`,
            '',
            ...page.props.roles.map(() => ''),
        ]);

        for (const [name, label] of Object.entries(permissions)) {
            rows.push([
                permissionLabel(name, label),
                name,
                ...page.props.roles.map((role) =>
                    roleHasPermission(role.name, name) ? 'Ya' : 'Tidak',
                ),
            ]);
        }
    }

    const csv = rows
        .map((row) =>
            row
                .map((cell) => `"${String(cell).replaceAll('"', '""')}"`)
                .join(','),
        )
        .join('\n');

    const blob = new Blob([`\uFEFF${csv}`], {
        type: 'text/csv;charset=utf-8;',
    });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');

    link.href = url;
    link.download = `matriks-permission-${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);

    success('Matriks Diekspor', 'File CSV berhasil diunduh.');
}

function handleBeforeUnload(event: BeforeUnloadEvent): void {
    if (!hasUnsavedChanges.value) return;

    event.preventDefault();
    event.returnValue = '';
}

function confirmLeaveNavigation(): void {
    const visit = pendingNavigation.value;

    allowNavigation = true;
    leaveModalOpen.value = false;

    if (!visit) {
        allowNavigation = false;
        return;
    }

    router.visit(visit.url, {
        method: visit.method,
        data: visit.data,
        headers: visit.headers,
        preserveScroll: visit.preserveScroll,
        preserveState: visit.preserveState,
        only: visit.only,
        except: visit.except,
        reset: visit.reset,
        onFinish: () => {
            allowNavigation = false;
            pendingNavigation.value = null;
        },
    });
}

if (selectedRole.value) {
    initializePermissions(selectedRole.value);
}

onMounted(() => {
    window.addEventListener('beforeunload', handleBeforeUnload);
    removeBeforeListener = router.on('before', (event) => {
        if (!hasUnsavedChanges.value || saving.value || allowNavigation) {
            return;
        }

        event.preventDefault();
        pendingNavigation.value = event.detail.visit;
        leaveModalOpen.value = true;

        return false;
    });
});

onBeforeUnmount(() => {
    window.removeEventListener('beforeunload', handleBeforeUnload);
    removeBeforeListener?.();
});
</script>

<template>
    <div class="space-y-6 pb-20">
        <!-- Header -->
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
                    Tinjau dan atur permission setiap peran. Jumlah anggota
                    dihitung langsung dari data akun di ruang kerja.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <span
                    class="inline-flex items-center gap-2 rounded-xl border border-surface-200/80 bg-white px-3 py-1.5 text-xs font-semibold text-surface-700 shadow-2xs dark:border-surface-800 dark:bg-surface-900 dark:text-surface-200"
                >
                    <component
                        :is="getIcon('UsersRound')"
                        class="h-3.5 w-3.5 text-surface-400"
                    />
                    {{ summary.roles_count }} Role
                </span>
                <span
                    class="inline-flex items-center gap-2 rounded-xl border border-surface-200/80 bg-white px-3 py-1.5 text-xs font-semibold text-surface-700 shadow-2xs dark:border-surface-800 dark:bg-surface-900 dark:text-surface-200"
                >
                    <component
                        :is="getIcon('Shield')"
                        class="h-3.5 w-3.5 text-surface-400"
                    />
                    {{ summary.permissions_count }} Permission
                </span>
            </div>
        </header>

        <!-- Summary stats -->
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div
                class="rounded-2xl border border-surface-200/80 bg-white p-4 shadow-xs dark:border-surface-800 dark:bg-surface-900"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-[10px] font-bold tracking-wider text-surface-500 uppercase dark:text-surface-400"
                    >
                        Total Role
                    </span>
                    <span
                        class="grid h-8 w-8 place-items-center rounded-lg bg-primary-50 text-primary-700 dark:bg-primary-950/40 dark:text-primary-300"
                    >
                        <component
                            :is="getIcon('ShieldCheck')"
                            class="h-4 w-4"
                        />
                    </span>
                </div>
                <p
                    class="mt-2 font-serif text-3xl text-surface-950 dark:text-surface-50"
                >
                    {{ summary.roles_count }}
                </p>
                <p
                    class="mt-0.5 text-[11px] text-surface-500 dark:text-surface-400"
                >
                    {{
                        page.props.roles.filter((role) => role.is_system).length
                    }}
                    role sistem dikunci
                </p>
            </div>

            <div
                class="rounded-2xl border border-surface-200/80 bg-white p-4 shadow-xs dark:border-surface-800 dark:bg-surface-900"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-[10px] font-bold tracking-wider text-surface-500 uppercase dark:text-surface-400"
                    >
                        Jenis Permission
                    </span>
                    <span
                        class="grid h-8 w-8 place-items-center rounded-lg bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300"
                    >
                        <component :is="getIcon('KeyRound')" class="h-4 w-4" />
                    </span>
                </div>
                <p
                    class="mt-2 font-serif text-3xl text-surface-950 dark:text-surface-50"
                >
                    {{ summary.permissions_count }}
                </p>
                <p
                    class="mt-0.5 text-[11px] text-surface-500 dark:text-surface-400"
                >
                    Tersebar dalam
                    {{ matrixCategories.length }} kategori
                </p>
            </div>

            <div
                class="rounded-2xl border border-surface-200/80 bg-white p-4 shadow-xs dark:border-surface-800 dark:bg-surface-900"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-[10px] font-bold tracking-wider text-surface-500 uppercase dark:text-surface-400"
                    >
                        Total Penugasan
                    </span>
                    <span
                        class="grid h-8 w-8 place-items-center rounded-lg bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300"
                    >
                        <component
                            :is="getIcon('ListChecks')"
                            class="h-4 w-4"
                        />
                    </span>
                </div>
                <p
                    class="mt-2 font-serif text-3xl text-surface-950 dark:text-surface-50"
                >
                    {{ summary.assignments_count }}
                </p>
                <p
                    class="mt-0.5 text-[11px] text-surface-500 dark:text-surface-400"
                >
                    Pasangan role dan permission aktif
                </p>
            </div>

            <div
                class="rounded-2xl border border-surface-200/80 bg-white p-4 shadow-xs dark:border-surface-800 dark:bg-surface-900"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-[10px] font-bold tracking-wider text-surface-500 uppercase dark:text-surface-400"
                    >
                        Anggota (per role)
                    </span>
                    <span
                        class="grid h-8 w-8 place-items-center rounded-lg bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-300"
                    >
                        <component :is="getIcon('Users')" class="h-4 w-4" />
                    </span>
                </div>
                <p
                    class="mt-2 font-serif text-3xl text-surface-950 dark:text-surface-50"
                >
                    {{ summary.members_count }}
                </p>
                <p
                    class="mt-0.5 text-[11px] text-surface-500 dark:text-surface-400"
                >
                    Satu pengguna dapat memiliki lebih dari satu role
                </p>
            </div>
        </div>

        <!-- View mode switcher -->
        <div
            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        >
            <div
                class="inline-flex w-fit rounded-xl border border-surface-200 bg-white p-1 shadow-2xs dark:border-surface-800 dark:bg-surface-900"
                role="tablist"
                aria-label="Mode tampilan hak akses"
            >
                <button
                    type="button"
                    role="tab"
                    :aria-selected="viewMode === 'manage'"
                    class="inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-xs font-semibold transition"
                    :class="
                        viewMode === 'manage'
                            ? 'bg-[#0F5132] text-white shadow-xs dark:bg-primary-600'
                            : 'text-surface-600 hover:bg-surface-100 dark:text-surface-300 dark:hover:bg-surface-800'
                    "
                    @click="switchView('manage')"
                >
                    <component
                        :is="getIcon('SlidersHorizontal')"
                        class="h-3.5 w-3.5"
                    />
                    Kelola Role
                </button>
                <button
                    type="button"
                    role="tab"
                    :aria-selected="viewMode === 'matrix'"
                    class="inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-xs font-semibold transition"
                    :class="
                        viewMode === 'matrix'
                            ? 'bg-[#0F5132] text-white shadow-xs dark:bg-primary-600'
                            : 'text-surface-600 hover:bg-surface-100 dark:text-surface-300 dark:hover:bg-surface-800'
                    "
                    @click="switchView('matrix')"
                >
                    <component :is="getIcon('Grid3X3')" class="h-3.5 w-3.5" />
                    Matriks Izin
                </button>
            </div>

            <div class="flex items-center gap-2">
                <span
                    v-if="viewMode === 'manage' && hasUnsavedChanges"
                    class="inline-flex items-center gap-1.5 rounded-full border border-amber-200/80 bg-amber-50 px-2.5 py-1 text-[10px] font-bold text-amber-800 dark:border-amber-800/40 dark:bg-amber-950/30 dark:text-amber-300"
                >
                    <span
                        class="h-1.5 w-1.5 animate-pulse rounded-full bg-amber-500"
                    />
                    Perubahan belum disimpan
                </span>
                <Button
                    v-if="viewMode === 'matrix'"
                    variant="secondary"
                    size="sm"
                    class="text-xs"
                    @click="exportMatrix"
                >
                    <component :is="getIcon('Download')" class="h-3.5 w-3.5" />
                    Ekspor CSV
                </Button>
            </div>
        </div>

        <!-- MANAGE VIEW -->
        <div v-if="viewMode === 'manage'" class="space-y-4">
            <!-- Mobile role switcher -->
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
                        class="py-0.2 rounded-full px-1.5 text-[10px] font-bold"
                        :class="
                            selectedRole === role.name
                                ? 'bg-white/20 text-white'
                                : 'bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-400'
                        "
                    >
                        {{ role.permissions_count }}
                    </span>
                    <span
                        class="text-[10px] opacity-75"
                        :title="`${role.users_count} anggota`"
                    >
                        · {{ role.users_count }} anggota
                    </span>
                </button>
            </div>

            <div
                class="grid gap-4 lg:grid-cols-[310px_minmax(0,1fr)] lg:items-start"
            >
                <!-- Roles list -->
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
                            <span
                                class="text-xs font-medium text-surface-500 dark:text-surface-400"
                            >
                                {{ filteredRoles.length }} role
                            </span>
                        </div>

                        <div class="relative mt-3">
                            <span
                                class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-surface-400"
                            >
                                <component
                                    :is="getIcon('Search')"
                                    class="h-3.5 w-3.5"
                                />
                            </span>
                            <input
                                v-model="roleSearch"
                                type="search"
                                placeholder="Cari role..."
                                class="input py-2 pl-9 text-xs"
                            />
                        </div>

                        <div
                            class="mt-3.5 max-h-[520px] space-y-1.5 overflow-y-auto pr-0.5"
                        >
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
                                    <div class="flex items-center gap-1.5">
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
                                        <component
                                            v-if="role.is_system"
                                            :is="getIcon('Lock')"
                                            class="h-3 w-3 shrink-0 text-amber-500"
                                        />
                                    </div>
                                    <p
                                        class="mt-0.5 truncate text-[11px] text-surface-500 dark:text-surface-400"
                                    >
                                        {{ role.users_count }} anggota ·
                                        {{ role.permissions_count }} permission
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

                    <div
                        class="rounded-3xl border border-primary-200/70 bg-primary-50/50 p-4 text-xs text-primary-900 dark:border-primary-800/40 dark:bg-primary-950/20 dark:text-primary-200"
                    >
                        <div class="flex items-center gap-2 font-semibold">
                            <component
                                :is="getIcon('Info')"
                                class="h-4 w-4 text-primary-700 dark:text-primary-300"
                            />
                            <span>Dampak Perubahan</span>
                        </div>
                        <p
                            class="mt-1 text-[11px] leading-relaxed text-primary-800/90 dark:text-primary-300/80"
                        >
                            Perubahan permission langsung berlaku untuk seluruh
                            anggota yang memiliki role tersebut. Perubahan belum
                            disimpan akan dilindungi saat Anda berpindah
                            halaman.
                        </p>
                    </div>
                </aside>

                <!-- Permission panel -->
                <main class="min-w-0">
                    <div
                        v-if="!selectedRole"
                        class="rounded-3xl border border-surface-200/80 bg-white p-12 text-center shadow-xs dark:border-surface-800 dark:bg-surface-900"
                    >
                        <component
                            :is="getIcon('Shield')"
                            class="mx-auto h-12 w-12 text-surface-300"
                        />
                        <h3
                            class="mt-3 text-base font-semibold text-surface-800 dark:text-surface-200"
                        >
                            Pilih Role untuk Memulai
                        </h3>
                        <p class="mt-1 text-xs text-surface-500">
                            Pilih salah satu role di sebelah kiri untuk melihat
                            dan menyesuaikan permission.
                        </p>
                    </div>

                    <div
                        v-else
                        class="overflow-hidden rounded-3xl border border-surface-200/80 bg-white shadow-xs dark:border-surface-800 dark:bg-surface-900"
                    >
                        <!-- Role header -->
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
                                        {{
                                            roleInitials(roleLabel(currentRole))
                                        }}
                                    </span>

                                    <div class="min-w-0">
                                        <div
                                            class="flex flex-wrap items-center gap-2"
                                        >
                                            <h2
                                                class="text-xl font-semibold tracking-tight text-surface-950 dark:text-surface-50"
                                            >
                                                {{ roleLabel(currentRole) }}
                                            </h2>
                                            <span
                                                v-if="isAdministratorSelected"
                                                class="inline-flex items-center gap-1 rounded-full border border-amber-200/80 bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-800 dark:border-amber-800/40 dark:bg-amber-950/40 dark:text-amber-300"
                                            >
                                                <component
                                                    :is="getIcon('Lock')"
                                                    class="h-3 w-3"
                                                />
                                                Role Sistem
                                            </span>
                                            <span
                                                class="inline-flex items-center gap-1 rounded-full bg-surface-100 px-2 py-0.5 text-[10px] font-semibold text-surface-600 dark:bg-surface-800 dark:text-surface-300"
                                            >
                                                <component
                                                    :is="getIcon('Users')"
                                                    class="h-3 w-3"
                                                />
                                                {{
                                                    currentRole?.users_count ??
                                                    0
                                                }}
                                                anggota
                                            </span>
                                            <span
                                                v-if="hasUnsavedChanges"
                                                class="inline-flex items-center gap-1.5 rounded-full border border-primary-200/80 bg-primary-50 px-2 py-0.5 text-[10px] font-bold text-primary-800 dark:border-primary-800/40 dark:bg-primary-950/40 dark:text-primary-300"
                                            >
                                                <span
                                                    class="h-1.5 w-1.5 animate-pulse rounded-full bg-[#0F5132] dark:bg-primary-400"
                                                />
                                                Belum disimpan
                                            </span>
                                        </div>

                                        <p
                                            class="mt-1 text-xs text-surface-500 dark:text-surface-400"
                                        >
                                            {{ roleDescription(currentRole) }}
                                            <span
                                                class="mx-1 text-surface-300 dark:text-surface-600"
                                                >·</span
                                            >
                                            <code
                                                class="font-mono text-[11px] text-surface-400"
                                                >{{ selectedRole }}</code
                                            >
                                        </p>

                                        <div
                                            class="mt-3 flex items-center gap-3"
                                        >
                                            <div
                                                class="h-1.5 w-36 overflow-hidden rounded-full bg-surface-100 dark:bg-surface-800"
                                            >
                                                <div
                                                    class="h-full rounded-full bg-[#0F5132] transition-all duration-500 dark:bg-primary-400"
                                                    :style="{
                                                        width: `${permissionProgress}%`,
                                                    }"
                                                />
                                            </div>
                                            <span
                                                class="text-xs text-surface-500 dark:text-surface-400"
                                            >
                                                <strong
                                                    class="font-semibold text-surface-800 dark:text-surface-200"
                                                    >{{
                                                        activePermissionCount
                                                    }}</strong
                                                >
                                                dari
                                                {{ totalPermissionCount }}
                                                permission aktif ({{
                                                    permissionProgress
                                                }}%)
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    v-if="!isAdministratorSelected"
                                    class="hidden shrink-0 items-center gap-2 sm:flex"
                                >
                                    <span
                                        v-if="!hasUnsavedChanges"
                                        class="inline-flex items-center gap-1.5 rounded-xl border border-surface-200 bg-surface-50 px-3 py-1.5 text-xs font-semibold text-surface-500 dark:border-surface-800 dark:bg-surface-900 dark:text-surface-400"
                                    >
                                        <component
                                            :is="getIcon('Check')"
                                            class="h-3.5 w-3.5 text-emerald-600"
                                        />
                                        Tersimpan
                                    </span>
                                    <template v-else>
                                        <Button
                                            variant="secondary"
                                            size="sm"
                                            @click="resetChanges"
                                        >
                                            <component
                                                :is="getIcon('RotateCcw')"
                                                class="h-3.5 w-3.5"
                                            />
                                            Reset
                                        </Button>
                                        <Button
                                            variant="primary"
                                            size="sm"
                                            :loading="saving"
                                            @click="openConfirmSave"
                                        >
                                            <component
                                                :is="getIcon('Save')"
                                                class="h-3.5 w-3.5"
                                            />
                                            Simpan
                                        </Button>
                                    </template>
                                </div>
                            </div>
                        </div>

                        <!-- Toolbar -->
                        <div
                            class="flex flex-col gap-3 border-b border-surface-200/80 p-4 sm:flex-row sm:items-center sm:justify-between sm:px-6 dark:border-surface-800"
                        >
                            <div class="relative w-full sm:max-w-xs">
                                <span
                                    class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-surface-400"
                                >
                                    <component
                                        :is="getIcon('Search')"
                                        class="h-3.5 w-3.5"
                                    />
                                </span>
                                <input
                                    v-model="permissionSearch"
                                    type="search"
                                    placeholder="Cari permission..."
                                    class="input py-2 pl-9 text-xs"
                                />
                            </div>

                            <div class="flex items-center gap-1.5">
                                <button
                                    v-for="filter in [
                                        { value: 'all', label: 'Semua' },
                                        { value: 'active', label: 'Aktif' },
                                        {
                                            value: 'inactive',
                                            label: 'Nonaktif',
                                        },
                                    ]"
                                    :key="filter.value"
                                    type="button"
                                    class="rounded-xl px-2.5 py-1 text-xs font-semibold transition"
                                    :class="
                                        permissionStateFilter === filter.value
                                            ? 'bg-[#0F5132] text-white dark:bg-primary-600'
                                            : 'bg-surface-100 text-surface-600 hover:bg-surface-200 dark:bg-surface-800 dark:text-surface-300'
                                    "
                                    @click="
                                        permissionStateFilter = filter.value as
                                            'all' | 'active' | 'inactive'
                                    "
                                >
                                    {{ filter.label }}
                                </button>
                            </div>
                        </div>

                        <div
                            v-if="isAdministratorSelected"
                            class="m-4 flex items-start gap-3 rounded-2xl border border-amber-200/80 bg-amber-50/60 p-4 sm:m-6 dark:border-amber-800/40 dark:bg-amber-950/20"
                        >
                            <span
                                class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-300"
                            >
                                <component
                                    :is="getIcon('Lock')"
                                    class="h-4 w-4"
                                />
                            </span>
                            <div>
                                <p
                                    class="text-xs font-bold text-amber-900 dark:text-amber-200"
                                >
                                    Role Administrator Dikunci
                                </p>
                                <p
                                    class="mt-0.5 text-xs leading-relaxed text-amber-800/80 dark:text-amber-300/80"
                                >
                                    Hak akses Administrator dipertahankan penuh
                                    pada level kode agar sistem selalu dapat
                                    dikelola dan tidak kehilangan akses.
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="hasVisiblePermissions"
                            class="grid grid-cols-1 gap-4 p-4 sm:p-6 xl:grid-cols-2"
                        >
                            <div
                                v-for="[
                                    category,
                                    group,
                                ] in permissionGroupEntries"
                                :key="category"
                                class="flex flex-col overflow-hidden rounded-2xl border border-surface-200/80 bg-white transition hover:border-surface-300 dark:border-surface-800 dark:bg-surface-900 dark:hover:border-surface-700"
                            >
                                <div
                                    class="flex items-center justify-between gap-3 border-b border-surface-100 bg-surface-50/60 px-4 py-3 dark:border-surface-800/80 dark:bg-surface-950/30"
                                >
                                    <div class="flex items-center gap-2.5">
                                        <span
                                            class="grid h-7 w-7 place-items-center rounded-lg bg-white text-surface-600 shadow-2xs dark:bg-surface-800 dark:text-surface-300"
                                        >
                                            <component
                                                :is="
                                                    getIcon(
                                                        categoryIcon(category),
                                                    )
                                                "
                                                class="h-3.5 w-3.5 text-primary-700 dark:text-primary-400"
                                            />
                                        </span>
                                        <div>
                                            <h3
                                                class="text-xs font-semibold text-surface-900 dark:text-surface-100"
                                            >
                                                {{ categoryLabel(category) }}
                                            </h3>
                                            <p
                                                class="text-[10px] text-surface-500 dark:text-surface-400"
                                            >
                                                {{ group.activeCount }} dari
                                                {{ group.totalCount }} aktif
                                            </p>
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        :disabled="
                                            isAdministratorSelected || saving
                                        "
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

                                <div
                                    class="divide-y divide-surface-100 dark:divide-surface-800/60"
                                >
                                    <label
                                        v-for="permission in group.permissions"
                                        :key="permission.name"
                                        class="flex items-center gap-3 px-4 py-2.5 transition-colors"
                                        :class="[
                                            isAdministratorSelected
                                                ? 'cursor-not-allowed opacity-80'
                                                : 'cursor-pointer hover:bg-surface-50/70 dark:hover:bg-surface-800/30',
                                            permission.checked
                                                ? 'bg-primary-50/20 dark:bg-primary-950/10'
                                                : '',
                                        ]"
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="permission.checked"
                                            :disabled="
                                                isAdministratorSelected ||
                                                saving
                                            "
                                            class="h-4 w-4 rounded border-surface-300 text-[#0F5132] focus:ring-2 focus:ring-[#0F5132]/20 disabled:cursor-not-allowed dark:border-surface-600 dark:bg-surface-800"
                                            @change="
                                                togglePermission(
                                                    permission.name,
                                                )
                                            "
                                        />

                                        <div class="min-w-0 flex-1">
                                            <p
                                                class="truncate text-xs font-medium"
                                                :class="
                                                    permission.checked
                                                        ? 'font-semibold text-surface-950 dark:text-surface-50'
                                                        : 'text-surface-600 dark:text-surface-400'
                                                "
                                            >
                                                {{ permission.label }}
                                            </p>
                                            <p
                                                class="font-mono text-[10px] text-surface-400 dark:text-surface-500"
                                            >
                                                {{ permission.name }}
                                            </p>
                                        </div>

                                        <span
                                            v-if="permission.checked"
                                            class="h-2 w-2 rounded-full bg-[#0F5132] dark:bg-primary-400"
                                        />
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div v-else class="p-12 text-center">
                            <component
                                :is="getIcon('Search')"
                                class="mx-auto h-8 w-8 text-surface-300 dark:text-surface-600"
                            />
                            <h4
                                class="mt-2 text-sm font-semibold text-surface-800 dark:text-surface-200"
                            >
                                Tidak Ada Permission yang Sesuai
                            </h4>
                            <p class="mt-1 text-xs text-surface-500">
                                Coba bersihkan kolom pencarian atau ubah filter
                                status aktif/nonaktif.
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
        </div>

        <!-- MATRIX VIEW -->
        <section v-else class="space-y-4">
            <div
                class="rounded-3xl border border-surface-200/80 bg-white p-4 shadow-xs sm:p-5 dark:border-surface-800 dark:bg-surface-900"
            >
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h2
                            class="text-sm font-semibold text-surface-950 dark:text-surface-50"
                        >
                            Matriks Permission per Role
                        </h2>
                        <p
                            class="mt-0.5 text-[11px] text-surface-500 dark:text-surface-400"
                        >
                            {{ matrixCellCount }} permission ditampilkan pada
                            {{ page.props.roles.length }} role. Klik nama role
                            untuk mengubah izinnya.
                        </p>
                    </div>

                    <div
                        class="flex flex-col gap-2 sm:flex-row sm:items-center"
                    >
                        <div class="relative">
                            <span
                                class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-surface-400"
                            >
                                <component
                                    :is="getIcon('Search')"
                                    class="h-3.5 w-3.5"
                                />
                            </span>
                            <input
                                v-model="matrixSearch"
                                type="search"
                                placeholder="Cari permission..."
                                class="input py-2 pl-9 text-xs sm:w-56"
                            />
                        </div>

                        <select
                            v-model="matrixCategoryFilter"
                            class="input py-2 text-xs sm:w-48"
                            aria-label="Filter kategori permission"
                        >
                            <option value="all">Semua kategori</option>
                            <option
                                v-for="category in matrixCategories"
                                :key="category"
                                :value="category"
                            >
                                {{ categoryLabel(category) }}
                            </option>
                        </select>
                    </div>
                </div>
            </div>

            <div
                class="overflow-hidden rounded-3xl border border-surface-200/80 bg-white shadow-xs dark:border-surface-800 dark:bg-surface-900"
            >
                <div class="overflow-x-auto">
                    <table
                        class="w-full min-w-[880px] border-collapse text-left"
                    >
                        <thead>
                            <tr
                                class="border-b border-surface-200/80 bg-surface-50/70 dark:border-surface-800 dark:bg-surface-950/40"
                            >
                                <th
                                    class="sticky left-0 z-10 min-w-[260px] border-r border-surface-200/80 bg-surface-50/95 px-4 py-3 text-[10px] font-bold tracking-wider text-surface-500 uppercase backdrop-blur dark:border-surface-800 dark:bg-surface-950/95 dark:text-surface-400"
                                >
                                    Permission
                                </th>
                                <th
                                    v-for="role in page.props.roles"
                                    :key="role.id"
                                    class="min-w-[130px] px-3 py-3 text-center"
                                >
                                    <button
                                        type="button"
                                        class="group mx-auto flex w-full flex-col items-center gap-1.5 rounded-xl px-2 py-1.5 transition hover:bg-white dark:hover:bg-surface-800"
                                        :title="`Kelola role ${roleLabel(role)}`"
                                        @click="openRoleFromMatrix(role.name)"
                                    >
                                        <span
                                            class="grid h-8 w-8 place-items-center rounded-lg text-[10px] font-bold"
                                            :class="
                                                role.is_system
                                                    ? 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300'
                                                    : 'bg-primary-50 text-primary-700 dark:bg-primary-950/40 dark:text-primary-300'
                                            "
                                        >
                                            {{ roleInitials(roleLabel(role)) }}
                                        </span>
                                        <span
                                            class="max-w-[120px] truncate text-[11px] font-semibold text-surface-800 group-hover:text-primary-700 dark:text-surface-200 dark:group-hover:text-primary-300"
                                        >
                                            {{ roleLabel(role) }}
                                        </span>
                                        <span
                                            class="text-[10px] text-surface-400 dark:text-surface-500"
                                        >
                                            {{ role.users_count }} anggota ·
                                            {{ role.permissions_count }}
                                        </span>
                                    </button>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <template
                                v-for="group in matrixGroups"
                                :key="group.category"
                            >
                                <tr
                                    class="border-b border-surface-200/80 bg-surface-50/40 dark:border-surface-800 dark:bg-surface-950/20"
                                >
                                    <td
                                        :colspan="page.props.roles.length + 1"
                                        class="sticky left-0 px-4 py-2"
                                    >
                                        <div
                                            class="flex items-center gap-2 text-[10px] font-bold tracking-wider text-surface-500 uppercase dark:text-surface-400"
                                        >
                                            <component
                                                :is="
                                                    getIcon(
                                                        categoryIcon(
                                                            group.category,
                                                        ),
                                                    )
                                                "
                                                class="h-3.5 w-3.5 text-primary-600 dark:text-primary-400"
                                            />
                                            {{ categoryLabel(group.category) }}
                                        </div>
                                    </td>
                                </tr>

                                <tr
                                    v-for="permission in group.permissions"
                                    :key="permission.name"
                                    class="border-b border-surface-100 transition-colors hover:bg-surface-50/50 dark:border-surface-800/70 dark:hover:bg-surface-800/20"
                                >
                                    <td
                                        class="sticky left-0 z-10 border-r border-surface-200/80 bg-white px-4 py-2.5 dark:border-surface-800 dark:bg-surface-900"
                                    >
                                        <p
                                            class="text-xs font-medium text-surface-800 dark:text-surface-200"
                                        >
                                            {{ permission.label }}
                                        </p>
                                        <p
                                            class="font-mono text-[10px] text-surface-400 dark:text-surface-500"
                                        >
                                            {{ permission.name }}
                                        </p>
                                    </td>
                                    <td
                                        v-for="role in page.props.roles"
                                        :key="`${role.id}-${permission.name}`"
                                        class="px-3 py-2.5 text-center"
                                    >
                                        <button
                                            type="button"
                                            class="mx-auto grid h-7 w-7 place-items-center rounded-lg transition"
                                            :class="
                                                roleHasPermission(
                                                    role.name,
                                                    permission.name,
                                                )
                                                    ? 'bg-emerald-50 text-emerald-600 hover:bg-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300 dark:hover:bg-emerald-900/50'
                                                    : 'bg-surface-100 text-surface-300 hover:bg-surface-200 dark:bg-surface-800/60 dark:text-surface-600 dark:hover:bg-surface-800'
                                            "
                                            :aria-label="`${permission.label}: ${roleHasPermission(role.name, permission.name) ? 'aktif' : 'tidak aktif'} untuk ${roleLabel(role)}`"
                                            @click="
                                                openRoleFromMatrix(role.name)
                                            "
                                        >
                                            <component
                                                :is="
                                                    getIcon(
                                                        roleHasPermission(
                                                            role.name,
                                                            permission.name,
                                                        )
                                                            ? 'Check'
                                                            : 'Minus',
                                                    )
                                                "
                                                class="h-3.5 w-3.5"
                                                :class="
                                                    roleHasPermission(
                                                        role.name,
                                                        permission.name,
                                                    )
                                                        ? 'stroke-[3]'
                                                        : ''
                                                "
                                            />
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div v-if="matrixGroups.length === 0" class="p-12 text-center">
                    <component
                        :is="getIcon('Search')"
                        class="mx-auto h-8 w-8 text-surface-300 dark:text-surface-600"
                    />
                    <h4
                        class="mt-2 text-sm font-semibold text-surface-800 dark:text-surface-200"
                    >
                        Tidak Ada Permission yang Cocok
                    </h4>
                    <p class="mt-1 text-xs text-surface-500">
                        Ubah kata kunci atau pilih kategori lain.
                    </p>
                    <button
                        type="button"
                        class="btn btn-secondary mt-3 text-xs"
                        @click="
                            matrixSearch = '';
                            matrixCategoryFilter = 'all';
                        "
                    >
                        Reset Filter
                    </button>
                </div>

                <div
                    class="flex flex-wrap items-center gap-4 border-t border-surface-200/80 px-4 py-3 text-[10px] text-surface-500 dark:border-surface-800 dark:text-surface-400"
                >
                    <span class="inline-flex items-center gap-1.5">
                        <span
                            class="grid h-4 w-4 place-items-center rounded bg-emerald-50 text-emerald-600 dark:bg-emerald-950/40 dark:text-emerald-300"
                        >
                            <component
                                :is="getIcon('Check')"
                                class="h-2.5 w-2.5 stroke-[3]"
                            />
                        </span>
                        Permission aktif
                    </span>
                    <span class="inline-flex items-center gap-1.5">
                        <span
                            class="grid h-4 w-4 place-items-center rounded bg-surface-100 text-surface-400 dark:bg-surface-800 dark:text-surface-500"
                        >
                            <component
                                :is="getIcon('Minus')"
                                class="h-2.5 w-2.5"
                            />
                        </span>
                        Tidak diberikan
                    </span>
                    <span class="ml-auto">
                        Angka pada header: jumlah anggota · jumlah permission
                    </span>
                </div>
            </div>
        </section>

        <!-- Sticky save bar -->
        <div
            v-if="
                viewMode === 'manage' &&
                selectedRole &&
                hasUnsavedChanges &&
                !isAdministratorSelected
            "
            class="fixed inset-x-0 bottom-4 z-40 mx-auto max-w-lg px-4"
        >
            <div
                class="flex items-center justify-between gap-3 rounded-2xl border border-surface-200 bg-white/95 p-3.5 shadow-xl backdrop-blur-md dark:border-surface-700 dark:bg-surface-900/95"
            >
                <div class="min-w-0">
                    <p
                        class="truncate text-xs font-bold text-surface-900 dark:text-surface-100"
                    >
                        Perubahan Belum Disimpan
                    </p>
                    <p
                        class="truncate text-[11px] text-surface-500 dark:text-surface-400"
                    >
                        Role: {{ roleLabel(currentRole) }}
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
                        Simpan
                    </Button>
                </div>
            </div>
        </div>

        <!-- Save confirmation -->
        <Modal
            :open="confirmSaveModal"
            title="Konfirmasi Perubahan Hak Akses"
            size="sm"
            @close="confirmSaveModal = false"
        >
            <p
                class="text-xs leading-relaxed text-surface-600 dark:text-surface-300"
            >
                Simpan perubahan permission untuk role
                <strong
                    class="font-semibold text-surface-900 dark:text-surface-100"
                    >{{ roleLabel(currentRole) }}</strong
                >?
            </p>
            <div
                class="mt-3 grid grid-cols-2 gap-2 rounded-xl border border-surface-200/80 bg-surface-50/60 p-3 text-xs dark:border-surface-800 dark:bg-surface-950/30"
            >
                <div>
                    <p class="text-surface-500 dark:text-surface-400">
                        Sebelumnya
                    </p>
                    <p
                        class="mt-0.5 font-semibold text-surface-800 dark:text-surface-200"
                    >
                        {{ originalPermissionNames.length }} permission
                    </p>
                </div>
                <div>
                    <p class="text-surface-500 dark:text-surface-400">
                        Setelah disimpan
                    </p>
                    <p
                        class="mt-0.5 font-semibold text-surface-800 dark:text-surface-200"
                    >
                        {{ activePermissionCount }} permission
                    </p>
                </div>
            </div>
            <p class="mt-3 text-[11px] text-surface-500 dark:text-surface-400">
                Perubahan langsung berlaku untuk
                {{ currentRole?.users_count ?? 0 }} anggota dengan role ini.
            </p>

            <template #footer>
                <Button
                    variant="secondary"
                    size="sm"
                    @click="confirmSaveModal = false"
                >
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

        <!-- Unsaved role switch confirmation -->
        <Modal
            :open="switchRoleModalOpen"
            title="Tinggalkan Perubahan?"
            size="sm"
            @close="
                switchRoleModalOpen = false;
                pendingRole = null;
            "
        >
            <p
                class="text-xs leading-relaxed text-surface-600 dark:text-surface-300"
            >
                Anda memiliki perubahan permission yang belum disimpan untuk
                role
                <strong
                    class="font-semibold text-surface-900 dark:text-surface-100"
                    >{{ roleLabel(currentRole) }}</strong
                >. Beralih ke role lain akan membuang perubahan tersebut.
            </p>

            <template #footer>
                <Button
                    variant="secondary"
                    size="sm"
                    @click="
                        switchRoleModalOpen = false;
                        pendingRole = null;
                    "
                >
                    Tetap di Sini
                </Button>
                <Button
                    variant="destructive"
                    size="sm"
                    @click="confirmRoleSwitch"
                >
                    Buang & Pindah
                </Button>
            </template>
        </Modal>

        <!-- Unsaved navigation confirmation -->
        <Modal
            :open="leaveModalOpen"
            title="Perubahan Belum Disimpan"
            size="sm"
            @close="
                leaveModalOpen = false;
                pendingNavigation = null;
            "
        >
            <p
                class="text-xs leading-relaxed text-surface-600 dark:text-surface-300"
            >
                Anda akan meninggalkan halaman ini. Perubahan permission yang
                belum disimpan akan hilang jika Anda melanjutkan.
            </p>

            <template #footer>
                <Button
                    variant="secondary"
                    size="sm"
                    @click="
                        leaveModalOpen = false;
                        pendingNavigation = null;
                    "
                >
                    Tetap di Halaman
                </Button>
                <Button
                    variant="destructive"
                    size="sm"
                    @click="confirmLeaveNavigation"
                >
                    Buang & Tinggalkan
                </Button>
            </template>
        </Modal>
    </div>
</template>
