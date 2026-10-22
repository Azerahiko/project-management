<script setup lang="ts">
import { ref, reactive, watch, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { usePage, Link } from '@inertiajs/vue3';
import projects from '@/routes/projects';
import AppLayout from '@/layouts/AppLayout.vue';
import { useProjects } from '@/composables/useProjects';
import { getIcon } from '@/components/sidebar/icons';
import { useToast } from '@/composables/useToast';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Select from '@/components/ui/Select.vue';
import Table from '@/components/ui/Table.vue';
import Pagination from '@/components/ui/Pagination.vue';
import Modal from '@/components/ui/Modal.vue';
import Avatar from '@/components/ui/Avatar.vue';
import type { Project, ProjectFilters } from '@/types';

defineOptions({
    layout: AppLayout,
});

const page = usePage();
const { success, error: toastError } = useToast();

// Filter state
const search = ref('');
let searchTimeout: ReturnType<typeof setTimeout>;
const filters = reactive<ProjectFilters>({
    search: '',
    status: '',
    priority: '',
    owner_id: '',
    sort_by: 'created_at',
    sort_direction: 'desc',
    page: 1,
    per_page: 15,
});

const {
    projects: projectList,
    meta,
    loading,
    error,
    fetchProjects,
    deleteProject,
} = useProjects(filters);

const deleteModalOpen = ref(false);
const projectToDelete = ref<Project | null>(null);

const statusOptions = [
    { value: '', label: 'Semua Status' },
    { value: 'draft', label: 'Draft' },
    { value: 'active', label: 'Aktif' },
    { value: 'on_hold', label: 'Ditahan' },
    { value: 'completed', label: 'Selesai' },
    { value: 'archived', label: 'Arsip' },
];

const priorityOptions = [
    { value: '', label: 'Semua Prioritas' },
    { value: 'low', label: 'Rendah' },
    { value: 'medium', label: 'Sedang' },
    { value: 'high', label: 'Tinggi' },
    { value: 'critical', label: 'Kritis' },
];

const sortOptions = [
    { value: 'name', label: 'Nama' },
    { value: 'status', label: 'Status' },
    { value: 'priority', label: 'Prioritas' },
    { value: 'start_date', label: 'Tanggal Mulai' },
    { value: 'end_date', label: 'Deadline' },
    { value: 'created_at', label: 'Dibuat' },
];

const columns = [
    { key: 'name', label: 'Nama', class: 'max-w-xs' },
    { key: 'status', label: 'Status' },
    { key: 'priority', label: 'Prioritas' },
    { key: 'owner', label: 'Manager', class: 'max-w-xs' },
    { key: 'dates', label: 'Tanggal', class: 'max-w-md' },
    { key: 'members_count', label: 'Anggota' },
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

function confirmDelete(project: Project): void {
    projectToDelete.value = project;
    deleteModalOpen.value = true;
}

async function executeDelete(): Promise<void> {
    if (!projectToDelete.value) return;

    try {
        await deleteProject(projectToDelete.value.id);
        success('Proyek dihapus', 'Proyek telah berhasil dihapus.');
        deleteModalOpen.value = false;
        projectToDelete.value = null;
        await fetchProjects();
    } catch {
        // Error handled by api interceptor
    }
}

onMounted(() => {
    fetchProjects();
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
        filters.status,
        filters.priority,
        filters.owner_id,
        filters.sort_by,
        filters.sort_direction,
        filters.page,
    ],
    () => {
        fetchProjects();
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
                    Proyek
                </h1>
                <p class="mt-1 text-surface-600 dark:text-surface-400">
                    Kelola seluruh proyek dalam satu tempat
                </p>
            </div>

            <Link
                v-if="
                    page.props.auth.user?.permissions.includes(
                        'projects.create',
                    )
                "
                :href="projects.create.url()"
            >
                <Button>
                    <component :is="getIcon('Plus')" class="h-4 w-4" />
                    Tambah Proyek
                </Button>
            </Link>
        </div>

        <!-- Filters -->
        <div class="card">
            <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap">
                <Input
                    v-model="search"
                    placeholder="Cari nama atau deskripsi..."
                    class="min-w-[200px] flex-1"
                />
                <Select
                    v-model="filters.status"
                    :options="statusOptions"
                    class="w-full sm:w-40"
                    @update:model-value="filters.page = 1"
                />
                <Select
                    v-model="filters.priority"
                    :options="priorityOptions"
                    class="w-full sm:w-40"
                    @update:model-value="filters.page = 1"
                />
                <Select
                    v-model="filters.sort_by"
                    :options="sortOptions"
                    class="w-full sm:w-40"
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
            :data="projectList"
            :loading="loading"
            :sort-by="filters.sort_by"
            :sort-direction="filters.sort_direction"
            :on-sort="handleSort"
            row-key="id"
        >
            <template #name="{ row }">
                <Link
                    :href="projects.show.url({ project: row.id })"
                    class="block"
                >
                    <p
                        class="truncate font-medium text-surface-900 hover:text-primary-600 dark:text-surface-100 dark:hover:text-primary-400"
                    >
                        {{ row.name }}
                    </p>
                    <p
                        v-if="row.description"
                        class="mt-1 truncate text-sm text-surface-500 dark:text-surface-400"
                    >
                        {{ row.description }}
                    </p>
                </Link>
            </template>

            <template #status="{ row }">
                <span class="badge" :class="row.status_color">
                    {{ row.status_label }}
                </span>
            </template>

            <template #priority="{ row }">
                <span class="badge" :class="row.priority_color">
                    {{ row.priority_label }}
                </span>
            </template>

            <template #owner="{ row }">
                <div v-if="row.owner" class="flex items-center gap-2">
                    <Avatar
                        :initials="row.owner.initials"
                        :src="row.owner.avatar"
                        size="sm"
                    />
                    <span
                        class="truncate text-sm text-surface-900 dark:text-surface-100"
                    >
                        {{ row.owner.name }}
                    </span>
                </div>
                <span
                    v-else
                    class="text-sm text-surface-500 dark:text-surface-400"
                    >—</span
                >
            </template>

            <template #dates="{ row }">
                <div class="space-y-1 text-sm">
                    <div
                        v-if="row.start_date"
                        class="flex items-center gap-1 text-surface-600 dark:text-surface-400"
                    >
                        <component
                            :is="getIcon('Calendar')"
                            class="h-3.5 w-3.5"
                        />
                        <span
                            >Mulai:
                            {{
                                new Date(row.start_date).toLocaleDateString(
                                    'id-ID',
                                    {
                                        day: '2-digit',
                                        month: 'short',
                                        year: 'numeric',
                                    },
                                )
                            }}</span
                        >
                    </div>
                    <div
                        v-if="row.end_date"
                        class="flex items-center gap-1 text-surface-600 dark:text-surface-400"
                    >
                        <component
                            :is="getIcon('Calendar')"
                            class="h-3.5 w-3.5"
                        />
                        <span
                            >Deadline:
                            {{
                                new Date(row.end_date).toLocaleDateString(
                                    'id-ID',
                                    {
                                        day: '2-digit',
                                        month: 'short',
                                        year: 'numeric',
                                    },
                                )
                            }}</span
                        >
                    </div>
                    <span
                        v-if="!row.start_date && !row.end_date"
                        class="text-surface-400 dark:text-surface-500"
                        >Belum dijadwalkan</span
                    >
                </div>
            </template>

            <template #members_count="{ row }">
                <span
                    class="text-sm font-medium text-surface-900 dark:text-surface-100"
                >
                    {{ row.members_count ?? 0 }}
                </span>
            </template>

            <template #actions="{ row }">
                <div class="flex items-center gap-2">
                    <Link
                        :href="projects.show.url({ project: row.id })"
                        class="btn-ghost btn-icon text-surface-500 hover:bg-surface-100 dark:text-surface-400 dark:hover:bg-surface-800"
                        title="Lihat Detail"
                    >
                        <component :is="getIcon('Eye')" class="h-4 w-4" />
                    </Link>

                    <Link
                        v-if="
                            row.can_update &&
                            page.props.auth.user?.permissions.includes(
                                'projects.update',
                            )
                        "
                        :href="projects.edit.url({ project: row.id })"
                        class="btn-ghost btn-icon text-surface-500 hover:bg-surface-100 dark:text-surface-400 dark:hover:bg-surface-800"
                        title="Edit"
                    >
                        <component :is="getIcon('Pencil')" class="h-4 w-4" />
                    </Link>

                    <Button
                        v-if="
                            row.can_delete &&
                            page.props.auth.user?.permissions.includes(
                                'projects.delete',
                            )
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
        title="Hapus Proyek"
        @close="deleteModalOpen = false"
    >
        <p class="text-surface-600 dark:text-surface-300">
            Apakah Anda yakin ingin menghapus proyek
            <strong class="text-surface-900 dark:text-surface-100">{{
                projectToDelete?.name
            }}</strong>
            ?
        </p>
        <p class="mt-2 text-sm text-surface-500 dark:text-surface-400">
            Tindakan ini tidak dapat dibatalkan. Semua data anggota proyek juga
            akan dihapus.
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
