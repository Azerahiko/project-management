<script setup lang="ts">
import { ref, reactive, onMounted, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { Link, usePage } from '@inertiajs/vue3';
import tasks from '@/routes/tasks';
import api from '@/lib/api';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTasks } from '@/composables/useTasks';
import { useToast } from '@/composables/useToast';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Select from '@/components/ui/Select.vue';
import type { Task, TaskFilters } from '@/types';

defineOptions({
    layout: AppLayout,
});

const page = usePage();
const { success, error: toastError } = useToast();
const { fetchAssignees } = useTasks({} as TaskFilters);

const task = page.props.task as Task;
const taskId = task.id;

const statusOptions = [
    { value: 'todo', label: 'To Do' },
    { value: 'in_progress', label: 'Sedang Dikerjakan' },
    { value: 'review', label: 'Review' },
    { value: 'done', label: 'Selesai' },
];

const priorityOptions = [
    { value: 'low', label: 'Rendah' },
    { value: 'medium', label: 'Sedang' },
    { value: 'high', label: 'Tinggi' },
    { value: 'urgent', label: 'Mendesak' },
];

const form = reactive({
    project_id: String(task.project_id),
    title: task.title,
    description: task.description || '',
    status: task.status,
    priority: task.priority,
    assignee_id: task.assignee_id ? String(task.assignee_id) : '',
    start_date: task.start_date ? task.start_date.split('T')[0] : '',
    due_date: task.due_date ? task.due_date.split('T')[0] : '',
});

const errors = ref<Record<string, string[]>>({});
const processing = ref(false);
const projects = ref<
    Array<{
        id: number;
        name: string;
    }>
>([]);
const assignees = ref<
    Array<{
        id: number;
        name: string;
        email: string;
        avatar: string | null;
        initials: string;
    }>
>([]);
const loadingProjects = ref(false);
const loadingAssignees = ref(false);

async function loadProjects(): Promise<void> {
    loadingProjects.value = true;
    try {
        const { data: response } = await api.get<{
            data: Array<{
                id: number;
                name: string;
            }>;
        }>('/projects', {
            params: {
                per_page: 100,
                sort_by: 'name',
                sort_direction: 'asc',
            },
        });
        projects.value = response.data;
    } catch {
        toastError('Gagal memuat daftar proyek', 'Silakan coba lagi.');
    } finally {
        loadingProjects.value = false;
    }
}

async function loadAssigneesForProject(projectId: string): Promise<void> {
    if (!projectId) {
        assignees.value = [];
        return;
    }

    loadingAssignees.value = true;
    try {
        assignees.value = await fetchAssignees(Number(projectId));
    } catch {
        toastError('Gagal memuat daftar anggota', 'Silakan coba lagi.');
    } finally {
        loadingAssignees.value = false;
    }
}

function setErrors(err: Record<string, string[]>): void {
    errors.value = err;
}

function clearError(field: string): void {
    if (errors.value[field]) {
        delete errors.value[field];
    }
}

async function handleSubmit(): Promise<void> {
    processing.value = true;
    errors.value = {};

    try {
        const payload = {
            title: form.title,
            description: form.description || null,
            status: form.status,
            priority: form.priority,
            assignee_id: form.assignee_id ? Number(form.assignee_id) : null,
            start_date: form.start_date || null,
            due_date: form.due_date || null,
        };

        await api.put(`/tasks/${taskId}`, payload);
        success('Tugas diperbarui', 'Tugas telah berhasil diperbarui.');
        router.visit(tasks.show.url({ task: taskId }));
    } catch (err: any) {
        if (err.response?.data?.errors) {
            setErrors(err.response.data.errors);
        }
    } finally {
        processing.value = false;
    }
}

function goBack(): void {
    router.visit(tasks.show.url({ task: taskId }));
}

onMounted(() => {
    loadProjects();
    loadAssigneesForProject(form.project_id);
});

watch(
    () => form.project_id,
    (newProjectId) => {
        loadAssigneesForProject(newProjectId);
    },
);
</script>

<template>
    <div class="mx-auto max-w-2xl space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1
                    class="text-2xl font-semibold tracking-tight text-surface-950 dark:text-surface-50"
                >
                    Edit Tugas
                </h1>
                <p
                    class="mt-1 text-sm leading-6 text-surface-700 dark:text-surface-200"
                >
                    Perbarui informasi tugas di bawah ini
                </p>
            </div>
            <Link :href="tasks.show.url({ task: taskId })">
                <Button variant="ghost">Kembali</Button>
            </Link>
        </div>

        <form @submit.prevent="handleSubmit" class="space-y-6">
            <div class="card space-y-6">
                <h2
                    class="text-lg font-semibold text-surface-900 dark:text-surface-100"
                >
                    Informasi Tugas
                </h2>

                <Select
                    v-model="form.project_id"
                    :options="[
                        { value: '', label: 'Pilih Proyek *' },
                        ...projects.map((p) => ({
                            value: String(p.id),
                            label: p.name,
                        })),
                    ]"
                    label="Proyek *"
                    required
                    :error="errors.project_id?.[0]"
                    :loading="loadingProjects"
                    @change="clearError('project_id')"
                >
                    <template #loading>
                        <option value="" disabled>Memuat proyek...</option>
                    </template>
                </Select>

                <Input
                    v-model="form.title"
                    label="Judul Tugas *"
                    placeholder="Masukkan judul tugas"
                    required
                    :error="errors.title?.[0]"
                    autocomplete="off"
                    @input="clearError('title')"
                />

                <Input
                    v-model="form.description"
                    label="Deskripsi"
                    placeholder="Masukkan deskripsi tugas (opsional)"
                    type="textarea"
                    rows="4"
                    :error="errors.description?.[0]"
                    @input="clearError('description')"
                />

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <Select
                        v-model="form.status"
                        :options="statusOptions"
                        label="Status *"
                        required
                        :error="errors.status?.[0]"
                        @change="clearError('status')"
                    />

                    <Select
                        v-model="form.priority"
                        :options="priorityOptions"
                        label="Prioritas *"
                        required
                        :error="errors.priority?.[0]"
                        @change="clearError('priority')"
                    />
                </div>

                <Select
                    v-model="form.assignee_id"
                    :options="[
                        { value: '', label: 'Pilih Penugasan' },
                        ...assignees.map((a) => ({
                            value: String(a.id),
                            label: `${a.name} (${a.email})`,
                        })),
                    ]"
                    label="Penugasan"
                    :loading="loadingAssignees"
                    :error="errors.assignee_id?.[0]"
                    @change="clearError('assignee_id')"
                >
                    <template #loading>
                        <option value="" disabled>Memuat anggota...</option>
                    </template>
                </Select>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                    <Input
                        v-model="form.start_date"
                        type="date"
                        label="Tanggal Mulai"
                        placeholder="Pilih tanggal mulai"
                        :error="errors.start_date?.[0]"
                        @input="clearError('start_date')"
                    />

                    <Input
                        v-model="form.due_date"
                        type="date"
                        label="Deadline"
                        placeholder="Pilih deadline"
                        :error="errors.due_date?.[0]"
                        @input="clearError('due_date')"
                    />
                </div>
            </div>

            <div
                v-if="errors.form"
                class="rounded-lg bg-accent-50 p-4 text-accent-700 dark:bg-accent-900/30 dark:text-accent-300"
            >
                {{ errors.form[0] }}
            </div>

            <div class="flex justify-end gap-3">
                <Link :href="tasks.show.url({ task: taskId })">
                    <Button type="button" variant="secondary">Batal</Button>
                </Link>
                <Button type="submit" variant="primary" :loading="processing">
                    Simpan Perubahan
                </Button>
            </div>
        </form>
    </div>
</template>
