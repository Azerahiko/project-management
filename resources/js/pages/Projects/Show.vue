<script setup lang="ts">
import { ref, reactive, onMounted, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { usePage, Link } from '@inertiajs/vue3';
import projects from '@/routes/projects';
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
import type { Project, ProjectMember, ProjectFilters } from '@/types';

defineOptions({
    layout: AppLayout,
});

const page = usePage();
const { success, error: toastError, warning } = useToast();
const { fetchAvailableUsers, addMember, updateMember, removeMember } =
    useProjects({} as ProjectFilters);

const project = page.props.project as Project;
const projectId = project.id;

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

onMounted(loadAvailableUsers);

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
                    class="text-2xl font-bold text-surface-900 dark:text-surface-100"
                >
                    {{ project.name }}
                </h1>
                <p class="mt-1 text-surface-600 dark:text-surface-400">
                    {{ project.description || 'Tidak ada deskripsi' }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <span class="badge" :class="project.status_color">
                    {{ project.status_label }}
                </span>
                <span class="badge" :class="project.priority_color">
                    {{ project.priority_label }}
                </span>
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
                        class="dropdown-item text-red-600 dark:text-red-400"
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
                <p
                    class="mt-2 font-medium text-surface-900 dark:text-surface-100"
                >
                    {{ formatDate(project.end_date) }}
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
                    class="font-medium text-red-600 text-surface-900 dark:text-surface-100"
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
                            class="badge"
                            :class="[
                                row.role === 'owner'
                                    ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400'
                                    : row.role === 'manager'
                                      ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
                                      : row.role === 'member'
                                        ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
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
                        class="btn-ghost btn-icon text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20"
                        @click="confirmRemoveMember(row)"
                        title="Hapus Anggota"
                    >
                        <component :is="getIcon('UserMinus')" class="h-4 w-4" />
                    </button>
                    <span
                        v-else-if="row.is_owner"
                        class="text-xs text-surface-400 dark:text-surface-500"
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
</template>
