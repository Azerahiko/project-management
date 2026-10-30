<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Avatar from '@/components/ui/Avatar.vue';
import Button from '@/components/ui/Button.vue';
import EmptyState from '@/components/ui/EmptyState.vue';
import Input from '@/components/ui/Input.vue';
import Modal from '@/components/ui/Modal.vue';
import Pagination from '@/components/ui/Pagination.vue';
import { useToast } from '@/composables/useToast';
import announcements from '@/routes/announcements';
import {
    CalendarDays,
    Megaphone,
    Pin,
    PinOff,
    Plus,
    Trash2,
    UsersRound,
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

const page = usePage();
const { success, error: toastError } = useToast();

const rawAnnouncements = (page.props.announcements as any) ?? {};
const canManage = computed(() => Boolean(page.props.can_manage));

const announcementList = computed<AnnouncementItem[]>(() => {
    if (Array.isArray(rawAnnouncements.data)) return rawAnnouncements.data;
    if (Array.isArray(rawAnnouncements)) return rawAnnouncements;
    return [];
});

const meta = computed(() => ({
    current_page: Number(
        rawAnnouncements.meta?.current_page ??
            rawAnnouncements.current_page ??
            1,
    ),
    last_page: Number(
        rawAnnouncements.meta?.last_page ?? rawAnnouncements.last_page ?? 1,
    ),
    from: rawAnnouncements.meta?.from ?? rawAnnouncements.from ?? null,
    to: rawAnnouncements.meta?.to ?? rawAnnouncements.to ?? null,
    total: Number(
        rawAnnouncements.meta?.total ??
            rawAnnouncements.total ??
            announcementList.value.length,
    ),
}));

const featured = computed<AnnouncementItem | null>(() => {
    return (
        announcementList.value.find((announcement) => announcement.is_pinned) ??
        announcementList.value[0] ??
        null
    );
});

const otherAnnouncements = computed(() =>
    announcementList.value.filter(
        (announcement) => announcement.id !== featured.value?.id,
    ),
);

const stats = computed(() => ({
    total: meta.value.total,
    pinned: announcementList.value.filter(
        (announcement) => announcement.is_pinned,
    ).length,
    contributors: new Set(
        announcementList.value.map((announcement) => announcement.author.id),
    ).size,
}));

const createOpen = ref(false);
const deleteTarget = ref<AnnouncementItem | null>(null);
const deleting = ref(false);

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

function askDelete(announcement: AnnouncementItem): void {
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

function changePage(pageNumber: number): void {
    router.get(
        announcements.index.url({ query: { page: pageNumber } }),
        {},
        { preserveScroll: true, preserveState: true },
    );
}

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
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

function authorInitials(announcement: AnnouncementItem): string {
    return announcement.author.initials;
}
</script>

<template>
    <div class="space-y-6">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <div
                    class="flex items-center gap-2 text-xs font-semibold tracking-wider text-primary-700 uppercase dark:text-primary-300"
                >
                    <Megaphone class="h-4 w-4" />
                    Komunikasi
                </div>
                <h1
                    class="mt-2 text-2xl font-semibold tracking-tight text-surface-950 dark:text-surface-50"
                >
                    Pengumuman Tim
                </h1>
                <p
                    class="mt-1 max-w-2xl text-sm leading-6 text-surface-700 dark:text-surface-200"
                >
                    Informasi penting, pengingat, dan kabar terbaru untuk
                    seluruh anggota tim.
                </p>
            </div>

            <Button v-if="canManage" @click="openCreate">
                <Plus class="h-4 w-4" />
                Buat Pengumuman
            </Button>
        </div>

        <!-- Stats -->
        <div class="grid gap-3 sm:grid-cols-3">
            <div class="card flex items-center gap-4 p-4">
                <span
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300"
                >
                    <Megaphone class="h-5 w-5" />
                </span>
                <div>
                    <p
                        class="text-xs font-medium text-surface-500 dark:text-surface-400"
                    >
                        Total pengumuman
                    </p>
                    <p
                        class="mt-0.5 text-xl font-bold text-surface-950 dark:text-surface-50"
                    >
                        {{ stats.total }}
                    </p>
                </div>
            </div>
            <div class="card flex items-center gap-4 p-4">
                <span
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-accent-50 text-accent-700 dark:bg-accent-900/30 dark:text-accent-300"
                >
                    <Pin class="h-5 w-5" />
                </span>
                <div>
                    <p
                        class="text-xs font-medium text-surface-500 dark:text-surface-400"
                    >
                        Disematkan
                    </p>
                    <p
                        class="mt-0.5 text-xl font-bold text-surface-950 dark:text-surface-50"
                    >
                        {{ stats.pinned }}
                    </p>
                </div>
            </div>
            <div class="card flex items-center gap-4 p-4">
                <span
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-surface-100 text-surface-700 dark:bg-surface-800 dark:text-surface-200"
                >
                    <UsersRound class="h-5 w-5" />
                </span>
                <div>
                    <p
                        class="text-xs font-medium text-surface-500 dark:text-surface-400"
                    >
                        Kontributor
                    </p>
                    <p
                        class="mt-0.5 text-xl font-bold text-surface-950 dark:text-surface-50"
                    >
                        {{ stats.contributors }}
                    </p>
                </div>
            </div>
        </div>

        <template v-if="announcementList.length > 0">
            <!-- Featured announcement -->
            <article
                v-if="featured"
                class="relative overflow-hidden rounded-3xl border border-primary-700/20 bg-primary-900 p-6 text-white shadow-lg sm:p-8 dark:border-primary-700/40 dark:bg-primary-950"
            >
                <div
                    class="pointer-events-none absolute -top-20 -right-16 h-64 w-64 rounded-full bg-white/10 blur-2xl"
                />
                <div
                    class="pointer-events-none absolute -bottom-24 -left-10 h-56 w-56 rounded-full bg-accent-400/20 blur-2xl"
                />

                <div class="relative flex flex-col gap-5">
                    <div class="flex flex-wrap items-center gap-3">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-[11px] font-semibold tracking-wide uppercase"
                        >
                            <Pin class="h-3.5 w-3.5" />
                            {{ featured.is_pinned ? 'Disematkan' : 'Terbaru' }}
                        </span>
                        <span
                            class="inline-flex items-center gap-1.5 text-xs text-primary-100/90"
                        >
                            <CalendarDays class="h-3.5 w-3.5" />
                            {{ relativeTime(featured.published_at) }}
                        </span>
                    </div>

                    <div>
                        <h2
                            class="font-serif text-2xl leading-tight font-normal tracking-tight sm:text-3xl"
                        >
                            {{ featured.title }}
                        </h2>
                        <p
                            class="mt-3 max-w-3xl text-sm leading-7 whitespace-pre-line text-primary-50/90"
                        >
                            {{ featured.body }}
                        </p>
                    </div>

                    <div
                        class="flex flex-wrap items-center justify-between gap-3 border-t border-white/15 pt-4"
                    >
                        <div class="flex items-center gap-2.5">
                            <Avatar
                                :src="featured.author.avatar"
                                :initials="authorInitials(featured)"
                                :alt="featured.author.name"
                                size="sm"
                                class="ring-2 ring-white/30"
                            />
                            <div class="leading-tight">
                                <p class="text-xs font-semibold">
                                    {{ featured.author.name }}
                                </p>
                                <p class="text-[11px] text-primary-100/80">
                                    {{ formatDate(featured.published_at) }}
                                </p>
                            </div>
                        </div>

                        <div v-if="canManage" class="flex items-center gap-1.5">
                            <button
                                type="button"
                                class="rounded-lg p-2 text-primary-100 transition hover:bg-white/10 hover:text-white"
                                :title="
                                    featured.is_pinned
                                        ? 'Lepas sematan'
                                        : 'Sematkan'
                                "
                                @click="togglePin(featured)"
                            >
                                <PinOff
                                    v-if="featured.is_pinned"
                                    class="h-4 w-4"
                                />
                                <Pin v-else class="h-4 w-4" />
                            </button>
                            <button
                                type="button"
                                class="rounded-lg p-2 text-primary-100 transition hover:bg-rose-500/20 hover:text-white"
                                title="Hapus pengumuman"
                                @click="askDelete(featured)"
                            >
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </article>

            <!-- Announcement list -->
            <div
                v-if="otherAnnouncements.length > 0"
                class="grid gap-4 lg:grid-cols-2"
            >
                <article
                    v-for="announcement in otherAnnouncements"
                    :key="announcement.id"
                    class="card group flex flex-col p-5 transition hover:-translate-y-0.5 hover:shadow-md"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-center gap-2.5">
                            <Avatar
                                :src="announcement.author.avatar"
                                :initials="authorInitials(announcement)"
                                :alt="announcement.author.name"
                                size="sm"
                            />
                            <div class="min-w-0 leading-tight">
                                <p
                                    class="truncate text-xs font-semibold text-surface-900 dark:text-surface-100"
                                >
                                    {{ announcement.author.name }}
                                </p>
                                <p
                                    class="text-[11px] text-surface-500 dark:text-surface-400"
                                >
                                    {{
                                        relativeTime(announcement.published_at)
                                    }}
                                </p>
                            </div>
                        </div>

                        <div class="flex shrink-0 items-center gap-1">
                            <span
                                v-if="announcement.is_pinned"
                                class="inline-flex items-center gap-1 rounded-full bg-accent-50 px-2 py-0.5 text-[10px] font-semibold text-accent-700 dark:bg-accent-900/30 dark:text-accent-300"
                            >
                                <Pin class="h-3 w-3" />
                                Disematkan
                            </span>
                            <template v-if="canManage">
                                <button
                                    type="button"
                                    class="rounded-lg p-1.5 text-surface-400 opacity-0 transition group-hover:opacity-100 hover:bg-surface-100 hover:text-surface-700 dark:hover:bg-surface-800 dark:hover:text-surface-200"
                                    :title="
                                        announcement.is_pinned
                                            ? 'Lepas sematan'
                                            : 'Sematkan'
                                    "
                                    @click="togglePin(announcement)"
                                >
                                    <PinOff
                                        v-if="announcement.is_pinned"
                                        class="h-4 w-4"
                                    />
                                    <Pin v-else class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    class="rounded-lg p-1.5 text-surface-400 opacity-0 transition group-hover:opacity-100 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-900/20 dark:hover:text-rose-400"
                                    title="Hapus pengumuman"
                                    @click="askDelete(announcement)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </template>
                        </div>
                    </div>

                    <h3
                        class="mt-4 text-base leading-snug font-semibold text-surface-950 dark:text-surface-50"
                    >
                        {{ announcement.title }}
                    </h3>
                    <p
                        class="mt-2 line-clamp-4 text-sm leading-6 whitespace-pre-line text-surface-700 dark:text-surface-200"
                    >
                        {{ announcement.body }}
                    </p>

                    <div
                        class="mt-auto flex items-center gap-1.5 pt-4 text-[11px] text-surface-500 dark:text-surface-400"
                    >
                        <CalendarDays class="h-3.5 w-3.5" />
                        {{ formatDate(announcement.published_at) }}
                    </div>
                </article>
            </div>

            <Pagination
                v-if="meta.last_page > 1"
                :current-page="meta.current_page"
                :last-page="meta.last_page"
                :from="meta.from"
                :to="meta.to"
                :total="meta.total"
                @change="changePage"
            />
        </template>

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
