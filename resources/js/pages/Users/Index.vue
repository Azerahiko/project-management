<script setup lang="ts">
import { ref, reactive, watch, computed, onMounted } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import users from '@/routes/users';
import AppLayout from '@/layouts/AppLayout.vue';
import { useUsers, type UserFilters } from '@/composables/useUsers';
import { useToast } from '@/composables/useToast';
import { getIcon } from '@/components/sidebar/icons';
import Button from '@/components/ui/Button.vue';
import Pagination from '@/components/ui/Pagination.vue';
import Modal from '@/components/ui/Modal.vue';
import Avatar from '@/components/ui/Avatar.vue';
import type { User } from '@/types';

defineOptions({
    layout: AppLayout,
});

const page = usePage();
const { success } = useToast();

const viewMode = ref<'grid' | 'table'>('grid');

// Filter state
const search = ref('');
let searchTimeout: ReturnType<typeof setTimeout>;
const filters = reactive<UserFilters>({
    search: '',
    role: '',
    is_active: '',
    sort_by: 'created_at',
    sort_direction: 'desc',
    page: 1,
    per_page: 12,
});

const {
    users: userList,
    meta,
    loading,
    error,
    fetchUsers,
    deleteUser,
} = useUsers(filters);

const deleteModalOpen = ref(false);
const userToDelete = ref<User | null>(null);

const roleOptions = [
    { value: '', label: 'Semua Peran' },
    { value: 'administrator', label: 'Administrator' },
    { value: 'project_manager', label: 'Project Manager' },
    { value: 'team_leader', label: 'Team Leader' },
    { value: 'member', label: 'Member' },
    { value: 'reviewer', label: 'Reviewer' },
    { value: 'client', label: 'Client' },
];

const statusOptions = [
    { value: '', label: 'Semua Status' },
    { value: 'true', label: 'Aktif' },
    { value: 'false', label: 'Nonaktif' },
];

const sortOptions = [
    { value: 'created_at:desc', label: 'Terbaru' },
    { value: 'name:asc', label: 'Nama A–Z' },
    { value: 'email:asc', label: 'Email' },
];

const sortValue = ref('created_at:desc');

function handleSortChange(val: string): void {
    sortValue.value = val;
    const [field, dir] = val.split(':');
    filters.sort_by = field;
    filters.sort_direction = (dir as 'asc' | 'desc') || 'asc';
}

// KPI Statistics
const activeUsersCount = computed(() => {
    return userList.value.filter((u) => u.is_active).length;
});

const adminAndPmCount = computed(() => {
    return userList.value.filter((u) =>
        u.roles?.some(
            (r) =>
                r.toLowerCase().includes('admin') ||
                r.toLowerCase().includes('manager'),
        ),
    ).length;
});

const memberCount = computed(() => {
    return userList.value.filter((u) =>
        u.roles?.some(
            (r) =>
                r.toLowerCase().includes('member') ||
                r.toLowerCase().includes('leader'),
        ),
    ).length;
});

const columns = [
    { key: 'name', label: 'Pengguna', class: 'max-w-xs' },
    { key: 'email', label: 'Email', sortable: true, class: 'max-w-md' },
    { key: 'roles', label: 'Peran', class: 'max-w-md' },
    { key: 'is_active', label: 'Status' },
    { key: 'created_at', label: 'Bergabung', sortable: true },
    { key: 'actions', label: 'Aksi' },
];

function handleSort(key: string): void {
    if (filters.sort_by === key) {
        filters.sort_direction =
            filters.sort_direction === 'asc' ? 'desc' : 'asc';
    } else {
        filters.sort_by = key;
        filters.sort_direction = 'asc';
    }
}

function handlePageChange(pageNum: number): void {
    filters.page = pageNum;
}

function confirmDelete(user: User): void {
    userToDelete.value = user;
    deleteModalOpen.value = true;
}

async function executeDelete(): Promise<void> {
    if (!userToDelete.value) return;

    try {
        await deleteUser(userToDelete.value.id);
        deleteModalOpen.value = false;
        userToDelete.value = null;
        await fetchUsers();
        success('Pengguna berhasil dihapus');
    } catch {
        // Error handled by api interceptor
    }
}

function copyEmail(email: string): void {
    navigator.clipboard.writeText(email);
    success(`Email ${email} disalin ke papan klip`);
}

function formatDate(dateString: string): string {
    if (!dateString) return '—';
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

onMounted(() => {
    fetchUsers();
});

watch(search, (value) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        filters.search = value;
        filters.page = 1;
    }, 300);
});

watch(
    () => [
        filters.search,
        filters.role,
        filters.is_active,
        filters.sort_by,
        filters.sort_direction,
        filters.page,
    ],
    () => {
        fetchUsers();
    },
    { deep: true },
);
</script>

<template>
    <div class="space-y-6">
        <!-- 1. Header Section: Calm System Style -->
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
                    <span>Direktori Tim</span>
                    <span class="text-surface-300 dark:text-surface-700"
                        >·</span
                    >
                    <span>Tasks Flow Workspace</span>
                </div>
                <h1
                    class="mt-1 font-serif text-2xl font-normal tracking-tight text-surface-950 sm:text-3xl dark:text-surface-50"
                >
                    Anggota & Peran
                </h1>
                <p
                    class="mt-1 max-w-xl text-xs text-surface-600 sm:text-sm dark:text-surface-400"
                >
                    Kelola seluruh akses pengguna, otorisasi peran, dan
                    kolaborator proyek tim dalam ruang kerja yang terorganisir.
                </p>
            </div>

            <Link
                v-if="
                    page.props.auth.user?.permissions.includes('users.create')
                "
                :href="users.create.url()"
                class="inline-flex shrink-0 items-center gap-2 rounded-full bg-[#0F5132] px-5 py-2.5 text-xs font-medium text-white shadow-xs transition hover:bg-[#0c4329] active:scale-95 sm:text-sm dark:bg-[#0F5132] dark:hover:bg-[#14643f]"
            >
                <component :is="getIcon('UserPlus')" class="h-4 w-4" />
                <span>Tambah Pengguna</span>
            </Link>
        </div>

        <!-- 2. KPI Summary Cards Row (Calm System Reference Style) -->
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Stat 1: Total Pengguna -->
            <div
                class="rounded-3xl border border-surface-200/80 bg-white p-5 shadow-xs dark:border-surface-800 dark:bg-surface-900"
            >
                <div class="flex items-center justify-between text-surface-400">
                    <span
                        class="text-[11px] font-semibold tracking-wider uppercase"
                        >Total Pengguna</span
                    >
                    <component
                        :is="getIcon('Users')"
                        class="h-4 w-4 text-[#0F5132] dark:text-[#b7d9c1]"
                    />
                </div>
                <div
                    class="mt-2 font-serif text-3xl font-normal text-surface-950 dark:text-surface-50"
                >
                    {{ meta?.total ?? userList.length }}
                </div>
                <p class="mt-1 text-[11px] text-surface-500">
                    Akun terdaftar dalam sistem
                </p>
            </div>

            <!-- Stat 2: Pengguna Aktif -->
            <div
                class="rounded-3xl border border-surface-200/80 bg-white p-5 shadow-xs dark:border-surface-800 dark:bg-surface-900"
            >
                <div class="flex items-center justify-between text-surface-400">
                    <span
                        class="text-[11px] font-semibold tracking-wider uppercase"
                        >Pengguna Aktif</span
                    >
                    <component
                        :is="getIcon('CheckCircle')"
                        class="h-4 w-4 text-emerald-600 dark:text-emerald-400"
                    />
                </div>
                <div
                    class="mt-2 font-serif text-3xl font-normal text-emerald-700 dark:text-emerald-400"
                >
                    {{ activeUsersCount }}
                </div>
                <div
                    class="mt-1 flex items-center gap-1.5 text-[11px] text-surface-500"
                >
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                    <span>Siap berkolaborasi dalam tugas</span>
                </div>
            </div>

            <!-- Stat 3: Administrator & PM -->
            <div
                class="rounded-3xl border border-surface-200/80 bg-white p-5 shadow-xs dark:border-surface-800 dark:bg-surface-900"
            >
                <div class="flex items-center justify-between text-surface-400">
                    <span
                        class="text-[11px] font-semibold tracking-wider uppercase"
                        >Manajemen & Admin</span
                    >
                    <component
                        :is="getIcon('Shield')"
                        class="h-4 w-4 text-primary-700 dark:text-primary-300"
                    />
                </div>
                <div
                    class="mt-2 font-serif text-3xl font-normal text-surface-950 dark:text-surface-50"
                >
                    {{ adminAndPmCount }}
                </div>
                <p class="mt-1 text-[11px] text-surface-500">
                    Akses pengelola & pengawas
                </p>
            </div>

            <!-- Stat 4: Anggota Reguler -->
            <div
                class="rounded-3xl border border-surface-200/80 bg-white p-5 shadow-xs dark:border-surface-800 dark:bg-surface-900"
            >
                <div class="flex items-center justify-between text-surface-400">
                    <span
                        class="text-[11px] font-semibold tracking-wider uppercase"
                        >Anggota Tim</span
                    >
                    <component
                        :is="getIcon('Briefcase')"
                        class="h-4 w-4 text-surface-600 dark:text-surface-300"
                    />
                </div>
                <div
                    class="mt-2 font-serif text-3xl font-normal text-surface-950 dark:text-surface-50"
                >
                    {{ memberCount }}
                </div>
                <p class="mt-1 text-[11px] text-surface-500">
                    Eksekutor alur kerja utama
                </p>
            </div>
        </div>

        <!-- 3. Filter & View Switcher Bar (Calm System 04 & 07 Style) -->
        <div
            class="flex flex-col gap-4 rounded-3xl border border-surface-200/80 bg-white p-4 shadow-xs lg:flex-row lg:items-center lg:justify-between dark:border-surface-800 dark:bg-surface-900"
        >
            <!-- Search & Filters -->
            <div class="flex flex-1 flex-col gap-3 sm:flex-row sm:items-center">
                <!-- Search Field -->
                <div class="relative flex-1">
                    <div
                        class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-surface-400"
                    >
                        <component :is="getIcon('Search')" class="h-4 w-4" />
                    </div>
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Cari nama anggota atau email..."
                        class="h-10 w-full rounded-full border border-surface-300 bg-surface-50/80 pr-4 pl-9 text-xs text-surface-900 placeholder-surface-400 shadow-2xs transition focus:border-primary-600 focus:bg-white focus:ring-2 focus:ring-primary-600/15 focus:outline-hidden dark:border-surface-700 dark:bg-surface-800/80 dark:text-surface-100 dark:placeholder-surface-500 dark:focus:border-primary-400"
                    />
                </div>

                <!-- Role Filter -->
                <select
                    v-model="filters.role"
                    class="h-10 rounded-full border border-surface-300 bg-surface-50/80 px-4 text-xs font-medium text-surface-700 shadow-2xs transition focus:border-primary-600 focus:bg-white focus:outline-hidden sm:w-44 dark:border-surface-700 dark:bg-surface-800/80 dark:text-surface-200 dark:focus:border-primary-400"
                    @change="filters.page = 1"
                >
                    <option
                        v-for="opt in roleOptions"
                        :key="opt.value"
                        :value="opt.value"
                    >
                        {{ opt.label }}
                    </option>
                </select>

                <!-- Status Filter -->
                <select
                    v-model="filters.is_active"
                    class="h-10 rounded-full border border-surface-300 bg-surface-50/80 px-4 text-xs font-medium text-surface-700 shadow-2xs transition focus:border-primary-600 focus:bg-white focus:outline-hidden sm:w-36 dark:border-surface-700 dark:bg-surface-800/80 dark:text-surface-200 dark:focus:border-primary-400"
                    @change="filters.page = 1"
                >
                    <option
                        v-for="opt in statusOptions"
                        :key="opt.value"
                        :value="opt.value"
                    >
                        {{ opt.label }}
                    </option>
                </select>

                <!-- Sort Filter -->
                <select
                    v-model="sortValue"
                    class="h-10 rounded-full border border-surface-300 bg-surface-50/80 px-4 text-xs font-medium text-surface-700 shadow-2xs transition focus:border-primary-600 focus:bg-white focus:outline-hidden sm:w-36 dark:border-surface-700 dark:bg-surface-800/80 dark:text-surface-200 dark:focus:border-primary-400"
                    @change="handleSortChange(sortValue)"
                >
                    <option
                        v-for="opt in sortOptions"
                        :key="opt.value"
                        :value="opt.value"
                    >
                        {{ opt.label }}
                    </option>
                </select>
            </div>

            <!-- View Switcher Tabs (Calm 07 TABS) -->
            <div
                class="flex shrink-0 items-center rounded-full border border-surface-200 bg-surface-100 p-1 dark:border-surface-800 dark:bg-surface-800"
            >
                <button
                    type="button"
                    class="flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-medium transition active:scale-95"
                    :class="
                        viewMode === 'grid'
                            ? 'bg-[#0F5132] text-white shadow-2xs dark:bg-[#0F5132]'
                            : 'text-surface-600 hover:text-surface-900 dark:text-surface-400 dark:hover:text-white'
                    "
                    @click="viewMode = 'grid'"
                >
                    <component
                        :is="getIcon('LayoutGrid')"
                        class="h-3.5 w-3.5"
                    />
                    <span>Kartu</span>
                </button>
                <button
                    type="button"
                    class="flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-medium transition active:scale-95"
                    :class="
                        viewMode === 'table'
                            ? 'bg-[#0F5132] text-white shadow-2xs dark:bg-[#0F5132]'
                            : 'text-surface-600 hover:text-surface-900 dark:text-surface-400 dark:hover:text-white'
                    "
                    @click="viewMode = 'table'"
                >
                    <component :is="getIcon('List')" class="h-3.5 w-3.5" />
                    <span>Tabel</span>
                </button>
            </div>
        </div>

        <!-- Error State -->
        <div
            v-if="error"
            class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-xs font-medium text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/20 dark:text-rose-300"
        >
            {{ error }}
        </div>

        <!-- Loading Indicator -->
        <div
            v-if="loading && userList.length === 0"
            class="flex items-center justify-center py-20"
        >
            <div
                class="h-8 w-8 animate-spin rounded-full border-3 border-[#0F5132] border-t-transparent dark:border-[#b7d9c1]"
            />
        </div>

        <!-- Empty State -->
        <div
            v-else-if="userList.length === 0"
            class="flex flex-col items-center justify-center rounded-3xl border border-surface-200/80 bg-white p-12 text-center shadow-xs dark:border-surface-800 dark:bg-surface-900"
        >
            <div
                class="flex h-14 w-14 items-center justify-center rounded-2xl bg-[#0F5132]/10 text-[#0F5132] dark:bg-[#b7d9c1]/10 dark:text-[#b7d9c1]"
            >
                <component :is="getIcon('Users')" class="h-7 w-7" />
            </div>
            <h3
                class="mt-4 font-serif text-lg font-normal text-surface-950 dark:text-surface-50"
            >
                Pengguna Tidak Ditemukan
            </h3>
            <p class="mt-1 max-w-sm text-xs text-surface-500">
                Tidak ada pengguna yang cocok dengan kriteria pencarian atau
                filter yang dipilih.
            </p>
            <button
                type="button"
                class="mt-4 inline-flex items-center gap-1.5 rounded-full border border-surface-300 bg-white px-4 py-2 text-xs font-medium text-surface-700 shadow-2xs hover:bg-surface-50 dark:border-surface-700 dark:bg-surface-800 dark:text-surface-300"
                @click="
                    search = '';
                    filters.role = '';
                    filters.is_active = '';
                    filters.page = 1;
                "
            >
                Reset Filter
            </button>
        </div>

        <!-- 4A. GRID VIEW: Modern Tactile Profile Cards (NO status badge pills!) -->
        <div
            v-else-if="viewMode === 'grid'"
            class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
        >
            <article
                v-for="userItem in userList"
                :key="userItem.id"
                class="group relative flex flex-col justify-between rounded-3xl border border-surface-200/80 bg-white p-5 shadow-xs transition-all duration-200 hover:-translate-y-0.5 hover:border-[#0F5132]/40 hover:shadow-md dark:border-surface-800 dark:bg-surface-900"
            >
                <!-- Top Card: Avatar & Quick Action -->
                <div>
                    <div class="flex items-start justify-between gap-3">
                        <!-- Avatar with Online Dot -->
                        <div class="relative">
                            <Avatar
                                :initials="userItem.initials"
                                :src="userItem.avatar"
                                :alt="userItem.name"
                                size="lg"
                            />
                            <span
                                class="absolute -right-0.5 -bottom-0.5 h-3 w-3 rounded-full ring-2 ring-white dark:ring-surface-900"
                                :class="
                                    userItem.is_active
                                        ? 'bg-emerald-500'
                                        : 'bg-surface-400'
                                "
                                :title="
                                    userItem.is_active
                                        ? 'Pengguna Aktif'
                                        : 'Nonaktif'
                                "
                            />
                        </div>

                        <!-- Action Buttons -->
                        <div
                            class="flex items-center gap-1 opacity-80 group-hover:opacity-100"
                        >
                            <Link
                                :href="users.edit.url({ user: userItem.id })"
                                class="flex h-8 w-8 items-center justify-center rounded-xl text-surface-400 transition hover:bg-surface-100 hover:text-surface-900 dark:text-surface-400 dark:hover:bg-surface-800 dark:hover:text-surface-100"
                                title="Edit Pengguna"
                            >
                                <component
                                    :is="getIcon('Pencil')"
                                    class="h-4 w-4"
                                />
                            </Link>
                            <button
                                v-if="
                                    page.props.auth.user?.permissions.includes(
                                        'users.delete',
                                    ) && page.props.auth.user.id !== userItem.id
                                "
                                type="button"
                                class="flex h-8 w-8 items-center justify-center rounded-xl text-surface-400 transition hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/20 dark:hover:text-rose-400"
                                title="Hapus Pengguna"
                                @click="confirmDelete(userItem)"
                            >
                                <component
                                    :is="getIcon('Trash2')"
                                    class="h-4 w-4"
                                />
                            </button>
                        </div>
                    </div>

                    <!-- User Name & Email -->
                    <div class="mt-4">
                        <h2
                            class="truncate font-serif text-base font-normal text-surface-950 transition group-hover:text-[#0F5132] dark:text-surface-50 dark:group-hover:text-[#b7d9c1]"
                            :title="userItem.name"
                        >
                            {{ userItem.name }}
                        </h2>
                        <button
                            type="button"
                            class="mt-1 flex items-center gap-1.5 truncate text-xs text-surface-500 hover:text-surface-800 dark:text-surface-400 dark:hover:text-surface-200"
                            :title="`Salin ${userItem.email}`"
                            @click="copyEmail(userItem.email)"
                        >
                            <component
                                :is="getIcon('Mail')"
                                class="h-3 w-3 shrink-0 text-surface-400"
                            />
                            <span class="truncate">{{ userItem.email }}</span>
                        </button>
                    </div>

                    <!-- Roles List (Clean rounded-md tags, NO pill badges!) -->
                    <div class="mt-3.5 flex flex-wrap gap-1.5">
                        <span
                            v-for="role in userItem.roles"
                            :key="role"
                            class="inline-flex items-center rounded-md border border-surface-200/80 bg-surface-50 px-2 py-0.5 text-[11px] font-medium text-surface-700 dark:border-surface-700 dark:bg-surface-800 dark:text-surface-300"
                        >
                            {{ role }}
                        </span>
                    </div>
                </div>

                <!-- Footer: Joined Date & Status Indicator (NO badge pills) -->
                <div
                    class="mt-5 flex items-center justify-between border-t border-surface-100 pt-3 text-xs dark:border-surface-800"
                >
                    <div class="flex items-center gap-1.5 text-surface-500">
                        <span
                            class="h-1.5 w-1.5 rounded-full"
                            :class="
                                userItem.is_active
                                    ? 'bg-emerald-500'
                                    : 'bg-surface-400'
                            "
                        />
                        <span
                            :class="
                                userItem.is_active
                                    ? 'font-medium text-surface-700 dark:text-surface-300'
                                    : 'text-surface-400'
                            "
                        >
                            {{ userItem.is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </div>

                    <span
                        class="text-[11px] text-surface-400"
                        :title="`Bergabung ${formatDate(userItem.created_at)}`"
                    >
                        {{ formatDate(userItem.created_at) }}
                    </span>
                </div>
            </article>
        </div>

        <!-- 4B. TABLE VIEW: Polished Clean Table (NO pill badges) -->
        <div
            v-else
            class="overflow-hidden rounded-3xl border border-surface-200/80 bg-white shadow-xs dark:border-surface-800 dark:bg-surface-900"
        >
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead
                        class="border-b border-surface-100 bg-surface-50/70 text-[11px] font-semibold tracking-wider text-surface-500 uppercase dark:border-surface-800 dark:bg-surface-800/40"
                    >
                        <tr>
                            <th class="px-6 py-3.5">Pengguna</th>
                            <th class="px-6 py-3.5">Email</th>
                            <th class="px-6 py-3.5">Peran</th>
                            <th class="px-6 py-3.5">Status</th>
                            <th class="px-6 py-3.5">Bergabung</th>
                            <th class="px-6 py-3.5 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-surface-100 dark:divide-surface-800"
                    >
                        <tr
                            v-for="userItem in userList"
                            :key="userItem.id"
                            class="transition hover:bg-surface-50/60 dark:hover:bg-surface-800/30"
                        >
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <Avatar
                                        :initials="userItem.initials"
                                        :src="userItem.avatar"
                                        :alt="userItem.name"
                                        size="md"
                                    />
                                    <div>
                                        <p
                                            class="font-medium text-surface-950 dark:text-surface-50"
                                        >
                                            {{ userItem.name }}
                                        </p>
                                        <p class="text-[11px] text-surface-400">
                                            ID #{{ userItem.id }}
                                        </p>
                                    </div>
                                </div>
                            </td>
                            <td
                                class="px-6 py-4 text-surface-600 dark:text-surface-300"
                            >
                                {{ userItem.email }}
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex flex-wrap gap-1">
                                    <span
                                        v-for="role in userItem.roles"
                                        :key="role"
                                        class="inline-flex items-center rounded-md border border-surface-200/80 bg-surface-50 px-2 py-0.5 text-[11px] font-medium text-surface-700 dark:border-surface-700 dark:bg-surface-800 dark:text-surface-300"
                                    >
                                        {{ role }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div
                                    class="inline-flex items-center gap-1.5 text-xs font-medium"
                                >
                                    <span
                                        class="h-1.5 w-1.5 rounded-full"
                                        :class="
                                            userItem.is_active
                                                ? 'bg-emerald-500'
                                                : 'bg-surface-400'
                                        "
                                    />
                                    <span
                                        :class="
                                            userItem.is_active
                                                ? 'text-surface-800 dark:text-surface-200'
                                                : 'text-surface-400'
                                        "
                                    >
                                        {{
                                            userItem.is_active
                                                ? 'Aktif'
                                                : 'Nonaktif'
                                        }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-surface-500">
                                {{ formatDate(userItem.created_at) }}
                            </td>
                            <td class="px-6 py-4 text-right">
                                <div
                                    class="flex items-center justify-end gap-1"
                                >
                                    <Link
                                        :href="
                                            users.edit.url({
                                                user: userItem.id,
                                            })
                                        "
                                        class="flex h-8 w-8 items-center justify-center rounded-lg text-surface-400 transition hover:bg-surface-100 hover:text-surface-800 dark:hover:bg-surface-800 dark:hover:text-surface-100"
                                        title="Edit"
                                    >
                                        <component
                                            :is="getIcon('Pencil')"
                                            class="h-4 w-4"
                                        />
                                    </Link>
                                    <button
                                        v-if="
                                            page.props.auth.user?.permissions.includes(
                                                'users.delete',
                                            ) &&
                                            page.props.auth.user.id !==
                                                userItem.id
                                        "
                                        type="button"
                                        class="flex h-8 w-8 items-center justify-center rounded-lg text-surface-400 transition hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/20 dark:hover:text-rose-400"
                                        title="Hapus"
                                        @click="confirmDelete(userItem)"
                                    >
                                        <component
                                            :is="getIcon('Trash2')"
                                            class="h-4 w-4"
                                        />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 5. Pagination -->
        <Pagination
            :current-page="meta.current_page"
            :last-page="meta.last_page"
            :from="meta.from"
            :to="meta.to"
            :total="meta.total"
            @change="handlePageChange"
        />
    </div>

    <!-- Delete Confirmation Modal -->
    <Modal
        :open="deleteModalOpen"
        title="Hapus Pengguna"
        @close="deleteModalOpen = false"
    >
        <p class="text-xs text-surface-600 sm:text-sm dark:text-surface-300">
            Apakah Anda yakin ingin menghapus pengguna
            <strong class="text-surface-900 dark:text-surface-100">{{
                userToDelete?.name
            }}</strong>
            ({{ userToDelete?.email }})?
        </p>
        <p class="mt-2 text-xs text-surface-500 dark:text-surface-400">
            Tindakan ini tidak dapat dibatalkan.
        </p>

        <template #footer>
            <Button variant="secondary" @click="deleteModalOpen = false">
                Batal
            </Button>
            <Button
                variant="destructive"
                @click="executeDelete"
                :loading="loading"
            >
                Hapus
            </Button>
        </template>
    </Modal>
</template>
