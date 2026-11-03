<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Avatar from '@/components/ui/Avatar.vue';
import Button from '@/components/ui/Button.vue';
import Dropdown from '@/components/ui/Dropdown.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Input from '@/components/ui/Input.vue';
import Modal from '@/components/ui/Modal.vue';
import Pagination from '@/components/ui/Pagination.vue';
import { useToast } from '@/composables/useToast';
import announcements from '@/routes/announcements';
import {
    ArrowRight,
    CalendarDays,
    Clock,
    Copy,
    Eye,
    Inbox,
    Megaphone,
    MoreHorizontal,
    Pin,
    PinOff,
    Plus,
    Search,
    Trash2,
    UsersRound,
    X,
} from '@lucide/vue';

defineOptions({
    layout: AppLayout,
});

interface AnnouncementAuthor {
    id: number;
    name: string;
    avatar: string | null;
    initials: string;
}

interface AnnouncementItem {
    id: number;
    title: string;
    body: string;
    is_pinned: boolean;
    published_at: string;
    author: AnnouncementAuthor;
}

interface AnnouncementSummary {
    total: number;
    pinned: number;
    recent: number;
    this_month: number;
    contributors: number;
}

const page = usePage();
const { success, error: toastError } = useToast();

const announcementsPayload = computed(
    () => (page.props.announcements as any) ?? {},
);
const filters = computed(
    () =>
        (page.props.filters as
            | { search?: string; filter?: string }
            | undefined) ?? {},
);

const canManage = computed(() => Boolean(page.props.can_manage));

const announcementList = computed<AnnouncementItem[]>(() => {
    const raw = announcementsPayload.value;
    if (Array.isArray(raw.data)) return raw.data;
    if (Array.isArray(raw)) return raw;
    return [];
});

const meta = computed(() => {
    const raw = announcementsPayload.value;
    return {
        current_page: Number(raw.meta?.current_page ?? raw.current_page ?? 1),
        last_page: Number(raw.meta?.last_page ?? raw.last_page ?? 1),
        from: raw.meta?.from ?? raw.from ?? null,
        to: raw.meta?.to ?? raw.to ?? null,
        total: Number(
            raw.meta?.total ?? raw.total ?? announcementList.value.length,
        ),
    };
});

const summary = computed<AnnouncementSummary>(() => {
    const raw = page.props.summary as Partial<AnnouncementSummary> | undefined;

    return {
        total: Number(raw?.total ?? meta.value.total),
        pinned: Number(raw?.pinned ?? 0),
        recent: Number(raw?.recent ?? 0),
        this_month: Number(raw?.this_month ?? 0),
        contributors: Number(raw?.contributors ?? 0),
    };
});

const searchQuery = ref(filters.value.search ?? '');
const activeFilter = ref(filters.value.filter ?? 'all');

const filterOptions = computed(() => [
    {
        value: 'all',
        label: 'Semua pengumuman',
        icon: Inbox,
        count: summary.value.total,
    },
    {
        value: 'pinned',
        label: 'Disematkan',
        icon: Pin,
        count: summary.value.pinned,
    },
    {
        value: 'recent',
        label: '7 hari terakhir',
        icon: Clock,
        count: summary.value.recent,
    },
    {
        value: 'month',
        label: 'Bulan ini',
        icon: CalendarDays,
        count: summary.value.this_month,
    },
]);

const activeFilterLabel = computed(
    () =>
        filterOptions.value.find(
            (option) => option.value === activeFilter.value,
        )?.label ?? 'Semua pengumuman',
);

const highlights = computed(() => [
    {
        label: 'Total',
        value: summary.value.total,
        icon: Megaphone,
        iconClass:
            'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300',
    },
    {
        label: 'Disematkan',
        value: summary.value.pinned,
        icon: Pin,
        iconClass:
            'bg-accent-50 text-accent-700 dark:bg-accent-900/30 dark:text-accent-300',
    },
    {
        label: '7 hari',
        value: summary.value.recent,
        icon: Clock,
        iconClass:
            'bg-amber-50 text-amber-700 dark:bg-amber-400/10 dark:text-amber-300',
    },
    {
        label: 'Kontributor',
        value: summary.value.contributors,
        icon: UsersRound,
        iconClass:
            'bg-sky-50 text-sky-700 dark:bg-sky-400/10 dark:text-sky-300',
    },
]);

const hasActiveFilters = computed(
    () => searchQuery.value.trim() !== '' || activeFilter.value !== 'all',
);

let searchTimer: ReturnType<typeof setTimeout> | null = null;

watch(searchQuery, () => {
    if (searchTimer) {
        clearTimeout(searchTimer);
    }

    searchTimer = setTimeout(() => {
        visitAnnouncements(1);
    }, 350);
});

onBeforeUnmount(() => {
    if (searchTimer) {
        clearTimeout(searchTimer);
    }
});

function visitAnnouncements(pageNumber = 1): void {
    router.get(
        announcements.index.url({
            query: {
                search: searchQuery.value.trim() || undefined,
                filter:
                    activeFilter.value !== 'all'
                        ? activeFilter.value
                        : undefined,
                page: pageNumber > 1 ? pageNumber : undefined,
            },
        }),
        {},
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['announcements', 'filters', 'summary'],
        },
    );
}

function setFilter(value: string): void {
    activeFilter.value = value;
    visitAnnouncements(1);
}

function resetFilters(): void {
    searchQuery.value = '';
    activeFilter.value = 'all';
    visitAnnouncements(1);
}

function changePage(pageNumber: number): void {
    visitAnnouncements(pageNumber);
}

const createOpen = ref(false);
const deleteTarget = ref<AnnouncementItem | null>(null);
const deleting = ref(false);
const detailId = ref<number | null>(null);

const detailAnnouncement = computed(
    () =>
        announcementList.value.find(
            (announcement) => announcement.id === detailId.value,
        ) ?? null,
);

const form = useForm({
    title: '',
    body: '',
    is_pinned: false,
});

function openCreate(): void {
    form.reset();
    form.clearErrors();
    createOpen.value = true;
}

function closeCreate(): void {
    if (form.processing) return;
    createOpen.value = false;
}

function submitAnnouncement(): void {
    form.post(announcements.store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            createOpen.value = false;
            form.reset();
            success(
                'Pengumuman dibuat',
                'Pengumuman berhasil dibagikan ke tim.',
            );
        },
        onError: () => {
            toastError(
                'Gagal membuat pengumuman',
                'Periksa kembali judul dan isi pengumuman.',
            );
        },
    });
}

function openDetail(announcement: AnnouncementItem): void {
    detailId.value = announcement.id;
}

function closeDetail(): void {
    detailId.value = null;
}

function askDelete(announcement: AnnouncementItem): void {
    detailId.value = null;
    deleteTarget.value = announcement;
}

function closeDelete(): void {
    if (deleting.value) return;
    deleteTarget.value = null;
}

function deleteAnnouncement(): void {
    if (!deleteTarget.value) return;

    deleting.value = true;

    router.delete(announcements.destroy.url(deleteTarget.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            success('Pengumuman dihapus');
            deleteTarget.value = null;
            detailId.value = null;
        },
        onError: () => {
            toastError('Gagal menghapus pengumuman');
        },
        onFinish: () => {
            deleting.value = false;
        },
    });
}

function togglePin(announcement: AnnouncementItem): void {
    router.patch(
        announcements.pin.url(announcement.id),
        {},
        {
            preserveScroll: true,
            onSuccess: () => {
                success(
                    announcement.is_pinned
                        ? 'Sematan dilepas'
                        : 'Pengumuman disematkan',
                );
            },
            onError: () => {
                toastError('Gagal mengubah sematan');
            },
        },
    );
}

async function copyAnnouncementLink(
    announcement: AnnouncementItem,
): Promise<void> {
    const url = `${window.location.origin}${announcements.index.url()}#pengumuman-${announcement.id}`;

    try {
        await navigator.clipboard.writeText(url);
        success('Tautan disalin', 'Tautan pengumuman siap dibagikan ke tim.');
    } catch {
        toastError(
            'Gagal menyalin tautan',
            'Salin tautan langsung dari bilah alamat browser.',
        );
    }
}

function formatDate(value: string, long = false): string {
    return new Date(value).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: long ? 'long' : 'short',
        year: 'numeric',
    });
}

function relativeTime(value: string): string {
    const date = new Date(value);
    const seconds = Math.floor((Date.now() - date.getTime()) / 1000);

    if (seconds < 60) return 'Baru saja';
    if (seconds < 3600) return `${Math.floor(seconds / 60)} menit lalu`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)} jam lalu`;
    if (seconds < 604800) return `${Math.floor(seconds / 86400)} hari lalu`;

    return formatDate(value);
}
</script>

<template>
    <div class="space-y-6">
        <!-- Page header -->
        <header
            class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
        >
            <div>
                <span
                    class="inline-flex h-6 items-center gap-1.5 rounded-full border border-primary-200 bg-primary-50 px-2.5 text-[10px] font-bold tracking-wider text-primary-700 uppercase dark:border-primary-700/40 dark:bg-primary-900/25 dark:text-primary-200"
                >
                    <Megaphone class="h-3 w-3" />
                    Komunikasi Tim
                </span>
                <h1
                    class="mt-2 text-2xl font-semibold tracking-tight text-surface-950 sm:text-3xl dark:text-surface-50"
                >
                    Pengumuman
                </h1>
                <p
                    class="mt-1 max-w-2xl text-sm leading-6 text-surface-600 dark:text-surface-300"
                >
                    Kabar penting, pengingat sprint, dan keputusan tim dalam
                    satu direktori yang mudah dipindai.
                </p>
            </div>

            <button
                v-if="canManage"
                type="button"
                class="btn btn-primary w-full sm:w-auto"
                @click="openCreate"
            >
                <Plus class="h-4 w-4" />
                Buat Pengumuman
            </button>
        </header>

        <!-- Summary -->
        <section
            class="grid grid-cols-2 gap-px overflow-hidden rounded-2xl border border-surface-200/80 bg-surface-200/70 shadow-xs sm:grid-cols-4 dark:border-surface-800 dark:bg-surface-800"
        >
            <div
                v-for="item in highlights"
                :key="item.label"
                class="flex items-center gap-3 bg-white px-4 py-4 sm:px-5 dark:bg-surface-900"
            >
                <span
                    class="grid h-10 w-10 shrink-0 place-items-center rounded-xl"
                    :class="item.iconClass"
                >
                    <component :is="item.icon" class="h-4.5 w-4.5" />
                </span>
                <div class="min-w-0">
                    <p
                        class="font-serif text-2xl leading-none text-surface-950 dark:text-surface-50"
                    >
                        {{ item.value }}
                    </p>
                    <p
                        class="mt-1.5 truncate text-[11px] font-semibold tracking-[0.1em] text-surface-500 uppercase dark:text-surface-400"
                    >
                        {{ item.label }}
                    </p>
                </div>
            </div>
        </section>

        <!-- Search and filters -->
        <section
            class="rounded-2xl border border-surface-200/80 bg-white p-3 shadow-xs sm:p-4 dark:border-surface-800 dark:bg-surface-900"
        >
            <div class="flex flex-col gap-3 xl:flex-row xl:items-center">
                <div class="relative min-w-0 flex-1">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3.5 h-4 w-4 -translate-y-1/2 text-surface-400"
                    />
                    <input
                        v-model="searchQuery"
                        type="search"
                        placeholder="Cari pengumuman, penulis, atau isi kabar tim..."
                        class="input h-11 pl-10"
                        aria-label="Cari pengumuman"
                    />
                    <button
                        v-if="searchQuery"
                        type="button"
                        class="absolute top-1/2 right-2 grid h-7 w-7 -translate-y-1/2 place-items-center rounded-full text-surface-400 transition hover:bg-surface-100 hover:text-surface-700 dark:hover:bg-surface-800 dark:hover:text-surface-200"
                        aria-label="Hapus pencarian"
                        @click="searchQuery = ''"
                    >
                        <X class="h-3.5 w-3.5" />
                    </button>
                </div>

                <div
                    class="no-scrollbar flex items-center gap-1.5 overflow-x-auto pb-0.5 xl:justify-end xl:pb-0"
                >
                    <button
                        v-for="option in filterOptions"
                        :key="option.value"
                        type="button"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded-full border px-3 py-2 text-xs font-semibold transition"
                        :class="
                            activeFilter === option.value
                                ? 'border-primary-600 bg-primary-600 text-white shadow-sm shadow-primary-600/20'
                                : 'border-surface-200 bg-white text-surface-600 hover:border-primary-300 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-surface-300 dark:hover:border-primary-500/50 dark:hover:text-primary-200'
                        "
                        :aria-pressed="activeFilter === option.value"
                        @click="setFilter(option.value)"
                    >
                        <component :is="option.icon" class="h-3.5 w-3.5" />
                        {{ option.label }}
                        <span
                            class="rounded-full px-1.5 py-0.5 text-[10px] font-bold"
                            :class="
                                activeFilter === option.value
                                    ? 'bg-white/20 text-white'
                                    : 'bg-surface-100 text-surface-500 dark:bg-surface-800 dark:text-surface-400'
                            "
                        >
                            {{ option.count }}
                        </span>
                    </button>
                </div>
            </div>
        </section>

        <!-- Result meta -->
        <div
            class="flex flex-col gap-2 px-1 sm:flex-row sm:items-center sm:justify-between"
        >
            <p
                class="text-xs font-medium text-surface-500 dark:text-surface-400"
            >
                <template v-if="meta.total > 0">
                    Menampilkan
                    <span
                        class="font-semibold text-surface-800 dark:text-surface-200"
                    >
                        {{ meta.from ?? 0 }}–{{ meta.to ?? 0 }}
                    </span>
                    dari
                    <span
                        class="font-semibold text-surface-800 dark:text-surface-200"
                    >
                        {{ meta.total }}
                    </span>
                    pengumuman
                </template>
                <template v-else> Tidak ada pengumuman yang cocok </template>
            </p>

            <div class="flex flex-wrap items-center gap-1.5">
                <span
                    v-if="activeFilter !== 'all'"
                    class="inline-flex items-center gap-1 rounded-full bg-primary-50 px-2.5 py-1 text-[10px] font-semibold text-primary-700 dark:bg-primary-900/30 dark:text-primary-300"
                >
                    {{ activeFilterLabel }}
                    <button
                        type="button"
                        class="rounded-full p-0.5 transition hover:bg-primary-100 dark:hover:bg-primary-900/50"
                        aria-label="Hapus filter"
                        @click="setFilter('all')"
                    >
                        <X class="h-2.5 w-2.5" />
                    </button>
                </span>
                <span
                    v-if="searchQuery.trim()"
                    class="inline-flex items-center gap-1 rounded-full bg-surface-100 px-2.5 py-1 text-[10px] font-semibold text-surface-600 dark:bg-surface-800 dark:text-surface-300"
                >
                    “{{ searchQuery.trim() }}”
                    <button
                        type="button"
                        class="rounded-full p-0.5 transition hover:bg-surface-200 dark:hover:bg-surface-700"
                        aria-label="Hapus pencarian"
                        @click="searchQuery = ''"
                    >
                        <X class="h-2.5 w-2.5" />
                    </button>
                </span>
            </div>
        </div>

        <!-- Announcement grid -->
        <div
            v-if="announcementList.length > 0"
            class="grid gap-4 md:grid-cols-2"
        >
            <article
                v-for="announcement in announcementList"
                :id="`pengumuman-${announcement.id}`"
                :key="announcement.id"
                class="group relative flex cursor-pointer flex-col rounded-2xl border p-5 shadow-xs transition duration-200 hover:-translate-y-0.5 hover:shadow-md focus-visible:ring-2 focus-visible:ring-primary-600/40 focus-visible:outline-hidden sm:p-6"
                :class="
                    announcement.is_pinned
                        ? 'border-accent-300/80 bg-gradient-to-br from-accent-50/80 via-white to-white hover:border-accent-400 dark:border-accent-700/50 dark:from-accent-900/20 dark:via-surface-900 dark:to-surface-900 dark:hover:border-accent-600/60'
                        : 'border-surface-200/80 bg-white hover:border-surface-300 dark:border-surface-800 dark:bg-surface-900 dark:hover:border-surface-700'
                "
                role="button"
                tabindex="0"
                :aria-label="`Baca pengumuman: ${announcement.title}`"
                @click="openDetail(announcement)"
                @keydown.enter="openDetail(announcement)"
                @keydown.space.prevent="openDetail(announcement)"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 flex-wrap items-center gap-2">
                        <span
                            v-if="announcement.is_pinned"
                            class="inline-flex items-center gap-1 rounded-full bg-accent-100 px-2.5 py-1 text-[10px] font-bold text-accent-800 dark:bg-accent-900/40 dark:text-accent-200"
                        >
                            <Pin class="h-3 w-3" />
                            Disematkan
                        </span>
                        <span
                            class="inline-flex items-center gap-1 text-[11px] font-medium text-surface-400 dark:text-surface-500"
                        >
                            <Clock class="h-3 w-3" />
                            {{ relativeTime(announcement.published_at) }}
                        </span>
                    </div>

                    <div class="-mt-1 -mr-1 shrink-0" @click.stop>
                        <Dropdown align="right" label="Menu pengumuman">
                            <template #trigger>
                                <MoreHorizontal
                                    class="h-4 w-4 text-surface-400"
                                />
                            </template>

                            <button
                                type="button"
                                class="dropdown-item w-full text-surface-700 dark:text-surface-200"
                                @click="openDetail(announcement)"
                            >
                                <Eye class="h-3.5 w-3.5" />
                                Lihat detail
                            </button>
                            <button
                                type="button"
                                class="dropdown-item w-full text-surface-700 dark:text-surface-200"
                                @click="copyAnnouncementLink(announcement)"
                            >
                                <Copy class="h-3.5 w-3.5" />
                                Salin tautan
                            </button>
                            <template v-if="canManage">
                                <div
                                    class="my-1 h-px bg-surface-100 dark:bg-surface-800"
                                />
                                <button
                                    type="button"
                                    class="dropdown-item w-full text-surface-700 dark:text-surface-200"
                                    @click="togglePin(announcement)"
                                >
                                    <PinOff
                                        v-if="announcement.is_pinned"
                                        class="h-3.5 w-3.5"
                                    />
                                    <Pin v-else class="h-3.5 w-3.5" />
                                    {{
                                        announcement.is_pinned
                                            ? 'Lepas sematan'
                                            : 'Sematkan'
                                    }}
                                </button>
                                <button
                                    type="button"
                                    class="dropdown-item w-full text-rose-600 dark:text-rose-300"
                                    @click="askDelete(announcement)"
                                >
                                    <Trash2 class="h-3.5 w-3.5" />
                                    Hapus pengumuman
                                </button>
                            </template>
                        </Dropdown>
                    </div>
                </div>

                <div class="mt-3 flex-1">
                    <h2
                        class="line-clamp-2 text-lg leading-snug font-semibold tracking-tight text-surface-950 transition group-hover:text-primary-700 dark:text-surface-50 dark:group-hover:text-primary-300"
                    >
                        {{ announcement.title }}
                    </h2>
                    <p
                        class="mt-2 line-clamp-3 text-sm leading-6 whitespace-pre-line text-surface-500 dark:text-surface-400"
                    >
                        {{ announcement.body }}
                    </p>
                </div>

                <div
                    class="mt-5 flex items-center justify-between gap-3 border-t border-surface-200/70 pt-4 dark:border-surface-800"
                >
                    <div class="flex min-w-0 items-center gap-2.5">
                        <Avatar
                            :src="announcement.author.avatar"
                            :initials="announcement.author.initials"
                            :alt="announcement.author.name"
                            size="sm"
                        />
                        <div class="min-w-0">
                            <p
                                class="truncate text-xs font-semibold text-surface-800 dark:text-surface-100"
                            >
                                {{ announcement.author.name }}
                            </p>
                            <p
                                class="mt-0.5 flex items-center gap-1 text-[10px] text-surface-400 dark:text-surface-500"
                            >
                                <CalendarDays class="h-3 w-3" />
                                {{ formatDate(announcement.published_at) }}
                            </p>
                        </div>
                    </div>

                    <span
                        class="inline-flex shrink-0 items-center gap-1.5 text-xs font-semibold text-primary-700 dark:text-primary-300"
                    >
                        Baca
                        <ArrowRight
                            class="h-3.5 w-3.5 transition group-hover:translate-x-0.5"
                        />
                    </span>
                </div>
            </article>

            <!-- Create prompt fills the last grid slot on odd counts -->
            <button
                v-if="
                    canManage &&
                    !hasActiveFilters &&
                    announcementList.length % 2 === 1
                "
                type="button"
                class="group flex min-h-[260px] flex-col items-center justify-center gap-3 rounded-2xl border border-dashed border-surface-300 bg-surface-50/50 p-6 text-center transition hover:border-primary-400 hover:bg-primary-50/40 dark:border-surface-700 dark:bg-surface-900/30 dark:hover:border-primary-500/60 dark:hover:bg-primary-900/10"
                @click="openCreate"
            >
                <span
                    class="grid h-12 w-12 place-items-center rounded-2xl bg-primary-100 text-primary-700 transition group-hover:scale-105 dark:bg-primary-900/40 dark:text-primary-300"
                >
                    <Plus class="h-5 w-5" />
                </span>
                <span
                    class="text-sm font-semibold text-surface-800 dark:text-surface-100"
                >
                    Bagikan kabar baru
                </span>
                <span
                    class="max-w-[17rem] text-xs leading-5 text-surface-500 dark:text-surface-400"
                >
                    Tulis pengumuman singkat agar tim tetap selaras tanpa rapat
                    tambahan.
                </span>
            </button>
        </div>

        <!-- Search empty state -->
        <div
            v-else-if="summary.total > 0"
            class="rounded-2xl border border-surface-200/80 bg-white py-14 text-center shadow-xs dark:border-surface-800 dark:bg-surface-900"
        >
            <span
                class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-surface-100 text-surface-400 dark:bg-surface-800 dark:text-surface-500"
            >
                <Search class="h-5 w-5" />
            </span>
            <p
                class="mt-3 text-lg font-bold text-surface-950 dark:text-surface-50"
            >
                Pengumuman tidak ditemukan
            </p>
            <p
                class="mx-auto mt-1 max-w-sm text-xs leading-5 text-surface-500 dark:text-surface-400"
            >
                Coba kata kunci lain, ubah filter, atau tampilkan semua
                pengumuman.
            </p>
            <button
                v-if="hasActiveFilters"
                type="button"
                class="mt-4 rounded-full border border-surface-200 bg-white px-3.5 py-1.5 text-[11px] font-semibold text-surface-700 transition hover:bg-surface-50 dark:border-surface-700 dark:bg-surface-900 dark:text-surface-200 dark:hover:bg-surface-800"
                @click="resetFilters"
            >
                Reset filter
            </button>
        </div>

        <!-- First empty state -->
        <EmptyState
            v-else
            icon="Radio"
            title="Belum ada pengumuman"
            description="Bagikan informasi penting, pengingat sprint, atau kabar terbaru agar seluruh tim tetap selaras."
        >
            <Button v-if="canManage" class="mt-5" @click="openCreate">
                <Plus class="h-4 w-4" />
                Buat Pengumuman Pertama
            </Button>
        </EmptyState>

        <Pagination
            v-if="meta.total > 0"
            :current-page="meta.current_page"
            :last-page="meta.last_page"
            :from="meta.from"
            :to="meta.to"
            :total="meta.total"
            @change="changePage"
        />

        <!-- Detail modal -->
        <Modal
            :open="detailAnnouncement !== null"
            title="Detail Pengumuman"
            size="lg"
            @close="closeDetail"
        >
            <div v-if="detailAnnouncement" class="space-y-4">
                <div class="flex flex-wrap items-center gap-2">
                    <span
                        v-if="detailAnnouncement.is_pinned"
                        class="inline-flex items-center gap-1 rounded-full bg-accent-50 px-2.5 py-1 text-[10px] font-bold text-accent-700 dark:bg-accent-900/30 dark:text-accent-300"
                    >
                        <Pin class="h-3 w-3" />
                        Disematkan
                    </span>
                    <span
                        class="inline-flex items-center gap-1 text-[11px] text-surface-500 dark:text-surface-400"
                    >
                        <CalendarDays class="h-3.5 w-3.5" />
                        {{ formatDate(detailAnnouncement.published_at, true) }}
                    </span>
                </div>

                <h2
                    class="text-xl leading-tight font-bold text-surface-950 dark:text-surface-50"
                >
                    {{ detailAnnouncement.title }}
                </h2>

                <div class="flex items-center gap-2.5">
                    <Avatar
                        :src="detailAnnouncement.author.avatar"
                        :initials="detailAnnouncement.author.initials"
                        :alt="detailAnnouncement.author.name"
                        size="sm"
                    />
                    <div class="leading-tight">
                        <p
                            class="text-xs font-semibold text-surface-900 dark:text-surface-100"
                        >
                            {{ detailAnnouncement.author.name }}
                        </p>
                        <p
                            class="text-[10px] text-surface-400 dark:text-surface-500"
                        >
                            {{ relativeTime(detailAnnouncement.published_at) }}
                        </p>
                    </div>
                </div>

                <div
                    class="max-h-[45vh] overflow-y-auto rounded-2xl border border-surface-100 bg-surface-50/70 p-4 dark:border-surface-800 dark:bg-surface-800/40"
                >
                    <p
                        class="text-sm leading-7 whitespace-pre-line text-surface-700 dark:text-surface-200"
                    >
                        {{ detailAnnouncement.body }}
                    </p>
                </div>

                <div
                    class="flex flex-wrap items-center justify-between gap-2 border-t border-surface-100 pt-3 dark:border-surface-800"
                >
                    <button
                        type="button"
                        class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-surface-500 transition hover:text-primary-700 dark:text-surface-400 dark:hover:text-primary-300"
                        @click="copyAnnouncementLink(detailAnnouncement)"
                    >
                        <Copy class="h-3.5 w-3.5" />
                        Salin tautan
                    </button>

                    <div
                        v-if="canManage"
                        class="flex flex-wrap items-center gap-2"
                    >
                        <Button
                            variant="secondary"
                            size="sm"
                            @click="togglePin(detailAnnouncement)"
                        >
                            <PinOff
                                v-if="detailAnnouncement.is_pinned"
                                class="h-3.5 w-3.5"
                            />
                            <Pin v-else class="h-3.5 w-3.5" />
                            {{
                                detailAnnouncement.is_pinned
                                    ? 'Lepas sematan'
                                    : 'Sematkan'
                            }}
                        </Button>
                        <Button
                            variant="destructive"
                            size="sm"
                            @click="askDelete(detailAnnouncement)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                            Hapus
                        </Button>
                    </div>
                </div>
            </div>
        </Modal>

        <!-- Create modal -->
        <Modal
            :open="createOpen"
            title="Buat Pengumuman"
            size="lg"
            @close="closeCreate"
        >
            <form class="space-y-4" @submit.prevent="submitAnnouncement">
                <Input
                    v-model="form.title"
                    label="Judul"
                    placeholder="Contoh: Sprint review hari Jumat"
                    :error="form.errors.title"
                    required
                />
                <Input
                    v-model="form.body"
                    type="textarea"
                    label="Isi pengumuman"
                    placeholder="Tulis informasi yang ingin dibagikan ke tim..."
                    :rows="6"
                    :error="form.errors.body"
                    required
                />
                <label
                    class="flex cursor-pointer items-start gap-3 rounded-xl border border-surface-200 bg-surface-50/70 p-3 dark:border-surface-700 dark:bg-surface-800/40"
                >
                    <input
                        v-model="form.is_pinned"
                        type="checkbox"
                        class="mt-0.5 h-4 w-4 rounded border-surface-300 text-primary-600 focus:ring-primary-500"
                    />
                    <span>
                        <span
                            class="block text-sm font-medium text-surface-800 dark:text-surface-100"
                            >Sematkan di atas</span
                        >
                        <span
                            class="block text-xs text-surface-500 dark:text-surface-400"
                            >Pengumuman penting akan ditampilkan paling
                            atas.</span
                        >
                    </span>
                </label>

                <div
                    class="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end"
                >
                    <Button
                        type="button"
                        variant="secondary"
                        @click="closeCreate"
                        >Batal</Button
                    >
                    <Button type="submit" :loading="form.processing"
                        >Bagikan Pengumuman</Button
                    >
                </div>
            </form>
        </Modal>

        <!-- Delete confirmation -->
        <Modal
            :open="deleteTarget !== null"
            title="Hapus Pengumuman"
            size="sm"
            @close="closeDelete"
        >
            <p class="text-sm leading-6 text-surface-700 dark:text-surface-200">
                Yakin ingin menghapus
                <span class="font-semibold">“{{ deleteTarget?.title }}”</span>?
                Tindakan ini tidak dapat dibatalkan.
            </p>
            <div
                class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end"
            >
                <Button variant="secondary" @click="closeDelete">Batal</Button>
                <Button
                    variant="destructive"
                    :loading="deleting"
                    @click="deleteAnnouncement"
                >
                    Hapus
                </Button>
            </div>
        </Modal>
    </div>
</template>
