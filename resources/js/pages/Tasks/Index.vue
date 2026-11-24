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
import { useToast } from '@/composables/useToast';
import Button from '@/components/ui/Button.vue';
import Select from '@/components/ui/Select.vue';
import Pagination from '@/components/ui/Pagination.vue';
import Modal from '@/components/ui/Modal.vue';
import Avatar from '@/components/ui/Avatar.vue';
import DeadlineBadge from '@/components/tasks/DeadlineBadge.vue';
import {
    CheckSquare,
    Plus,
    Search,
    List,
    Kanban,
    MoreHorizontal,
    Pencil,
    Trash2,
    RotateCcw,
    Circle,
    Clock,
    Eye,
    CheckCircle2,
    ChevronRight,
    ArrowUpRight,
} from '@lucide/vue';
import type { Task, TaskFilters, TaskStatus } from '@/types';

defineOptions({
    layout: AppLayout,
});

const page = usePage();
const { success } = useToast();

const user = computed(() => page.props.auth.user);
const userRoles = computed(() => user.value?.roles ?? []);

const isManagerial = computed(() =>
    userRoles.value.some((r) =>
        ['administrator', 'project_manager'].includes(r),
    ),
);

const canCreate = computed(
    () =>
        isManagerial.value ||
        Boolean(user.value?.permissions.includes('tasks.create')),
);

const canViewTrashed = computed(
    () =>
        isManagerial.value ||
        Boolean(user.value?.permissions.includes('tasks.delete')),
);

const search = ref('');
let searchTimeout: ReturnType<typeof setTimeout>;

const viewMode = ref<'list' | 'board'>('list');

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
    { value: 'in_progress', label: 'In Progress', dot: 'bg-blue-500' },
    { value: 'review', label: 'Review', dot: 'bg-amber-500' },
    { value: 'done', label: 'Selesai', dot: 'bg-emerald-600' },
];

const hasActiveFilters = computed(
    () =>
        search.value.trim() !== '' ||
        filters.status !== '' ||
        filters.priority !== '' ||
        sortValue.value !== 'created_at:desc',
);

// Grouping tasks for Board View
const boardColumns = computed(() => [
    {
        status: 'todo',
        label: 'To Do',
        color: 'bg-surface-400',
        tasks: taskList.value.filter((t) => t.status === 'todo'),
    },
    {
        status: 'in_progress',
        label: 'Sedang Dikerjakan',
        color: 'bg-blue-500',
        tasks: taskList.value.filter((t) => t.status === 'in_progress'),
    },
    {
        status: 'review',
        label: 'Review / Validasi',
        color: 'bg-amber-500',
        tasks: taskList.value.filter((t) => t.status === 'review'),
    },
    {
        status: 'done',
        label: 'Selesai',
        color: 'bg-emerald-500',
        tasks: taskList.value.filter((t) => t.status === 'done'),
    },
]);

function getPriorityBadge(priority: string | null): {
    label: string;
    class: string;
} {
    if (priority === 'urgent')
        return {
            label: 'Mendesak',
            class: 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300',
        };
    if (priority === 'high')
        return {
            label: 'Tinggi',
            class: 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300',
        };
    if (priority === 'medium')
        return {
            label: 'Sedang',
            class: 'bg-blue-100 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300',
        };
    return {
        label: 'Rendah',
        class: 'bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-400',
    };
}

function formatDate(value: string | null): string {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
    });
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
    <div class="mx-auto max-w-7xl space-y-6">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span
                        class="inline-flex h-2 w-2 rounded-full bg-emerald-500"
                    />
                    <span
                        class="text-xs font-semibold tracking-wider text-surface-500 uppercase dark:text-surface-400"
                    >
                        Manajemen Tugas
                    </span>
                </div>
                <h1
                    class="text-xl font-bold tracking-tight text-surface-900 sm:text-2xl dark:text-surface-50"
                >
                    Tugas Tim
                </h1>
                <p class="text-xs text-surface-500 dark:text-surface-400">
                    Pantau status sprint, alokasi penanggung jawab, dan tenggat
                    waktu tugas.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <Link
                    v-if="canViewTrashed"
                    href="/tasks/trashed"
                    class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-surface-200 bg-white px-3 text-xs font-medium text-surface-700 shadow-2xs transition hover:bg-surface-50 dark:border-surface-800 dark:bg-surface-900 dark:text-surface-300 dark:hover:bg-surface-800"
                >
                    <Trash2 class="h-3.5 w-3.5 text-surface-400" />
                    <span>Sampah</span>
                </Link>

                <Link
                    v-if="canCreate"
                    :href="tasksRoute.create.url()"
                    class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-primary-600 px-3.5 text-xs font-medium text-white shadow-xs transition hover:bg-primary-700 active:scale-98 dark:bg-primary-500 dark:text-surface-950 dark:hover:bg-primary-400"
                >
                    <Plus class="h-3.5 w-3.5 stroke-[2.5]" />
                    <span>Tambah Tugas</span>
                </Link>
            </div>
        </div>

        <!-- Filter & View Toolbar -->
        <div
            class="space-y-3 rounded-xl border border-surface-200 bg-white p-4 shadow-xs dark:border-surface-800 dark:bg-surface-900"
        >
            <div
                class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
            >
                <!-- Search input -->
                <div class="relative max-w-md flex-1">
                    <Search
                        class="pointer-events-none absolute top-2.5 left-3 h-3.5 w-3.5 text-surface-400"
                    />
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Cari tugas berdasarkan judul atau deskripsi..."
                        class="h-9 w-full rounded-lg border border-surface-200/90 bg-surface-50/80 pr-4 pl-8.5 text-xs text-surface-900 placeholder-surface-400 shadow-2xs transition focus:border-primary-500 focus:bg-white focus:ring-1 focus:ring-primary-500 focus:outline-hidden dark:border-surface-800 dark:bg-surface-900/60 dark:text-surface-100 dark:placeholder-surface-400"
                    />
                </div>

                <!-- Right controls: Priority, Sort, View Toggle -->
                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        v-if="canViewTrashed"
                        href="/tasks/trashed"
                        class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-surface-200 bg-white px-3 text-xs font-medium text-surface-700 shadow-2xs transition hover:bg-surface-50 dark:border-surface-800 dark:bg-surface-900 dark:text-surface-300 dark:hover:bg-surface-800"
                    >
                        <Trash2 class="h-3.5 w-3.5 text-surface-400" />
                        <span>Sampah</span>
                    </Link>

                    <div class="w-36">
                        <Select
                            v-model="filters.priority"
                            :options="priorityOptions"
                            size="sm"
                        />
                    </div>

                    <div class="w-40">
                        <Select
                            v-model="sortValue"
                            :options="sortOptions"
                            size="sm"
                        />
                    </div>

                    <!-- View Switcher (List vs Board) -->
                    <div
                        class="flex items-center rounded-lg border border-surface-200 bg-surface-50/80 p-0.5 dark:border-surface-800 dark:bg-surface-900/60"
                    >
                        <button
                            type="button"
                            class="flex h-7 w-7 items-center justify-center rounded-md transition-colors"
                            :class="
                                viewMode === 'list'
                                    ? 'bg-white text-surface-900 shadow-2xs dark:bg-surface-800 dark:text-white'
                                    : 'text-surface-400 hover:text-surface-700 dark:hover:text-surface-200'
                            "
                            title="Tampilan Tabel Linear"
                            @click="viewMode = 'list'"
                        >
                            <List class="h-3.5 w-3.5" />
                        </button>
                        <button
                            type="button"
                            class="flex h-7 w-7 items-center justify-center rounded-md transition-colors"
                            :class="
                                viewMode === 'board'
                                    ? 'bg-white text-surface-900 shadow-2xs dark:bg-surface-800 dark:text-white'
                                    : 'text-surface-400 hover:text-surface-700 dark:hover:text-surface-200'
                            "
                            title="Tampilan Papan Kanban"
                            @click="viewMode = 'board'"
                        >
                            <Kanban class="h-3.5 w-3.5" />
                        </button>
                    </div>

                    <button
                        v-if="hasActiveFilters"
                        type="button"
                        class="rounded-lg px-2.5 py-1 text-xs font-medium text-surface-500 transition hover:text-surface-900 dark:text-surface-400 dark:hover:text-surface-100"
                        @click="resetFilters"
                    >
                        Reset
                    </button>
                </div>
            </div>

            <!-- Status Tabs Ribbon -->
            <div
                class="no-scrollbar flex items-center gap-1.5 overflow-x-auto border-t border-surface-100 pt-3 dark:border-surface-800/60"
            >
                <button
                    v-for="tab in statusTabs"
                    :key="tab.value"
                    type="button"
                    class="inline-flex shrink-0 items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-medium transition"
                    :class="
                        filters.status === tab.value
                            ? 'bg-primary-600 font-semibold text-white shadow-2xs dark:bg-primary-500 dark:text-surface-950'
                            : 'bg-surface-100/70 text-surface-600 hover:bg-surface-200/70 dark:bg-surface-800/50 dark:text-surface-300 dark:hover:bg-surface-800'
                    "
                    @click="setStatus(tab.value)"
                >
                    <span
                        v-if="tab.dot"
                        class="h-1.5 w-1.5 rounded-full"
                        :class="
                            filters.status === tab.value
                                ? 'bg-white dark:bg-surface-950'
                                : tab.dot
                        "
                    />
                    {{ tab.label }}
                </button>
            </div>
        </div>

        <!-- Result count & loading indicator -->
        <div
            v-if="!error && (taskList.length > 0 || loading)"
            class="flex items-center justify-between text-xs text-surface-500 dark:text-surface-400"
        >
            <span>
                Menampilkan
                <strong class="text-surface-900 dark:text-surface-100">{{
                    taskList.length
                }}</strong>
                dari
                <strong class="text-surface-900 dark:text-surface-100">{{
                    meta.total
                }}</strong>
                tugas
            </span>
            <span
                v-if="loading"
                class="flex items-center gap-1.5 text-primary-600 dark:text-primary-400"
            >
                <RefreshCw class="h-3 w-3 animate-spin" />
                <span>Memperbarui...</span>
            </span>
        </div>

        <!-- Error Alert -->
        <div
            v-if="error"
            class="flex items-center justify-between rounded-xl border border-rose-200 bg-rose-50 p-4 text-xs text-rose-800 dark:border-rose-900/40 dark:bg-rose-950/20 dark:text-rose-300"
        >
            <div class="flex items-center gap-2">
                <AlertTriangle class="h-4 w-4 text-rose-600" />
                <span>{{ error }}</span>
            </div>
            <button
                type="button"
                class="rounded-lg bg-white px-2.5 py-1 text-xs font-semibold shadow-2xs dark:bg-surface-900"
                @click="fetchTasks"
            >
                Coba lagi
            </button>
        </div>

        <!-- 1. LINEAR-STYLE LIST VIEW (Clean, High-Density Rows) -->
        <div
            v-if="viewMode === 'list' && taskList.length > 0"
            class="overflow-hidden rounded-xl border border-surface-200/90 bg-white shadow-card dark:border-surface-800 dark:bg-surface-900/90"
        >
            <div class="divide-y divide-surface-100 dark:divide-surface-800/60">
                <div
                    v-for="task in taskList"
                    :key="task.id"
                    class="group flex flex-col gap-2.5 p-3.5 transition-colors hover:bg-surface-50/80 sm:flex-row sm:items-center sm:justify-between dark:hover:bg-surface-800/30"
                >
                    <!-- Left: Status Icon, Task Key, Title & Project -->
                    <div
                        class="flex min-w-0 flex-1 items-start gap-3 sm:items-center"
                    >
                        <!-- Status Icon -->
                        <div class="mt-0.5 shrink-0 sm:mt-0">
                            <CheckCircle2
                                v-if="task.status === 'done'"
                                class="h-4.5 w-4.5 text-emerald-600 dark:text-emerald-400"
                            />
                            <Clock
                                v-else-if="task.status === 'in_progress'"
                                class="h-4.5 w-4.5 text-blue-500"
                            />
                            <Eye
                                v-else-if="task.status === 'review'"
                                class="h-4.5 w-4.5 text-amber-500"
                            />
                            <Circle
                                v-else
                                class="h-4.5 w-4.5 text-surface-400"
                            />
                        </div>

                        <!-- Monospace Key & Title -->
                        <div
                            class="flex min-w-0 flex-1 flex-col gap-1 sm:flex-row sm:items-center sm:gap-2.5"
                        >
                            <span
                                class="shrink-0 font-mono text-[11px] text-surface-400"
                            >
                                #{{ task.id }}
                            </span>

                            <Link
                                :href="tasksRoute.show.url({ task: task.id })"
                                class="truncate text-xs font-semibold text-surface-900 transition-colors hover:text-primary-600 sm:text-sm dark:text-surface-100 dark:hover:text-primary-400"
                            >
                                {{ task.title }}
                            </Link>

                            <!-- Project Tag Pill -->
                            <Link
                                v-if="task.project"
                                :href="
                                    projectsRoute.show.url({
                                        project: task.project.id,
                                    })
                                "
                                class="py-0.2 inline-flex shrink-0 items-center gap-1 rounded bg-surface-100 px-1.5 text-[10px] font-medium text-surface-600 transition hover:bg-surface-200 dark:bg-surface-800 dark:text-surface-300 dark:hover:bg-surface-700"
                            >
                                <span
                                    class="h-1.5 w-1.5 rounded-full bg-primary-500"
                                />
                                <span>{{ task.project.name }}</span>
                            </Link>
                        </div>
                    </div>

                    <!-- Right: Priority, Assignee, Deadline & Actions -->
                    <div
                        class="flex shrink-0 items-center justify-between gap-3 border-t border-surface-100 pt-1 sm:justify-end sm:border-0 sm:pt-0 dark:border-surface-800"
                    >
                        <!-- Priority Badge -->
                        <span
                            class="rounded px-2 py-0.5 text-[10px] font-semibold"
                            :class="getPriorityBadge(task.priority).class"
                        >
                            {{ getPriorityBadge(task.priority).label }}
                        </span>

                        <!-- Assignee Avatar -->
                        <div
                            v-if="task.assignee"
                            class="flex items-center gap-1.5"
                            :title="task.assignee.name"
                        >
                            <Avatar
                                :initials="task.assignee.initials"
                                :src="task.assignee.avatar"
                                :alt="task.assignee.name"
                                size="sm"
                            />
                        </div>
                        <div v-else class="text-[10px] text-surface-400 italic">
                            Unassigned
                        </div>

                        <!-- Due Date Badge -->
                        <div class="text-[11px]">
                            <DeadlineBadge
                                :due-date="task.due_date"
                                :status="task.status"
                                :is-overdue="task.is_overdue"
                            />
                        </div>

                        <!-- Delete Action Button -->
                        <button
                            v-if="task.can_delete"
                            type="button"
                            class="rounded-lg p-1 text-surface-400 transition hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/20 dark:hover:text-rose-400"
                            title="Hapus Tugas"
                            @click.stop="confirmDelete(task)"
                        >
                            <Trash2 class="h-4 w-4" />
                        </button>

                        <!-- Quick Action Link -->
                        <Link
                            :href="tasksRoute.show.url({ task: task.id })"
                            class="rounded-lg p-1 text-surface-400 transition hover:bg-surface-100 hover:text-surface-700 dark:hover:bg-surface-800 dark:hover:text-surface-200"
                        >
                            <ChevronRight class="h-4 w-4" />
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. KANBAN BOARD VIEW (4 Dynamic Columns) -->
        <div
            v-else-if="viewMode === 'board' && taskList.length > 0"
            class="grid grid-cols-1 items-start gap-4 md:grid-cols-2 lg:grid-cols-4"
        >
            <div
                v-for="col in boardColumns"
                :key="col.status"
                class="space-y-3 rounded-2xl border border-surface-200/80 bg-surface-50/50 p-3.5 dark:border-surface-800/60 dark:bg-surface-900/30"
            >
                <!-- Column Header -->
                <div class="flex items-center justify-between px-1">
                    <div class="flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full" :class="col.color" />
                        <h3
                            class="text-xs font-bold tracking-wider text-surface-900 uppercase dark:text-surface-100"
                        >
                            {{ col.label }}
                        </h3>
                    </div>
                    <span
                        class="py-0.2 rounded bg-surface-200/70 px-1.5 text-[10px] font-bold text-surface-600 dark:bg-surface-800 dark:text-surface-400"
                    >
                        {{ col.tasks.length }}
                    </span>
                </div>

                <!-- Column Cards -->
                <div class="space-y-2.5">
                    <div
                        v-for="task in col.tasks"
                        :key="task.id"
                        class="group rounded-xl border border-surface-200/90 bg-white p-3.5 shadow-card transition-all duration-150 hover:-translate-y-0.5 hover:border-surface-300 hover:shadow-panel dark:border-surface-800 dark:bg-surface-900/90 dark:hover:border-surface-700"
                    >
                        <div
                            class="mb-2 flex items-center justify-between gap-1"
                        >
                            <span
                                class="font-mono text-[10px] text-surface-400"
                            >
                                #{{ task.id }}
                            </span>

                            <div class="flex items-center gap-1">
                                <span
                                    class="py-0.2 rounded px-1.5 text-[9.5px] font-semibold"
                                    :class="
                                        getPriorityBadge(task.priority).class
                                    "
                                >
                                    {{ getPriorityBadge(task.priority).label }}
                                </span>

                                <button
                                    v-if="task.can_delete"
                                    type="button"
                                    class="rounded p-0.5 text-surface-400 transition hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/20 dark:hover:text-rose-400"
                                    title="Hapus Tugas"
                                    @click.stop="confirmDelete(task)"
                                >
                                    <Trash2 class="h-3 w-3" />
                                </button>
                            </div>
                        </div>

                        <Link
                            :href="tasksRoute.show.url({ task: task.id })"
                            class="mb-2 line-clamp-2 block text-xs font-semibold text-surface-900 transition-colors hover:text-primary-600 dark:text-surface-100 dark:hover:text-primary-400"
                        >
                            {{ task.title }}
                        </Link>

                        <div
                            v-if="task.project"
                            class="mb-3 truncate text-[10px] text-surface-500 dark:text-surface-400"
                        >
                            {{ task.project.name }}
                        </div>

                        <div
                            class="flex items-center justify-between border-t border-surface-100 pt-2 dark:border-surface-800"
                        >
                            <div
                                v-if="task.assignee"
                                :title="task.assignee.name"
                            >
                                <Avatar
                                    :initials="task.assignee.initials"
                                    :src="task.assignee.avatar"
                                    :alt="task.assignee.name"
                                    size="sm"
                                />
                            </div>
                            <div
                                v-else
                                class="text-[9.5px] text-surface-400 italic"
                            >
                                Belum ditugaskan
                            </div>

                            <span class="text-[10px] text-surface-500">
                                {{ formatDate(task.due_date) }}
                            </span>
                        </div>
                    </div>

                    <div
                        v-if="col.tasks.length === 0"
                        class="rounded-xl border border-dashed border-surface-200/80 p-6 text-center text-xs text-surface-400 dark:border-surface-800"
                    >
                        Tidak ada tugas
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div
            v-else-if="!loading && !error"
            class="rounded-2xl border border-dashed border-surface-300 bg-white px-6 py-16 text-center dark:border-surface-700 dark:bg-surface-900/40"
        >
            <CheckSquare class="mx-auto h-10 w-10 text-surface-400" />
            <h2
                class="mt-3 text-base font-bold text-surface-900 dark:text-surface-100"
            >
                {{
                    hasActiveFilters
                        ? 'Tugas tidak ditemukan'
                        : 'Belum ada tugas dibuat'
                }}
            </h2>
            <p class="mx-auto mt-1 max-w-sm text-xs text-surface-500">
                {{
                    hasActiveFilters
                        ? 'Coba ubah filter pencarian untuk menemukan tugas yang dicari.'
                        : 'Buat tugas baru untuk mulai mengorganisir alur pengerjaan tim.'
                }}
            </p>
            <button
                v-if="hasActiveFilters"
                type="button"
                class="mt-4 rounded-lg bg-surface-100 px-3 py-1.5 text-xs font-semibold text-surface-700 dark:bg-surface-800 dark:text-surface-300"
                @click="resetFilters"
            >
                Reset Filter
            </button>
        </div>

        <!-- Pagination -->
        <div v-if="meta.total > 0 && viewMode === 'list'" class="pt-2">
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
            <div class="space-y-3">
                <p class="text-xs text-surface-600 dark:text-surface-300">
                    Apakah Anda yakin ingin menghapus tugas
                    <strong class="font-bold text-surface-900 dark:text-white">
                        "{{ taskToDelete?.title }}" </strong
                    >? Tindakan ini tidak dapat dibatalkan.
                </p>
                <div class="flex justify-end gap-2 pt-3">
                    <Button
                        variant="secondary"
                        size="sm"
                        @click="deleteModalOpen = false"
                    >
                        Batal
                    </Button>
                    <Button
                        variant="destructive"
                        size="sm"
                        :loading="deletingTask"
                        @click="executeDelete"
                    >
                        Hapus Tugas
                    </Button>
                </div>
            </div>
        </Modal>
    </div>
</template>
