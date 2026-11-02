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
    Filter,
    Inbox,
    Megaphone,
    MoreHorizontal,
    Pin,
    PinOff,
    Plus,
    Search,
    Sparkles,
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
const mobileFiltersOpen = ref(false);

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
    mobileFiltersOpen.value = false;
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

function pad(value: number): string {
    return String(value).padStart(2, '0');
}
</script>

<template>
    <div class="space-y-6">
        <!-- Editorial header -->
        <div
            class="flex flex-col gap-5 border-b border-surface-200/80 pb-6 lg:flex-row lg:items-end lg:justify-between dark:border-surface-800"
        >
            <div>
                <div class="flex items-center gap-3">
                    <span class="h-px w-8 bg-accent-500" />
                    <span
                        class="text-[10px] font-bold tracking-[0.22em] text-surface-500 uppercase dark:text-surface-400"
                    >
                        Komunikasi tim
                    </span>
                </div>
                <h1
                    class="mt-3 flex flex-wrap items-baseline gap-x-3 gap-y-1 font-serif text-3xl font-normal tracking-tight text-surface-950 sm:text-4xl dark:text-surface-50"
                >
                    <span class="text-accent-600 dark:text-accent-400">{{
                        pad(summary.total)
                    }}</span>
                    <span>Pengumuman</span>
                </h1>
                <p
                    class="mt-2 max-w-xl text-sm leading-6 text-surface-600 dark:text-surface-300"
                >
                    Kabar penting, pengingat sprint, dan keputusan tim dalam
                    satu direktori yang mudah dipindai.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-full border border-surface-200 bg-white px-3.5 py-2 text-xs font-semibold text-surface-700 shadow-2xs transition hover:border-surface-300 hover:bg-surface-50 active:scale-95 lg:hidden dark:border-surface-700 dark:bg-surface-900 dark:text-surface-200 dark:hover:bg-surface-800"
                    @click="mobileFiltersOpen = !mobileFiltersOpen"
                >
                    <Filter class="h-3.5 w-3.5" />
                    Filter
                </button>
                <button
                    v-if="canManage"
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-full bg-accent-600 px-4 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-accent-700 active:scale-95 dark:bg-accent-500 dark:text-surface-950 dark:hover:bg-accent-400"
                    @click="openCreate"
                >
                    <Plus class="h-3.5 w-3.5" />
                    Buat pengumuman
                </button>
            </div>
        </div>

        <!-- Search -->
        <div class="relative">
            <Search
                class="pointer-events-none absolute top-1/2 left-4 h-4 w-4 -translate-y-1/2 text-surface-400"
            />
            <input
                v-model="searchQuery"
                type="search"
                placeholder="Cari pengumuman, penulis, atau isi kabar tim..."
                class="h-12 w-full rounded-2xl border border-surface-200 bg-white pr-11 pl-11 text-sm text-surface-900 placeholder-surface-400 shadow-xs transition focus:border-primary-600 focus:ring-2 focus:ring-primary-600/15 focus:outline-hidden dark:border-surface-700 dark:bg-surface-900 dark:text-surface-100 dark:placeholder-surface-500 dark:focus:border-primary-400"
            />
            <button
                v-if="searchQuery"
                type="button"
                class="absolute top-1/2 right-3 grid h-7 w-7 -translate-y-1/2 place-items-center rounded-full text-surface-400 transition hover:bg-surface-100 hover:text-surface-700 dark:hover:bg-surface-800 dark:hover:text-surface-200"
                aria-label="Hapus pencarian"
                @click="searchQuery = ''"
            >
                <X class="h-3.5 w-3.5" />
            </button>
        </div>

        <!-- Mobile filters -->
        <div v-if="mobileFiltersOpen" class="flex flex-wrap gap-2 lg:hidden">
            <button
                v-for="option in filterOptions"
                :key="`mobile-${option.value}`"
                type="button"
                class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-[11px] font-semibold transition"
                :class="
                    activeFilter === option.value
                        ? 'border-surface-900 bg-surface-900 text-white dark:border-white dark:bg-white dark:text-surface-950'
                        : 'border-surface-200 bg-white text-surface-600 dark:border-surface-700 dark:bg-surface-900 dark:text-surface-300'
                "
                @click="setFilter(option.value)"
            >
                <component :is="option.icon" class="h-3 w-3" />
                {{ option.label }}
                <span class="text-[10px] opacity-60">{{ option.count }}</span>
            </button>
        </div>

        <div class="grid gap-6 lg:grid-cols-[15rem_minmax(0,1fr)]">
            <!-- Filter sidebar -->
            <aside class="hidden lg:block">
                <div class="sticky top-24 space-y-4">
                    <nav
                        class="rounded-[28px] border border-surface-200/80 bg-white p-3 shadow-xs dark:border-surface-800 dark:bg-surface-900"
                    >
                        <p
                            class="px-3 pt-1 pb-2 text-[10px] font-bold tracking-[0.16em] text-surface-400 uppercase dark:text-surface-500"
                        >
                            Kotak masuk
                        </p>

                        <button
                            v-for="option in filterOptions"
                            :key="option.value"
                            type="button"
                            class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2.5 text-left text-xs font-medium transition"
                            :class="
                                activeFilter === option.value
                                    ? 'bg-primary-50 text-primary-800 dark:bg-primary-900/30 dark:text-primary-200'
                                    : 'text-surface-600 hover:bg-surface-50 hover:text-surface-900 dark:text-surface-300 dark:hover:bg-surface-800 dark:hover:text-white'
                            "
                            :aria-pressed="activeFilter === option.value"
                            @click="setFilter(option.value)"
                        >
                            <component
                                :is="option.icon"
                                class="h-4 w-4 shrink-0"
                            />
                            <span class="flex-1">{{ option.label }}</span>
                            <span
                                class="rounded-full px-1.5 py-0.5 text-[10px] font-bold"
                                :class="
                                    activeFilter === option.value
                                        ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/50 dark:text-primary-200'
                                        : 'bg-surface-100 text-surface-500 dark:bg-surface-800 dark:text-surface-400'
                                "
                            >
                                {{ option.count }}
                            </span>
                        </button>

                        <div
                            class="my-3 h-px bg-surface-100 dark:bg-surface-800"
                        />

                        <div
                            class="rounded-2xl bg-surface-50/80 p-3 dark:bg-surface-800/40"
                        >
                            <div class="flex items-center gap-2">
                                <UsersRound
                                    class="h-4 w-4 text-accent-600 dark:text-accent-400"
                                />
                                <p
                                    class="text-[11px] font-semibold text-surface-800 dark:text-surface-100"
                                >
                                    {{ summary.contributors }} kontributor
                                </p>
                            </div>
                            <p
                                class="mt-1 text-[10px] leading-4 text-surface-400 dark:text-surface-500"
                            >
                                Anggota yang pernah membagikan kabar untuk tim.
                            </p>
                        </div>
                    </nav>

                    <section
                        v-if="canManage"
                        class="relative overflow-hidden rounded-[28px] bg-primary-950 p-5 text-white shadow-sm dark:bg-primary-950"
                    >
                        <div
                            class="pointer-events-none absolute -top-14 -right-10 h-36 w-36 rounded-full bg-white/10 blur-2xl"
                        />
                        <div
                            class="pointer-events-none absolute -bottom-14 -left-8 h-32 w-32 rounded-full bg-accent-400/20 blur-2xl"
                        />

                        <div class="relative">
                            <span
                                class="grid h-9 w-9 place-items-center rounded-xl bg-white/10 text-accent-200 ring-1 ring-white/15"
                            >
                                <Megaphone class="h-4 w-4" />
                            </span>
                            <p
                                class="mt-4 font-serif text-lg leading-snug text-white"
                            >
                                Bagikan kabar terbaru.
                            </p>
                            <p
                                class="mt-1 text-[11px] leading-5 text-primary-100/80"
                            >
                                Tulis pengumuman singkat agar tim tetap selaras
                                tanpa rapat tambahan.
                            </p>
                            <button
                                type="button"
                                class="mt-4 inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-[10px] font-bold text-primary-950 transition hover:bg-primary-50 active:scale-95"
                                @click="openCreate"
                            >
                                <Plus class="h-3 w-3" />
                                Buat sekarang
                            </button>
                        </div>
                    </section>

                    <div
                        class="flex items-start gap-2 rounded-2xl border border-dashed border-surface-200 px-3 py-2.5 dark:border-surface-700"
                    >
                        <Sparkles
                            class="mt-0.5 h-3.5 w-3.5 shrink-0 text-accent-500"
                        />
                        <p
                            class="text-[10px] leading-4 text-surface-400 dark:text-surface-500"
                        >
                            Sematkan pengumuman penting agar selalu muncul di
                            urutan teratas.
                        </p>
                    </div>
                </div>
            </aside>

            <!-- Announcement directory -->
            <div class="min-w-0 space-y-4">
                <div
                    class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p
                        class="text-[11px] text-surface-500 dark:text-surface-400"
                    >
                        <template v-if="meta.total > 0">
                            Menampilkan
                            <span
                                class="font-semibold text-surface-800 dark:text-surface-200"
                                >{{ meta.from ?? 0 }}–{{ meta.to ?? 0 }}</span
                            >
                            dari
                            <span
                                class="font-semibold text-surface-800 dark:text-surface-200"
                                >{{ meta.total }}</span
                            >
                            pengumuman
                        </template>
                        <template v-else>
                            Tidak ada pengumuman yang cocok
                        </template>
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

                <div
                    v-if="announcementList.length > 0"
                    class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3"
                >
                    <article
                        v-for="announcement in announcementList"
                        :id="`pengumuman-${announcement.id}`"
                        :key="announcement.id"
                        class="group relative flex cursor-pointer flex-col rounded-[24px] border border-surface-200/80 bg-white p-4 shadow-xs transition duration-200 hover:-translate-y-0.5 hover:border-surface-300 hover:shadow-md dark:border-surface-800 dark:bg-surface-900 dark:hover:border-surface-700"
                        role="button"
                        tabindex="0"
                        @click="openDetail(announcement)"
                        @keydown.enter="openDetail(announcement)"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <div class="relative">
                                <Avatar
                                    :src="announcement.author.avatar"
                                    :initials="announcement.author.initials"
                                    :alt="announcement.author.name"
                                    size="lg"
                                    class="ring-2 ring-surface-100 dark:ring-surface-800"
                                />
                                <span
                                    v-if="announcement.is_pinned"
                                    class="absolute -right-1 -bottom-1 grid h-5 w-5 place-items-center rounded-full bg-accent-500 text-white ring-2 ring-white dark:ring-surface-900"
                                    title="Disematkan"
                                >
                                    <Pin class="h-2.5 w-2.5" />
                                </span>
                            </div>

                            <div @click.stop>
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
                                        @click="
                                            copyAnnouncementLink(announcement)
                                        "
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

                        <div class="mt-3">
                            <h2
                                class="truncate font-serif text-base text-surface-950 dark:text-surface-50"
                            >
                                {{ announcement.author.name }}
                            </h2>
                            <p
                                class="mt-0.5 text-[10px] font-semibold tracking-[0.12em] text-surface-400 uppercase dark:text-surface-500"
                            >
                                Penulis pengumuman
                            </p>
                        </div>

                        <div
                            class="mt-3 grid grid-cols-2 gap-2 border-y border-surface-100 py-2.5 dark:border-surface-800"
                        >
                            <div>
                                <p
                                    class="text-[9px] font-bold tracking-[0.12em] text-surface-400 uppercase dark:text-surface-500"
                                >
                                    Dipublikasikan
                                </p>
                                <p
                                    class="mt-0.5 inline-flex items-center gap-1 text-[10px] font-semibold text-surface-700 dark:text-surface-200"
                                >
                                    <CalendarDays class="h-3 w-3" />
                                    {{ formatDate(announcement.published_at) }}
                                </p>
                            </div>
                            <div>
                                <p
                                    class="text-[9px] font-bold tracking-[0.12em] text-surface-400 uppercase dark:text-surface-500"
                                >
                                    Status
                                </p>
                                <p
                                    class="mt-0.5 inline-flex items-center gap-1 text-[10px] font-semibold text-surface-700 dark:text-surface-200"
                                >
                                    <span
                                        class="h-1.5 w-1.5 rounded-full"
                                        :class="
                                            announcement.is_pinned
                                                ? 'bg-accent-500'
                                                : 'bg-surface-300 dark:bg-surface-500'
                                        "
                                    />
                                    {{
                                        announcement.is_pinned
                                            ? 'Disematkan'
                                            : 'Reguler'
                                    }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-3 flex-1">
                            <h3
                                class="line-clamp-2 text-sm leading-5 font-semibold text-surface-950 dark:text-surface-50"
                            >
                                {{ announcement.title }}
                            </h3>
                            <p
                                class="mt-1.5 line-clamp-3 text-xs leading-5 whitespace-pre-line text-surface-500 dark:text-surface-400"
                            >
                                {{ announcement.body }}
                            </p>
                        </div>

                        <div
                            class="mt-3 flex items-center justify-between gap-2 border-t border-surface-100 pt-3 dark:border-surface-800"
                        >
                            <span
                                class="inline-flex items-center gap-1 text-[10px] text-surface-400 dark:text-surface-500"
                            >
                                <Clock class="h-3 w-3" />
                                {{ relativeTime(announcement.published_at) }}
                            </span>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 text-[11px] font-semibold text-primary-700 transition hover:gap-1.5 hover:underline dark:text-primary-300"
                                @click.stop="openDetail(announcement)"
                            >
                                Baca
                                <ArrowRight class="h-3 w-3" />
                            </button>
                        </div>
                    </article>
                </div>

                <div
                    v-else-if="summary.total > 0"
                    class="rounded-[28px] border border-surface-200/80 bg-white px-6 py-12 text-center shadow-xs dark:border-surface-800 dark:bg-surface-900"
                >
                    <span
                        class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-surface-100 text-surface-400 dark:bg-surface-800 dark:text-surface-500"
                    >
                        <Search class="h-5 w-5" />
                    </span>
                    <p
                        class="mt-3 font-serif text-xl text-surface-900 dark:text-surface-100"
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
            </div>
        </div>

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
                    class="font-serif text-2xl leading-tight text-surface-950 dark:text-surface-50"
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
