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
import type { Project, ProjectFilters } from '@/types';

defineOptions({
    layout: AppLayout,
});

const page = usePage();
const { success } = useToast();

const search = ref('');
let searchTimeout: ReturnType<typeof setTimeout>;

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
    { value: 'on_hold', label: 'Ditahan', dot: 'bg-accent-500' },
    { value: 'completed', label: 'Selesai', dot: 'bg-primary-700' },
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

interface DeadlineInfo {
    label: string;
    tone: 'overdue' | 'soon';
}

const projectCovers = [
    '/images/folder-marble-blue.jpg',
    '/images/folder-marble-dark.jpg',
    '/images/project-cover-sunset.jpg',
    '/images/project-cover-hills.jpg',
];

function getProjectCover(id: number): string {
    return projectCovers[id % projectCovers.length];
}

function isDarkFolderVariant(id: number): boolean {
    return id % 3 === 1;
}

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

function deadlineInfo(project: Project): DeadlineInfo | null {
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
        return {
            label: `Lewat ${Math.abs(diffDays)} hari`,
            tone: 'overdue',
        };
    }

    if (diffDays === 0) {
        return { label: 'Hari ini', tone: 'overdue' };
    }

    if (diffDays <= 7) {
        return { label: `${diffDays} hari lagi`, tone: 'soon' };
    }

    return null;
}

function deadlineBoxClass(project: Project): string {
    const info = deadlineInfo(project);

    if (info?.tone === 'overdue') {
        return 'border-accent-300 bg-accent-50/70 dark:border-accent-700/40 dark:bg-accent-900/20';
    }

    if (info?.tone === 'soon') {
        return 'border-accent-200 bg-accent-50/50 dark:border-accent-800/40 dark:bg-accent-900/10';
    }

    return 'border-surface-100 bg-surface-50/70 dark:border-surface-800 dark:bg-surface-800/40';
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
    if (!projectToDelete.value || deleting.value) {
        return;
    }

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
    } catch {
        // Error handled by api interceptor
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
    <div class="space-y-6">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
        >
            <div>
                <span
                    class="inline-flex h-6 items-center gap-1.5 rounded-full border border-primary-200 bg-primary-50 px-2.5 text-[10px] font-bold tracking-wider text-primary-700 uppercase dark:border-primary-700/40 dark:bg-primary-900/25 dark:text-primary-200"
                >
                    <component :is="getIcon('FolderKanban')" class="h-3 w-3" />
                    Portofolio Proyek
                </span>
                <h1
                    class="mt-2 text-2xl font-semibold tracking-tight text-surface-950 sm:text-3xl dark:text-surface-50"
                >
                    Proyek
                </h1>
                <p
                    class="mt-1 max-w-2xl text-sm leading-6 text-surface-700 dark:text-surface-200"
                >
                    Kelola seluruh proyek tim, pantau status, prioritas, dan
                    tenggatnya dalam satu tempat.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span
                    v-if="!loading"
                    class="hidden items-center gap-2 rounded-xl border border-surface-200 bg-white px-3 py-2 text-xs font-semibold text-surface-600 sm:inline-flex dark:border-surface-700 dark:bg-surface-900 dark:text-surface-300"
                >
                    <span class="h-1.5 w-1.5 rounded-full bg-primary-500" />
                    {{ meta.total }} proyek
                </span>
                <Link
                    v-if="canCreate"
                    :href="projectsRoute.create.url()"
                    class="btn btn-primary"
                >
                    <component :is="getIcon('Plus')" class="h-4 w-4" />
                    Tambah Proyek
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
                        placeholder="Cari proyek berdasarkan nama atau deskripsi..."
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
                        Gagal memuat daftar proyek
                    </p>
                    <p
                        class="mt-0.5 text-xs text-accent-700 dark:text-accent-300"
                    >
                        {{ error }}
                    </p>
                </div>
            </div>
            <Button variant="secondary" size="sm" @click="fetchProjects">
                <component :is="getIcon('RefreshCw')" class="h-3.5 w-3.5" />
                Coba lagi
            </Button>
        </div>

        <!-- Result summary -->
        <div
            v-if="!error && (projectList.length > 0 || loading)"
            class="flex items-center justify-between gap-2"
        >
            <p
                class="text-sm font-medium text-surface-600 dark:text-surface-300"
            >
                <template v-if="loading && projectList.length === 0">
                    Memuat daftar proyek...
                </template>
                <template v-else>
                    Menampilkan
                    <span
                        class="font-bold text-surface-900 dark:text-surface-50"
                    >
                        {{ projectList.length }}
                    </span>
                    dari
                    <span
                        class="font-bold text-surface-900 dark:text-surface-50"
                    >
                        {{ meta.total }}
                    </span>
                    proyek
                </template>
            </p>
            <span
                v-if="loading && projectList.length > 0"
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
            v-if="loading && projectList.length === 0"
            class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3"
            aria-hidden="true"
        >
            <div
                v-for="index in 6"
                :key="index"
                class="animate-pulse rounded-2xl border border-surface-200 bg-white p-5 dark:border-surface-700 dark:bg-surface-900"
            >
                <div class="flex gap-2">
                    <div
                        class="h-5 w-16 rounded-full bg-surface-100 dark:bg-surface-800"
                    />
                    <div
                        class="h-5 w-14 rounded-full bg-surface-100 dark:bg-surface-800"
                    />
                </div>
                <div
                    class="mt-5 h-4 w-3/4 rounded bg-surface-100 dark:bg-surface-800"
                />
                <div
                    class="mt-2 h-3 w-full rounded bg-surface-100 dark:bg-surface-800"
                />
                <div
                    class="mt-1.5 h-3 w-4/5 rounded bg-surface-100 dark:bg-surface-800"
                />
                <div class="mt-4 grid grid-cols-2 gap-2">
                    <div
                        class="h-14 rounded-xl bg-surface-100 dark:bg-surface-800"
                    />
                    <div
                        class="h-14 rounded-xl bg-surface-100 dark:bg-surface-800"
                    />
                </div>
                <div
                    class="mt-5 flex items-center gap-2 border-t border-surface-100 pt-4 dark:border-surface-800"
                >
                    <div
                        class="h-8 w-8 rounded-full bg-surface-100 dark:bg-surface-800"
                    />
                    <div
                        class="h-3 w-28 rounded bg-surface-100 dark:bg-surface-800"
                    />
                </div>
            </div>
        </div>

        <!-- Project grid -->
        <div
            v-else-if="projectList.length > 0"
            class="grid grid-cols-1 gap-4 transition-opacity md:grid-cols-2 xl:grid-cols-3"
            :class="loading ? 'opacity-60' : ''"
            :aria-busy="loading"
        >
            <article
                v-for="project in projectList"
                :key="project.id"
                class="group relative flex min-h-[350px] flex-col p-1.5 transition-all duration-300 select-none hover:-translate-y-2"
            >
                <!-- Outer Ambient Soft 3D Shadow -->
                <div
                    class="pointer-events-none absolute inset-x-3 top-12 bottom-0 rounded-[28px] bg-black/10 blur-xl transition-all duration-300 group-hover:bg-black/20 group-hover:blur-2xl dark:bg-black/35"
                />

                <!-- 1. BACK FOLDER LAYER with Fluid Marble Artwork & Top Tab -->
                <div
                    class="relative h-44 w-full overflow-hidden rounded-[26px] shadow-xs"
                >
                    <img
                        :src="getProjectCover(project.id)"
                        :alt="project.name"
                        class="h-full w-full object-cover transition-transform duration-700 ease-out group-hover:scale-105"
                    />
                    <!-- Subtle top sheen and dark gradient at bottom for depth -->
                    <div
                        class="pointer-events-none absolute inset-0 bg-gradient-to-b from-white/10 via-transparent to-black/30"
                    />
                </div>

                <!-- 2. DOCUMENT / PROJECT SHEET (Tucked inside folder, lifts up on hover!) -->
                <div
                    class="pointer-events-none absolute inset-x-6 top-5 z-10 flex h-44 flex-col justify-between rounded-t-[22px] border border-surface-200/90 bg-white p-4 shadow-md transition-transform duration-300 ease-out group-hover:-translate-y-5 dark:border-surface-700 dark:bg-surface-800"
                >
                    <div
                        class="flex items-center justify-between border-b border-surface-100 pb-2 dark:border-surface-700"
                    >
                        <div
                            class="flex items-center gap-1.5 text-[11px] font-semibold tracking-wider text-surface-400 uppercase dark:text-surface-400"
                        >
                            <component
                                :is="getIcon('FileText')"
                                class="h-3.5 w-3.5 text-surface-500"
                            />
                            <span>Lembar Proyek</span>
                        </div>
                        <span
                            class="rounded-full bg-surface-100 px-2 py-0.5 text-[10px] font-bold text-surface-700 dark:bg-surface-700 dark:text-surface-200"
                        >
                            {{ project.tasks_count ?? 0 }} Tugas
                        </span>
                    </div>
                    <p
                        class="line-clamp-2 text-xs leading-relaxed text-surface-600 dark:text-surface-300"
                    >
                        {{
                            project.description ||
                            'Ruang kerja proyek terkelola dengan alur tugas dan kolaborasi tim terintegrasi.'
                        }}
                    </p>
                    <div
                        class="flex items-center justify-between text-[11px] text-surface-500"
                    >
                        <span
                            >Tenggat:
                            <strong
                                class="text-surface-700 dark:text-surface-300"
                                >{{ formatDate(project.end_date) }}</strong
                            ></span
                        >
                        <span
                            class="font-semibold text-emerald-600 dark:text-emerald-400"
                        >
                            {{ project.status_label }}
                        </span>
                    </div>
                </div>

                <!-- 3. FRONT FLAP (Frosted Glass Acrylic or Sleek Dark Matte) -->
                <div
                    class="relative z-20 -mt-16 flex flex-1 flex-col justify-between rounded-[26px] p-5 shadow-xl transition-all duration-300"
                    :class="
                        isDarkFolderVariant(project.id)
                            ? 'border border-white/10 bg-[#121316]/92 text-white shadow-[0_20px_40px_rgba(0,0,0,0.45)] backdrop-blur-2xl'
                            : 'border border-white/70 bg-white/80 text-surface-900 shadow-[0_16px_36px_rgba(0,0,0,0.1)] backdrop-blur-2xl dark:border-white/10 dark:bg-surface-900/85 dark:text-white'
                    "
                >
                    <!-- Specular Top Rim Refraction Edge -->
                    <div
                        class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-white/80 to-transparent dark:via-white/30"
                    />

                    <div>
                        <!-- Folder Tab Header: Date / Status & Priority Badge -->
                        <div class="flex items-center justify-between">
                            <div
                                class="text-xs font-medium tracking-tight"
                                :class="
                                    isDarkFolderVariant(project.id)
                                        ? 'text-surface-400'
                                        : 'text-surface-500 dark:text-surface-400'
                                "
                            >
                                <span>{{
                                    formatRelativeTime(project.created_at)
                                }}</span>
                                <span class="mx-1.5 opacity-60">•</span>
                                <span
                                    class="font-semibold"
                                    :class="
                                        isDarkFolderVariant(project.id)
                                            ? 'text-white'
                                            : 'text-surface-800 dark:text-surface-200'
                                    "
                                >
                                    {{ project.status_label }}
                                </span>
                            </div>

                            <span
                                class="rounded-full px-2.5 py-0.5 text-[11px] font-medium"
                                :class="
                                    isDarkFolderVariant(project.id)
                                        ? 'bg-white/10 text-surface-200'
                                        : 'bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-300'
                                "
                            >
                                {{ project.priority_label }}
                            </span>
                        </div>

                        <!-- Project Title (Clickable) -->
                        <Link
                            :href="
                                projectsRoute.show.url({ project: project.id })
                            "
                            class="group/title mt-3 block"
                        >
                            <h2
                                class="line-clamp-1 font-sans text-lg font-bold tracking-tight transition group-hover/title:text-[#0F5132] dark:group-hover/title:text-[#b7d9c1]"
                                :class="
                                    isDarkFolderVariant(project.id)
                                        ? 'text-white'
                                        : 'text-surface-950 dark:text-surface-50'
                                "
                            >
                                {{ project.name }}
                            </h2>
                        </Link>

                        <!-- Project Description preview -->
                        <p
                            class="mt-1 line-clamp-2 text-xs leading-relaxed"
                            :class="
                                isDarkFolderVariant(project.id)
                                    ? 'text-surface-300'
                                    : 'text-surface-500 dark:text-surface-400'
                            "
                        >
                            {{
                                project.description ||
                                'Tidak ada catatan tambahan untuk folder proyek ini.'
                            }}
                        </p>
                    </div>

                    <!-- Bottom Flap Row: Avatar Stack on Left & Functional Squircle Button on Right -->
                    <div
                        class="mt-5 flex items-center justify-between border-t pt-3.5"
                        :class="
                            isDarkFolderVariant(project.id)
                                ? 'border-white/10'
                                : 'border-surface-200/60 dark:border-surface-800'
                        "
                    >
                        <!-- Left: Circular Avatar Stack with +N count -->
                        <div class="flex items-center -space-x-2">
                            <template
                                v-if="
                                    project.members &&
                                    project.members.length > 0
                                "
                            >
                                <div
                                    v-for="member in project.members.slice(
                                        0,
                                        3,
                                    )"
                                    :key="member.id"
                                    class="relative inline-block rounded-full ring-2"
                                    :class="
                                        isDarkFolderVariant(project.id)
                                            ? 'ring-surface-950'
                                            : 'ring-white dark:ring-surface-900'
                                    "
                                    :title="member.user?.name"
                                >
                                    <Avatar
                                        :initials="member.user?.initials"
                                        :src="member.user?.avatar"
                                        :alt="member.user?.name"
                                        size="sm"
                                    />
                                </div>
                            </template>
                            <div
                                v-else-if="project.owner"
                                class="relative inline-block rounded-full ring-2"
                                :class="
                                    isDarkFolderVariant(project.id)
                                        ? 'ring-surface-950'
                                        : 'ring-white dark:ring-surface-900'
                                "
                                :title="project.owner.name"
                            >
                                <Avatar
                                    :initials="project.owner.initials"
                                    :src="project.owner.avatar"
                                    :alt="project.owner.name"
                                    size="sm"
                                />
                            </div>
                            <span
                                v-if="
                                    project.members &&
                                    project.members.length > 3
                                "
                                class="ml-2.5 text-xs font-semibold"
                                :class="
                                    isDarkFolderVariant(project.id)
                                        ? 'text-surface-300'
                                        : 'text-surface-500 dark:text-surface-400'
                                "
                            >
                                +{{ project.members.length - 3 }}
                            </span>
                        </div>

                        <!-- Right: Fully Functional Squircle Action Button (Matching '+' and 'ooo') -->
                        <Dropdown align="right">
                            <template #trigger>
                                <button
                                    type="button"
                                    class="flex h-10 w-10 items-center justify-center rounded-2xl shadow-lg transition-all duration-200 hover:scale-105 active:scale-95"
                                    :class="
                                        isDarkFolderVariant(project.id)
                                            ? 'bg-white text-surface-950 shadow-black/40 hover:bg-surface-100'
                                            : 'bg-black text-white shadow-black/25 hover:bg-surface-800 dark:bg-white dark:text-surface-950 dark:hover:bg-surface-100'
                                    "
                                    title="Menu Opsi Proyek"
                                >
                                    <!-- In Top-Left variant: Plus icon; in Right variant: 3 rounded square dots -->
                                    <template v-if="project.id % 2 === 0">
                                        <component
                                            :is="getIcon('Plus')"
                                            class="h-5 w-5 stroke-[2.5]"
                                        />
                                    </template>
                                    <template v-else>
                                        <div class="flex items-center gap-0.5">
                                            <span
                                                class="h-1.5 w-1.5 rounded-xs"
                                                :class="
                                                    isDarkFolderVariant(
                                                        project.id,
                                                    )
                                                        ? 'bg-surface-950'
                                                        : 'bg-white dark:bg-surface-950'
                                                "
                                            />
                                            <span
                                                class="h-1.5 w-1.5 rounded-xs"
                                                :class="
                                                    isDarkFolderVariant(
                                                        project.id,
                                                    )
                                                        ? 'bg-surface-950'
                                                        : 'bg-white dark:bg-surface-950'
                                                "
                                            />
                                            <span
                                                class="h-1.5 w-1.5 rounded-xs"
                                                :class="
                                                    isDarkFolderVariant(
                                                        project.id,
                                                    )
                                                        ? 'bg-surface-950'
                                                        : 'bg-white dark:bg-surface-950'
                                                "
                                            />
                                        </div>
                                    </template>
                                </button>
                            </template>
                            <div class="w-48 py-1.5">
                                <Link
                                    :href="
                                        projectsRoute.show.url({
                                            project: project.id,
                                        })
                                    "
                                    class="flex w-full items-center gap-2.5 px-3.5 py-2 text-xs font-medium text-surface-700 hover:bg-surface-100 dark:text-surface-300 dark:hover:bg-surface-800"
                                >
                                    <component
                                        :is="getIcon('FolderOpen')"
                                        class="h-4 w-4 text-primary-600 dark:text-primary-400"
                                    />
                                    <span>Buka Rincian Proyek</span>
                                </Link>
                                <Link
                                    v-if="canUpdateProject(project)"
                                    :href="
                                        projectsRoute.edit.url({
                                            project: project.id,
                                        })
                                    "
                                    class="flex w-full items-center gap-2.5 px-3.5 py-2 text-xs font-medium text-surface-700 hover:bg-surface-100 dark:text-surface-300 dark:hover:bg-surface-800"
                                >
                                    <component
                                        :is="getIcon('Pencil')"
                                        class="h-4 w-4 text-surface-500"
                                    />
                                    <span>Edit Proyek</span>
                                </Link>
                                <Link
                                    :href="`/tasks/create?project_id=${project.id}`"
                                    class="flex w-full items-center gap-2.5 px-3.5 py-2 text-xs font-medium text-surface-700 hover:bg-surface-100 dark:text-surface-300 dark:hover:bg-surface-800"
                                >
                                    <component
                                        :is="getIcon('PlusCircle')"
                                        class="h-4 w-4 text-emerald-600 dark:text-emerald-400"
                                    />
                                    <span>Tambah Tugas Baru</span>
                                </Link>
                                <div
                                    class="my-1 border-t border-surface-100 dark:border-surface-800"
                                />
                                <button
                                    v-if="canDeleteProject(project)"
                                    type="button"
                                    class="flex w-full items-center gap-2.5 px-3.5 py-2 text-xs font-medium text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-950/20"
                                    @click="confirmDelete(project)"
                                >
                                    <component
                                        :is="getIcon('Trash2')"
                                        class="h-4 w-4 text-rose-500"
                                    />
                                    <span>Hapus Proyek</span>
                                </button>
                            </div>
                        </Dropdown>
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
                <component :is="getIcon('FolderKanban')" class="h-6 w-6" />
            </div>
            <h2
                class="mt-4 text-lg font-bold text-surface-950 dark:text-surface-50"
            >
                {{
                    hasActiveFilters
                        ? 'Proyek tidak ditemukan'
                        : 'Belum ada proyek'
                }}
            </h2>
            <p
                class="mx-auto mt-2 max-w-md text-sm leading-6 text-surface-500 dark:text-surface-400"
            >
                {{
                    hasActiveFilters
                        ? 'Coba ubah kata kunci atau filter yang dipilih untuk menemukan proyek yang dicari.'
                        : 'Mulai dengan membuat proyek pertama, lalu tambahkan anggota dan tugas di dalamnya.'
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
                    :href="projectsRoute.create.url()"
                    class="btn btn-primary"
                >
                    <component :is="getIcon('Plus')" class="h-4 w-4" />
                    Tambah Proyek
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

    <!-- Delete Confirmation Modal -->
    <Modal
        :open="deleteModalOpen"
        title="Hapus Proyek"
        @close="deleteModalOpen = false"
    >
        <p class="text-surface-600 dark:text-surface-300">
            Apakah Anda yakin ingin menghapus proyek
            <strong class="text-surface-900 dark:text-surface-100">{{
                projectToDelete?.name
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
                @click="executeDelete"
                :loading="deleting"
            >
                Hapus
            </Button>
        </template>
    </Modal>
</template>
