<script setup lang="ts">
import {
    computed,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch,
} from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import tasksRoute from '@/routes/tasks';
import projectsRoute from '@/routes/projects';
import AppLayout from '@/layouts/AppLayout.vue';
import { useTrashedTasks } from '@/composables/useTrashedTasks';
import { getIcon } from '@/components/sidebar/icons';
import { useToast } from '@/composables/useToast';
import Button from '@/components/ui/Button.vue';
import Select from '@/components/ui/Select.vue';
import Pagination from '@/components/ui/Pagination.vue';
import Modal from '@/components/ui/Modal.vue';
import Avatar from '@/components/ui/Avatar.vue';
import type { Task, TaskFilters, Project } from '@/types';

defineOptions({
    layout: AppLayout,
});

const page = usePage();
const { success, error: toastError } = useToast();

const props = defineProps<{
    project: Project;
}>();

const search = ref('');
let searchTimeout: ReturnType<typeof setTimeout>;

const filters = reactive<TaskFilters>({
    search: '',
    status: '',
    priority: '',
    assignee_id: '',
    project_id: String(props.project.id),
    sort_by: 'deleted_at',
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
    restoreTask,
} = useTrashedTasks(props.project.id, filters);

const restoreModalOpen = ref(false);
const taskToRestore = ref<Task | null>(null);
const restoring = ref(false);

const canRestore = computed(() =>
    Boolean(page.props.auth.user?.permissions.includes('tasks.update')),
);

const sortValue = ref('deleted_at:desc');

const sortOptions = [
    { value: 'deleted_at:desc', label: 'Terbaru Dihapus' },
    { value: 'deleted_at:asc', label: 'Terlama Dihapus' },
    { value: 'due_date:asc', label: 'Deadline Terdekat' },
    { value: 'title:asc', label: 'Nama A–Z' },
];

const hasActiveFilters = computed(
    () =>
        search.value.trim() !== '' ||
        filters.status !== '' ||
        filters.priority !== '' ||
        sortValue.value !== 'deleted_at:desc',
);

const statusTabs = [
    { value: '', label: 'Semua' },
    { value: 'todo', label: 'Belum Dimulai', dot: 'bg-surface-400' },
    { value: 'in_progress', label: 'Sedang Dikerjakan', dot: 'bg-primary-500' },
    { value: 'review', label: 'Review', dot: 'bg-accent-500' },
    { value: 'done', label: 'Selesai', dot: 'bg-emerald-500' },
];

function formatRelativeTime(dateString: string | null): string {
    if (!dateString) return 'Baru saja';
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMins / 60);
    const diffDays = Math.floor(diffHours / 24);

    if (diffMins < 1) return 'Baru saja';
    if (diffMins < 60) return `${diffMins}m lalu`;
    if (diffHours < 24) return `${diffHours}j lalu`;
    if (diffDays === 1) return 'Kemarin';
    if (diffDays < 30) return `${diffDays} hari lalu`;
    return date.toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
}

function formatDate(value: string | null): string {
    if (!value) return '—';

    return new Date(value).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function setStatus(value: string): void {
    filters.status = value;
    filters.page = 1;
}

function setSort(value: string): void {
    const [sortBy, direction] = value.split(':');
    filters.sort_by = sortBy;
    filters.sort_direction = direction as 'asc' | 'desc';
    filters.page = 1;
}

function resetFilters(): void {
    clearTimeout(searchTimeout);
    search.value = '';
    filters.search = '';
    filters.status = '';
    filters.priority = '';
    sortValue.value = 'deleted_at:desc';
    filters.page = 1;
}

function handlePageChange(pageNumber: number): void {
    filters.page = pageNumber;
}

function confirmRestore(task: Task): void {
    taskToRestore.value = task;
    restoreModalOpen.value = true;
}

async function executeRestore(): Promise<void> {
    if (!taskToRestore.value || restoring.value) {
        return;
    }

    restoring.value = true;

    try {
        await restoreTask(taskToRestore.value.id);

        success('Tugas dipulihkan', 'Tugas telah berhasil dipulihkan.');
        restoreModalOpen.value = false;
        taskToRestore.value = null;
        await fetchTasks();
    } catch {
        toastError(
            'Gagal memulihkan tugas',
            'Tugas gagal dipulihkan. Silakan coba lagi.',
        );
    } finally {
        restoring.value = false;
    }
}

onMounted(() => {
    fetchTasks();
});

onBeforeUnmount(() => {
    clearTimeout(searchTimeout);
});

watch(search, (value) => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        filters.search = value.trim();
        filters.page = 1;
    }, 300);
});

watch(sortValue, (value) => {
    const [sortBy, direction] = value.split(':');

    filters.sort_by = sortBy;
    filters.sort_direction = direction as 'asc' | 'desc';
    filters.page = 1;
});

watch(
    () => [
        filters.search,
        filters.status,
        filters.priority,
        filters.assignee_id,
        filters.sort_by,
        filters.sort_direction,
        filters.page,
    ],
    () => {
        fetchTasks();
    },
    { deep: true },
);
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
        >
            <div>
                <span
                    class="inline-flex h-6 items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-2.5 text-[10px] font-bold tracking-wider text-amber-700 uppercase dark:border-amber-700/40 dark:bg-amber-900/25 dark:text-amber-200"
                >
                    <component :is="getIcon('Trash2')" class="h-3 w-3" />
                    Sampah Tugas
                </span>
                <h1
                    class="mt-2 text-2xl font-semibold tracking-tight text-surface-950 sm:text-3xl dark:text-surface-50"
                >
                    Tugas yang Dihapus
                </h1>
                <p
                    class="mt-1 max-w-2xl text-sm leading-6 text-surface-700 dark:text-surface-200"
                >
                    Kelola tugas yang dihapus dari proyek "{{ project.name }}".
                    Pulihkan atau biarkan mereka dihapus permanen.
                </p>
            </div>

            <div
                class="flex w-full flex-wrap items-center gap-2 sm:w-auto sm:justify-end"
            >
                <span
                    v-if="!loading"
                    class="hidden items-center gap-2 rounded-xl border border-surface-200 bg-white px-3 py-2 text-xs font-semibold text-surface-600 sm:inline-flex dark:border-surface-700 dark:bg-surface-900 dark:text-surface-300"
                >
                    <span class="h-1.5 w-1.5 rounded-full bg-amber-500" />
                    {{ meta.total }} tugas dihapus
                </span>
                <Link
                    :href="projectsRoute.show.url({ project: project.id })"
                    class="btn btn-secondary w-full sm:w-auto"
                >
                    <component :is="getIcon('ArrowLeft')" class="h-4 w-4" />
                    Kembali ke Proyek
                </Link>
            </div>
        </div>

        <!-- Filters -->
        <section
            class="rounded-2xl border border-surface-200 bg-white p-3 shadow-xs sm:p-4 dark:border-surface-700 dark:bg-surface-900"
        >
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center">
                <div class="relative min-w-0 flex-1">
                    <span
                        class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-surface-400 dark:text-surface-500"
                    >
                        <component :is="getIcon('Search')" class="h-4 w-4" />
                    </span>
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Cari tugas yang dihapus..."
                        class="input pl-10"
                    />
                </div>

                <div class="grid grid-cols-2 gap-2 lg:flex lg:items-center">
                    <Select
                        v-model="filters.priority"
                        :options="[
                            { value: '', label: 'Semua Prioritas' },
                            { value: 'low', label: 'Rendah' },
                            { value: 'medium', label: 'Sedang' },
                            { value: 'high', label: 'Tinggi' },
                            { value: 'critical', label: 'Kritis' },
                        ]"
                        class="w-full lg:w-44"
                    />
                    <Select
                        v-model="sortValue"
                        :options="sortOptions"
                        class="w-full lg:w-52"
                    />
                </div>

                <button
                    v-if="hasActiveFilters"
                    type="button"
                    class="self-start px-2 py-1 text-sm font-medium text-surface-500 transition-colors hover:text-surface-900 lg:self-auto dark:text-surface-400 dark:hover:text-surface-100"
                    @click="resetFilters"
                >
                    Reset
                </button>
            </div>

            <!-- Status tabs -->
            <div
                class="no-scrollbar mt-3 flex items-center gap-2 overflow-x-auto border-t border-surface-100 pt-3 pb-1 dark:border-surface-800/80"
            >
                <button
                    v-for="tab in statusTabs"
                    :key="tab.value"
                    type="button"
                    class="inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold transition-colors"
                    :class="
                        filters.status === tab.value
                            ? 'border-primary-600 bg-primary-600 text-white shadow-sm shadow-primary-600/25'
                            : 'border-surface-200 bg-white text-surface-600 hover:border-primary-300 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-surface-300 dark:hover:border-primary-500/50 dark:hover:text-primary-200'
                    "
                    :aria-pressed="filters.status === tab.value"
                    @click="setStatus(tab.value)"
                >
                    <span
                        v-if="tab.dot"
                        class="h-1.5 w-1.5 rounded-full"
                        :class="
                            filters.status === tab.value ? 'bg-white' : tab.dot
                        "
                    />
                    {{ tab.label }}
                </button>
            </div>
        </section>

        <!-- Error -->
        <div
            v-if="error"
            class="flex flex-col items-start gap-3 rounded-2xl border border-accent-200 bg-accent-50 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-accent-700/40 dark:bg-accent-900/20"
            role="alert"
        >
            <div class="flex items-start gap-3">
                <span
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white/70 text-accent-700 dark:bg-surface-900/40 dark:text-accent-300"
                >
                    <component :is="getIcon('AlertTriangle')" class="h-4 w-4" />
                </span>
                <div>
                    <p
                        class="text-sm font-bold text-accent-800 dark:text-accent-200"
                    >
                        Gagal memuat daftar tugas
                    </p>
                    <p
                        class="mt-0.5 text-xs text-accent-700 dark:text-accent-300"
                    >
                        {{ error }}
                    </p>
                </div>
            </div>
            <Button variant="secondary" size="sm" @click="fetchTasks">
                <component :is="getIcon('RefreshCw')" class="h-3.5 w-3.5" />
                Coba lagi
            </Button>
        </div>

        <!-- Result summary -->
        <div
            v-if="!error && (taskList.length > 0 || loading)"
            class="flex items-center justify-between gap-2"
        >
            <p
                class="text-sm font-medium text-surface-600 dark:text-surface-300"
            >
                <template v-if="loading && taskList.length === 0">
                    Memuat daftar tugas...
                </template>
                <template v-else>
                    Menampilkan
                    <span
                        class="font-bold text-surface-900 dark:text-surface-50"
                    >
                        {{ taskList.length }}
                    </span>
                    dari
                    <span
                        class="font-bold text-surface-900 dark:text-surface-50"
                    >
                        {{ meta.total }}
                    </span>
                    tugas
                </template>
            </p>
            <span
                v-if="loading && taskList.length > 0"
                class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary-700 dark:text-primary-300"
            >
                <span
                    class="h-3 w-3 animate-spin rounded-full border-2 border-primary-600 border-t-transparent dark:border-primary-300 dark:border-t-transparent"
                />
                Memuat
            </span>
        </div>

        <!-- Skeleton -->
        <div
            v-if="loading && taskList.length === 0"
            class="grid grid-cols-1 gap-4 md:grid-cols-2"
            aria-hidden="true"
        >
            <div
                v-for="index in 6"
                :key="index"
                class="animate-pulse rounded-2xl border border-surface-200 bg-white p-5 dark:border-surface-700 dark:bg-surface-900"
            >
                <div
                    class="h-5 w-3/4 rounded bg-surface-100 dark:bg-surface-800"
                />
                <div
                    class="mt-2 h-3 w-full rounded bg-surface-100 dark:bg-surface-800"
                />
                <div
                    class="mt-1.5 h-3 w-4/5 rounded bg-surface-100 dark:bg-surface-800"
                />
                <div class="mt-4 flex gap-2">
                    <div
                        class="h-5 w-20 rounded bg-surface-100 dark:bg-surface-800"
                    />
                    <div
                        class="h-5 w-24 rounded bg-surface-100 dark:bg-surface-800"
                    />
                </div>
            </div>
        </div>

        <!-- Task list -->
        <div
            v-else-if="taskList.length > 0"
            class="divide-y divide-surface-200 dark:divide-surface-700"
            :class="loading ? 'opacity-60' : ''"
            :aria-busy="loading"
        >
            <article
                v-for="task in taskList"
                :key="task.id"
                class="py-4 transition-opacity"
            >
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <Link
                                :href="tasksRoute.show.url({ task: task.id })"
                                class="group/title"
                            >
                                <h3
                                    class="font-medium text-surface-900 group-hover/title:text-primary-600 dark:text-surface-50 dark:group-hover/title:text-primary-400"
                                >
                                    {{ task.title }}
                                </h3>
                            </Link>

                            <span
                                class="inline-flex h-5 items-center rounded-full bg-primary-100 px-2 py-0.5 text-[11px] font-semibold text-primary-700 dark:bg-primary-900/30 dark:text-primary-300"
                            >
                                {{ task.status_label }}
                            </span>

                            <span
                                class="inline-flex h-5 items-center rounded-full px-2 py-0.5 text-[11px] font-semibold"
                                :class="
                                    task.priority === 'low'
                                        ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
                                        : task.priority === 'medium'
                                          ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300'
                                          : task.priority === 'high'
                                            ? 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300'
                                            : 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-300'
                                "
                            >
                                {{ task.priority_label }}
                            </span>

                            <span
                                class="inline-flex h-5 items-center rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-300"
                            >
                                Terhapus
                            </span>
                        </div>

                        <p
                            v-if="task.description"
                            class="mt-1 line-clamp-2 text-sm text-surface-500 dark:text-surface-400"
                        >
                            {{ task.description }}
                        </p>

                        <div
                            class="mt-2 flex flex-wrap items-center gap-3 text-xs text-surface-500 dark:text-surface-400"
                        >
                            <div class="flex items-center gap-1">
                                <component
                                    :is="getIcon('Calendar')"
                                    class="h-3.5 w-3.5"
                                />
                                <span
                                    >Dihapus:
                                    {{ formatDate(task.deleted_at) }}</span
                                >
                            </div>

                            <div
                                v-if="task.due_date"
                                class="flex items-center gap-1"
                            >
                                <component
                                    :is="getIcon('Clock')"
                                    class="h-3.5 w-3.5"
                                />
                                <span
                                    :class="
                                        task.due_date &&
                                        new Date(task.due_date).setHours(
                                            0,
                                            0,
                                            0,
                                            0,
                                        ) < new Date().setHours(0, 0, 0, 0) &&
                                        task.status !== 'done'
                                            ? 'text-rose-600 dark:text-rose-400'
                                            : ''
                                    "
                                >
                                    Deadline: {{ formatDate(task.due_date) }}
                                </span>
                            </div>

                            <div
                                v-if="task.start_date"
                                class="flex items-center gap-1"
                            >
                                <component
                                    :is="getIcon('Play')"
                                    class="h-3.5 w-3.5"
                                />
                                <span
                                    >Mulai:
                                    {{ formatDate(task.start_date) }}</span
                                >
                            </div>

                            <div
                                v-if="task.assignee"
                                class="flex items-center gap-1"
                            >
                                <Avatar
                                    :initials="task.assignee.initials"
                                    :src="task.assignee.avatar"
                                    :alt="task.assignee.name"
                                    size="xs"
                                />
                                <span>{{ task.assignee.name }}</span>
                            </div>

                            <div class="flex items-center gap-1">
                                <component
                                    :is="getIcon('User')"
                                    class="h-3.5 w-3.5"
                                />
                                <span>{{ task.creator?.name }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Restore action -->
                    <div class="flex-shrink-0">
                        <button
                            v-if="canRestore"
                            type="button"
                            class="grid h-9 w-9 place-items-center rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-600 shadow-2xs transition hover:border-emerald-300 hover:bg-emerald-100 hover:text-emerald-700 active:scale-95 dark:border-emerald-700/40 dark:bg-emerald-900/20 dark:text-emerald-400 dark:hover:bg-emerald-900/30 dark:hover:text-emerald-300"
                            @click="confirmRestore(task)"
                            :aria-label="`Pulihkan tugas ${task.title}`"
                            title="Pulihkan tugas"
                        >
                            <component
                                :is="getIcon('RotateCcw')"
                                class="h-4 w-4"
                            />
                        </button>
                    </div>
                </div>
            </article>
        </div>

        <!-- Empty state -->
        <div
            v-else-if="!loading && !error"
            class="rounded-2xl border border-dashed border-surface-300 bg-white px-6 py-16 text-center dark:border-surface-700 dark:bg-surface-900"
        >
            <div
                class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-200"
            >
                <component :is="getIcon('Trash2')" class="h-6 w-6" />
            </div>
            <h2
                class="mt-4 text-lg font-bold text-surface-950 dark:text-surface-50"
            >
                {{
                    hasActiveFilters
                        ? 'Tugas tidak ditemukan'
                        : 'Tidak ada tugas yang dihapus'
                }}
            </h2>
            <p
                class="mx-auto mt-2 max-w-md text-sm leading-6 text-surface-500 dark:text-surface-400"
            >
                {{
                    hasActiveFilters
                        ? 'Coba ubah kata kunci atau filter yang dipilih untuk menemukan tugas yang dicari.'
                        : 'Tugas yang dihapus akan muncul di sini. Klik tombol pulihkan untuk mengembalikan tugas.'
                }}
            </p>
            <div class="mt-5 flex flex-wrap items-center justify-center gap-2">
                <button
                    v-if="hasActiveFilters"
                    type="button"
                    class="btn btn-secondary"
                    @click="resetFilters"
                >
                    <component :is="getIcon('RefreshCw')" class="h-4 w-4" />
                    Reset Filter
                </button>
                <Link
                    :href="projectsRoute.show.url({ project: project.id })"
                    class="btn btn-primary"
                >
                    <component :is="getIcon('ArrowLeft')" class="h-4 w-4" />
                    Kembali ke Proyek
                </Link>
            </div>
        </div>

        <!-- Pagination -->
        <div
            v-if="meta.total > 0"
            class="overflow-hidden rounded-2xl border border-surface-200 bg-white shadow-xs dark:border-surface-700 dark:bg-surface-900"
        >
            <Pagination
                :current-page="meta.current_page"
                :last-page="meta.last_page"
                :from="meta.from"
                :to="meta.to"
                :total="meta.total"
                @change="handlePageChange"
            />
        </div>
    </div>

    <!-- Restore Confirmation Modal -->
    <Modal
        :open="restoreModalOpen"
        title="Pulihkan Tugas"
        @close="restoreModalOpen = false"
    >
        <p class="text-surface-600 dark:text-surface-300">
            Apakah Anda yakin ingin memulihkan tugas
            <strong class="text-surface-900 dark:text-surface-100">{{
                taskToRestore?.title
            }}</strong>
            ?
        </p>
        <p class="mt-2 text-sm text-surface-500 dark:text-surface-400">
            Tugas akan kembali ke daftar tugas proyek beserta status dan
            assignee-nya.
        </p>

        <template #footer>
            <Button variant="secondary" @click="restoreModalOpen = false">
                Batal
            </Button>
            <Button
                variant="primary"
                @click="executeRestore"
                :loading="restoring"
            >
                <component :is="getIcon('RotateCcw')" class="h-4 w-4" />
                Pulihkan
            </Button>
        </template>
    </Modal>
</template>
