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
import projectsRoute from '@/routes/projects';
import AppLayout from '@/layouts/AppLayout.vue';
import { useProjects } from '@/composables/useProjects';
import { getIcon } from '@/components/sidebar/icons';
import { useToast } from '@/composables/useToast';
import Button from '@/components/ui/Button.vue';
import Select from '@/components/ui/Select.vue';
import Pagination from '@/components/ui/Pagination.vue';
import Modal from '@/components/ui/Modal.vue';
import Dropdown from '@/components/ui/Dropdown.vue';
import Avatar from '@/components/ui/Avatar.vue';
import {
    FolderKanban,
    Plus,
    Search,
    SlidersHorizontal,
    LayoutGrid,
    List,
    MoreHorizontal,
    FolderOpen,
    PlusCircle,
    Pencil,
    Trash2,
    Calendar,
    CheckSquare,
    AlertTriangle,
    RefreshCw,
} from '@lucide/vue';
import type { Project, ProjectFilters } from '@/types';

defineOptions({
    layout: AppLayout,
});

const page = usePage();
const { success } = useToast();

const search = ref('');
let searchTimeout: ReturnType<typeof setTimeout>;

const viewMode = ref<'grid' | 'list'>('grid');

const filters = reactive<ProjectFilters>({
    search: '',
    status: '',
    priority: '',
    owner_id: '',
    sort_by: 'created_at',
    sort_direction: 'desc',
    page: 1,
    per_page: 15,
});

const {
    projects: projectList,
    meta,
    loading,
    error,
    fetchProjects,
    deleteProject,
} = useProjects(filters);

const deleteModalOpen = ref(false);
const projectToDelete = ref<Project | null>(null);
const deleting = ref(false);

const canCreate = computed(() =>
    Boolean(page.props.auth.user?.permissions.includes('projects.create')),
);

const canViewTrashed = computed(() =>
    Boolean(page.props.auth.user?.permissions.includes('projects.view')),
);

const canCreateTask = computed(() => {
    const perms = page.props.auth.user?.permissions ?? [];
    return Boolean(
        perms.includes('tasks.view') ||
        perms.includes('tasks.create') ||
        perms.includes('projects.create') ||
        perms.includes('projects.update'),
    );
});

const priorityOptions = [
    { value: '', label: 'Semua Prioritas' },
    { value: 'low', label: 'Rendah' },
    { value: 'medium', label: 'Sedang' },
    { value: 'high', label: 'Tinggi' },
    { value: 'critical', label: 'Kritis' },
];

const sortValue = ref('created_at:desc');

const sortOptions = [
    { value: 'created_at:desc', label: 'Terbaru' },
    { value: 'name:asc', label: 'Nama A–Z' },
    { value: 'end_date:asc', label: 'Deadline Terdekat' },
    { value: 'start_date:desc', label: 'Mulai Terbaru' },
];

const statusTabs = [
    { value: '', label: 'Semua' },
    { value: 'active', label: 'Aktif', dot: 'bg-primary-500' },
    { value: 'draft', label: 'Draft', dot: 'bg-surface-400' },
    { value: 'on_hold', label: 'Ditahan', dot: 'bg-amber-500' },
    { value: 'completed', label: 'Selesai', dot: 'bg-emerald-600' },
    { value: 'archived', label: 'Arsip', dot: 'bg-surface-300' },
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

function formatDate(value: string | null): string {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    });
}

function getDeadlineMeta(
    project: Project,
): { label: string; isOverdue: boolean } | null {
    if (
        !project.end_date ||
        project.status === 'completed' ||
        project.status === 'archived'
    ) {
        return null;
    }

    const end = new Date(project.end_date);
    end.setHours(0, 0, 0, 0);

    const diffDays = Math.round((end.getTime() - today.getTime()) / 86_400_000);

    if (diffDays < 0) {
        return { label: `Lewat ${Math.abs(diffDays)} hari`, isOverdue: true };
    }
    if (diffDays === 0) {
        return { label: 'Hari ini', isOverdue: true };
    }
    if (diffDays <= 7) {
        return { label: `${diffDays} hari lagi`, isOverdue: false };
    }
    return { label: formatDate(project.end_date), isOverdue: false };
}

function getPriorityBadgeClass(priority: string | null): string {
    if (priority === 'critical')
        return 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300';
    if (priority === 'high')
        return 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300';
    if (priority === 'medium')
        return 'bg-blue-100 text-blue-700 dark:bg-blue-950/40 dark:text-blue-300';
    return 'bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-400';
}

function getStatusBadgeClass(status: string | null): string {
    if (status === 'active')
        return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300';
    if (status === 'completed')
        return 'bg-primary-100 text-primary-800 dark:bg-primary-950/40 dark:text-primary-300';
    if (status === 'on_hold')
        return 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300';
    if (status === 'archived')
        return 'bg-surface-200 text-surface-700 dark:bg-surface-800 dark:text-surface-300';
    return 'bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-400';
}

function canUpdateProject(project: Project): boolean {
    return Boolean(
        project.can_update &&
        page.props.auth.user?.permissions.includes('projects.update'),
    );
}

function canDeleteProject(project: Project): boolean {
    return Boolean(
        project.can_delete &&
        page.props.auth.user?.permissions.includes('projects.delete'),
    );
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

function confirmDelete(project: Project): void {
    projectToDelete.value = project;
    deleteModalOpen.value = true;
}

async function executeDelete(): Promise<void> {
    if (!projectToDelete.value || deleting.value) return;

    deleting.value = true;
    try {
        const isLastItemOnPage = projectList.value.length === 1;
        const shouldGoBack = isLastItemOnPage && filters.page > 1;

        await deleteProject(projectToDelete.value.id);
        success('Proyek dihapus', 'Proyek telah berhasil dihapus.');
        deleteModalOpen.value = false;
        projectToDelete.value = null;

        if (shouldGoBack) {
            filters.page -= 1;
        } else {
            await fetchProjects();
        }
    } finally {
        deleting.value = false;
    }
}

onMounted(() => {
    fetchProjects();
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
        filters.owner_id,
        filters.sort_by,
        filters.sort_direction,
        filters.page,
    ],
    () => {
        fetchProjects();
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
                        class="inline-flex h-2 w-2 rounded-full bg-primary-600"
                    />
                    <span
                        class="text-xs font-semibold tracking-wider text-surface-500 uppercase dark:text-surface-400"
                    >
                        Portofolio Kerja
                    </span>
                </div>
                <h1
                    class="text-xl font-bold tracking-tight text-surface-900 sm:text-2xl dark:text-surface-50"
                >
                    Proyek Tim
                </h1>
                <p class="text-xs text-surface-500 dark:text-surface-400">
                    Kelola inisiatif strategis, pantau progres pencapaian, dan
                    alokasikan anggota tim.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <Link
                    v-if="canViewTrashed"
                    href="/projects/trashed"
                    class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-surface-200 bg-white px-3 text-xs font-medium text-surface-700 shadow-2xs transition hover:bg-surface-50 dark:border-surface-800 dark:bg-surface-900 dark:text-surface-300 dark:hover:bg-surface-800"
                >
                    <Trash2 class="h-3.5 w-3.5 text-surface-400" />
                    <span>Sampah</span>
                </Link>

                <Link
                    v-if="canCreate"
                    :href="projectsRoute.create.url()"
                    class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-primary-600 px-3.5 text-xs font-medium text-white shadow-xs transition hover:bg-primary-700 active:scale-98 dark:bg-primary-500 dark:text-surface-950 dark:hover:bg-primary-400"
                >
                    <Plus class="h-3.5 w-3.5 stroke-[2.5]" />
                    <span>Tambah Proyek</span>
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
                        placeholder="Cari berdasarkan nama atau deskripsi proyek..."
                        class="h-9 w-full rounded-lg border border-surface-200/90 bg-surface-50/80 pr-4 pl-8.5 text-xs text-surface-900 placeholder-surface-400 shadow-2xs transition focus:border-primary-500 focus:bg-white focus:ring-1 focus:ring-primary-500 focus:outline-hidden dark:border-surface-800 dark:bg-surface-900/60 dark:text-surface-100 dark:placeholder-surface-400"
                    />
                </div>

                <!-- Right controls: Priority, Sort, View Toggle -->
                <div class="flex flex-wrap items-center gap-2">
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

                    <!-- View Switcher (Grid vs List) -->
                    <div
                        class="flex items-center rounded-lg border border-surface-200 bg-surface-50/80 p-0.5 dark:border-surface-800 dark:bg-surface-900/60"
                    >
                        <button
                            type="button"
                            class="flex h-7 w-7 items-center justify-center rounded-md transition-colors"
                            :class="
                                viewMode === 'grid'
                                    ? 'bg-white text-surface-900 shadow-2xs dark:bg-surface-800 dark:text-white'
                                    : 'text-surface-400 hover:text-surface-700 dark:hover:text-surface-200'
                            "
                            title="Tampilan Grid"
                            @click="viewMode = 'grid'"
                        >
                            <LayoutGrid class="h-3.5 w-3.5" />
                        </button>
                        <button
                            type="button"
                            class="flex h-7 w-7 items-center justify-center rounded-md transition-colors"
                            :class="
                                viewMode === 'list'
                                    ? 'bg-white text-surface-900 shadow-2xs dark:bg-surface-800 dark:text-white'
                                    : 'text-surface-400 hover:text-surface-700 dark:hover:text-surface-200'
                            "
                            title="Tampilan Tabel List"
                            @click="viewMode = 'list'"
                        >
                            <List class="h-3.5 w-3.5" />
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
            v-if="!error && (projectList.length > 0 || loading)"
            class="flex items-center justify-between text-xs text-surface-500 dark:text-surface-400"
        >
            <span>
                Menampilkan
                <strong class="text-surface-900 dark:text-surface-100">{{
                    projectList.length
                }}</strong>
                dari
                <strong class="text-surface-900 dark:text-surface-100">{{
                    meta.total
                }}</strong>
                proyek
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
                @click="fetchProjects"
            >
                Coba lagi
            </button>
        </div>

        <!-- GRID VIEW (Redesigned Project Cards) -->
        <div
            v-if="viewMode === 'grid' && projectList.length > 0"
            class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-3"
        >
            <article
                v-for="project in projectList"
                :key="project.id"
                class="group flex flex-col justify-between rounded-xl border border-surface-200/90 bg-white p-5 shadow-card transition-all duration-180 hover:-translate-y-0.5 hover:border-surface-300 hover:shadow-panel dark:border-surface-800 dark:bg-surface-900/90 dark:hover:border-surface-700"
            >
                <!-- Top Card Bar: Tags, Status, Action Dropdown -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span
                                class="rounded px-2 py-0.5 text-[10px] font-semibold"
                                :class="getStatusBadgeClass(project.status)"
                            >
                                {{ project.status_label }}
                            </span>
                            <span
                                class="rounded px-2 py-0.5 text-[10px] font-semibold"
                                :class="getPriorityBadgeClass(project.priority)"
                            >
                                {{ project.priority_label }}
                            </span>
                        </div>

                        <!-- 3-dot dropdown menu -->
                        <Dropdown align="right" bare>
                            <template #trigger="{ open }">
                                <button
                                    type="button"
                                    class="rounded-lg p-1 text-surface-400 transition hover:bg-surface-100 hover:text-surface-700 dark:hover:bg-surface-800 dark:hover:text-surface-200"
                                    :aria-expanded="open"
                                    title="Menu Proyek"
                                >
                                    <MoreHorizontal class="h-4 w-4" />
                                </button>
                            </template>
                            <div class="w-48 py-1">
                                <Link
                                    :href="
                                        projectsRoute.show.url({
                                            project: project.id,
                                        })
                                    "
                                    class="flex w-full items-center gap-2 px-3 py-1.5 text-xs text-surface-700 hover:bg-surface-100 dark:text-surface-300 dark:hover:bg-surface-800"
                                >
                                    <FolderOpen
                                        class="h-3.5 w-3.5 text-primary-600"
                                    />
                                    <span>Buka Proyek</span>
                                </Link>
                                <Link
                                    v-if="canCreateTask"
                                    :href="`/tasks/create?project_id=${project.id}`"
                                    class="flex w-full items-center gap-2 px-3 py-1.5 text-xs text-surface-700 hover:bg-surface-100 dark:text-surface-300 dark:hover:bg-surface-800"
                                >
                                    <PlusCircle
                                        class="h-3.5 w-3.5 text-emerald-600"
                                    />
                                    <span>Tambah Tugas</span>
                                </Link>
                                <Link
                                    v-if="canUpdateProject(project)"
                                    :href="
                                        projectsRoute.edit.url({
                                            project: project.id,
                                        })
                                    "
                                    class="flex w-full items-center gap-2 px-3 py-1.5 text-xs text-surface-700 hover:bg-surface-100 dark:text-surface-300 dark:hover:bg-surface-800"
                                >
                                    <Pencil
                                        class="h-3.5 w-3.5 text-surface-500"
                                    />
                                    <span>Edit</span>
                                </Link>
                                <div
                                    class="my-1 border-t border-surface-100 dark:border-surface-800"
                                />
                                <button
                                    v-if="canDeleteProject(project)"
                                    type="button"
                                    class="flex w-full items-center gap-2 px-3 py-1.5 text-xs text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/20"
                                    @click="confirmDelete(project)"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                    <span>Hapus</span>
                                </button>
                            </div>
                        </Dropdown>
                    </div>

                    <!-- Project Title & Excerpt -->
                    <div class="space-y-1">
                        <Link
                            :href="
                                projectsRoute.show.url({ project: project.id })
                            "
                            class="line-clamp-1 block text-base font-bold text-surface-900 transition-colors hover:text-primary-600 dark:text-surface-100 dark:hover:text-primary-400"
                        >
                            {{ project.name }}
                        </Link>
                        <p
                            class="line-clamp-2 text-xs leading-relaxed text-surface-500 dark:text-surface-400"
                        >
                            {{
                                project.description ||
                                'Tidak ada ringkasan deskripsi untuk proyek ini.'
                            }}
                        </p>
                    </div>

                    <!-- Progress Bar -->
                    <div class="space-y-1.5 pt-2">
                        <div
                            class="flex items-center justify-between text-[11px] text-surface-500 dark:text-surface-400"
                        >
                            <span>Progres Tugas</span>
                            <span
                                class="font-bold text-surface-900 dark:text-surface-100"
                            >
                                {{
                                    (project as any).progress_percent ??
                                    (project.tasks_count
                                        ? Math.round(
                                              (((project as any)
                                                  .completed_tasks_count || 0) /
                                                  project.tasks_count) *
                                                  100,
                                          )
                                        : 0)
                                }}%
                            </span>
                        </div>
                        <div
                            class="h-1.5 w-full overflow-hidden rounded-full bg-surface-100 dark:bg-surface-800"
                        >
                            <div
                                class="h-full bg-primary-600 transition-all duration-300 dark:bg-primary-400"
                                :style="{
                                    width: `${(project as any).progress_percent ?? (project.tasks_count ? Math.round((((project as any).completed_tasks_count || 0) / project.tasks_count) * 100) : 0)}%`,
                                }"
                            />
                        </div>
                    </div>
                </div>

                <!-- Bottom Row: Team Avatars & Deadline -->
                <div
                    class="mt-5 flex items-center justify-between border-t border-surface-100 pt-3 dark:border-surface-800/80"
                >
                    <!-- Overlapping Avatar Stack -->
                    <div class="flex items-center -space-x-1.5">
                        <template
                            v-if="project.members && project.members.length > 0"
                        >
                            <div
                                v-for="developer in project.members.slice(0, 3)"
                                :key="developer.id"
                                class="relative inline-block rounded-full ring-2 ring-white dark:ring-surface-900"
                                :title="developer.user?.name"
                            >
                                <Avatar
                                    :initials="developer.user?.initials"
                                    :src="developer.user?.avatar"
                                    :alt="developer.user?.name"
                                    size="sm"
                                />
                            </div>
                            <span
                                v-if="project.members.length > 3"
                                class="pl-2 text-[10px] font-semibold text-surface-400"
                            >
                                +{{ project.members.length - 3 }}
                            </span>
                        </template>
                        <div
                            v-else-if="project.owner"
                            class="relative inline-block rounded-full ring-2 ring-white dark:ring-surface-900"
                            :title="project.owner.name"
                        >
                            <Avatar
                                :initials="project.owner.initials"
                                :src="project.owner.avatar"
                                :alt="project.owner.name"
                                size="sm"
                            />
                        </div>
                    </div>

                    <!-- Deadline Badge -->
                    <div
                        v-if="getDeadlineMeta(project)"
                        class="flex items-center gap-1 text-[11px]"
                    >
                        <Calendar class="h-3 w-3 text-surface-400" />
                        <span
                            :class="
                                getDeadlineMeta(project)!.isOverdue
                                    ? 'font-bold text-rose-600 dark:text-rose-400'
                                    : 'text-surface-500 dark:text-surface-400'
                            "
                        >
                            {{ getDeadlineMeta(project)!.label }}
                        </span>
                    </div>
                    <div v-else class="text-[11px] text-surface-400">
                        Tanpa tenggat
                    </div>
                </div>
            </article>
        </div>

        <!-- LIST / TABLE VIEW (Linear High-Density Mode) -->
        <div
            v-else-if="viewMode === 'list' && projectList.length > 0"
            class="overflow-hidden rounded-xl border border-surface-200/90 bg-white shadow-card dark:border-surface-800 dark:bg-surface-900/90"
        >
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead
                        class="border-b border-surface-100 bg-surface-50/70 text-[10px] tracking-wider text-surface-500 uppercase dark:border-surface-800 dark:bg-surface-900/80 dark:text-surface-400"
                    >
                        <tr>
                            <th class="py-3 pr-3 pl-4 font-semibold">
                                Nama Proyek
                            </th>
                            <th class="px-3 py-3 font-semibold">Status</th>
                            <th class="px-3 py-3 font-semibold">Prioritas</th>
                            <th class="px-3 py-3 font-semibold">Progres</th>
                            <th class="px-3 py-3 font-semibold">Tim</th>
                            <th class="px-3 py-3 font-semibold">Tenggat</th>
                            <th class="py-3 pr-4 pl-3 text-right font-semibold">
                                Aksi
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-surface-100 dark:divide-surface-800/60"
                    >
                        <tr
                            v-for="project in projectList"
                            :key="project.id"
                            class="group transition-colors hover:bg-surface-50/80 dark:hover:bg-surface-800/40"
                        >
                            <td class="py-3 pr-3 pl-4">
                                <Link
                                    :href="
                                        projectsRoute.show.url({
                                            project: project.id,
                                        })
                                    "
                                    class="block font-semibold text-surface-900 transition-colors hover:text-primary-600 dark:text-surface-100 dark:hover:text-primary-400"
                                >
                                    {{ project.name }}
                                </Link>
                                <span
                                    class="line-clamp-1 text-[11px] text-surface-400"
                                >
                                    {{
                                        project.description ||
                                        'Tidak ada deskripsi'
                                    }}
                                </span>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                <span
                                    class="rounded px-2 py-0.5 text-[10px] font-semibold"
                                    :class="getStatusBadgeClass(project.status)"
                                >
                                    {{ project.status_label }}
                                </span>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                <span
                                    class="rounded px-2 py-0.5 text-[10px] font-semibold"
                                    :class="
                                        getPriorityBadgeClass(project.priority)
                                    "
                                >
                                    {{ project.priority_label }}
                                </span>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                <div class="flex items-center gap-2">
                                    <div
                                        class="h-1.5 w-16 overflow-hidden rounded-full bg-surface-100 dark:bg-surface-800"
                                    >
                                        <div
                                            class="h-full bg-primary-600 dark:bg-primary-400"
                                            :style="{
                                                width: `${(project as any).progress_percent ?? (project.tasks_count ? Math.round((((project as any).completed_tasks_count || 0) / project.tasks_count) * 100) : 0)}%`,
                                            }"
                                        />
                                    </div>
                                    <span
                                        class="text-[11px] font-medium text-surface-500"
                                    >
                                        {{
                                            (project as any).progress_percent ??
                                            (project.tasks_count
                                                ? Math.round(
                                                      (((project as any)
                                                          .completed_tasks_count ||
                                                          0) /
                                                          project.tasks_count) *
                                                          100,
                                                  )
                                                : 0)
                                        }}%
                                    </span>
                                </div>
                            </td>
                            <td class="px-3 py-3 whitespace-nowrap">
                                <div class="flex items-center -space-x-1.5">
                                    <template
                                        v-if="
                                            project.members &&
                                            project.members.length > 0
                                        "
                                    >
                                        <div
                                            v-for="developer in project.members.slice(
                                                0,
                                                3,
                                            )"
                                            :key="developer.id"
                                            class="relative inline-block rounded-full ring-1 ring-white dark:ring-surface-900"
                                            :title="developer.user?.name"
                                        >
                                            <Avatar
                                                :initials="
                                                    developer.user?.initials
                                                "
                                                :src="developer.user?.avatar"
                                                :alt="developer.user?.name"
                                                size="sm"
                                            />
                                        </div>
                                    </template>
                                </div>
                            </td>
                            <td
                                class="px-3 py-3 whitespace-nowrap text-surface-500"
                            >
                                {{ formatDate(project.end_date) }}
                            </td>
                            <td
                                class="py-3 pr-4 pl-3 text-right whitespace-nowrap"
                            >
                                <Link
                                    :href="
                                        projectsRoute.show.url({
                                            project: project.id,
                                        })
                                    "
                                    class="inline-flex items-center gap-1 text-xs font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400"
                                >
                                    <span>Buka</span>
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Empty State -->
        <div
            v-else-if="!loading && !error"
            class="rounded-2xl border border-dashed border-surface-300 bg-white px-6 py-16 text-center dark:border-surface-700 dark:bg-surface-900/40"
        >
            <FolderKanban class="mx-auto h-10 w-10 text-surface-400" />
            <h2
                class="mt-3 text-base font-bold text-surface-900 dark:text-surface-100"
            >
                {{
                    hasActiveFilters
                        ? 'Proyek tidak ditemukan'
                        : 'Belum ada proyek dibuat'
                }}
            </h2>
            <p class="mx-auto mt-1 max-w-sm text-xs text-surface-500">
                {{
                    hasActiveFilters
                        ? 'Coba ubah filter pencarian untuk menemukan proyek yang dicari.'
                        : 'Buat proyek pertama Anda untuk mulai mengelola alur kerja tim.'
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
        <div v-if="meta.total > 0" class="pt-2">
            <Pagination
                :current-page="meta.current_page"
                :last-page="meta.last_page"
                :from="meta.from"
                :to="meta.to"
                :total="meta.total"
                @change="handlePageChange"
            />
        </div>

        <!-- Delete confirmation modal -->
        <Modal
            :open="deleteModalOpen"
            title="Hapus Proyek"
            @close="deleteModalOpen = false"
        >
            <div class="space-y-3">
                <p class="text-xs text-surface-600 dark:text-surface-300">
                    Apakah Anda yakin ingin menghapus proyek
                    <strong class="font-bold text-surface-900 dark:text-white">
                        "{{ projectToDelete?.name }}" </strong
                    >? Data proyek ini akan dipindahkan ke tempat sampah.
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
                        :loading="deleting"
                        @click="executeDelete"
                    >
                        Hapus Proyek
                    </Button>
                </div>
            </div>
        </Modal>
    </div>
</template>
