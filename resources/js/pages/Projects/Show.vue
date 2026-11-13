<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { usePage, Link } from '@inertiajs/vue3';
import projects from '@/routes/projects';
import tasks from '@/routes/tasks';
import api from '@/lib/api';
import AppLayout from '@/layouts/AppLayout.vue';
import { useProjects } from '@/composables/useProjects';
import { useToast } from '@/composables/useToast';
import { getIcon } from '@/components/sidebar/icons';
import Button from '@/components/ui/Button.vue';
import Dropdown from '@/components/ui/Dropdown.vue';
import Avatar from '@/components/ui/Avatar.vue';
import Modal from '@/components/ui/Modal.vue';
import Table from '@/components/ui/Table.vue';
import Input from '@/components/ui/Input.vue';
import Select from '@/components/ui/Select.vue';
import DeadlineBadge from '@/components/tasks/DeadlineBadge.vue';
import type {
    Project,
    ProjectMember,
    ProjectFile,
    ProjectFilters,
} from '@/types';

defineOptions({
    layout: AppLayout,
});

const page = usePage();
const { success, error: toastError, warning } = useToast();
const { fetchAvailableUsers, addMember, updateMember, removeMember } =
    useProjects({} as ProjectFilters);

const project = page.props.project as Project;

const canViewTrashedTasks = computed(() => {
    const userPerms = (page.props.auth?.user as any)?.permissions || [];
    return userPerms.includes('tasks.view');
});
const projectId = project.id;

const projectStatusForBadge = computed(() => {
    if (project.status === 'completed') return 'done';
    if (project.status === 'archived') return 'done';
    return 'todo';
});

const memberRoleOptions = [
    { value: 'manager', label: 'Manajer' },
    { value: 'member', label: 'Anggota' },
    { value: 'viewer', label: 'Peninjau' },
];

const memberColumns = [
    { key: 'user', label: 'Anggota', class: 'max-w-xs' },
    { key: 'role', label: 'Peran', class: 'max-w-xs' },
    { key: 'joined_at', label: 'Bergabung', class: 'max-w-xs' },
    { key: 'actions', label: 'Aksi' },
];

const availableUsers = ref<
    Array<{
        id: number;
        name: string;
        email: string;
        avatar: string | null;
        initials: string;
    }>
>([]);
const loadingAvailableUsers = ref(false);

const addMemberModalOpen = ref(false);
const newMemberForm = reactive({
    user_id: '' as string,
    role: 'member' as string,
});
const newMemberErrors = ref<Record<string, string[]>>({});
const newMemberErrorBag = computed(() => newMemberErrors.value);
const addingMember = ref(false);

const deleteModalOpen = ref(false);
const deleteLoading = ref(false);

const editMemberRole = ref<{ member: ProjectMember; role: string } | null>(
    null,
);
const savingMemberRole = ref(false);

const fileColumns = [
    { key: 'name', label: 'Berkas', class: 'max-w-xs' },
    { key: 'size', label: 'Ukuran', class: 'max-w-xs' },
    { key: 'uploader', label: 'Pengunggah', class: 'max-w-xs' },
    { key: 'created_at', label: 'Diunggah', class: 'max-w-xs' },
    { key: 'actions', label: 'Aksi' },
];

const files = ref<ProjectFile[]>([]);
const filesLoading = ref(true);
const filesError = ref(false);

const uploadModalOpen = ref(false);
const selectedFile = ref<File | null>(null);
const uploadError = ref<string | null>(null);
const uploading = ref(false);

const fileToDelete = ref<ProjectFile | null>(null);
const deletingFile = ref(false);
const downloadingFileId = ref<number | null>(null);

async function loadFiles(): Promise<void> {
    filesLoading.value = true;
    filesError.value = false;

    try {
        const { data } = await api.get(`/projects/${projectId}/files`, {
            params: { per_page: 100 },
        });
        files.value = data.data;
    } catch {
        filesError.value = true;
    } finally {
        filesLoading.value = false;
    }
}

function openUploadModal(): void {
    selectedFile.value = null;
    uploadError.value = null;
    uploadModalOpen.value = true;
}

function handleFileChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    selectedFile.value = input.files?.[0] ?? null;
    uploadError.value = null;
}

async function submitUpload(): Promise<void> {
    if (!selectedFile.value || uploading.value) return;

    if (selectedFile.value.size > 25 * 1024 * 1024) {
        uploadError.value = 'Ukuran berkas melebihi batas 25 MB.';
        return;
    }

    uploading.value = true;
    uploadError.value = null;

    const formData = new FormData();
    formData.append('file', selectedFile.value);

    try {
        const { data } = await api.post(
            `/projects/${projectId}/files`,
            formData,
        );
        files.value.unshift(data.data);
        uploadModalOpen.value = false;
        success(
            'Berkas diunggah',
            `${data.data.original_name} berhasil diunggah.`,
        );
    } catch (err: any) {
        uploadError.value =
            err.response?.data?.errors?.file?.[0] ??
            err.response?.data?.message ??
            'Berkas gagal diunggah.';
    } finally {
        uploading.value = false;
    }
}

function filenameFromDisposition(header?: string): string | null {
    if (!header) return null;

    const encoded = /filename\*=utf-8''([^;]+)/i.exec(header);
    if (encoded?.[1]) {
        try {
            return decodeURIComponent(encoded[1]);
        } catch {
            return null;
        }
    }

    return /filename="?([^";]+)"?/i.exec(header)?.[1] ?? null;
}

async function downloadFile(file: ProjectFile): Promise<void> {
    if (downloadingFileId.value !== null) return;

    downloadingFileId.value = file.id;

    try {
        const response = await api.get(
            `/projects/${projectId}/files/${file.id}/download`,
            { responseType: 'blob' },
        );

        const url = URL.createObjectURL(response.data);
        const link = document.createElement('a');
        link.href = url;
        link.download =
            filenameFromDisposition(
                response.headers['content-disposition'] as string | undefined,
            ) ?? file.original_name;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
    } catch {
        toastError('Gagal mengunduh berkas', 'Silakan coba lagi.');
    } finally {
        downloadingFileId.value = null;
    }
}

function confirmDeleteFile(file: ProjectFile): void {
    fileToDelete.value = file;
}

async function executeDeleteFile(): Promise<void> {
    if (!fileToDelete.value || deletingFile.value) return;

    const file = fileToDelete.value;
    deletingFile.value = true;

    try {
        await api.delete(`/projects/${projectId}/files/${file.id}`);
        files.value = files.value.filter((item) => item.id !== file.id);
        fileToDelete.value = null;
        success(
            'Berkas dihapus',
            `${file.original_name} dihapus dari daftar proyek.`,
        );
    } catch {
        toastError('Gagal menghapus berkas', 'Silakan coba lagi.');
    } finally {
        deletingFile.value = false;
    }
}

function formatFileSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function fileExtension(name: string): string {
    const extension = name.split('.').pop();
    return extension && extension !== name ? extension.toUpperCase() : 'FILE';
}

onMounted(loadFiles);

async function loadAvailableUsers(): Promise<void> {
    loadingAvailableUsers.value = true;
    try {
        availableUsers.value = await fetchAvailableUsers(projectId);
    } catch {
        toastError('Gagal memuat daftar pengguna', 'Silakan coba lagi.');
    } finally {
        loadingAvailableUsers.value = false;
    }
}

function openAddMemberModal(): void {
    newMemberForm.user_id = '';
    newMemberForm.role = 'member';
    newMemberErrors.value = {};
    addMemberModalOpen.value = true;
    void loadAvailableUsers();
}

async function executeAddMember(): Promise<void> {
    if (!newMemberForm.user_id) return;

    addingMember.value = true;
    newMemberErrors.value = {};

    try {
        await addMember(
            projectId,
            Number(newMemberForm.user_id),
            newMemberForm.role,
        );
        success('Anggota ditambahkan', 'Pengguna telah ditambahkan ke proyek.');
        addMemberModalOpen.value = false;
        router.reload();
    } catch (err: any) {
        if (err.response?.data?.errors) {
            newMemberErrors.value = err.response.data.errors;
        } else if (err.response?.data?.message) {
            toastError('Gagal menambah anggota', err.response.data.message);
        }
    } finally {
        addingMember.value = false;
    }
}

function startEditMemberRole(member: ProjectMember): void {
    // Don't allow editing owner
    if (member.is_owner) return;
    editMemberRole.value = { member, role: member.role };
}

async function saveMemberRole(): Promise<void> {
    if (!editMemberRole.value || savingMemberRole.value) return;

    const { member, role } = editMemberRole.value;

    savingMemberRole.value = true;

    try {
        await updateMember(projectId, member.user_id, role);
        success('Peran diperbarui', `Peran ${member.user?.name} telah diubah.`);
        editMemberRole.value = null;
        router.reload();
    } catch (err: any) {
        if (err.response?.data?.message) {
            toastError('Gagal memperbarui peran', err.response.data.message);
        }
    } finally {
        savingMemberRole.value = false;
    }
}

function cancelEditMemberRole(): void {
    editMemberRole.value = null;
}

async function confirmRemoveMember(member: ProjectMember): Promise<void> {
    if (member.is_owner) {
        warning(
            'Tidak dapat dihapus',
            'Pemilik proyek tidak dapat dihapus dari anggota.',
        );
        return;
    }

    try {
        await removeMember(projectId, member.user_id);
        success(
            'Anggota dihapus',
            `${member.user?.name} telah dihapus dari proyek.`,
        );
        router.reload();
    } catch (err: any) {
        if (err.response?.data?.message) {
            toastError('Gagal menghapus anggota', err.response.data.message);
        }
    }
}

function confirmDeleteProject(): void {
    deleteModalOpen.value = true;
}

async function executeDeleteProject(): Promise<void> {
    deleteLoading.value = true;

    try {
        await api.delete(`/projects/${projectId}`);
        success('Proyek dihapus', 'Proyek telah berhasil dihapus.');
        router.visit(projects.index.url());
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
                <Link
                    :href="projects.index.url()"
                    class="btn-ghost btn-sm mb-2"
                >
                    <component
                        :is="getIcon('ArrowLeft')"
                        class="mr-1 h-4 w-4"
                    />
                    Kembali ke Daftar
                </Link>
                <h1
                    class="text-2xl font-semibold tracking-tight text-surface-950 dark:text-surface-50"
                >
                    {{ project.name }}
                </h1>
                <p
                    class="mt-1 text-sm leading-6 text-surface-700 dark:text-surface-200"
                >
                    {{ project.description || 'Tidak ada deskripsi' }}
                </p>
            </div>

            <!-- Clean Calm System status & priority indicators (NO pill badges) -->
            <div
                class="flex flex-wrap items-center gap-2.5 text-xs font-medium text-surface-600 dark:text-surface-300"
            >
                <div
                    class="inline-flex items-center gap-1.5 rounded-lg border border-surface-200/80 bg-surface-50 px-2.5 py-1 dark:border-surface-700 dark:bg-surface-800/60"
                >
                    <span
                        class="h-1.5 w-1.5 rounded-full bg-primary-700 dark:bg-primary-300"
                    />
                    <span>{{ project.status_label }}</span>
                </div>
                <div
                    class="inline-flex items-center gap-1.5 rounded-lg border border-surface-200/80 bg-surface-50 px-2.5 py-1 text-surface-500 dark:border-surface-700 dark:bg-surface-800/60 dark:text-surface-400"
                >
                    <span>Prioritas: {{ project.priority_label }}</span>
                </div>
            </div>

            <Dropdown
                v-if="
                    project.can_update ||
                    project.can_delete ||
                    project.can_manage_members
                "
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
                    <Link
                        v-if="project.can_update"
                        :href="projects.edit.url({ project: projectId })"
                        class="dropdown-item"
                    >
                        <component
                            :is="getIcon('Pencil')"
                            class="mr-2 h-4 w-4"
                        />
                        Edit Proyek
                    </Link>

                    <Link
                        v-if="canViewTrashedTasks"
                        :href="tasks.trashed.url({ project: projectId })"
                        class="dropdown-item"
                    >
                        <component
                            :is="getIcon('Archive')"
                            class="mr-2 h-4 w-4"
                        />
                        Tugas yang Dihapus
                    </Link>

                    <button
                        v-if="project.can_manage_members"
                        class="dropdown-item"
                        @click="openAddMemberModal"
                    >
                        <component
                            :is="getIcon('UserPlus')"
                            class="mr-2 h-4 w-4"
                        />
                        Tambah Anggota
                    </button>

                    <button
                        v-if="project.can_delete"
                        class="dropdown-item text-accent-700 dark:text-accent-300"
                        @click="confirmDeleteProject"
                    >
                        <component
                            :is="getIcon('Trash2')"
                            class="mr-2 h-4 w-4"
                        />
                        Hapus Proyek
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
                    Project Manager
                </h3>
                <div v-if="project.owner" class="mt-2 flex items-center gap-3">
                    <Avatar
                        :initials="project.owner.initials"
                        :src="project.owner.avatar"
                        size="md"
                    />
                    <div>
                        <p
                            class="font-medium text-surface-900 dark:text-surface-100"
                        >
                            {{ project.owner.name }}
                        </p>
                        <p
                            class="text-sm text-surface-500 dark:text-surface-400"
                        >
                            {{ project.owner.email }}
                        </p>
                    </div>
                </div>
                <p v-else class="mt-2 text-surface-500 dark:text-surface-400">
                    —
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
                    {{ formatDate(project.start_date) }}
                </p>
            </div>

            <div class="card">
                <h3
                    class="text-sm font-medium text-surface-500 dark:text-surface-400"
                >
                    Deadline
                </h3>
                <p class="mt-2">
                    <DeadlineBadge
                        :due-date="project.end_date"
                        :status="projectStatusForBadge"
                        :is-overdue="
                            Boolean(
                                project.end_date &&
                                new Date(project.end_date) < new Date() &&
                                project.status !== 'completed',
                            )
                        "
                        size="md"
                    />
                </p>
            </div>

            <div class="card">
                <h3
                    class="text-sm font-medium text-surface-500 dark:text-surface-400"
                >
                    Total Anggota
                </h3>
                <p
                    class="mt-2 font-medium text-surface-900 dark:text-surface-100"
                >
                    {{ project.members_count ?? project.members?.length ?? 0 }}
                </p>
            </div>
        </div>

        <!-- Dates Detail -->
        <div class="card">
            <h3
                class="mb-4 text-lg font-semibold text-surface-900 dark:text-surface-100"
            >
                Detail Tanggal
            </h3>
            <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                <dt class="text-surface-500 dark:text-surface-400">Dibuat</dt>
                <dd class="font-medium text-surface-900 dark:text-surface-100">
                    {{ formatDate(project.created_at) }}
                </dd>
                <dt class="text-surface-500 dark:text-surface-400">
                    Diperbarui
                </dt>
                <dd class="font-medium text-surface-900 dark:text-surface-100">
                    {{ formatDate(project.updated_at) }}
                </dd>
                <dt
                    v-if="project.deleted_at"
                    class="text-surface-500 dark:text-surface-400"
                >
                    Dihapus
                </dt>
                <dd
                    v-if="project.deleted_at"
                    class="font-medium text-accent-700 text-surface-900 dark:text-surface-100"
                >
                    {{ formatDate(project.deleted_at) }}
                </dd>
            </dl>
        </div>

        <!-- Members Section -->
        <div class="card">
            <div
                class="mb-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <h3
                    class="text-lg font-semibold text-surface-900 dark:text-surface-100"
                >
                    Anggota Proyek
                </h3>
                <Button
                    v-if="project.can_manage_members"
                    size="sm"
                    @click="openAddMemberModal"
                >
                    <component :is="getIcon('UserPlus')" class="mr-1 h-4 w-4" />
                    Tambah Anggota
                </Button>
            </div>

            <Table
                v-if="project.members && project.members.length > 0"
                :columns="memberColumns"
                :data="project.members"
                row-key="id"
            >
                <template #user="{ row }">
                    <div class="flex items-center gap-3">
                        <Avatar
                            :initials="row.user?.initials ?? ''"
                            :src="row.user?.avatar"
                            size="md"
                        />
                        <div class="min-w-0">
                            <p
                                class="truncate font-medium text-surface-900 dark:text-surface-100"
                            >
                                {{ row.user?.name }}
                            </p>
                            <p
                                class="truncate text-sm text-surface-500 dark:text-surface-400"
                            >
                                {{ row.user?.email }}
                            </p>
                        </div>
                    </div>
                </template>

                <template #role="{ row }">
                    <div
                        v-if="
                            editMemberRole &&
                            editMemberRole.member.id === row.id
                        "
                    >
                        <Select
                            v-model="editMemberRole.role"
                            :options="memberRoleOptions"
                            class="w-40"
                            @change="saveMemberRole"
                        />
                    </div>
                    <div v-else class="flex items-center gap-2">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-xs font-medium"
                            :class="[
                                row.role === 'owner'
                                    ? 'bg-primary-50 text-primary-700 dark:bg-primary-950/30 dark:text-primary-300'
                                    : row.role === 'manager'
                                      ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400'
                                      : row.role === 'member'
                                        ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400'
                                        : 'bg-surface-100 text-surface-700 dark:bg-surface-800 dark:text-surface-300',
                            ]"
                        >
                            {{ row.role_label }}
                        </span>
                        <button
                            v-if="project.can_manage_members && !row.is_owner"
                            class="btn-ghost btn-icon text-surface-500 hover:bg-surface-100 dark:text-surface-400 dark:hover:bg-surface-800"
                            @click="startEditMemberRole(row)"
                            title="Ubah Peran"
                        >
                            <component
                                :is="getIcon('Pencil')"
                                class="h-3.5 w-3.5"
                            />
                        </button>
                    </div>
                </template>

                <template #joined_at="{ row }">
                    <p class="text-sm text-surface-500 dark:text-surface-400">
                        {{ formatDateShort(row.joined_at) }}
                    </p>
                </template>

                <template #actions="{ row }">
                    <button
                        v-if="project.can_manage_members && !row.is_owner"
                        class="btn-ghost btn-icon text-accent-700 hover:bg-accent-50 dark:hover:bg-accent-900/20"
                        @click="confirmRemoveMember(row)"
                        title="Hapus Anggota"
                    >
                        <component :is="getIcon('UserMinus')" class="h-4 w-4" />
                    </button>
                    <span
                        v-else-if="row.is_owner"
                        class="text-xs text-surface-600 dark:text-surface-300"
                    >
                        Pemilik
                    </span>
                </template>
            </Table>

            <div
                v-else
                class="py-8 text-center text-surface-500 dark:text-surface-400"
            >
                <component
                    :is="getIcon('Users')"
                    class="mx-auto mb-3 h-12 w-12 text-surface-200 dark:text-surface-700"
                />
                <p>Belum ada anggota proyek</p>
                <Button
                    v-if="project.can_manage_members"
                    size="sm"
                    class="mt-3"
                    @click="openAddMemberModal"
                >
                    <component :is="getIcon('UserPlus')" class="mr-1 h-4 w-4" />
                    Tambah Anggota Pertama
                </Button>
            </div>
        </div>

        <!-- Project Files Section -->
        <div class="card">
            <div
                class="mb-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-center gap-2">
                    <h3
                        class="text-lg font-semibold text-surface-900 dark:text-surface-100"
                    >
                        Berkas Proyek
                    </h3>
                    <span
                        v-if="!filesLoading && !filesError"
                        class="rounded-md bg-surface-100 px-2 py-0.5 text-xs font-medium text-surface-700 dark:bg-surface-800 dark:text-surface-300"
                    >
                        {{ files.length }}
                    </span>
                </div>
                <Button
                    v-if="project.can_update"
                    size="sm"
                    @click="openUploadModal"
                >
                    <component :is="getIcon('Plus')" class="mr-1 h-4 w-4" />
                    Unggah Berkas
                </Button>
            </div>

            <div
                v-if="filesLoading"
                class="py-8 text-center text-sm text-surface-500 dark:text-surface-400"
            >
                Memuat berkas...
            </div>

            <div
                v-else-if="filesError"
                class="py-8 text-center text-surface-500 dark:text-surface-400"
            >
                <p>Gagal memuat berkas.</p>
                <Button
                    size="sm"
                    variant="secondary"
                    class="mt-3"
                    @click="loadFiles"
                >
                    Coba Lagi
                </Button>
            </div>

            <div
                v-else-if="files.length === 0"
                class="py-8 text-center text-surface-500 dark:text-surface-400"
            >
                <component
                    :is="getIcon('FileText')"
                    class="mx-auto mb-3 h-12 w-12 text-surface-200 dark:text-surface-700"
                />
                <p>Belum ada berkas pada proyek ini</p>
                <Button
                    v-if="project.can_update"
                    size="sm"
                    class="mt-3"
                    @click="openUploadModal"
                >
                    <component :is="getIcon('Plus')" class="mr-1 h-4 w-4" />
                    Unggah Berkas Pertama
                </Button>
            </div>

            <Table v-else :columns="fileColumns" :data="files" row-key="id">
                <template #name="{ row }">
                    <div class="flex items-center gap-3">
                        <component
                            :is="getIcon('FileText')"
                            class="h-4 w-4 shrink-0 text-surface-400"
                        />
                        <div class="min-w-0">
                            <p
                                class="truncate font-medium text-surface-900 dark:text-surface-100"
                            >
                                {{ row.original_name }}
                            </p>
                            <p
                                class="text-xs text-surface-500 dark:text-surface-400"
                            >
                                {{ fileExtension(row.original_name) }} ·
                                {{ row.mime_type }}
                            </p>
                        </div>
                    </div>
                </template>

                <template #size="{ row }">
                    <p class="text-sm text-surface-500 dark:text-surface-400">
                        {{ formatFileSize(row.size) }}
                    </p>
                </template>

                <template #uploader="{ row }">
                    <p class="text-sm text-surface-500 dark:text-surface-400">
                        {{ row.uploader?.name ?? '—' }}
                    </p>
                </template>

                <template #created_at="{ row }">
                    <p class="text-sm text-surface-500 dark:text-surface-400">
                        {{ formatDateShort(row.created_at) }}
                    </p>
                </template>

                <template #actions="{ row }">
                    <div class="flex items-center gap-1">
                        <button
                            class="btn-ghost btn-icon text-surface-500 hover:bg-surface-100 dark:text-surface-400 dark:hover:bg-surface-800"
                            title="Unduh"
                            :disabled="downloadingFileId !== null"
                            @click="downloadFile(row)"
                        >
                            <component
                                :is="getIcon('Download')"
                                class="h-4 w-4"
                            />
                        </button>
                        <button
                            v-if="project.can_update"
                            class="btn-ghost btn-icon text-accent-700 hover:bg-accent-50 dark:hover:bg-accent-900/20"
                            title="Hapus"
                            @click="confirmDeleteFile(row)"
                        >
                            <component
                                :is="getIcon('Trash2')"
                                class="h-4 w-4"
                            />
                        </button>
                    </div>
                </template>
            </Table>
        </div>
    </div>

    <!-- Add Member Modal -->
    <Modal
        :open="addMemberModalOpen"
        title="Tambah Anggota Proyek"
        @close="addMemberModalOpen = false"
    >
        <div class="space-y-4">
            <Select
                v-model="newMemberForm.user_id"
                :options="[
                    { value: '', label: 'Pilih Pengguna' },
                    ...availableUsers.map((user) => ({
                        value: String(user.id),
                        label: `${user.name} (${user.email})`,
                    })),
                ]"
                label="Pengguna *"
                required
                :error="newMemberErrorBag.user_id?.[0]"
                :loading="loadingAvailableUsers"
                @change="
                    () => {
                        if (newMemberErrorBag.user_id)
                            delete newMemberErrorBag.user_id;
                    }
                "
            >
                <template #loading>
                    <option value="" disabled>Memuat pengguna...</option>
                </template>
            </Select>

            <Select
                v-model="newMemberForm.role"
                :options="memberRoleOptions"
                label="Peran *"
                required
                :error="newMemberErrorBag.role?.[0]"
                @change="
                    () => {
                        if (newMemberErrorBag.role)
                            delete newMemberErrorBag.role;
                    }
                "
            />
        </div>

        <template #footer>
            <Button variant="secondary" @click="addMemberModalOpen = false">
                Batal
            </Button>
            <Button
                variant="primary"
                @click="executeAddMember"
                :loading="addingMember"
            >
                Tambah Anggota
            </Button>
        </template>
    </Modal>

    <!-- Delete Project Modal -->
    <Modal
        :open="deleteModalOpen"
        title="Hapus Proyek"
        @close="deleteModalOpen = false"
    >
        <p class="text-surface-600 dark:text-surface-300">
            Apakah Anda yakin ingin menghapus proyek
            <strong class="text-surface-900 dark:text-surface-100">{{
                project.name
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
                @click="executeDeleteProject"
                :loading="deleteLoading"
            >
                Hapus Proyek
            </Button>
        </template>
    </Modal>

    <!-- Upload File Modal -->
    <Modal
        :open="uploadModalOpen"
        title="Unggah Berkas"
        @close="uploadModalOpen = false"
    >
        <div class="space-y-4">
            <p class="text-sm text-surface-600 dark:text-surface-300">
                Format yang diterima: gambar (JPG, PNG, GIF, WEBP), PDF, dokumen
                Office (DOC, DOCX, XLS, XLSX, PPT, PPTX), teks (TXT, CSV), dan
                ZIP. Maksimal 25 MB per berkas.
            </p>

            <label
                class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-surface-300 bg-surface-50/40 px-4 py-6 text-sm font-medium text-surface-600 transition hover:border-primary-500 hover:bg-surface-50 dark:border-surface-700 dark:bg-surface-950/20 dark:text-surface-400"
            >
                <component
                    :is="getIcon('Plus')"
                    class="h-5 w-5 text-surface-400"
                />
                <span class="text-center">
                    {{
                        selectedFile
                            ? selectedFile.name
                            : 'Pilih berkas untuk diunggah'
                    }}
                </span>
                <input
                    type="file"
                    class="hidden"
                    accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip"
                    @change="handleFileChange"
                />
            </label>

            <p
                v-if="selectedFile"
                class="text-xs text-surface-500 dark:text-surface-400"
            >
                Ukuran: {{ formatFileSize(selectedFile.size) }}
            </p>

            <p
                v-if="uploadError"
                class="text-sm text-accent-700 dark:text-accent-300"
            >
                {{ uploadError }}
            </p>
        </div>

        <template #footer>
            <Button variant="secondary" @click="uploadModalOpen = false">
                Batal
            </Button>
            <Button
                variant="primary"
                :loading="uploading"
                :disabled="!selectedFile"
                @click="submitUpload"
            >
                Unggah
            </Button>
        </template>
    </Modal>

    <!-- Delete File Modal -->
    <Modal
        :open="fileToDelete !== null"
        title="Hapus Berkas"
        @close="fileToDelete = null"
    >
        <p class="text-surface-600 dark:text-surface-300">
            Hapus berkas
            <strong class="text-surface-900 dark:text-surface-100">{{
                fileToDelete?.original_name
            }}</strong>
            dari daftar proyek?
        </p>
        <p class="mt-2 text-sm text-surface-500 dark:text-surface-400">
            Berkas akan dihapus dari daftar proyek ini dan tidak lagi
            ditampilkan.
        </p>

        <template #footer>
            <Button variant="secondary" @click="fileToDelete = null">
                Batal
            </Button>
            <Button
                variant="destructive"
                :loading="deletingFile"
                @click="executeDeleteFile"
            >
                Hapus Berkas
            </Button>
        </template>
    </Modal>
</template>
