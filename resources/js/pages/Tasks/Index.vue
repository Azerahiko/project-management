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
import { useTasks } from '@/composables/useTasks';
import { getIcon } from '@/components/sidebar/icons';
import { useToast } from '@/composables/useToast';
import Button from '@/components/ui/Button.vue';
import Select from '@/components/ui/Select.vue';
import Pagination from '@/components/ui/Pagination.vue';
import Modal from '@/components/ui/Modal.vue';
import Avatar from '@/components/ui/Avatar.vue';
import type { Task, TaskFilters, TaskStatus } from '@/types';

defineOptions({
    layout: AppLayout,
});

const page = usePage();
const { success } = useToast();

const canCreate = computed(
    () => page.props.auth.user?.permissions.includes('tasks.view') ?? false,
);

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
} = useTasks(filters);

const deleteModalOpen = ref(false);
const taskToDelete = ref<Task | null>(null);
const deletingTask = ref(false);

const priorityOptions = [
    { value: '', label: 'Semua Prioritas' },
    { value: 'low', label: 'Rendah' },
    { value: 'medium', label: 'Sedang' },
    { value: 'high', label: 'Tinggi' },
    { value: 'urgent', label: 'Mendesak' },
];

const sortValue = ref('created_at:desc');

const sortOptions = [
    { value: 'created_at:desc', label: 'Terbaru' },
    { value: 'due_date:asc', label: 'Tenggat Terdekat' },
    { value: 'title:asc', label: 'Judul A–Z' },
    { value: 'updated_at:desc', label: 'Terakhir Diperbarui' },
];

const statusTabs = [
    { value: '', label: 'Semua' },
    { value: 'todo', label: 'To Do', dot: 'bg-surface-400' },
    { value: 'in_progress', label: 'Sedang Dikerjakan', dot: 'bg-primary-500' },
    { value: 'review', label: 'Review', dot: 'bg-accent-500' },
    { value: 'done', label: 'Selesai', dot: 'bg-primary-700' },
];

const hasActiveFilters = computed(
    () =>
        search.value.trim() !== '' ||
        filters.status !== '' ||
        filters.priority !== '' ||
        sortValue.value !== 'created_at:desc',
);

const today = new Date();
today.setHours(0, 0, 0, 0);

interface DeadlineInfo {
    label: string;
    tone: 'overdue' | 'soon';
}

const statusIcons: Record<TaskStatus, string> = {
    todo: 'CheckSquare',
    in_progress: 'Clock',
    review: 'Eye',
    done: 'CheckCircle2',
};

function statusIcon(status: TaskStatus): string {
    return statusIcons[status] ?? 'CheckSquare';
}

function statusTileClass(status: TaskStatus): string {
    switch (status) {
        case 'in_progress':
            return 'border-primary-200 bg-primary-50 text-primary-700 dark:border-primary-700/40 dark:bg-primary-900/25 dark:text-primary-200';
        case 'review':
            return 'border-accent-200 bg-accent-50 text-accent-700 dark:border-accent-700/40 dark:bg-accent-900/20 dark:text-accent-300';
        case 'done':
            return 'border-primary-300 bg-primary-100 text-primary-800 dark:border-primary-600/50 dark:bg-primary-800/40 dark:text-primary-100';
        default:
            return 'border-surface-200 bg-surface-50 text-surface-500 dark:border-surface-700 dark:bg-surface-800/60 dark:text-surface-300';
    }
}

function formatDate(value: string | null): string {
    if (!value) return '—';

    return new Date(value).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function deadlineInfo(task: Task): DeadlineInfo | null {
    if (!task.due_date || task.status === 'done') {
        return null;
    }

    const due = new Date(task.due_date);
    due.setHours(0, 0, 0, 0);

    const diffDays = Math.round((due.getTime() - today.getTime()) / 86_400_000);

    if (diffDays < 0) {
        return {
            label:
                diffDays === -1
                    ? 'Kemarin'
                    : `${Math.abs(diffDays)} hari lewat`,
            tone: 'overdue',
        };
    }

    if (diffDays === 0) {
        return { label: 'Hari ini', tone: 'soon' };
    }

    if (diffDays === 1) {
        return { label: 'Besok', tone: 'soon' };
    }

    if (diffDays <= 7) {
        return { label: `${diffDays} hari lagi`, tone: 'soon' };
    }

    return null;
}

function deadlineBoxClass(task: Task): string {
    const info = deadlineInfo(task);

    if (info?.tone === 'overdue') {
        return 'border-accent-300 bg-accent-50/70 dark:border-accent-700/40 dark:bg-accent-900/20';
    }

    if (info?.tone === 'soon') {
        return 'border-accent-200 bg-accent-50/50 dark:border-accent-800/40 dark:bg-accent-900/10';
    }

    return 'border-surface-100 bg-surface-50/70 dark:border-surface-800 dark:bg-surface-800/40';
}

function setStatus(value: string): void {
    filters.status = value;
    filters.page = 1;
}

function resetFilters(): void {
    clearTimeout(searchTimeout);
    search.value = '';
    filters.search = '';
    filters.status = '';
    filters.priority = '';
    sortValue.value = 'created_at:desc';
    filters.page = 1;
}

function handlePageChange(pageNumber: number): void {
    filters.page = pageNumber;
}

function confirmDelete(task: Task): void {
    taskToDelete.value = task;
    deleteModalOpen.value = true;
}

async function executeDelete(): Promise<void> {
    if (!taskToDelete.value || deletingTask.value) return;

    deletingTask.value = true;

    try {
        const isLastItemOnPage = taskList.value.length === 1;
        const shouldGoBack = isLastItemOnPage && filters.page > 1;

        await deleteTask(taskToDelete.value.id);
        success('Tugas dihapus', 'Tugas telah berhasil dihapus.');
        deleteModalOpen.value = false;
        taskToDelete.value = null;

        if (shouldGoBack) {
            filters.page -= 1;
        } else {
            await fetchTasks();
        }
    } catch {
        // Error handled by api interceptor
    } finally {
        deletingTask.value = false;
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
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
        >
            <div>
                <span
                    class="inline-flex h-6 items-center gap-1.5 rounded-full border border-primary-200 bg-primary-50 px-2.5 text-[10px] font-bold tracking-wider text-primary-700 uppercase dark:border-primary-700/40 dark:bg-primary-900/25 dark:text-primary-200"
                >
                    <component :is="getIcon('CheckSquare')" class="h-3 w-3" />
                    Pusat Tugas
                </span>
                <h1
                    class="mt-2 text-2xl font-semibold tracking-tight text-surface-950 sm:text-3xl dark:text-surface-50"
                >
                    Tugas
                </h1>
                <p
                    class="mt-1 max-w-2xl text-sm leading-6 text-surface-700 dark:text-surface-200"
                >
                    Pantau status, prioritas, penanggung jawab, dan tenggat
                    setiap tugas tim dalam satu tampilan.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span
                    v-if="!loading"
                    class="hidden items-center gap-2 rounded-xl border border-surface-200 bg-white px-3 py-2 text-xs font-semibold text-surface-600 sm:inline-flex dark:border-surface-700 dark:bg-surface-900 dark:text-surface-300"
                >
                    <span class="h-1.5 w-1.5 rounded-full bg-primary-500" />
                    {{ meta.total }} tugas
                </span>
                <Link
                    v-if="canCreate"
                    :href="tasksRoute.create.url()"
                    class="btn btn-primary"
                >
                    <component :is="getIcon('Plus')" class="h-4 w-4" />
                    Tambah Tugas
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
                        placeholder="Cari judul atau deskripsi tugas..."
                        class="input"
                        style="padding-left: 2.5rem"
                    />
                </div>

                <div class="grid grid-cols-2 gap-2 lg:flex lg:items-center">
                    <Select
                        v-model="filters.priority"
                        :options="priorityOptions"
                        class="w-full lg:w-44"
                        @update:model-value="filters.page = 1"
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
                class="mt-3 flex flex-wrap items-center gap-2 border-t border-surface-100 pt-3 dark:border-surface-800/80"
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
            class="space-y-3"
            aria-hidden="true"
        >
            <div
                v-for="index in 5"
                :key="index"
                class="animate-pulse rounded-2xl border border-surface-200 bg-white p-4 sm:p-5 dark:border-surface-700 dark:bg-surface-900"
            >
                <div class="flex items-start gap-4">
                    <div
                        class="hidden h-10 w-10 shrink-0 rounded-xl bg-surface-100 sm:block dark:bg-surface-800"
                    />
                    <div class="min-w-0 flex-1">
                        <div class="flex gap-2">
                            <div
                                class="h-5 w-16 rounded-full bg-surface-100 dark:bg-surface-800"
                            />
                            <div
                                class="h-5 w-14 rounded-full bg-surface-100 dark:bg-surface-800"
                            />
                        </div>
                        <div
                            class="mt-4 h-4 w-2/3 rounded bg-surface-100 dark:bg-surface-800"
                        />
                        <div
                            class="mt-2 h-3 w-40 rounded bg-surface-100 dark:bg-surface-800"
                        />
                        <div
                            class="mt-4 flex items-center justify-between gap-3 border-t border-surface-100 pt-3 dark:border-surface-800"
                        >
                            <div class="flex items-center gap-2">
                                <div
                                    class="h-7 w-7 rounded-full bg-surface-100 dark:bg-surface-800"
                                />
                                <div
                                    class="h-3 w-24 rounded bg-surface-100 dark:bg-surface-800"
                                />
                            </div>
                            <div
                                class="h-9 w-32 rounded-xl bg-surface-100 dark:bg-surface-800"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Task list -->
        <div
            v-else-if="taskList.length > 0"
            class="space-y-3 transition-opacity"
            :class="loading ? 'opacity-60' : ''"
            :aria-busy="loading"
        >
            <article
                v-for="task in taskList"
                :key="task.id"
                class="group rounded-2xl border border-surface-200 bg-white p-4 shadow-xs transition-all duration-200 hover:border-primary-300/80 hover:shadow-lg motion-safe:hover:-translate-y-0.5 sm:p-5 dark:border-surface-700 dark:bg-surface-900 dark:hover:border-primary-500/40"
            >
                <div class="flex items-start gap-3 sm:gap-4">
                    <span
                        class="hidden h-10 w-10 shrink-0 items-center justify-center rounded-xl border sm:flex"
                        :class="statusTileClass(task.status)"
                        :title="task.status_label"
                    >
                        <component
                            :is="getIcon(statusIcon(task.status))"
                            class="h-5 w-5"
                        />
                    </span>

                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-3">
                            <!-- Clean Calm indicator without pill badges -->
                            <div
                                class="flex min-w-0 flex-wrap items-center gap-2 text-xs font-medium text-surface-600 dark:text-surface-300"
                            >
                                <div class="inline-flex items-center gap-1.5">
                                    <span
                                        class="h-1.5 w-1.5 rounded-full bg-primary-700 dark:bg-primary-300"
                                    />
                                    <span>{{ task.status_label }}</span>
                                </div>
                                <span
                                    class="text-surface-300 dark:text-surface-700"
                                    >·</span
                                >
                                <span class="text-surface-500">{{
                                    task.priority_label
                                }}</span>
                            </div>

                            <div
                                class="-mt-1 -mr-1 flex shrink-0 items-center gap-0.5"
                            >
                                <Link
                                    :href="
                                        tasksRoute.show.url({ task: task.id })
                                    "
                                    class="flex h-8 w-8 items-center justify-center rounded-lg text-surface-400 transition-colors hover:bg-surface-100 hover:text-primary-700 dark:text-surface-500 dark:hover:bg-surface-800 dark:hover:text-primary-300"
                                    title="Lihat detail tugas"
                                    aria-label="Lihat detail tugas"
                                >
                                    <component
                                        :is="getIcon('Eye')"
                                        class="h-4 w-4"
                                    />
                                </Link>
                                <Link
                                    v-if="task.can_update"
                                    :href="
                                        tasksRoute.edit.url({ task: task.id })
                                    "
                                    class="flex h-8 w-8 items-center justify-center rounded-lg text-surface-400 transition-colors hover:bg-surface-100 hover:text-primary-700 dark:text-surface-500 dark:hover:bg-surface-800 dark:hover:text-primary-300"
                                    title="Edit tugas"
                                    aria-label="Edit tugas"
                                >
                                    <component
                                        :is="getIcon('Pencil')"
                                        class="h-4 w-4"
                                    />
                                </Link>
                                <button
                                    v-if="task.can_delete"
                                    type="button"
                                    class="flex h-8 w-8 items-center justify-center rounded-lg text-surface-400 transition-colors hover:bg-accent-50 hover:text-accent-700 dark:text-surface-500 dark:hover:bg-accent-900/20 dark:hover:text-accent-300"
                                    title="Hapus tugas"
                                    aria-label="Hapus tugas"
                                    @click="confirmDelete(task)"
                                >
                                    <component
                                        :is="getIcon('Trash2')"
                                        class="h-4 w-4"
                                    />
                                </button>
                            </div>
                        </div>

                        <Link
                            :href="tasksRoute.show.url({ task: task.id })"
                            class="mt-2 block"
                        >
                            <h2
                                class="line-clamp-2 text-[15px] leading-6 font-bold tracking-tight text-surface-950 transition-colors group-hover:text-primary-700 dark:text-surface-50 dark:group-hover:text-primary-300"
                            >
                                {{ task.title }}
                            </h2>
                        </Link>

                        <div
                            class="mt-1 flex items-center gap-1.5 text-xs text-surface-500 dark:text-surface-400"
                        >
                            <component
                                :is="getIcon('FolderKanban')"
                                class="h-3.5 w-3.5 shrink-0"
                            />
                            <Link
                                v-if="task.project"
                                :href="
                                    projectsRoute.show.url({
                                        project: task.project.id,
                                    })
                                "
                                class="truncate font-medium hover:text-primary-700 hover:underline hover:underline-offset-2 dark:hover:text-primary-300"
                            >
                                {{ task.project.name }}
                            </Link>
                            <span v-else class="truncate">Tanpa proyek</span>
                        </div>

                        <p
                            v-if="task.description"
                            class="mt-2 line-clamp-2 text-sm leading-6 text-surface-500 dark:text-surface-400"
                        >
                            {{ task.description }}
                        </p>

                        <div
                            class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-surface-100 pt-3 dark:border-surface-800"
                        >
                            <div class="flex min-w-0 items-center gap-2">
                                <Avatar
                                    v-if="task.assignee"
                                    :initials="task.assignee.initials"
                                    :src="task.assignee.avatar"
                                    :alt="task.assignee.name"
                                    size="sm"
                                />
                                <span
                                    v-else
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-dashed border-surface-300 text-surface-400 dark:border-surface-600 dark:text-surface-500"
                                >
                                    <component
                                        :is="getIcon('User')"
                                        class="h-4 w-4"
                                    />
                                </span>
                                <div class="min-w-0">
                                    <p
                                        class="text-[9px] font-bold tracking-wider text-surface-400 uppercase dark:text-surface-500"
                                    >
                                        Penanggung Jawab
                                    </p>
                                    <p
                                        class="truncate text-xs font-semibold text-surface-700 dark:text-surface-200"
                                    >
                                        {{
                                            task.assignee?.name ??
                                            'Belum ditugaskan'
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div
                                class="flex items-center gap-2 rounded-xl border px-2.5 py-1.5"
                                :class="deadlineBoxClass(task)"
                            >
                                <component
                                    :is="getIcon('Calendar')"
                                    class="h-3.5 w-3.5 shrink-0"
                                    :class="
                                        deadlineInfo(task)?.tone === 'overdue'
                                            ? 'text-accent-700 dark:text-accent-300'
                                            : deadlineInfo(task)?.tone ===
                                                'soon'
                                              ? 'text-accent-600 dark:text-accent-400'
                                              : 'text-surface-400 dark:text-surface-500'
                                    "
                                />
                                <div class="min-w-0">
                                    <p
                                        class="text-[9px] font-bold tracking-wider text-surface-400 uppercase dark:text-surface-500"
                                    >
                                        Tenggat
                                    </p>
                                    <p
                                        class="flex flex-wrap items-baseline gap-x-1 text-[11px] font-semibold text-surface-700 dark:text-surface-200"
                                    >
                                        {{
                                            task.due_date
                                                ? formatDate(task.due_date)
                                                : 'Tanpa tenggat'
                                        }}
                                        <span
                                            v-if="deadlineInfo(task)"
                                            class="text-[10px] font-bold"
                                            :class="
                                                deadlineInfo(task)?.tone ===
                                                'overdue'
                                                    ? 'text-accent-700 dark:text-accent-300'
                                                    : 'text-accent-600 dark:text-accent-400'
                                            "
                                        >
                                            · {{ deadlineInfo(task)?.label }}
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>
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
                class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-200"
            >
                <component :is="getIcon('CheckSquare')" class="h-6 w-6" />
            </div>
            <h2
                class="mt-4 text-lg font-bold text-surface-950 dark:text-surface-50"
            >
                {{
                    hasActiveFilters
                        ? 'Tugas tidak ditemukan'
                        : 'Belum ada tugas'
                }}
            </h2>
            <p
                class="mx-auto mt-2 max-w-md text-sm leading-6 text-surface-500 dark:text-surface-400"
            >
                {{
                    hasActiveFilters
                        ? 'Coba ubah kata kunci atau filter yang dipilih untuk menemukan tugas yang dicari.'
                        : 'Mulai dengan membuat tugas pertama, lalu tetapkan penanggung jawab dan tenggatnya.'
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
                    v-if="canCreate && !hasActiveFilters"
                    :href="tasksRoute.create.url()"
                    class="btn btn-primary"
                >
                    <component :is="getIcon('Plus')" class="h-4 w-4" />
                    Tambah Tugas
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

    <!-- Delete confirmation modal -->
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
                :loading="deletingTask"
                @click="executeDelete"
            >
                Hapus
            </Button>
        </template>
    </Modal>
</template>
