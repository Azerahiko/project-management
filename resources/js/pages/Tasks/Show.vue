<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { usePage, Link } from '@inertiajs/vue3';
import tasks from '@/routes/tasks';
import api from '@/lib/api';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTasks } from '@/composables/useTasks';
import { useToast } from '@/composables/useToast';
import { getIcon } from '@/components/sidebar/icons';
import Button from '@/components/ui/Button.vue';
import Dropdown from '@/components/ui/Dropdown.vue';
import Avatar from '@/components/ui/Avatar.vue';
import Modal from '@/components/ui/Modal.vue';
import type { Task, TaskFilters } from '@/types';

defineOptions({
    layout: AppLayout,
});

const page = usePage();
const { success, error: toastError, warning } = useToast();
const { fetchAssignees } = useTasks({} as TaskFilters);

const task = page.props.task as Task;
const taskId = task.id;

const editStatus = ref(false);
const statusOptions = [
    { value: 'todo', label: 'To Do' },
    { value: 'in_progress', label: 'Sedang Dikerjakan' },
    { value: 'review', label: 'Review' },
    { value: 'done', label: 'Selesai' },
];

const deleteModalOpen = ref(false);
const deleteLoading = ref(false);
const assigneeModalOpen = ref(false);
const assigneeOptions = ref<
    Array<{
        id: number;
        name: string;
        email: string;
        avatar: string | null;
        initials: string;
    }>
>([]);
const loadingAssignees = ref(false);
const newAssigneeId = ref('');
const assigningMember = ref(false);

async function loadAssignees(): Promise<void> {
    loadingAssignees.value = true;
    try {
        assigneeOptions.value = await fetchAssignees(task.project_id);
    } catch {
        toastError('Gagal memuat daftar anggota', 'Silakan coba lagi.');
    } finally {
        loadingAssignees.value = false;
    }
}

onMounted(() => {
    if (task.can_update) {
        loadAssignees();
    }
});

async function updateStatus(newStatus: string): Promise<void> {
    if (newStatus === task.status) return;

    editStatus.value = false;

    try {
        await api.put(`/tasks/${taskId}`, {
            status: newStatus,
        });
        success('Status diperbarui', `Status tugas telah diubah.`);
        router.reload();
    } catch (err: any) {
        if (err.response?.data?.message) {
            toastError('Gagal memperbarui status', err.response.data.message);
        }
    }
}

function openAssigneeModal(): void {
    newAssigneeId.value = task.assignee_id ? String(task.assignee_id) : '';
    assigneeModalOpen.value = true;
}

async function executeAssign(): Promise<void> {
    assigningMember.value = true;

    try {
        await api.put(`/tasks/${taskId}`, {
            assignee_id: newAssigneeId.value
                ? Number(newAssigneeId.value)
                : null,
        });
        success('Penugasan diperbarui', 'Penugasan tugas telah diubah.');
        assigneeModalOpen.value = false;
        router.reload();
    } catch (err: any) {
        if (err.response?.data?.message) {
            toastError(
                'Gagal memperbarui penugasan',
                err.response.data.message,
            );
        }
    } finally {
        assigningMember.value = false;
    }
}

function confirmDeleteTask(): void {
    deleteModalOpen.value = true;
}

async function executeDeleteTask(): Promise<void> {
    deleteLoading.value = true;

    try {
        await api.delete(`/tasks/${taskId}`);
        success('Tugas dihapus', 'Tugas telah berhasil dihapus.');
        router.visit(tasks.index.url());
    } catch {
        // Error handled by api interceptor
    } finally {
        deleteLoading.value = false;
    }
}

function formatDate(dateString: string | null): string {
    if (!dateString) return '—';
    return new Date(dateString).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'long',
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
        <!-- Header -->
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
        >
            <div class="flex-1">
                <Link :href="tasks.index.url()" class="btn-ghost btn-sm mb-2">
                    <component
                        :is="getIcon('ArrowLeft')"
                        class="mr-1 h-4 w-4"
                    />
                    Kembali ke Daftar
                </Link>
                <h1
                    class="text-2xl font-bold text-surface-900 dark:text-surface-100"
                >
                    {{ task.title }}
                </h1>
                <p class="mt-1 text-surface-600 dark:text-surface-400">
                    {{ task.description || 'Tidak ada deskripsi' }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <span class="badge" :class="task.status_color">
                    {{ task.status_label }}
                </span>
                <span class="badge" :class="task.priority_color">
                    {{ task.priority_label }}
                </span>
            </div>

            <Dropdown
                v-if="task.can_update || task.can_delete"
                triggerClass="btn-ghost"
            >
                <template #trigger>
                    <Button variant="ghost" size="sm">
                        <component
                            :is="getIcon('MoreHorizontal')"
                            class="h-4 w-4"
                        />
                    </Button>
                </template>

                <template #content>
                    <div v-if="task.can_update" class="dropdown-item">
                        <component
                            :is="getIcon('Pencil')"
                            class="mr-2 h-4 w-4"
                        />
                        <Link
                            :href="tasks.edit.url({ task: taskId })"
                            class="flex-1"
                        >
                            Edit Tugas
                        </Link>
                    </div>

                    <div
                        v-if="task.can_update && task.assignee_id === null"
                        class="dropdown-item"
                        @click="openAssigneeModal"
                    >
                        <component
                            :is="getIcon('UserPlus')"
                            class="mr-2 h-4 w-4"
                        />
                        Tugaskan Anggota
                    </div>

                    <div
                        v-if="task.can_update && task.assignee_id !== null"
                        class="dropdown-item"
                        @click="openAssigneeModal"
                    >
                        <component
                            :is="getIcon('UserCheck')"
                            class="mr-2 h-4 w-4"
                        />
                        Ubah Penugasan
                    </div>

                    <button
                        v-if="task.can_delete"
                        class="dropdown-item text-red-600 dark:text-red-400"
                        @click="confirmDeleteTask"
                    >
                        <component
                            :is="getIcon('Trash2')"
                            class="mr-2 h-4 w-4"
                        />
                        Hapus Tugas
                    </button>
                </template>
            </Dropdown>
        </div>

        <!-- Info Grid -->
        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
            <div class="card">
                <h3
                    class="text-sm font-medium text-surface-500 dark:text-surface-400"
                >
                    Proyek
                </h3>
                <div v-if="task.project" class="mt-2">
                    <Link
                        :href="
                            page.props.auth.user?.permissions.includes(
                                'projects.view',
                            )
                                ? '/projects/' + task.project.id
                                : '#'
                        "
                        class="font-medium text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300"
                    >
                        {{ task.project.name }}
                    </Link>
                </div>
                <p v-else class="mt-2 text-surface-500 dark:text-surface-400">
                    —
                </p>
            </div>

            <div class="card">
                <h3
                    class="text-sm font-medium text-surface-500 dark:text-surface-400"
                >
                    Penugasan
                </h3>
                <div v-if="task.assignee" class="mt-2 flex items-center gap-3">
                    <Avatar
                        :initials="task.assignee.initials"
                        :src="task.assignee.avatar"
                        size="md"
                    />
                    <div>
                        <p
                            class="font-medium text-surface-900 dark:text-surface-100"
                        >
                            {{ task.assignee.name }}
                        </p>
                        <p
                            class="text-sm text-surface-500 dark:text-surface-400"
                        >
                            {{ task.assignee.email }}
                        </p>
                    </div>
                </div>
                <p v-else class="mt-2 text-surface-500 dark:text-surface-400">
                    Belum ditugaskan
                </p>
            </div>

            <div class="card">
                <h3
                    class="text-sm font-medium text-surface-500 dark:text-surface-400"
                >
                    Tanggal Mulai
                </h3>
                <p
                    class="mt-2 font-medium text-surface-900 dark:text-surface-100"
                >
                    {{ formatDate(task.start_date) }}
                </p>
            </div>

            <div class="card">
                <h3
                    class="text-sm font-medium text-surface-500 dark:text-surface-400"
                >
                    Deadline
                </h3>
                <p
                    :class="[
                        'mt-2 font-medium',
                        task.is_overdue
                            ? 'text-red-600 dark:text-red-400'
                            : 'text-surface-900 dark:text-surface-100',
                    ]"
                >
                    {{ formatDate(task.due_date) }}
                    <span
                        v-if="task.is_overdue"
                        class="ml-2 text-xs text-red-600 dark:text-red-400"
                        >(Lewat tenggat)</span
                    >
                </p>
            </div>
        </div>

        <!-- Detail -->
        <div class="card">
            <h3
                class="mb-4 text-lg font-semibold text-surface-900 dark:text-surface-100"
            >
                Detail
            </h3>
            <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                <dt class="text-surface-500 dark:text-surface-400">
                    Dibuat oleh
                </dt>
                <dd
                    v-if="task.creator"
                    class="flex items-center gap-2 font-medium text-surface-900 dark:text-surface-100"
                >
                    <Avatar
                        :initials="task.creator.initials"
                        :src="task.creator.avatar"
                        size="sm"
                    />
                    {{ task.creator.name }}
                </dd>
                <dd
                    v-else
                    class="font-medium text-surface-900 dark:text-surface-100"
                >
                    —
                </dd>

                <dt class="text-surface-500 dark:text-surface-400">Dibuat</dt>
                <dd class="font-medium text-surface-900 dark:text-surface-100">
                    {{ formatDate(task.created_at) }}
                </dd>

                <dt class="text-surface-500 dark:text-surface-400">
                    Diperbarui
                </dt>
                <dd class="font-medium text-surface-900 dark:text-surface-100">
                    {{ formatDate(task.updated_at) }}
                </dd>

                <dt
                    v-if="task.completed_at"
                    class="text-surface-500 dark:text-surface-400"
                >
                    Diselesaikan
                </dt>
                <dd
                    v-if="task.completed_at"
                    class="font-medium text-green-600 text-surface-900 dark:text-surface-100"
                >
                    {{ formatDate(task.completed_at) }}
                </dd>

                <dt
                    v-if="task.deleted_at"
                    class="text-surface-500 dark:text-surface-400"
                >
                    Dihapus
                </dt>
                <dd
                    v-if="task.deleted_at"
                    class="font-medium text-red-600 text-surface-900 dark:text-surface-100"
                >
                    {{ formatDate(task.deleted_at) }}
                </dd>
            </dl>
        </div>

        <!-- Quick Actions -->
        <div v-if="task.can_update" class="card">
            <h3
                class="mb-4 text-lg font-semibold text-surface-900 dark:text-surface-100"
            >
                Aksi Cepat
            </h3>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="status in statusOptions"
                    :key="status.value"
                    :class="[
                        'btn-sm',
                        task.status === status.value
                            ? 'btn-primary'
                            : 'btn-secondary',
                    ]"
                    @click="updateStatus(status.value)"
                >
                    {{ status.label }}
                </button>
            </div>
        </div>
    </div>

    <!-- Assignee Modal -->
    <Modal
        :open="assigneeModalOpen"
        title="Tugaskan Anggota"
        @close="assigneeModalOpen = false"
    >
        <div class="space-y-4">
            <Select
                v-model="newAssigneeId"
                :options="[
                    { value: '', label: 'Hapus Penugasan' },
                    ...assigneeOptions.map((a) => ({
                        value: String(a.id),
                        label: `${a.name} (${a.email})`,
                    })),
                ]"
                label="Anggota"
                :loading="loadingAssignees"
            >
                <template #loading>
                    <option value="" disabled>Memuat anggota...</option>
                </template>
            </Select>
        </div>

        <template #footer>
            <Button variant="secondary" @click="assigneeModalOpen = false">
                Batal
            </Button>
            <Button
                variant="primary"
                @click="executeAssign"
                :loading="assigningMember"
            >
                Simpan
            </Button>
        </template>
    </Modal>

    <!-- Delete Task Modal -->
    <Modal
        :open="deleteModalOpen"
        title="Hapus Tugas"
        @close="deleteModalOpen = false"
    >
        <p class="text-surface-600 dark:text-surface-300">
            Apakah Anda yakin ingin menghapus tugas
            <strong class="text-surface-900 dark:text-surface-100">{{
                task.title
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
                @click="executeDeleteTask"
                :loading="deleteLoading"
            >
                Hapus Tugas
            </Button>
        </template>
    </Modal>
</template>
