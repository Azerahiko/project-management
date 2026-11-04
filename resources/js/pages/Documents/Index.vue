<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Select from '@/components/ui/Select.vue';
import { getIcon } from '@/components/sidebar/icons';
import projectRoutes from '@/routes/projects';
import taskRoutes from '@/routes/tasks';
import {
    Archive,
    ArrowRight,
    ArrowUpRight,
    BookOpen,
    CheckCircle2,
    Clock,
    ExternalLink,
    Files,
    FolderKanban,
    FolderOpen,
    LayoutGrid,
    List,
    Search,
    Sparkles,
} from '@lucide/vue';

defineOptions({
    layout: AppLayout,
});

interface ProjectNote {
    id: number;
    name: string;
    description: string | null;
    status: string;
    status_label: string;
    tasks_count: number;
    completed_tasks_count?: number;
    progress_percent?: number;
    updated_at: string | null;
}

interface DocumentStats {
    total_projects: number;
    documented_projects: number;
    active_documented: number;
    archived_documented: number;
    total_tasks: number;
    completed_tasks: number;
    documentation_rate: number;
}

interface RecentlyUpdatedItem {
    id: number;
    name: string;
    tasks_count: number;
    updated_at: string | null;
}

type LibrarySection = 'all' | 'active' | 'archived';
type ViewMode = 'grid' | 'list';

const page = usePage();
const projectNotes = computed(
    () => (page.props.project_notes as ProjectNote[]) ?? [],
);
const docStats = computed(
    () => page.props.document_stats as DocumentStats | undefined,
);
const recentlyUpdatedList = computed(
    () =>
        (page.props.recently_updated as RecentlyUpdatedItem[] | undefined) ??
        [],
);
const canViewProjects = computed(() => Boolean(page.props.can_view_projects));

const search = ref('');
const activeSection = ref<LibrarySection>('all');
const viewMode = ref<ViewMode>('grid');
const sort = ref<'name' | 'updated'>('name');

const sortOptions = [
    { value: 'name', label: 'Nama A–Z' },
    { value: 'updated', label: 'Terbaru diperbarui' },
];

const statusColors: Record<string, string> = {
    draft: 'bg-surface-100 text-surface-700 dark:bg-surface-800 dark:text-surface-300',
    active: 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300',
    on_hold:
        'bg-accent-100 text-accent-700 dark:bg-accent-900/30 dark:text-accent-300',
    completed:
        'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
    archived:
        'bg-surface-100 text-surface-500 dark:bg-surface-800 dark:text-surface-400',
};

const folderTones = [
    {
        tab: 'bg-primary-500 dark:bg-primary-600',
        icon: 'bg-primary-50 text-primary-700 dark:bg-primary-900/40 dark:text-primary-200',
    },
    {
        tab: 'bg-amber-500 dark:bg-amber-600',
        icon: 'bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200',
    },
    {
        tab: 'bg-sky-500 dark:bg-sky-600',
        icon: 'bg-sky-50 text-sky-800 dark:bg-sky-950/40 dark:text-sky-200',
    },
    {
        tab: 'bg-rose-500 dark:bg-rose-600',
        icon: 'bg-rose-50 text-rose-800 dark:bg-rose-950/40 dark:text-rose-200',
    },
    {
        tab: 'bg-emerald-500 dark:bg-emerald-600',
        icon: 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200',
    },
];

function isArchived(note: ProjectNote): boolean {
    return note.status === 'completed' || note.status === 'archived';
}

function folderTone(index: number) {
    return folderTones[index % folderTones.length];
}

function statusClass(status: string): string {
    return statusColors[status] ?? statusColors.draft;
}

function sortNotes(notes: ProjectNote[]): ProjectNote[] {
    return [...notes].sort((first, second) => {
        if (sort.value === 'updated') {
            return (
                new Date(second.updated_at ?? 0).getTime() -
                new Date(first.updated_at ?? 0).getTime()
            );
        }

        return first.name.localeCompare(second.name);
    });
}

const counts = computed(() => ({
    all: projectNotes.value.length,
    active: projectNotes.value.filter((note) => !isArchived(note)).length,
    archived: projectNotes.value.filter((note) => isArchived(note)).length,
}));

const totalTasks = computed(() =>
    projectNotes.value.reduce((total, note) => total + note.tasks_count, 0),
);

const documentationRate = computed(() => {
    if (docStats.value?.documentation_rate !== undefined) {
        return docStats.value.documentation_rate;
    }
    return 100;
});

const summaryCards = computed(() => [
    {
        label: 'Total Dokumen',
        value: counts.value.all,
        hint: 'Catatan proyek terdaftar',
        icon: Files,
        surface:
            'bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300',
    },
    {
        label: 'Proyek Berjalan',
        value: counts.value.active,
        hint: 'Dokumentasi aktif',
        icon: FolderOpen,
        surface:
            'bg-amber-50 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300',
    },
    {
        label: 'Selesai & Arsip',
        value: counts.value.archived,
        hint: 'Dokumen arsip proyek',
        icon: Archive,
        surface:
            'bg-surface-100 dark:bg-surface-800 text-surface-600 dark:text-surface-300',
    },
    {
        label: 'Tugas Terhubung',
        value: totalTasks.value,
        hint: 'Tugas terdokumentasi',
        icon: CheckCircle2,
        surface:
            'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-300',
    },
]);

const filteredNotes = computed(() => {
    const keyword = search.value.trim().toLowerCase();

    return projectNotes.value.filter((note) => {
        const matchesSearch =
            !keyword ||
            note.name.toLowerCase().includes(keyword) ||
            (note.description ?? '').toLowerCase().includes(keyword);
        const matchesSection =
            activeSection.value === 'all' ||
            (activeSection.value === 'archived'
                ? isArchived(note)
                : !isArchived(note));

        return matchesSearch && matchesSection;
    });
});

const groups = computed(() => {
    const active = sortNotes(
        filteredNotes.value.filter((note) => !isArchived(note)),
    );
    const archived = sortNotes(
        filteredNotes.value.filter((note) => isArchived(note)),
    );
    const result: {
        key: string;
        label: string;
        description: string;
        icon: any;
        items: ProjectNote[];
    }[] = [];

    if (active.length > 0) {
        result.push({
            key: 'active',
            label: 'Proyek Berjalan',
            description:
                'Catatan proyek yang sedang dalam eksekusi aktif, perencanaan, atau review.',
            icon: FolderKanban,
            items: active,
        });
    }

    if (archived.length > 0) {
        result.push({
            key: 'archived',
            label: 'Selesai & Arsip',
            description:
                'Catatan proyek yang telah tuntas atau disimpan sebagai referensi masa lalu.',
            icon: Archive,
            items: archived,
        });
    }

    return result;
});

function formatRelativeTime(value: string | null): string {
    if (!value) return 'Baru saja';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return 'Baru saja';

    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMinutes = Math.floor(diffMs / 60_000);
    const diffHours = Math.floor(diffMinutes / 60);
    const diffDays = Math.floor(diffHours / 24);

    if (diffMinutes < 1) return 'Baru saja';
    if (diffMinutes < 60) return `${diffMinutes}m lalu`;
    if (diffHours < 24) return `${diffHours}j lalu`;
    if (diffDays === 1) return 'Kemarin';
    if (diffDays < 30) return `${diffDays} hari lalu`;

    return date.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}
</script>

<template>
    <div class="space-y-6">
        <!-- Editorial Header -->
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <span
                    class="inline-flex h-6 items-center gap-1.5 rounded-full border border-primary-200 bg-primary-50 px-2.5 text-[10px] font-bold tracking-wider text-primary-700 uppercase dark:border-primary-700/40 dark:bg-primary-900/25 dark:text-primary-200"
                >
                    <Files class="h-3 w-3" />
                    Pusat Berkas & Dokumen
                </span>
                <h1
                    class="mt-2 text-2xl font-semibold tracking-tight text-surface-950 sm:text-3xl dark:text-surface-50"
                >
                    Berkas Dokumen
                </h1>
                <p
                    class="mt-1 max-w-xl text-sm leading-6 text-surface-600 dark:text-surface-300"
                >
                    Dokumentasi catatan, ringkasan lingkup, dan konteks proyek.
                    Semua berkas tersusun rapi untuk referensi cepat seluruh
                    tim.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Link :href="taskRoutes.index.url()" class="btn btn-secondary">
                    <CheckCircle2 class="h-4 w-4" />
                    Daftar Tugas
                </Link>
                <Link :href="projectRoutes.index.url()" class="btn btn-primary">
                    <FolderKanban class="h-4 w-4" />
                    Semua Proyek
                </Link>
            </div>
        </div>

        <!-- Pulse Strip (4 Summary Metric Cards - Sejajar & Rapi) -->
        <section
            class="grid grid-cols-2 gap-px overflow-hidden rounded-[28px] border border-surface-200/80 bg-surface-200/60 shadow-xs sm:grid-cols-4 dark:border-surface-800 dark:bg-surface-800"
        >
            <div
                v-for="card in summaryCards"
                :key="card.label"
                class="bg-white p-4 dark:bg-surface-900"
            >
                <div class="flex items-center justify-between gap-2">
                    <p
                        class="text-[10px] font-bold tracking-[0.14em] text-surface-400 uppercase dark:text-surface-500"
                    >
                        {{ card.label }}
                    </p>
                    <span
                        class="grid h-7 w-7 place-items-center rounded-lg"
                        :class="card.surface"
                    >
                        <component :is="card.icon" class="h-3.5 w-3.5" />
                    </span>
                </div>
                <p
                    class="mt-2 text-2xl leading-none font-bold text-surface-950 dark:text-surface-50"
                >
                    {{ card.value }}
                </p>
                <p
                    class="mt-1.5 text-[10px] text-surface-400 dark:text-surface-500"
                >
                    {{ card.hint }}
                </p>
            </div>
        </section>

        <!-- Main Workspace Grid (Left Sidebar + Right Library - Sejajar & Padat) -->
        <div class="grid items-start gap-6 lg:grid-cols-12">
            <!-- Left Sidebar (lg:col-span-4 xl:col-span-3 - Sticky & Padat) -->
            <aside
                class="sticky top-6 space-y-4 self-start lg:col-span-4 xl:col-span-3"
            >
                <!-- Navigasi Perpustakaan -->
                <div
                    class="rounded-[24px] border border-surface-200/80 bg-white p-4 shadow-xs dark:border-surface-800 dark:bg-surface-900"
                >
                    <p
                        class="px-2 text-[10px] font-bold tracking-[0.16em] text-surface-400 uppercase dark:text-surface-500"
                    >
                        Perpustakaan
                    </p>
                    <nav class="mt-2.5 space-y-1">
                        <button
                            type="button"
                            class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-left text-xs font-semibold transition"
                            :class="
                                activeSection === 'all'
                                    ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-200'
                                    : 'text-surface-600 hover:bg-surface-50 hover:text-surface-900 dark:text-surface-300 dark:hover:bg-surface-800/60 dark:hover:text-white'
                            "
                            @click="activeSection = 'all'"
                        >
                            <Files class="h-3.5 w-3.5" />
                            Semua Berkas
                            <span
                                class="ml-auto rounded-full bg-white px-2 py-0.5 text-[10px] font-bold text-surface-500 ring-1 ring-surface-200 dark:bg-surface-800 dark:text-surface-300 dark:ring-surface-700"
                            >
                                {{ counts.all }}
                            </span>
                        </button>

                        <button
                            type="button"
                            class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-left text-xs font-semibold transition"
                            :class="
                                activeSection === 'active'
                                    ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-200'
                                    : 'text-surface-600 hover:bg-surface-50 hover:text-surface-900 dark:text-surface-300 dark:hover:bg-surface-800/60 dark:hover:text-white'
                            "
                            @click="activeSection = 'active'"
                        >
                            <FolderOpen class="h-3.5 w-3.5" />
                            Proyek Berjalan
                            <span
                                class="ml-auto rounded-full bg-white px-2 py-0.5 text-[10px] font-bold text-surface-500 ring-1 ring-surface-200 dark:bg-surface-800 dark:text-surface-300 dark:ring-surface-700"
                            >
                                {{ counts.active }}
                            </span>
                        </button>

                        <button
                            type="button"
                            class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2 text-left text-xs font-semibold transition"
                            :class="
                                activeSection === 'archived'
                                    ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-200'
                                    : 'text-surface-600 hover:bg-surface-50 hover:text-surface-900 dark:text-surface-300 dark:hover:bg-surface-800/60 dark:hover:text-white'
                            "
                            @click="activeSection = 'archived'"
                        >
                            <Archive class="h-3.5 w-3.5" />
                            Selesai & Arsip
                            <span
                                class="ml-auto rounded-full bg-white px-2 py-0.5 text-[10px] font-bold text-surface-500 ring-1 ring-surface-200 dark:bg-surface-800 dark:text-surface-300 dark:ring-surface-700"
                            >
                                {{ counts.archived }}
                            </span>
                        </button>
                    </nav>
                </div>

                <!-- Ringkasan Statistik Berkas -->
                <div
                    class="rounded-[24px] border border-surface-200/80 bg-white p-4 shadow-xs dark:border-surface-800 dark:bg-surface-900"
                >
                    <p
                        class="px-2 text-[10px] font-bold tracking-[0.16em] text-surface-400 uppercase dark:text-surface-500"
                    >
                        Kualitas Dokumentasi
                    </p>
                    <div class="mt-3 space-y-3 px-2">
                        <div>
                            <div
                                class="flex items-center justify-between text-xs"
                            >
                                <span
                                    class="text-surface-500 dark:text-surface-400"
                                    >Kelengkapan Catatan</span
                                >
                                <span
                                    class="font-bold text-surface-900 dark:text-surface-100"
                                >
                                    {{ documentationRate }}%
                                </span>
                            </div>
                            <div
                                class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-surface-100 dark:bg-surface-800"
                            >
                                <div
                                    class="h-full rounded-full bg-primary-600 dark:bg-primary-400"
                                    :style="{ width: `${documentationRate}%` }"
                                />
                            </div>
                        </div>

                        <div
                            class="flex items-center justify-between pt-1 text-xs"
                        >
                            <span class="text-surface-500 dark:text-surface-400"
                                >Total Tugas Terkait</span
                            >
                            <span
                                class="font-semibold text-surface-900 dark:text-surface-100"
                            >
                                {{ totalTasks }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between text-xs">
                            <span class="text-surface-500 dark:text-surface-400"
                                >Rata-rata Tugas/Berkas</span
                            >
                            <span
                                class="font-semibold text-surface-900 dark:text-surface-100"
                            >
                                {{
                                    counts.all > 0
                                        ? (totalTasks / counts.all).toFixed(1)
                                        : 0
                                }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Berkas Baru Diperbarui -->
                <div
                    v-if="recentlyUpdatedList.length > 0"
                    class="rounded-[24px] border border-surface-200/80 bg-white p-4 shadow-xs dark:border-surface-800 dark:bg-surface-900"
                >
                    <div class="flex items-center justify-between px-2 pb-2">
                        <p
                            class="text-[10px] font-bold tracking-[0.16em] text-surface-400 uppercase dark:text-surface-500"
                        >
                            Baru Diperbarui
                        </p>
                        <Clock class="h-3 w-3 text-surface-400" />
                    </div>

                    <div
                        class="divide-y divide-surface-100 dark:divide-surface-800"
                    >
                        <component
                            :is="canViewProjects ? Link : 'div'"
                            v-for="item in recentlyUpdatedList"
                            :key="item.id"
                            :href="
                                canViewProjects
                                    ? projectRoutes.show.url({
                                          project: item.id,
                                      })
                                    : undefined
                            "
                            class="group flex items-center justify-between py-2 text-xs transition"
                        >
                            <div class="min-w-0 flex-1 pr-2">
                                <p
                                    class="truncate font-medium text-surface-800 transition group-hover:text-primary-700 dark:text-surface-200 dark:group-hover:text-primary-300"
                                >
                                    {{ item.name }}
                                </p>
                                <p class="text-[10px] text-surface-400">
                                    {{ item.tasks_count }} tugas ·
                                    {{ formatRelativeTime(item.updated_at) }}
                                </p>
                            </div>
                            <ArrowUpRight
                                class="h-3.5 w-3.5 shrink-0 text-surface-400 transition group-hover:text-primary-700 dark:group-hover:text-primary-300"
                            />
                        </component>
                    </div>
                </div>

                <!-- Panduan Dokumentasi Proyek -->
                <div
                    class="rounded-[24px] border border-dashed border-surface-200/80 bg-surface-50/50 p-4 dark:border-surface-800 dark:bg-surface-900/40"
                >
                    <div class="flex items-center gap-2">
                        <span
                            class="grid h-6 w-6 place-items-center rounded-lg bg-primary-100 text-primary-700 dark:bg-primary-900/40 dark:text-primary-300"
                        >
                            <BookOpen class="h-3 w-3" />
                        </span>
                        <p
                            class="text-xs font-semibold text-surface-800 dark:text-surface-100"
                        >
                            Struktur Catatan Proyek
                        </p>
                    </div>
                    <p
                        class="mt-2 text-[11px] leading-relaxed text-surface-500 dark:text-surface-400"
                    >
                        Pastikan setiap folder memuat tujuan spesifikasi,
                        milestone target, dan daftar PIC agar alur kerja
                        sinkron.
                    </p>
                </div>
            </aside>

            <!-- Right Library Content (lg:col-span-8 xl:col-span-9 - Sejajar, Rapi & Rata) -->
            <section class="min-w-0 space-y-5 lg:col-span-8 xl:col-span-9">
                <!-- Search & Sort Filter Bar -->
                <div
                    class="flex flex-col gap-3 rounded-2xl border border-surface-200/80 bg-white p-3 shadow-xs sm:flex-row sm:items-center dark:border-surface-800 dark:bg-surface-900"
                >
                    <div class="relative min-w-0 flex-1">
                        <Search
                            class="pointer-events-none absolute inset-y-0 left-3.5 my-auto h-4 w-4 text-surface-400 dark:text-surface-500"
                        />
                        <input
                            v-model="search"
                            type="search"
                            class="input"
                            style="padding-left: 2.5rem"
                            placeholder="Cari folder berkas atau catatan proyek..."
                            aria-label="Cari folder berkas atau catatan proyek"
                        />
                    </div>

                    <div class="flex items-center gap-2">
                        <Select
                            v-model="sort"
                            :options="sortOptions"
                            class="w-full sm:w-44"
                        />
                        <div
                            class="inline-flex shrink-0 rounded-xl border border-surface-200 bg-surface-50 p-1 dark:border-surface-700 dark:bg-surface-800"
                        >
                            <button
                                type="button"
                                class="grid h-8 w-8 place-items-center rounded-lg transition"
                                :class="
                                    viewMode === 'grid'
                                        ? 'bg-white text-surface-900 shadow-xs dark:bg-surface-700 dark:text-white'
                                        : 'text-surface-400 hover:text-surface-700 dark:hover:text-surface-200'
                                "
                                aria-label="Tampilan grid"
                                :aria-pressed="viewMode === 'grid'"
                                @click="viewMode = 'grid'"
                            >
                                <LayoutGrid class="h-4 w-4" />
                            </button>
                            <button
                                type="button"
                                class="grid h-8 w-8 place-items-center rounded-lg transition"
                                :class="
                                    viewMode === 'list'
                                        ? 'bg-white text-surface-900 shadow-xs dark:bg-surface-700 dark:text-white'
                                        : 'text-surface-400 hover:text-surface-700 dark:hover:text-surface-200'
                                "
                                aria-label="Tampilan daftar"
                                :aria-pressed="viewMode === 'list'"
                                @click="viewMode = 'list'"
                            >
                                <List class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Folder Groups -->
                <div v-for="group in groups" :key="group.key" class="space-y-4">
                    <div
                        class="flex items-end justify-between gap-3 border-b border-surface-100 pb-3 dark:border-surface-800"
                    >
                        <div class="flex items-center gap-2.5">
                            <span
                                class="grid h-8 w-8 place-items-center rounded-xl bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-300"
                            >
                                <component :is="group.icon" class="h-4 w-4" />
                            </span>
                            <div>
                                <h2
                                    class="text-sm font-semibold text-surface-950 dark:text-surface-50"
                                >
                                    {{ group.label }}
                                </h2>
                                <p
                                    class="text-[11px] text-surface-400 dark:text-surface-500"
                                >
                                    {{ group.description }}
                                </p>
                            </div>
                        </div>
                        <span
                            class="shrink-0 rounded-full bg-surface-100 px-2.5 py-1 text-[10px] font-bold text-surface-600 dark:bg-surface-800 dark:text-surface-300"
                        >
                            {{ group.items.length }} folder
                        </span>
                    </div>

                    <!-- Grid View Mode (Sejajar, Rata & Padat) -->
                    <div
                        v-if="viewMode === 'grid'"
                        class="grid items-stretch gap-4.5 sm:grid-cols-2 xl:grid-cols-3"
                    >
                        <article
                            v-for="(note, index) in group.items"
                            :key="note.id"
                            class="group relative flex flex-col pt-3"
                        >
                            <!-- Tab Top Indicator -->
                            <div
                                class="absolute top-0 left-4 h-4 w-24 rounded-t-xl"
                                :class="folderTone(index).tab"
                            />

                            <!-- Card Body -->
                            <component
                                :is="canViewProjects ? Link : 'div'"
                                :href="
                                    canViewProjects
                                        ? projectRoutes.show.url({
                                              project: note.id,
                                          })
                                        : undefined
                                "
                                class="relative flex h-full flex-col justify-between rounded-2xl border border-surface-200/80 bg-white p-5 shadow-xs transition duration-200 hover:-translate-y-0.5 hover:border-primary-300 hover:shadow-md dark:border-surface-800 dark:bg-surface-900 dark:hover:border-primary-700/60"
                            >
                                <div>
                                    <!-- Header: Folder Icon + Status Pill -->
                                    <div
                                        class="flex items-start justify-between gap-2"
                                    >
                                        <span
                                            class="grid h-10 w-10 place-items-center rounded-xl"
                                            :class="folderTone(index).icon"
                                        >
                                            <FolderOpen class="h-5 w-5" />
                                        </span>
                                        <span
                                            class="rounded-full px-2.5 py-0.5 text-[10px] font-bold"
                                            :class="statusClass(note.status)"
                                        >
                                            {{ note.status_label }}
                                        </span>
                                    </div>

                                    <!-- Project Title -->
                                    <h3
                                        class="mt-3.5 line-clamp-1 text-sm font-bold text-surface-950 transition group-hover:text-primary-700 dark:text-surface-50 dark:group-hover:text-primary-300"
                                    >
                                        {{ note.name }}
                                    </h3>

                                    <!-- Description (Equal Height Baseline) -->
                                    <p
                                        class="mt-1.5 line-clamp-3 min-h-[3.75rem] text-xs leading-relaxed text-surface-500 dark:text-surface-400"
                                    >
                                        {{
                                            note.description ||
                                            'Tidak ada deskripsi tambahan untuk berkas proyek ini.'
                                        }}
                                    </p>

                                    <!-- Mini Progress Bar (Tugas Selesai) -->
                                    <div
                                        class="mt-3 rounded-xl border border-surface-100 bg-surface-50/70 p-2.5 dark:border-surface-800 dark:bg-surface-800/40"
                                    >
                                        <div
                                            class="flex items-center justify-between text-[10px]"
                                        >
                                            <span class="text-surface-500"
                                                >Penyelesaian Tugas</span
                                            >
                                            <span
                                                class="font-semibold text-surface-800 dark:text-surface-200"
                                            >
                                                {{
                                                    note.completed_tasks_count ??
                                                    0
                                                }}
                                                / {{ note.tasks_count }} ({{
                                                    note.progress_percent ?? 0
                                                }}%)
                                            </span>
                                        </div>
                                        <div
                                            class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-surface-200/80 dark:bg-surface-700"
                                        >
                                            <div
                                                class="h-full rounded-full bg-primary-600 transition-all duration-300 dark:bg-primary-400"
                                                :style="{
                                                    width: `${note.progress_percent ?? 0}%`,
                                                }"
                                            />
                                        </div>
                                    </div>
                                </div>

                                <!-- Footer -->
                                <div
                                    class="mt-4 border-t border-surface-100 pt-3 dark:border-surface-800"
                                >
                                    <div
                                        class="flex items-center justify-between text-[10px] text-surface-400 dark:text-surface-500"
                                    >
                                        <span
                                            class="inline-flex items-center gap-1"
                                        >
                                            <CheckCircle2 class="h-3 w-3" />
                                            {{ note.tasks_count }} tugas
                                        </span>
                                        <span
                                            class="inline-flex items-center gap-1"
                                        >
                                            <Clock class="h-3 w-3" />
                                            {{
                                                formatRelativeTime(
                                                    note.updated_at,
                                                )
                                            }}
                                        </span>
                                    </div>

                                    <div
                                        class="mt-2.5 flex items-center justify-between"
                                    >
                                        <span
                                            class="text-[10px] font-semibold tracking-wider text-surface-400 uppercase"
                                        >
                                            Catatan Proyek
                                        </span>
                                        <span
                                            class="inline-flex items-center gap-1 text-[11px] font-semibold text-primary-700 transition group-hover:translate-x-0.5 dark:text-primary-300"
                                        >
                                            Buka Folder
                                            <ArrowUpRight class="h-3 w-3" />
                                        </span>
                                    </div>
                                </div>
                            </component>
                        </article>
                    </div>

                    <!-- List View Mode (Sejajar & Rata) -->
                    <div
                        v-else
                        class="overflow-hidden rounded-2xl border border-surface-200/80 bg-white shadow-xs dark:border-surface-800 dark:bg-surface-900"
                    >
                        <component
                            :is="canViewProjects ? Link : 'div'"
                            v-for="(note, index) in group.items"
                            :key="note.id"
                            :href="
                                canViewProjects
                                    ? projectRoutes.show.url({
                                          project: note.id,
                                      })
                                    : undefined
                            "
                            class="group flex flex-col gap-3 border-b border-surface-100 p-4 transition last:border-b-0 hover:bg-surface-50/70 sm:flex-row sm:items-center dark:border-surface-800 dark:hover:bg-surface-800/40"
                        >
                            <div class="flex min-w-0 flex-1 items-center gap-3">
                                <span
                                    class="grid h-10 w-10 shrink-0 place-items-center rounded-xl"
                                    :class="folderTone(index).icon"
                                >
                                    <FolderOpen class="h-4 w-4" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <p
                                            class="truncate text-sm font-semibold text-surface-900 group-hover:text-primary-700 dark:text-surface-100 dark:group-hover:text-primary-300"
                                        >
                                            {{ note.name }}
                                        </p>
                                        <span
                                            class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold"
                                            :class="statusClass(note.status)"
                                        >
                                            {{ note.status_label }}
                                        </span>
                                    </div>
                                    <p
                                        class="mt-0.5 line-clamp-1 text-xs text-surface-500 dark:text-surface-400"
                                    >
                                        {{ note.description }}
                                    </p>
                                </div>
                            </div>

                            <div
                                class="flex shrink-0 items-center justify-between gap-4 sm:justify-end"
                            >
                                <div
                                    class="text-right text-[11px] text-surface-400"
                                >
                                    <p
                                        class="font-medium text-surface-700 dark:text-surface-200"
                                    >
                                        {{ note.tasks_count }} tugas ({{
                                            note.progress_percent ?? 0
                                        }}% selesai)
                                    </p>
                                    <p>
                                        {{
                                            formatRelativeTime(note.updated_at)
                                        }}
                                    </p>
                                </div>

                                <span
                                    class="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-surface-400 transition group-hover:bg-primary-50 group-hover:text-primary-700 dark:group-hover:bg-primary-900/30 dark:group-hover:text-primary-300"
                                >
                                    <ArrowUpRight class="h-4 w-4" />
                                </span>
                            </div>
                        </component>
                    </div>
                </div>

                <!-- Empty State -->
                <EmptyState
                    v-if="groups.length === 0"
                    icon="FileText"
                    title="Belum ada folder yang cocok"
                    :description="
                        search
                            ? 'Tidak ada folder atau catatan proyek yang sesuai dengan pencarian Anda. Coba kata kunci lain.'
                            : 'Belum ada catatan berkas pada bagian ini. Berkas akan otomatis muncul saat proyek memiliki ringkasan catatan.'
                    "
                >
                    <button
                        v-if="search"
                        type="button"
                        class="btn btn-secondary mt-5"
                        @click="search = ''"
                    >
                        Hapus pencarian
                    </button>
                    <button
                        v-else-if="activeSection !== 'all'"
                        type="button"
                        class="btn btn-secondary mt-5"
                        @click="activeSection = 'all'"
                    >
                        Lihat semua berkas
                    </button>
                </EmptyState>
            </section>
        </div>
    </div>
</template>
