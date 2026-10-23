<script setup lang="ts">
import { ref, reactive, watch, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { usePage, Link } from '@inertiajs/vue3';
import tasks from '@/routes/tasks';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTasks } from '@/composables/useTasks';
import { getIcon } from '@/components/sidebar/icons';
import { useToast } from '@/composables/useToast';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Select from '@/components/ui/Select.vue';
import Table from '@/components/ui/Table.vue';
import Pagination from '@/components/ui/Pagination.vue';
import Modal from '@/components/ui/Modal.vue';
import Avatar from '@/components/ui/Avatar.vue';
import type { Task, TaskFilters } from '@/types';

defineOptions({
    layout: AppLayout,
});

const page = usePage();
const { success, error: toastError } = useToast();

// Filter state
const search = ref('');
let searchTimeout: ReturnType<typeof setTimeout>;
const filters = reactive<TaskFilters>({
    search: '',
    status: '',
    priority: '',
    assignee_id: '',
    project_id: '',
    sort_by: 'created_at',
    sort_direction: 'desc',
    page: 1,
    per_page: 15,
});

const {
    tasks: taskList,
    meta,
    loading,
    error,
    fetchTasks,
    deleteTask,
    fetchAssignees,
} = useTasks(filters);

const deleteModalOpen = ref(false);
const taskToDelete = ref<Task | null>(null);

const statusOptions = [
    { value: '', label: 'Semua Status' },
    { value: 'todo', label: 'To Do' },
    { value: 'in_progress', label: 'Sedang Dikerjakan' },
    { value: 'review', label: 'Review' },
    { value: 'done', label: 'Selesai' },
];

const priorityOptions = [
    { value: '', label: 'Semua Prioritas' },
    { value: 'low', label: 'Rendah' },
    { value: 'medium', label: 'Sedang' },
    { value: 'high', label: 'Tinggi' },
    { value: 'urgent', label: 'Mendesak' },
];

const sortOptions = [
    { value: 'title', label: 'Judul' },
    { value: 'status', label: 'Status' },
    { value: 'priority', label: 'Prioritas' },
    { value: 'start_date', label: 'Tanggal Mulai' },
    { value: 'due_date', label: 'Deadline' },
    { value: 'created_at', label: 'Dibuat' },
    { value: 'updated_at', label: 'Diperbarui' },
];

const columns = [
    { key: 'title', label: 'Judul', class: 'max-w-xs' },
    { key: 'project', label: 'Proyek', class: 'max-w-xs' },
    { key: 'status', label: 'Status' },
    { key: 'priority', label: 'Prioritas' },
    { key: 'assignee', label: 'Penugasan', class: 'max-w-xs' },
    { key: 'dates', label: 'Tanggal', class: 'max-w-md' },
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

function confirmDelete(task: Task): void {
    taskToDelete.value = task;
    deleteModalOpen.value = true;
}

async function executeDelete(): Promise<void> {
    if (!taskToDelete.value) return;

    try {
        await deleteTask(taskToDelete.value.id);
        success('Tugas dihapus', 'Tugas telah berhasil dihapus.');
        deleteModalOpen.value = false;
        taskToDelete.value = null;
        await fetchTasks();
    } catch {
        // Error handled by api interceptor
    }
}

onMounted(() => {
    fetchTasks();
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
        filters.assignee_id,
        filters.project_id,
        filters.sort_by,
        filters.sort_direction,
        filters.page,
    ],
    () => {
        fetchTasks();
    },
    { deep: true },
);

function formatDate(dateString: string | null): string {
    if (!dateString) return '—';
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function formatDateShort(dateString: string | null): string {
    if (!dateString) return '—';
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}
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
                    Tugas
                </h1>
                <p class="mt-1 text-surface-600 dark:text-surface-400">
                    Kelola tugas dan tenggat tim Anda
                </p>
            </div>

            <Link
                v-if="page.props.auth.user?.permissions.includes('tasks.view')"
                :href="tasks.create.url()"
            >
                <Button>
                    <component :is="getIcon('Plus')" class="h-4 w-4" />
                    Tambah Tugas
                </Button>
            </Link>
        </div>

        <!-- Filters -->
        <div class="card">
            <div class="flex flex-col gap-4 sm:flex-row sm:flex-wrap">
                <Input
                    v-model="search"
                    placeholder="Cari judul atau deskripsi..."
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
            :data="taskList"
            :loading="loading"
            :sort-by="filters.sort_by"
            :sort-direction="filters.sort_direction"
            :on-sort="handleSort"
            row-key="id"
        >
            <template #title="{ row }">
                <Link :href="tasks.show.url({ task: row.id })" class="block">
                    <p
                        class="truncate font-medium text-surface-900 hover:text-primary-600 dark:text-surface-100 dark:hover:text-primary-400"
                    >
                        {{ row.title }}
                    </p>
                    <p
                        v-if="row.description"
                        class="mt-1 truncate text-sm text-surface-500 dark:text-surface-400"
                    >
                        {{ row.description }}
                    </p>
                    <p
                        v-if="row.is_overdue"
                        class="mt-1 text-xs text-red-600 dark:text-red-400"
                    >
                        <component
                            :is="getIcon('AlertTriangle')"
                            class="mr-1 inline h-3 w-3"
                        />
                        Lewat tenggat
                    </p>
                </Link>
            </template>

            <template #project="{ row }">
                <Link
                    v-if="row.project"
                    :href="
                        page.props.auth.user?.permissions.includes(
                            'projects.view',
                        )
                            ? '/projects/' + row.project.id
                            : '#'
                    "
                    class="text-sm font-medium text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300"
                >
                    {{ row.project.name }}
                </Link>
                <span
                    v-else
                    class="text-sm text-surface-500 dark:text-surface-400"
                    >—</span
                >
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

            <template #assignee="{ row }">
                <div v-if="row.assignee" class="flex items-center gap-2">
                    <Avatar
                        :initials="row.assignee.initials"
                        :src="row.assignee.avatar"
                        size="sm"
                    />
                    <span
                        class="truncate text-sm text-surface-900 dark:text-surface-100"
                    >
                        {{ row.assignee.name }}
                    </span>
                </div>
                <span
                    v-else
                    class="text-sm text-surface-500 dark:text-surface-400"
                    >Belum ditugaskan</span
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
                            >Mulai: {{ formatDateShort(row.start_date) }}</span
                        >
                    </div>
                    <div
                        v-if="row.due_date"
                        :class="[
                            'flex items-center gap-1',
                            row.is_overdue
                                ? 'text-red-600 dark:text-red-400'
                                : 'text-surface-600 dark:text-surface-400',
                        ]"
                    >
                        <component
                            :is="getIcon('Calendar')"
                            class="h-3.5 w-3.5"
                        />
                        <span
                            >Deadline: {{ formatDateShort(row.due_date) }}</span
                        >
                    </div>
                    <span
                        v-if="!row.start_date && !row.due_date"
                        class="text-surface-400 dark:text-surface-500"
                        >Belum dijadwalkan</span
                    >
                </div>
            </template>

            <template #actions="{ row }">
                <div class="flex items-center gap-2">
                    <Link
                        :href="tasks.show.url({ task: row.id })"
                        class="btn-ghost btn-icon text-surface-500 hover:bg-surface-100 dark:text-surface-400 dark:hover:bg-surface-800"
                        title="Lihat Detail"
                    >
                        <component :is="getIcon('Eye')" class="h-4 w-4" />
                    </Link>

                    <Link
                        v-if="
                            row.can_update &&
                            page.props.auth.user?.permissions.includes(
                                'tasks.view',
                            )
                        "
                        :href="tasks.edit.url({ task: row.id })"
                        class="btn-ghost btn-icon text-surface-500 hover:bg-surface-100 dark:text-surface-400 dark:hover:bg-surface-800"
                        title="Edit"
                    >
                        <component :is="getIcon('Pencil')" class="h-4 w-4" />
                    </Link>

                    <Button
                        v-if="
                            row.can_delete &&
                            page.props.auth.user?.permissions.includes(
                                'tasks.view',
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
        title="Hapus Tugas"
        @close="deleteModalOpen = false"
    >
        <p class="text-surface-600 dark:text-surface-300">
            Apakah Anda yakin ingin menghapus tugas
            <strong class="text-surface-900 dark:text-surface-100">{{
                taskToDelete?.title
            }}</strong>
            ?
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
