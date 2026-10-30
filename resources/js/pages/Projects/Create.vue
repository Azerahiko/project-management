<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { Link } from '@inertiajs/vue3';
import projects from '@/routes/projects';
import api from '@/lib/api';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import Select from '@/components/ui/Select.vue';
import { useToast } from '@/composables/useToast';
import { useProjects } from '@/composables/useProjects';
import type { ProjectFilters } from '@/types';

defineOptions({
    layout: AppLayout,
});

const { success, error: toastError } = useToast();
const { fetchManagers } = useProjects({} as ProjectFilters);

const statusOptions = [
    { value: 'draft', label: 'Draft' },
    { value: 'active', label: 'Aktif' },
    { value: 'on_hold', label: 'Ditahan' },
    { value: 'completed', label: 'Selesai' },
    { value: 'archived', label: 'Arsip' },
];

const priorityOptions = [
    { value: 'low', label: 'Rendah' },
    { value: 'medium', label: 'Sedang' },
    { value: 'high', label: 'Tinggi' },
    { value: 'critical', label: 'Kritis' },
];

const form = reactive({
    name: '',
    description: '',
    status: 'draft' as string,
    priority: 'medium' as string,
    start_date: '',
    end_date: '',
    owner_id: '' as string,
});

const errors = ref<Record<string, string[]>>({});
const processing = ref(false);
const managers = ref<
    Array<{
        id: number;
        name: string;
        email: string;
        avatar: string | null;
        initials: string;
    }>
>([]);
const loadingManagers = ref(false);

async function loadManagers(): Promise<void> {
    loadingManagers.value = true;
    try {
        managers.value = await fetchManagers();
    } catch {
        toastError('Gagal memuat daftar manager', 'Silakan coba lagi.');
    } finally {
        loadingManagers.value = false;
    }
}

onMounted(loadManagers);

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
            name: form.name,
            description: form.description || null,
            status: form.status,
            priority: form.priority,
            start_date: form.start_date || null,
            end_date: form.end_date || null,
            owner_id: form.owner_id || undefined,
        };

        await api.post('/projects', payload);
        success('Proyek dibuat', 'Proyek baru telah ditambahkan.');
        router.visit(projects.index.url());
    } catch (err: any) {
        if (err.response?.data?.errors) {
            setErrors(err.response.data.errors);
        }
    } finally {
        processing.value = false;
    }
}

function goBack(): void {
    router.visit(projects.index.url());
}
</script>

<template>
    <div class="mx-auto max-w-2xl space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1
                    class="text-2xl font-semibold tracking-tight text-surface-950 dark:text-surface-50"
                >
                    Tambah Proyek Baru
                </h1>
                <p
                    class="mt-1 text-sm leading-6 text-surface-700 dark:text-surface-200"
                >
                    Isi formulir di bawah untuk membuat proyek baru
                </p>
            </div>
            <Link :href="projects.index.url()">
                <Button variant="ghost">Kembali</Button>
            </Link>
        </div>

        <form @submit.prevent="handleSubmit" class="space-y-6">
            <div class="card space-y-6">
                <h2
                    class="text-lg font-semibold text-surface-900 dark:text-surface-100"
                >
                    Informasi Proyek
                </h2>

                <Input
                    v-model="form.name"
                    label="Nama Proyek *"
                    placeholder="Masukkan nama proyek"
                    required
                    :error="errors.name?.[0]"
                    autocomplete="off"
                    @input="clearError('name')"
                />

                <Input
                    v-model="form.description"
                    label="Deskripsi"
                    placeholder="Masukkan deskripsi proyek (opsional)"
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
                        v-model="form.end_date"
                        type="date"
                        label="Deadline"
                        placeholder="Pilih deadline"
                        :error="errors.end_date?.[0]"
                        @input="clearError('end_date')"
                    />
                </div>

                <Select
                    v-model="form.owner_id"
                    :options="[
                        { value: '', label: 'Pilih Project Manager' },
                        ...managers.map((m) => ({
                            value: String(m.id),
                            label: `${m.name} (${m.email})`,
                        })),
                    ]"
                    label="Project Manager"
                    :loading="loadingManagers"
                    :error="errors.owner_id?.[0]"
                    @change="clearError('owner_id')"
                >
                    <template #loading>
                        <option value="" disabled>Memuat manager...</option>
                    </template>
                </Select>
            </div>

            <div
                v-if="errors.form"
                class="rounded-lg bg-accent-50 p-4 text-accent-700 dark:bg-accent-900/30 dark:text-accent-300"
            >
                {{ errors.form[0] }}
            </div>

            <div class="flex justify-end gap-3">
                <Link :href="projects.index.url()">
                    <Button type="button" variant="secondary">Batal</Button>
                </Link>
                <Button type="submit" variant="primary" :loading="processing">
                    Simpan Proyek
                </Button>
            </div>
        </form>
    </div>
</template>
