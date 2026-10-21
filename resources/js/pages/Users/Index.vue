<script setup lang="ts">
import { ref, reactive, watch, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { usePage, Link } from '@inertiajs/vue3';
import users from '@/routes/users';
import AppLayout from '@/layouts/AppLayout.vue';
import { useUsers, type UserFilters } from '@/composables/useUsers';
import { getIcon } from '@/components/sidebar/icons';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Select from '@/components/ui/Select.vue';
import Table from '@/components/ui/Table.vue';
import Pagination from '@/components/ui/Pagination.vue';
import Modal from '@/components/ui/Modal.vue';
import Avatar from '@/components/ui/Avatar.vue';
import type { User } from '@/types';

defineOptions({
    layout: AppLayout,
});

const page = usePage();

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
    per_page: 15,
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
    { value: 'name', label: 'Nama' },
    { value: 'email', label: 'Email' },
    { value: 'created_at', label: 'Dibuat' },
];

const columns = [
    { key: 'name', label: 'Nama', class: 'max-w-xs' },
    { key: 'email', label: 'Email', sortable: true, class: 'max-w-md' },
    { key: 'roles', label: 'Peran', class: 'max-w-md' },
    { key: 'is_active', label: 'Status' },
    { key: 'created_at', label: 'Dibuat', sortable: true },
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
    } catch {
        // Error handled by api interceptor
    }
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
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <h1
                    class="text-2xl font-bold text-surface-900 dark:text-surface-100"
                >
                    Manajemen Pengguna
                </h1>
                <p class="mt-1 text-surface-600 dark:text-surface-400">
                    Kelola pengguna dan peran mereka
                </p>
            </div>

            <Link
                v-if="
                    page.props.auth.user?.permissions.includes('users.create')
                "
                :href="users.create.url()"
            >
                <Button>
                    <component :is="getIcon('Plus')" class="h-4 w-4" />
                    Tambah Pengguna
                </Button>
            </Link>
        </div>

        <!-- Filters -->
        <div class="card">
            <div class="flex flex-col gap-4 sm:flex-row">
                <Input
                    v-model="search"
                    placeholder="Cari nama atau email..."
                    class="flex-1"
                />
                <Select
                    v-model="filters.role"
                    :options="roleOptions"
                    class="w-full sm:w-48"
                    @update:model-value="filters.page = 1"
                />
                <Select
                    v-model="filters.is_active"
                    :options="statusOptions"
                    class="w-full sm:w-40"
                    @update:model-value="filters.page = 1"
                />
            </div>
        </div>

        <!-- Error -->
        <div
            v-if="error"
            class="rounded-lg bg-red-50 p-4 text-red-700 dark:bg-red-900/30 dark:text-red-300"
        >
            {{ error }}
        </div>

        <!-- Table -->
        <Table
            :columns="columns"
            :data="userList"
            :loading="loading"
            :sort-by="filters.sort_by"
            :sort-direction="filters.sort_direction"
            :on-sort="handleSort"
            row-key="id"
        >
            <template #name="{ row }">
                <div class="flex items-center gap-3">
                    <Avatar
                        :initials="row.initials"
                        :src="row.avatar"
                        size="md"
                    />
                    <div class="min-w-0">
                        <p
                            class="truncate font-medium text-surface-900 dark:text-surface-100"
                        >
                            {{ row.name }}
                        </p>
                        <p
                            class="truncate text-sm text-surface-500 dark:text-surface-400"
                        >
                            {{ row.email }}
                        </p>
                    </div>
                </div>
            </template>

            <template #email="{ row }">
                <p
                    class="truncate text-sm text-surface-900 dark:text-surface-100"
                >
                    {{ row.email }}
                </p>
            </template>

            <template #roles="{ row }">
                <div class="flex flex-wrap gap-1">
                    <span
                        v-for="role in row.roles"
                        :key="role"
                        class="badge-primary"
                    >
                        {{ role }}
                    </span>
                </div>
            </template>

            <template #is_active="{ row }">
                <span
                    class="badge"
                    :class="row.is_active ? 'badge-success' : 'badge-danger'"
                >
                    {{ row.is_active ? 'Aktif' : 'Nonaktif' }}
                </span>
            </template>

            <template #created_at="{ row }">
                <p class="text-sm text-surface-500 dark:text-surface-400">
                    {{
                        new Date(row.created_at).toLocaleDateString('id-ID', {
                            day: '2-digit',
                            month: 'short',
                            year: 'numeric',
                        })
                    }}
                </p>
            </template>

            <template #actions="{ row }">
                <div class="flex items-center gap-2">
                    <Link
                        :href="users.edit.url({ user: row.id })"
                        class="btn-ghost btn-icon text-surface-500 hover:bg-surface-100 dark:text-surface-400 dark:hover:bg-surface-800"
                        title="Edit"
                    >
                        <component :is="getIcon('Pencil')" class="h-4 w-4" />
                    </Link>

                    <Button
                        v-if="
                            page.props.auth.user?.permissions.includes(
                                'users.delete',
                            ) && page.props.auth.user.id !== row.id
                        "
                        variant="ghost"
                        size="sm"
                        class="text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20"
                        title="Hapus"
                        @click="confirmDelete(row)"
                    >
                        <component :is="getIcon('Trash2')" class="h-4 w-4" />
                    </Button>
                </div>
            </template>
        </Table>

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
        <p class="text-surface-600 dark:text-surface-300">
            Apakah Anda yakin ingin menghapus pengguna
            <strong class="text-surface-900 dark:text-surface-100">{{
                userToDelete?.name
            }}</strong>
            ({{ userToDelete?.email }})?
        </p>
        <p class="mt-2 text-sm text-surface-500 dark:text-surface-400">
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
