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
import { getIcon } from '@/components/sidebar/icons';
import type { ProjectFilters } from '@/types';

defineOptions({
    layout: AppLayout,
});

const { success, error: toastError, warning } = useToast();
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

// Client Assets
const selectedFiles = ref<File[]>([]);
const fileErrors = ref<Record<number, string>>({});
const uploadingFiles = ref(false);
const uploadResults = ref<
    Record<number, { success: boolean; message: string }>
>({});
const MAX_FILE_SIZE = 25 * 1024 * 1024;
const ALLOWED_EXTENSIONS = [
    'jpg',
    'jpeg',
    'png',
    'gif',
    'webp',
    'pdf',
    'doc',
    'docx',
    'xls',
    'xlsx',
    'ppt',
    'pptx',
    'txt',
    'csv',
    'zip',
];

function formatFileSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function validateFile(file: File, index: number): boolean {
    if (file.size > MAX_FILE_SIZE) {
        fileErrors.value[index] = 'Ukuran berkas melebihi batas 25 MB.';
        return false;
    }
    const extension = file.name.split('.').pop()?.toLowerCase() ?? '';
    if (!ALLOWED_EXTENSIONS.includes(extension)) {
        fileErrors.value[index] = `Format .${extension} tidak didukung.`;
        return false;
    }
    delete fileErrors.value[index];
    return true;
}

function handleFileChange(event: Event): void {
    const input = event.target as HTMLInputElement;
    const newFiles = Array.from(input.files ?? []);

    newFiles.forEach((file, i) => {
        const index = selectedFiles.value.length + i;
        validateFile(file, index);
    });

    selectedFiles.value.push(...newFiles);
    input.value = '';
}

function removeFile(index: number): void {
    selectedFiles.value.splice(index, 1);
    delete fileErrors.value[index];
    // Re-validate remaining files to update their indices
    selectedFiles.value.forEach((file, i) => validateFile(file, i));
}

async function uploadFiles(projectId: number): Promise<void> {
    if (selectedFiles.value.length === 0) return;

    uploadingFiles.value = true;
    uploadResults.value = {};

    const validFiles = selectedFiles.value.filter((file, index) =>
        validateFile(file, index),
    );

    for (const file of validFiles) {
        const formData = new FormData();
        formData.append('file', file);

        try {
            const { data } = await api.post(
                `/projects/${projectId}/files`,
                formData,
            );
            uploadResults.value[file.lastModified] = {
                success: true,
                message: `${data.data.original_name} berhasil diunggah.`,
            };
        } catch (err: any) {
            uploadResults.value[file.lastModified] = {
                success: false,
                message:
                    err.response?.data?.errors?.file?.[0] ??
                    err.response?.data?.message ??
                    'Berkas gagal diunggah.',
            };
        }
    }

    uploadingFiles.value = false;
}

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

        const { data } = await api.post('/projects', payload);
        const projectId = data.data.id;

        success('Proyek dibuat', 'Proyek baru telah ditambahkan.');

        // Upload Client Assets after project is created
        if (selectedFiles.value.length > 0) {
            await uploadFiles(projectId);

            const failedUploads = Object.values(uploadResults.value).filter(
                (r) => !r.success,
            );
            if (failedUploads.length > 0) {
                if (failedUploads.length === selectedFiles.value.length) {
                    toastError(
                        'Proyek dibuat, tapi semua berkas gagal diunggah',
                        failedUploads.map((f) => f.message).join(', '),
                    );
                } else {
                    warning(
                        'Proyek dibuat, tapi beberapa berkas gagal diunggah',
                        failedUploads.map((f) => f.message).join(', '),
                    );
                }
            }
        }

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

            <!-- Client Assets Section -->
            <div class="card space-y-6">
                <h2
                    class="text-lg font-semibold text-surface-900 dark:text-surface-100"
                >
                    Berkas Proyek (Opsional)
                </h2>
                <p class="text-sm text-surface-600 dark:text-surface-400">
                    Berkas akan diunggah setelah proyek berhasil dibuat.
                    Maksimal 25 MB per berkas. Format: gambar (JPG, PNG, GIF,
                    WEBP), PDF, dokumen Office (DOC, DOCX, XLS, XLSX, PPT,
                    PPTX), teks (TXT, CSV), dan ZIP.
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
                            selectedFiles.length === 0
                                ? 'Pilih berkas untuk diunggah'
                                : `${selectedFiles.length} berkas dipilih`
                        }}
                    </span>
                    <input
                        type="file"
                        class="hidden"
                        accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.zip"
                        multiple
                        @change="handleFileChange"
                    />
                </label>

                <div
                    v-if="selectedFiles.length > 0"
                    class="max-h-64 space-y-3 overflow-y-auto"
                >
                    <div
                        v-for="(file, index) in selectedFiles"
                        :key="index"
                        class="flex items-center justify-between gap-3 rounded-lg border border-surface-200 bg-surface-50/50 p-3 dark:border-surface-700 dark:bg-surface-900/50"
                    >
                        <div class="flex min-w-0 flex-1 items-center gap-3">
                            <component
                                :is="getIcon('FileText')"
                                class="h-5 w-5 shrink-0 text-surface-400"
                            />
                            <div class="min-w-0">
                                <p
                                    class="truncate text-sm font-medium text-surface-900 dark:text-surface-100"
                                >
                                    {{ file.name }}
                                </p>
                                <p
                                    class="text-xs text-surface-500 dark:text-surface-400"
                                >
                                    {{ formatFileSize(file.size) }}
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                v-if="fileErrors[index]"
                                class="text-xs text-accent-700 dark:text-accent-300"
                            >
                                {{ fileErrors[index] }}
                            </span>
                            <button
                                type="button"
                                class="btn-ghost btn-icon text-accent-700 hover:bg-accent-50 dark:hover:bg-accent-900/20"
                                @click="removeFile(index)"
                                title="Hapus"
                            >
                                <component
                                    :is="getIcon('Trash2')"
                                    class="h-4 w-4"
                                />
                            </button>
                        </div>
                    </div>
                </div>
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
