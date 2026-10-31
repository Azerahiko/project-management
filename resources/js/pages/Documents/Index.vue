<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Select from '@/components/ui/Select.vue';
import { getIcon } from '@/components/sidebar/icons';
import projectsRoute from '@/routes/projects';
import {
    ArrowUpRight,
    Archive,
    Clock,
    Files,
    FolderOpen,
    LayoutGrid,
    List,
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
    updated_at: string | null;
}

type LibrarySection = 'all' | 'active' | 'archived';
type ViewMode = 'grid' | 'list';

const page = usePage();
const projectNotes = computed(
    () => (page.props.project_notes as ProjectNote[]) ?? [],
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
        'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300',
    archived:
        'bg-surface-100 text-surface-500 dark:bg-surface-800 dark:text-surface-400',
};

const folderTones = [
    {
        tab: 'bg-primary-200 dark:bg-primary-800/70',
        icon: 'bg-primary-100 text-primary-700 dark:bg-primary-900/40 dark:text-primary-200',
    },
    {
        tab: 'bg-[#ead9ad] dark:bg-amber-900/50',
        icon: 'bg-[#f7eed6] text-[#7a5a14] dark:bg-amber-950/40 dark:text-amber-200',
    },
    {
        tab: 'bg-[#c9dcec] dark:bg-sky-900/50',
        icon: 'bg-[#e7f0f9] text-[#28506f] dark:bg-sky-950/40 dark:text-sky-200',
    },
    {
        tab: 'bg-[#eccbd8] dark:bg-rose-900/50',
        icon: 'bg-[#f8e6ed] text-[#7b3a54] dark:bg-rose-950/40 dark:text-rose-200',
    },
    {
        tab: 'bg-surface-300 dark:bg-surface-700',
        icon: 'bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-300',
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
        icon: string;
        items: ProjectNote[];
    }[] = [];

    if (active.length > 0) {
        result.push({
            key: 'active',
            label: 'Proyek Berjalan',
            description:
                'Catatan proyek yang masih aktif, dalam perencanaan, atau ditahan.',
            icon: 'FolderKanban',
            items: active,
        });
    }

    if (archived.length > 0) {
        result.push({
            key: 'archived',
            label: 'Selesai & Arsip',
            description:
                'Catatan proyek yang sudah selesai atau disimpan sebagai arsip.',
            icon: 'Inbox',
            items: archived,
        });
    }

    return result;
});

function formatRelativeTime(value: string | null): string {
    if (!value) {
        return 'Baru saja';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return 'Baru saja';
    }

    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMinutes = Math.floor(diffMs / 60_000);
    const diffHours = Math.floor(diffMinutes / 60);
    const diffDays = Math.floor(diffHours / 24);

    if (diffMinutes < 1) {
        return 'Baru saja';
    }

    if (diffMinutes < 60) {
        return `${diffMinutes} menit lalu`;
    }

    if (diffHours < 24) {
        return `${diffHours} jam lalu`;
    }

    if (diffDays === 1) {
        return 'Kemarin';
    }

    if (diffDays < 30) {
        return `${diffDays} hari lalu`;
    }

    return date.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
    });
}
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
                    <Files class="h-3 w-3" />
                    Pusat Berkas
                </span>
                <h1
                    class="mt-2 text-2xl font-semibold tracking-tight text-surface-950 sm:text-3xl dark:text-surface-50"
                >
                    Berkas Dokumen
                </h1>
                <p
                    class="mt-1 max-w-2xl text-sm leading-6 text-surface-700 dark:text-surface-200"
                >
                    Kumpulan catatan dan dokumen proyek yang tersusun rapi dalam
                    folder.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span
                    class="inline-flex items-center gap-2 rounded-xl border border-surface-200 bg-white px-3 py-2 text-xs font-semibold text-surface-600 dark:border-surface-700 dark:bg-surface-900 dark:text-surface-300"
                >
                    <FolderOpen class="h-4 w-4 text-primary-600" />
                    {{ counts.all }} folder
                </span>
                <span
                    class="inline-flex items-center gap-2 rounded-xl border border-surface-200 bg-white px-3 py-2 text-xs font-semibold text-surface-600 dark:border-surface-700 dark:bg-surface-900 dark:text-surface-300"
                >
                    <component
                        :is="getIcon('CheckSquare')"
                        class="h-4 w-4 text-primary-600"
                    />
                    {{ totalTasks }} tugas
                </span>
            </div>
        </div>

        <div class="grid gap-5 xl:grid-cols-[260px_minmax(0,1fr)]">
            <!-- Sidebar -->
            <aside class="space-y-4">
                <div class="card !p-4">
                    <p
                        class="px-2 text-[10px] font-bold tracking-[0.16em] text-surface-400 uppercase dark:text-surface-500"
                    >
                        Perpustakaan
                    </p>
                    <nav class="mt-2 space-y-1">
                        <button
                            type="button"
                            class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2.5 text-left text-sm font-semibold transition-colors"
                            :class="
                                activeSection === 'all'
                                    ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/25 dark:text-primary-200'
                                    : 'text-surface-600 hover:bg-surface-50 hover:text-surface-900 dark:text-surface-300 dark:hover:bg-surface-800/60 dark:hover:text-white'
                            "
                            @click="activeSection = 'all'"
                        >
                            <Files class="h-4 w-4" />
                            Semua Berkas
                            <span
                                class="ml-auto rounded-full bg-white px-2 py-0.5 text-[10px] font-bold text-surface-500 ring-1 ring-surface-200 dark:bg-surface-800 dark:text-surface-300 dark:ring-surface-700"
                            >
                                {{ counts.all }}
                            </span>
                        </button>
                        <button
                            type="button"
                            class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2.5 text-left text-sm font-semibold transition-colors"
                            :class="
                                activeSection === 'active'
                                    ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/25 dark:text-primary-200'
                                    : 'text-surface-600 hover:bg-surface-50 hover:text-surface-900 dark:text-surface-300 dark:hover:bg-surface-800/60 dark:hover:text-white'
                            "
                            @click="activeSection = 'active'"
                        >
                            <FolderOpen class="h-4 w-4" />
                            Proyek Berjalan
                            <span
                                class="ml-auto rounded-full bg-white px-2 py-0.5 text-[10px] font-bold text-surface-500 ring-1 ring-surface-200 dark:bg-surface-800 dark:text-surface-300 dark:ring-surface-700"
                            >
                                {{ counts.active }}
                            </span>
                        </button>
                        <button
                            type="button"
                            class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2.5 text-left text-sm font-semibold transition-colors"
                            :class="
                                activeSection === 'archived'
                                    ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/25 dark:text-primary-200'
                                    : 'text-surface-600 hover:bg-surface-50 hover:text-surface-900 dark:text-surface-300 dark:hover:bg-surface-800/60 dark:hover:text-white'
                            "
                            @click="activeSection = 'archived'"
                        >
                            <Archive class="h-4 w-4" />
                            Selesai & Arsip
                            <span
                                class="ml-auto rounded-full bg-white px-2 py-0.5 text-[10px] font-bold text-surface-500 ring-1 ring-surface-200 dark:bg-surface-800 dark:text-surface-300 dark:ring-surface-700"
                            >
                                {{ counts.archived }}
                            </span>
                        </button>
                    </nav>
                </div>

                <div class="card !p-4">
                    <p
                        class="px-2 text-[10px] font-bold tracking-[0.16em] text-surface-400 uppercase dark:text-surface-500"
                    >
                        Ringkasan
                    </p>
                    <div class="mt-3 space-y-3">
                        <div class="flex items-center justify-between gap-3">
                            <span
                                class="text-xs text-surface-500 dark:text-surface-400"
                                >Total catatan</span
                            >
                            <span
                                class="text-sm font-bold text-surface-900 dark:text-surface-100"
                                >{{ counts.all }}</span
                            >
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span
                                class="text-xs text-surface-500 dark:text-surface-400"
                                >Proyek berjalan</span
                            >
                            <span
                                class="text-sm font-bold text-primary-700 dark:text-primary-300"
                                >{{ counts.active }}</span
                            >
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span
                                class="text-xs text-surface-500 dark:text-surface-400"
                                >Selesai & arsip</span
                            >
                            <span
                                class="text-sm font-bold text-surface-700 dark:text-surface-200"
                                >{{ counts.archived }}</span
                            >
                        </div>
                    </div>
                </div>

                <div
                    class="rounded-2xl border border-dashed border-surface-300 p-4 dark:border-surface-700"
                >
                    <FolderOpen
                        class="h-5 w-5 text-primary-600 dark:text-primary-300"
                    />
                    <p
                        class="mt-2 text-xs font-semibold text-surface-800 dark:text-surface-100"
                    >
                        Folder dari catatan proyek
                    </p>
                    <p
                        class="mt-1 text-[11px] leading-5 text-surface-500 dark:text-surface-400"
                    >
                        Setiap folder mewakili catatan proyek. Buka folder untuk
                        melihat detail proyek dan tugas terkait.
                    </p>
                </div>
            </aside>

            <!-- Library -->
            <section class="min-w-0 space-y-5">
                <div
                    class="flex flex-col gap-3 rounded-2xl border border-surface-200 bg-white p-3 shadow-xs sm:flex-row sm:items-center dark:border-surface-700 dark:bg-surface-900"
                >
                    <div class="relative min-w-0 flex-1">
                        <span
                            class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-surface-400 dark:text-surface-500"
                        >
                            <component
                                :is="getIcon('Search')"
                                class="h-4 w-4"
                            />
                        </span>
                        <input
                            v-model="search"
                            type="search"
                            class="input"
                            style="padding-left: 2.5rem"
                            placeholder="Cari folder atau catatan proyek..."
                            aria-label="Cari folder atau catatan proyek"
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
                                        ? 'bg-white text-surface-900 shadow-sm dark:bg-surface-700 dark:text-white'
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
                                        ? 'bg-white text-surface-900 shadow-sm dark:bg-surface-700 dark:text-white'
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

                <div v-for="group in groups" :key="group.key" class="space-y-3">
                    <div class="flex items-end justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span
                                class="grid h-9 w-9 place-items-center rounded-xl bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-300"
                            >
                                <component
                                    :is="getIcon(group.icon)"
                                    class="h-4 w-4"
                                />
                            </span>
                            <div>
                                <h2
                                    class="text-sm font-semibold text-surface-950 dark:text-surface-50"
                                >
                                    {{ group.label }}
                                </h2>
                                <p
                                    class="mt-0.5 text-xs text-surface-500 dark:text-surface-400"
                                >
                                    {{ group.description }}
                                </p>
                            </div>
                        </div>
                        <span
                            class="shrink-0 rounded-full bg-surface-100 px-2.5 py-1 text-[10px] font-bold text-surface-500 dark:bg-surface-800 dark:text-surface-300"
                        >
                            {{ group.items.length }} folder
                        </span>
                    </div>

                    <!-- Grid -->
                    <div
                        v-if="viewMode === 'grid'"
                        class="grid gap-4 sm:grid-cols-2 2xl:grid-cols-3"
                    >
                        <article
                            v-for="(note, index) in group.items"
                            :key="note.id"
                            class="group relative pt-3"
                        >
                            <div
                                class="absolute top-0 left-4 h-5 w-24 rounded-t-xl"
                                :class="folderTone(index).tab"
                            />
                            <component
                                :is="canViewProjects ? Link : 'div'"
                                :href="
                                    canViewProjects
                                        ? projectsRoute.show.url({
                                              project: note.id,
                                          })
                                        : undefined
                                "
                                class="relative flex h-full flex-col rounded-2xl border border-surface-200 bg-white p-4 shadow-xs transition-all duration-300 hover:-translate-y-0.5 hover:border-primary-300 hover:shadow-md dark:border-surface-700 dark:bg-surface-900 dark:hover:border-primary-600/50"
                            >
                                <div
                                    class="flex items-start justify-between gap-2"
                                >
                                    <span
                                        class="grid h-11 w-11 place-items-center rounded-xl"
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

                                <h3
                                    class="mt-4 line-clamp-1 text-sm font-bold text-surface-950 transition group-hover:text-primary-700 dark:text-surface-50 dark:group-hover:text-primary-300"
                                >
                                    {{ note.name }}
                                </h3>
                                <p
                                    class="mt-1.5 line-clamp-3 text-xs leading-5 text-surface-500 dark:text-surface-400"
                                >
                                    {{ note.description }}
                                </p>

                                <div
                                    class="mt-auto flex items-center justify-between gap-2 border-t border-surface-100 pt-3 text-[11px] text-surface-500 dark:border-surface-800 dark:text-surface-400"
                                >
                                    <span
                                        class="inline-flex items-center gap-1.5"
                                    >
                                        <component
                                            :is="getIcon('CheckSquare')"
                                            class="h-3.5 w-3.5"
                                        />
                                        {{ note.tasks_count }} tugas
                                    </span>
                                    <span
                                        class="inline-flex items-center gap-1.5"
                                    >
                                        <Clock class="h-3.5 w-3.5" />
                                        {{
                                            formatRelativeTime(note.updated_at)
                                        }}
                                    </span>
                                </div>

                                <div
                                    class="mt-3 flex items-center justify-between"
                                >
                                    <span
                                        class="text-[10px] font-semibold tracking-wide text-surface-400 uppercase"
                                    >
                                        Catatan proyek
                                    </span>
                                    <span
                                        class="inline-flex items-center gap-1 text-[11px] font-semibold text-primary-700 opacity-0 transition group-hover:opacity-100 dark:text-primary-300"
                                    >
                                        Buka
                                        <ArrowUpRight class="h-3 w-3" />
                                    </span>
                                </div>
                            </component>
                        </article>
                    </div>

                    <!-- List -->
                    <div
                        v-else
                        class="overflow-hidden rounded-2xl border border-surface-200 bg-white shadow-xs dark:border-surface-700 dark:bg-surface-900"
                    >
                        <component
                            :is="canViewProjects ? Link : 'div'"
                            v-for="(note, index) in group.items"
                            :key="note.id"
                            :href="
                                canViewProjects
                                    ? projectsRoute.show.url({
                                          project: note.id,
                                      })
                                    : undefined
                            "
                            class="group flex items-center gap-3 border-b border-surface-100 p-3.5 transition-colors last:border-b-0 hover:bg-surface-50 dark:border-surface-800 dark:hover:bg-surface-800/40"
                        >
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
                                        class="hidden shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold sm:inline"
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
                            <div
                                class="hidden shrink-0 text-right text-[11px] text-surface-500 sm:block dark:text-surface-400"
                            >
                                <p>{{ note.tasks_count }} tugas</p>
                                <p>{{ formatRelativeTime(note.updated_at) }}</p>
                            </div>
                            <span
                                class="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-surface-400 transition group-hover:bg-primary-50 group-hover:text-primary-700 dark:group-hover:bg-primary-900/30 dark:group-hover:text-primary-300"
                            >
                                <ArrowUpRight class="h-4 w-4" />
                            </span>
                        </component>
                    </div>
                </div>

                <EmptyState
                    v-if="groups.length === 0"
                    icon="FileText"
                    title="Belum ada folder yang cocok"
                    :description="
                        search
                            ? 'Tidak ada folder atau catatan proyek yang sesuai dengan pencarian Anda. Coba kata kunci lain.'
                            : 'Belum ada catatan proyek pada bagian ini. Tambahkan deskripsi pada detail proyek untuk membuat folder baru.'
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
                </EmptyState>
            </section>
        </div>
    </div>
</template>
