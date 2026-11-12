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
import { useHttp } from '@inertiajs/vue3';
import Button from '@/components/ui/Button.vue';
import Select from '@/components/ui/Select.vue';
import Pagination from '@/components/ui/Pagination.vue';
import Modal from '@/components/ui/Modal.vue';
import Avatar from '@/components/ui/Avatar.vue';
import type { Project, ProjectFilters } from '@/types';

defineOptions({
    layout: AppLayout,
});

const page = usePage();
const { success, error: toastError } = useToast();

const search = ref('');
let searchTimeout: ReturnType<typeof setTimeout>;

const filters = reactive<ProjectFilters>({
    search: '',
    status: '',
    priority: '',
    owner_id: '',
    sort_by: 'deleted_at',
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
} = useProjects(filters, true);

const restoreModalOpen = ref(false);
const projectToRestore = ref<Project | null>(null);
const restoring = ref(false);

const canRestore = computed(() =>
    Boolean(page.props.auth.user?.permissions.includes('projects.update')),
);

const sortValue = ref('deleted_at:desc');

const sortOptions = [
    { value: 'deleted_at:desc', label: 'Terbaru Dihapus' },
    { value: 'deleted_at:asc', label: 'Terlama Dihapus' },
    { value: 'name:asc', label: 'Nama A–Z' },
];

const hasActiveFilters = computed(
    () =>
        search.value.trim() !== '' ||
        filters.status !== '' ||
        filters.priority !== '' ||
        sortValue.value !== 'deleted_at:desc',
);

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

const projectCovers = [
    '/images/folder-marble-blue.jpg',
    '/images/folder-marble-dark.jpg',
    '/images/project-cover-sunset.jpg',
    '/images/project-cover-hills.jpg',
];

function getProjectCover(id: number): string {
    return projectCovers[id % projectCovers.length];
}

function canUpdateProject(project: Project): boolean {
    return Boolean(
        project.can_update &&
        page.props.auth.user?.permissions.includes('projects.update'),
    );
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

function confirmRestore(project: Project): void {
    projectToRestore.value = project;
    restoreModalOpen.value = true;
}

async function executeRestore(): Promise<void> {
    if (!projectToRestore.value || restoring.value) {
        return;
    }

    restoring.value = true;

    try {
        const { post } = useHttp();
        await post(`/api/projects/${projectToRestore.value.id}/restore`);

        success('Proyek dipulihkan', 'Proyek telah berhasil dipulihkan.');
        restoreModalOpen.value = false;
        projectToRestore.value = null;
        await fetchProjects();
    } catch {
        toastError(
            'Gagal memulihkan proyek',
            'Proyek gagal dipulihkan. Silakan coba lagi.',
        );
    } finally {
        restoring.value = false;
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
                    class="inline-flex h-6 items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-2.5 text-[10px] font-bold tracking-wider text-amber-700 uppercase dark:border-amber-700/40 dark:bg-amber-900/25 dark:text-amber-200"
                >
                    <component :is="getIcon('Trash2')" class="h-3 w-3" />
                    Sampah Proyek
                </span>
                <h1
                    class="mt-2 text-2xl font-semibold tracking-tight text-surface-950 sm:text-3xl dark:text-surface-50"
                >
                    Proyek yang Dihapus
                </h1>
                <p
                    class="mt-1 max-w-2xl text-sm leading-6 text-surface-700 dark:text-surface-200"
                >
                    Kelola proyek yang dihapus secara tidak sengaja. Pulihkan
                    atau biarkan mereka dihapus permanen.
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
                    {{ meta.total }} proyek dihapus
                </span>
                <Link
                    :href="projectsRoute.index.url()"
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
                        placeholder="Cari proyek yang dihapus..."
                        class="input pl-10"
                    />
                </div>

                <div class="grid grid-cols-2 gap-2 lg:flex lg:items-center">
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
                class="group relative z-0 flex min-h-[350px] flex-col p-1.5 transition-all duration-300 focus-within:z-30 hover:z-30 hover:-translate-y-2"
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
                        class="h-full w-full object-cover opacity-60 transition-transform duration-700 ease-out group-hover:scale-105"
                    />
                    <!-- Subtle top sheen and dark gradient at bottom for depth -->
                    <div
                        class="pointer-events-none absolute inset-0 bg-gradient-to-b from-white/10 via-transparent to-black/30"
                    />
                </div>

                <!-- 2. DOCUMENT / PROJECT SHEET (Tucked inside folder, lifts up on hover!) -->
                <div
                    class="pointer-events-none absolute inset-x-6 top-5 z-5 flex h-44 flex-col justify-between rounded-t-[22px] border border-surface-200/90 bg-white p-4 shadow-md transition-transform duration-300 ease-out group-hover:-translate-y-5 dark:border-surface-700 dark:bg-surface-800"
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
                            >Dihapus:
                            <strong
                                class="text-surface-700 dark:text-surface-300"
                                >{{ formatDate(project.deleted_at) }}</strong
                            ></span
                        >
                        <span
                            class="font-semibold text-amber-600 dark:text-amber-400"
                        >
                            Terhapus
                        </span>
                    </div>
                </div>

                <!-- 3. FRONT FLAP (Frosted Glass Acrylic or Sleek Dark Matte) -->
                <div
                    class="pointer-events-auto relative z-30 -mt-16 flex flex-1 flex-col justify-between rounded-[26px] border border-white/70 bg-white/88 p-5 text-surface-900 shadow-[0_16px_36px_rgba(15,81,50,0.12)] backdrop-blur-2xl transition-all duration-300 dark:border-white/10 dark:bg-surface-900/90 dark:text-white"
                >
                    <!-- Specular Top Rim Refraction Edge -->
                    <div
                        class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-white/80 to-transparent dark:via-white/30"
                    />

                    <div>
                        <!-- Folder Tab Header: Deleted Date & Status -->
                        <div class="flex items-center justify-between">
                            <div
                                class="text-xs font-medium tracking-tight text-surface-500 dark:text-surface-400"
                            >
                                <span>{{
                                    formatRelativeTime(project.deleted_at)
                                }}</span>
                                <span class="mx-1.5 opacity-60">•</span>
                                <span
                                    class="font-semibold text-surface-800 dark:text-surface-200"
                                >
                                    Terhapus
                                </span>
                            </div>

                            <span
                                class="rounded-full bg-amber-100 px-2.5 py-0.5 text-[11px] font-medium text-amber-700 dark:bg-amber-900/30 dark:text-amber-300"
                            >
                                {{ project.priority_label }}
                            </span>
                        </div>

                        <!-- Project Title -->
                        <Link
                            :href="
                                projectsRoute.show.url({ project: project.id })
                            "
                            class="group/title mt-3 block"
                        >
                            <h2
                                class="line-clamp-1 font-sans text-lg font-bold tracking-tight text-surface-950 transition group-hover/title:text-primary-700 dark:text-surface-50 dark:group-hover/title:text-primary-300"
                            >
                                {{ project.name }}
                            </h2>
                        </Link>

                        <!-- Project Description preview -->
                        <p
                            class="mt-1 line-clamp-2 text-xs leading-relaxed text-surface-500 dark:text-surface-400"
                        >
                            {{
                                project.description ||
                                'Tidak ada catatan tambahan untuk folder proyek ini.'
                            }}
                        </p>
                    </div>

                    <!-- Bottom Flap Row: Avatar Stack on Left & Restore Button on Right -->
                    <div
                        class="mt-5 flex items-center justify-between gap-2 border-t border-surface-200/60 pt-3.5 dark:border-surface-800"
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
                                    class="relative inline-block rounded-full ring-2 ring-white dark:ring-surface-900"
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
                            <span
                                v-if="
                                    project.members &&
                                    project.members.length > 3
                                "
                                class="ml-2.5 text-xs font-semibold text-surface-500 dark:text-surface-400"
                            >
                                +{{ project.members.length - 3 }}
                            </span>
                        </div>

                        <!-- Right: restore action -->
                        <div class="relative z-40">
                            <button
                                v-if="canUpdateProject(project)"
                                type="button"
                                class="grid h-9 w-9 place-items-center rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-600 shadow-2xs transition hover:border-emerald-300 hover:bg-emerald-100 hover:text-emerald-700 active:scale-95 dark:border-emerald-700/40 dark:bg-emerald-900/20 dark:text-emerald-400 dark:hover:bg-emerald-900/30 dark:hover:text-emerald-300"
                                @click="confirmRestore(project)"
                                :aria-label="`Pulihkan proyek ${project.name}`"
                                title="Pulihkan proyek"
                            >
                                <component
                                    :is="getIcon('RotateCcw')"
                                    class="h-4 w-4"
                                />
                            </button>
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
                class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-200"
            >
                <component :is="getIcon('Trash2')" class="h-6 w-6" />
            </div>
            <h2
                class="mt-4 text-lg font-bold text-surface-950 dark:text-surface-50"
            >
                {{
                    hasActiveFilters
                        ? 'Proyek tidak ditemukan'
                        : 'Tidak ada proyek yang dihapus'
                }}
            </h2>
            <p
                class="mx-auto mt-2 max-w-md text-sm leading-6 text-surface-500 dark:text-surface-400"
            >
                {{
                    hasActiveFilters
                        ? 'Coba ubah kata kunci atau filter yang dipilih untuk menemukan proyek yang dicari.'
                        : 'Proyek yang dihapus akan muncul di sini. Klik tombol pulihkan untuk mengembalikan proyek.'
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
                <Link :href="projectsRoute.index.url()" class="btn btn-primary">
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
        title="Pulihkan Proyek"
        @close="restoreModalOpen = false"
    >
        <p class="text-surface-600 dark:text-surface-300">
            Apakah Anda yakin ingin memulihkan proyek
            <strong class="text-surface-900 dark:text-surface-100">{{
                projectToRestore?.name
            }}</strong>
            ?
        </p>
        <p class="mt-2 text-sm text-surface-500 dark:text-surface-400">
            Proyek akan kembali ke daftar proyek aktif beserta semua anggotanya.
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
