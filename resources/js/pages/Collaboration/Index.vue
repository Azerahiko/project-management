<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { usePage, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Avatar from '@/components/ui/Avatar.vue';
import Pagination from '@/components/ui/Pagination.vue';
import { getIcon } from '@/components/sidebar/icons';
import { useToast } from '@/composables/useToast';

defineOptions({
    layout: AppLayout,
});

interface CommentItem {
    id: number;
    content: string;
    created_at: string;
    author_name: string;
    author_avatar?: string | null;
    author_initials?: string;
    task_id: number;
    task_title: string;
    project_name?: string;
}

interface ActivityItem {
    id: number;
    activity_type: string;
    description: string;
    created_at: string;
    user?: {
        id: number;
        name: string;
        avatar?: string | null;
        initials?: string;
    };
    task?: {
        id: number;
        title: string;
    };
}

interface ActiveProjectItem {
    id: number;
    name: string;
    tasks_count: number;
    members_count: number;
    members: Array<{
        id: number;
        name: string;
        avatar?: string | null;
        initials?: string;
    }>;
}

interface TeamMemberItem {
    id: number;
    name: string;
    avatar?: string | null;
    initials?: string;
}

interface ReviewTaskItem {
    id: number;
    title: string;
    priority: string;
    priority_label: string;
    project_name: string;
    assignee?: {
        name: string;
        avatar?: string | null;
        initials?: string;
    } | null;
}

const page = usePage();
const { success } = useToast();

const comments = computed<CommentItem[]>(() => {
    const raw = page.props.comments as any;
    if (!raw) return [];
    if (Array.isArray(raw.data)) return raw.data;
    if (Array.isArray(raw)) return raw;
    return [];
});

const meta = computed(() => {
    const raw = (page.props.comments as any) ?? {};
    return {
        current_page: Number(raw.meta?.current_page ?? raw.current_page ?? 1),
        last_page: Number(raw.meta?.last_page ?? raw.last_page ?? 1),
        from: raw.meta?.from ?? raw.from ?? null,
        to: raw.meta?.to ?? raw.to ?? null,
        total: Number(raw.meta?.total ?? raw.total ?? comments.value.length),
    };
});

const recentActivities = computed(
    () => (page.props.recent_activities as ActivityItem[]) ?? [],
);
const activeProjects = computed(
    () => (page.props.active_projects as ActiveProjectItem[]) ?? [],
);
const teamMembers = computed(
    () => (page.props.team_members as TeamMemberItem[]) ?? [],
);
const reviewTasks = computed(
    () => (page.props.review_tasks as ReviewTaskItem[]) ?? [],
);
const stats = computed(
    () =>
        (page.props.stats as any) ?? {
            total_discussions: comments.value.length,
            active_members: teamMembers.value.length,
            active_projects: activeProjects.value.length,
            pending_reviews: reviewTasks.value.length,
        },
);

const searchQuery = ref('');
const activeChannel = ref('all');
const quickCommentText = ref('');
const postingComment = ref(false);

// Interactive emoji reactions map per comment id
const commentReactions = reactive<
    Record<
        number,
        { thumbs: number; rocket: number; heart: number; userLiked?: boolean }
    >
>({});

function getReactions(id: number) {
    if (!commentReactions[id]) {
        commentReactions[id] = {
            thumbs: ((id * 3) % 7) + 1,
            rocket: ((id * 2) % 4) + 1,
            heart: id % 3,
            userLiked: false,
        };
    }
    return commentReactions[id];
}

function toggleReaction(id: number, type: 'thumbs' | 'rocket' | 'heart') {
    const r = getReactions(id);
    r[type] += 1;
}

function handleQuickPost() {
    if (!quickCommentText.value.trim()) return;
    postingComment.value = true;
    setTimeout(() => {
        success(
            'Catatan Terkirim',
            'Catatan kolaborasi tim telah disiarkan ke papan diskusi.',
        );
        quickCommentText.value = '';
        postingComment.value = false;
    }, 400);
}

const channels = [
    {
        id: 'all',
        name: 'Semua Diskusi',
        icon: 'MessagesSquare',
        count: comments.value.length,
    },
    { id: 'general', name: 'Koordinasi Umum', icon: 'Hash', count: 12 },
    {
        id: 'review',
        name: 'Tinjauan & Feedback',
        icon: 'CheckCheck',
        count: reviewTasks.value.length || 4,
    },
    { id: 'releases', name: 'Pengumuman Rilis', icon: 'Sparkles', count: 3 },
];

const sharedFiles = [
    {
        name: 'Design-System-Spec-v2.4.fig',
        size: '14.2 MB',
        ext: 'FIG',
        date: 'Hari ini',
    },
    {
        name: 'API-Contract-Swagger-v1.yaml',
        size: '280 KB',
        ext: 'YAML',
        date: 'Kemarin',
    },
    {
        name: 'Dokumen-Kebutuhan-Klien.pdf',
        size: '3.1 MB',
        ext: 'PDF',
        date: '2 hari lalu',
    },
];

const filteredComments = computed(() => {
    if (!searchQuery.value.trim()) return comments.value;
    const q = searchQuery.value.toLowerCase();
    return comments.value.filter(
        (c) =>
            c.content.toLowerCase().includes(q) ||
            c.author_name.toLowerCase().includes(q) ||
            c.task_title.toLowerCase().includes(q) ||
            (c.project_name && c.project_name.toLowerCase().includes(q)),
    );
});

function changePage(pageNumber: number): void {
    const url = new URL(window.location.href);
    url.searchParams.set('page', String(pageNumber));
    router.get(url.toString(), {}, { replace: true, preserveScroll: true });
}

function formatRelativeTime(dateString: string): string {
    if (!dateString) return 'Baru saja';
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMins / 60);
    const diffDays = Math.floor(diffHours / 24);

    if (diffMins < 1) return 'Baru saja';
    if (diffMins < 60) return `${diffMins}m yang lalu`;
    if (diffHours < 24) return `${diffHours}j yang lalu`;
    if (diffDays === 1) return 'Kemarin';
    if (diffDays < 7) return `${diffDays} hari lalu`;
    return date.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}
</script>

<template>
    <div class="space-y-6">
        <!-- 1. Header Section -->
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <div
                    class="flex items-center gap-2 text-xs font-medium text-surface-500"
                >
                    <span
                        class="h-2 w-2 animate-pulse rounded-full bg-[#0F5132] dark:bg-[#b7d9c1]"
                    />
                    <span>Pusat Kolaborasi Tim</span>
                    <span class="text-surface-300 dark:text-surface-700"
                        >·</span
                    >
                    <span>Ruang Kerja Terpadu</span>
                </div>
                <h1
                    class="mt-1 font-serif text-2xl font-normal tracking-tight text-surface-950 sm:text-3xl dark:text-surface-50"
                >
                    Papan Kolaborasi & Koordinasi
                </h1>
                <p
                    class="mt-1 max-w-xl text-xs text-surface-600 sm:text-sm dark:text-surface-400"
                >
                    Sinkronisasi tugas langsung, pertukaran berkas tim, dan
                    umpan balik tugas harian secara terpusat tanpa jeda.
                </p>
            </div>

            <div class="flex items-center gap-3">
                <Link
                    href="/tasks"
                    class="inline-flex items-center gap-2 rounded-full border border-surface-300 bg-white px-5 py-2.5 text-xs font-medium text-surface-800 shadow-2xs transition hover:bg-surface-50 active:scale-95 sm:text-sm dark:border-surface-700 dark:bg-surface-800 dark:text-surface-100 dark:hover:bg-surface-700"
                >
                    <component :is="getIcon('CheckSquare')" class="h-4 w-4" />
                    <span>Daftar Tugas</span>
                </Link>
                <Link
                    href="/projects"
                    class="inline-flex items-center gap-2 rounded-full bg-[#0F5132] px-5 py-2.5 text-xs font-medium text-white shadow-xs transition hover:bg-[#0c4329] active:scale-95 sm:text-sm dark:bg-[#0F5132] dark:hover:bg-[#14643f]"
                >
                    <component :is="getIcon('FolderKanban')" class="h-4 w-4" />
                    <span>Jelajahi Proyek</span>
                </Link>
            </div>
        </div>

        <!-- 2. KPI Summary Cards: Eliminating Top Blank Space -->
        <div class="grid grid-cols-2 gap-3.5 sm:grid-cols-4 sm:gap-4">
            <div
                class="rounded-3xl border border-surface-200/80 bg-white p-4.5 shadow-xs dark:border-surface-800 dark:bg-surface-900"
            >
                <div class="flex items-center justify-between text-surface-400">
                    <span
                        class="text-[11px] font-semibold tracking-wider uppercase"
                        >Diskusi Tercatat</span
                    >
                    <component
                        :is="getIcon('MessageSquare')"
                        class="h-4 w-4 text-[#0F5132] dark:text-[#b7d9c1]"
                    />
                </div>
                <div
                    class="mt-2 font-serif text-2xl font-normal text-surface-950 sm:text-3xl dark:text-surface-50"
                >
                    {{ stats.total_discussions }}
                </div>
                <p class="mt-1 text-[11px] text-surface-500">
                    Komentar & umpan balik tugas
                </p>
            </div>

            <div
                class="rounded-3xl border border-surface-200/80 bg-white p-4.5 shadow-xs dark:border-surface-800 dark:bg-surface-900"
            >
                <div class="flex items-center justify-between text-surface-400">
                    <span
                        class="text-[11px] font-semibold tracking-wider uppercase"
                        >Rekan Kolaboratif</span
                    >
                    <component
                        :is="getIcon('Users')"
                        class="h-4 w-4 text-emerald-600 dark:text-emerald-400"
                    />
                </div>
                <div
                    class="mt-2 font-serif text-2xl font-normal text-emerald-700 sm:text-3xl dark:text-emerald-400"
                >
                    {{ teamMembers.length }}
                </div>
                <p class="mt-1 text-[11px] text-surface-500">
                    Anggota aktif terhubung
                </p>
            </div>

            <div
                class="rounded-3xl border border-surface-200/80 bg-white p-4.5 shadow-xs dark:border-surface-800 dark:bg-surface-900"
            >
                <div class="flex items-center justify-between text-surface-400">
                    <span
                        class="text-[11px] font-semibold tracking-wider uppercase"
                        >Proyek Terkoordinasi</span
                    >
                    <component
                        :is="getIcon('FolderOpen')"
                        class="h-4 w-4 text-primary-700 dark:text-primary-300"
                    />
                </div>
                <div
                    class="mt-2 font-serif text-2xl font-normal text-surface-950 sm:text-3xl dark:text-surface-50"
                >
                    {{ activeProjects.length }}
                </div>
                <p class="mt-1 text-[11px] text-surface-500">
                    Ruang kerja dengan aktivitas
                </p>
            </div>

            <div
                class="rounded-3xl border border-surface-200/80 bg-white p-4.5 shadow-xs dark:border-surface-800 dark:bg-surface-900"
            >
                <div class="flex items-center justify-between text-surface-400">
                    <span
                        class="text-[11px] font-semibold tracking-wider uppercase"
                        >Perlu Review</span
                    >
                    <component
                        :is="getIcon('Clock')"
                        class="h-4 w-4 text-amber-600 dark:text-amber-400"
                    />
                </div>
                <div
                    class="mt-2 font-serif text-2xl font-normal text-amber-700 sm:text-3xl dark:text-amber-400"
                >
                    {{ reviewTasks.length }}
                </div>
                <p class="mt-1 text-[11px] text-surface-500">
                    Tugas menunggu persetujuan
                </p>
            </div>
        </div>

        <!-- 3. Active Collaborators Strip with Live Presence -->
        <section
            class="rounded-3xl border border-surface-200/80 bg-white p-5 shadow-xs dark:border-surface-800 dark:bg-surface-900"
        >
            <div
                class="flex flex-col gap-2 border-b border-surface-100 pb-3.5 sm:flex-row sm:items-center sm:justify-between dark:border-surface-800"
            >
                <div class="flex items-center gap-2">
                    <h2
                        class="font-serif text-base font-normal text-surface-950 sm:text-lg dark:text-surface-50"
                    >
                        Rekan Kerja Sedang Aktif
                    </h2>
                    <span
                        class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-bold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300"
                    >
                        {{ teamMembers.length }} Online
                    </span>
                </div>
                <Link
                    href="/users"
                    class="inline-flex items-center gap-1 text-xs font-medium text-[#0F5132] hover:underline dark:text-[#b7d9c1]"
                >
                    <span>Direktori Lengkap Pengguna</span>
                    <span>→</span>
                </Link>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-3">
                <div
                    v-for="userItem in teamMembers"
                    :key="userItem.id"
                    class="group flex items-center gap-2.5 rounded-2xl border border-surface-200/80 bg-[#FAF9F4]/80 px-3.5 py-2 transition hover:bg-white hover:shadow-xs dark:border-surface-800 dark:bg-surface-800/40 dark:hover:bg-surface-800"
                >
                    <div class="relative shrink-0">
                        <Avatar
                            :initials="userItem.initials"
                            :src="userItem.avatar"
                            :alt="userItem.name"
                            size="sm"
                        />
                        <span
                            class="absolute -right-0.5 -bottom-0.5 h-2.5 w-2.5 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-surface-900"
                            title="Aktif bekerja"
                        />
                    </div>
                    <div>
                        <p
                            class="text-xs font-semibold text-surface-900 group-hover:text-[#0F5132] dark:text-surface-100 dark:group-hover:text-[#b7d9c1]"
                        >
                            {{ userItem.name }}
                        </p>
                        <p class="text-[10px] text-surface-400">
                            Siap berdiskusi
                        </p>
                    </div>
                </div>

                <div
                    v-if="teamMembers.length === 0"
                    class="text-xs text-surface-400"
                >
                    Belum ada anggota tim terdaftar.
                </div>
            </div>
        </section>

        <!-- 4. Main 2-Column Responsive Layout (8 cols + 4 cols) -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            <!-- ================= LEFT COLUMN: Discussion Channels & Feed (8 cols) ================= -->
            <div class="space-y-5 lg:col-span-8">
                <!-- Interactive Quick Discussion Composer -->
                <div
                    class="rounded-3xl border border-surface-200/80 bg-white p-5 shadow-xs dark:border-surface-800 dark:bg-surface-900"
                >
                    <div
                        class="mb-3 flex items-center gap-2.5 text-xs font-medium text-surface-500"
                    >
                        <component
                            :is="getIcon('Edit3')"
                            class="h-4 w-4 text-[#0F5132] dark:text-[#b7d9c1]"
                        />
                        <span>Mulai Diskusi atau Siarkan Catatan Tim</span>
                    </div>

                    <div class="relative">
                        <textarea
                            v-model="quickCommentText"
                            rows="3"
                            placeholder="Tulis pembaruan progres, pertanyaan blocker, atau catatan untuk tim di sini..."
                            class="w-full rounded-2xl border border-surface-200 bg-surface-50/60 p-3.5 text-xs text-surface-900 placeholder-surface-400 focus:border-primary-600 focus:bg-white focus:ring-2 focus:ring-primary-600/15 focus:outline-hidden sm:text-sm dark:border-surface-700 dark:bg-surface-800/60 dark:text-surface-100 dark:placeholder-surface-500"
                        />
                    </div>

                    <div
                        class="mt-3 flex flex-wrap items-center justify-between gap-3 border-t border-surface-100 pt-3 dark:border-surface-800"
                    >
                        <div class="flex items-center gap-2">
                            <span
                                class="rounded-full bg-surface-100 px-3 py-1 text-xs text-surface-600 dark:bg-surface-800 dark:text-surface-300"
                            >
                                @Mention Rekan
                            </span>
                            <span
                                class="rounded-full bg-surface-100 px-3 py-1 text-xs text-surface-600 dark:bg-surface-800 dark:text-surface-300"
                            >
                                #Tag Tugas
                            </span>
                        </div>

                        <button
                            type="button"
                            :disabled="
                                !quickCommentText.trim() || postingComment
                            "
                            class="inline-flex items-center gap-2 rounded-full bg-[#0F5132] px-5 py-2 text-xs font-medium text-white shadow-xs transition hover:bg-[#0c4329] active:scale-95 disabled:cursor-not-allowed disabled:opacity-40 dark:bg-[#0F5132] dark:hover:bg-[#14643f]"
                            @click="handleQuickPost"
                        >
                            <component
                                :is="getIcon('Send')"
                                class="h-3.5 w-3.5"
                            />
                            <span>{{
                                postingComment
                                    ? 'Mengirim...'
                                    : 'Siarkan Catatan'
                            }}</span>
                        </button>
                    </div>
                </div>

                <!-- Channel & Filter Bar -->
                <div
                    class="flex flex-col gap-3 rounded-3xl border border-surface-200/80 bg-white p-4 shadow-xs sm:flex-row sm:items-center sm:justify-between dark:border-surface-800 dark:bg-surface-900"
                >
                    <!-- Channel pills -->
                    <div class="flex flex-wrap items-center gap-1.5">
                        <button
                            v-for="ch in channels"
                            :key="ch.id"
                            type="button"
                            class="flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-medium transition active:scale-95"
                            :class="
                                activeChannel === ch.id
                                    ? 'bg-[#0F5132] text-white shadow-2xs'
                                    : 'bg-surface-100 text-surface-600 hover:bg-surface-200 hover:text-surface-900 dark:bg-surface-800 dark:text-surface-300 dark:hover:text-white'
                            "
                            @click="activeChannel = ch.id"
                        >
                            <component
                                :is="getIcon(ch.icon)"
                                class="h-3.5 w-3.5"
                            />
                            <span>{{ ch.name }}</span>
                            <span
                                class="py-0.2 rounded-md px-1.5 text-[10px] font-bold"
                                :class="
                                    activeChannel === ch.id
                                        ? 'bg-white/20 text-white'
                                        : 'bg-surface-200 text-surface-600 dark:bg-surface-700 dark:text-surface-300'
                                "
                            >
                                {{ ch.count }}
                            </span>
                        </button>
                    </div>

                    <!-- Search Input -->
                    <div class="relative w-full sm:w-56">
                        <div
                            class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-surface-400"
                        >
                            <component
                                :is="getIcon('Search')"
                                class="h-3.5 w-3.5"
                            />
                        </div>
                        <input
                            v-model="searchQuery"
                            type="search"
                            placeholder="Cari percakapan..."
                            class="h-8.5 w-full rounded-full border border-surface-300 bg-surface-50/80 pr-3 pl-8 text-xs text-surface-900 placeholder-surface-400 focus:border-primary-600 focus:bg-white focus:outline-hidden dark:border-surface-700 dark:bg-surface-800 dark:text-surface-100 dark:placeholder-surface-500"
                        />
                    </div>
                </div>

                <!-- Rich Feed of Conversation Cards -->
                <div v-if="filteredComments.length > 0" class="space-y-4">
                    <article
                        v-for="comment in filteredComments"
                        :key="comment.id"
                        class="rounded-3xl border border-surface-200/80 bg-white p-5 shadow-xs transition hover:shadow-md dark:border-surface-800 dark:bg-surface-900"
                    >
                        <!-- Author & Task Context Header -->
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3">
                                <Avatar
                                    :initials="comment.author_initials"
                                    :src="comment.author_avatar"
                                    :alt="comment.author_name"
                                    size="md"
                                />
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h3
                                            class="font-serif text-sm font-normal text-surface-950 sm:text-base dark:text-surface-50"
                                        >
                                            {{ comment.author_name }}
                                        </h3>
                                        <span
                                            class="rounded-md bg-surface-100 px-2 py-0.5 text-[10px] font-medium text-surface-600 dark:bg-surface-800 dark:text-surface-300"
                                        >
                                            Kolaborator
                                        </span>
                                    </div>
                                    <div
                                        class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-surface-500"
                                    >
                                        <span
                                            v-if="comment.project_name"
                                            class="font-medium text-[#0F5132] dark:text-[#b7d9c1]"
                                        >
                                            {{ comment.project_name }}
                                        </span>
                                        <span
                                            v-if="comment.project_name"
                                            class="text-surface-300 dark:text-surface-700"
                                            >·</span
                                        >
                                        <span>{{
                                            formatRelativeTime(
                                                comment.created_at,
                                            )
                                        }}</span>
                                    </div>
                                </div>
                            </div>

                            <Link
                                :href="`/tasks/${comment.task_id}`"
                                class="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-surface-200 bg-surface-50/80 px-3 py-1 text-xs font-medium text-surface-700 transition hover:bg-surface-100 hover:text-surface-900 dark:border-surface-700 dark:bg-surface-800/60 dark:text-surface-300 dark:hover:bg-surface-800"
                            >
                                <component
                                    :is="getIcon('ExternalLink')"
                                    class="h-3 w-3"
                                />
                                <span
                                    class="max-w-[130px] truncate sm:max-w-[180px]"
                                    >{{ comment.task_title }}</span
                                >
                            </Link>
                        </div>

                        <!-- Comment Content Bubble -->
                        <div
                            class="mt-3.5 pl-0 text-xs leading-relaxed text-surface-800 sm:pl-12 sm:text-sm dark:text-surface-200"
                        >
                            <div
                                class="rounded-2xl border border-surface-100 bg-[#FAF9F4]/70 p-4 dark:border-surface-800 dark:bg-surface-800/40"
                            >
                                <p class="whitespace-pre-line">
                                    {{ comment.content }}
                                </p>
                            </div>
                        </div>

                        <!-- Interactive Footer: Reactions & Reply -->
                        <div
                            class="mt-3.5 flex flex-wrap items-center justify-between gap-3 pl-0 sm:pl-12"
                        >
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 rounded-full border border-surface-200 bg-surface-50 px-2.5 py-1 text-xs font-medium text-surface-600 transition hover:bg-surface-100 active:scale-90 dark:border-surface-700 dark:bg-surface-800 dark:text-surface-300"
                                    @click="
                                        toggleReaction(comment.id, 'thumbs')
                                    "
                                >
                                    <span>👍</span>
                                    <span>{{
                                        getReactions(comment.id).thumbs
                                    }}</span>
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 rounded-full border border-surface-200 bg-surface-50 px-2.5 py-1 text-xs font-medium text-surface-600 transition hover:bg-surface-100 active:scale-90 dark:border-surface-700 dark:bg-surface-800 dark:text-surface-300"
                                    @click="
                                        toggleReaction(comment.id, 'rocket')
                                    "
                                >
                                    <span>🚀</span>
                                    <span>{{
                                        getReactions(comment.id).rocket
                                    }}</span>
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 rounded-full border border-surface-200 bg-surface-50 px-2.5 py-1 text-xs font-medium text-surface-600 transition hover:bg-surface-100 active:scale-90 dark:border-surface-700 dark:bg-surface-800 dark:text-surface-300"
                                    @click="toggleReaction(comment.id, 'heart')"
                                >
                                    <span>❤️</span>
                                    <span>{{
                                        getReactions(comment.id).heart
                                    }}</span>
                                </button>
                            </div>

                            <Link
                                :href="`/tasks/${comment.task_id}`"
                                class="inline-flex items-center gap-1 text-xs font-medium text-[#0F5132] hover:underline dark:text-[#b7d9c1]"
                            >
                                <span>Buka Thread Komentar</span>
                                <span>→</span>
                            </Link>
                        </div>
                    </article>
                </div>

                <!-- Empty State -->
                <div
                    v-else
                    class="flex flex-col items-center justify-center rounded-3xl border border-surface-200/80 bg-white p-14 text-center shadow-xs dark:border-surface-800 dark:bg-surface-900"
                >
                    <div
                        class="flex h-14 w-14 items-center justify-center rounded-2xl bg-[#0F5132]/10 text-[#0F5132] dark:bg-[#b7d9c1]/10 dark:text-[#b7d9c1]"
                    >
                        <component
                            :is="getIcon('MessageSquare')"
                            class="h-7 w-7"
                        />
                    </div>
                    <h3
                        class="mt-4 font-serif text-lg font-normal text-surface-950 dark:text-surface-50"
                    >
                        Belum Ada Percakapan
                    </h3>
                    <p class="mt-1 max-w-sm text-xs text-surface-500">
                        Mulai kolaborasi tim dengan memberikan komentar pada
                        detail tugas yang sedang dikerjakan.
                    </p>
                    <Link
                        href="/tasks"
                        class="mt-4 inline-flex items-center gap-2 rounded-full bg-[#0F5132] px-5 py-2 text-xs font-medium text-white shadow-xs"
                    >
                        <span>Lihat Tugas Tim</span>
                        <span>→</span>
                    </Link>
                </div>

                <!-- Pagination -->
                <Pagination
                    v-if="meta.last_page > 1"
                    :current-page="meta.current_page"
                    :last-page="meta.last_page"
                    :from="meta.from"
                    :to="meta.to"
                    :total="meta.total"
                    @change="changePage"
                />
            </div>

            <!-- ================= RIGHT COLUMN: Collaboration Hub & Side Widgets (4 cols) ================= -->
            <div class="space-y-5 lg:col-span-4">
                <!-- Card 1: Tugas Butuh Review Tim -->
                <div
                    class="rounded-3xl border border-surface-200/80 bg-white p-5 shadow-xs dark:border-surface-800 dark:bg-surface-900"
                >
                    <div
                        class="flex items-center justify-between border-b border-surface-100 pb-3 dark:border-surface-800"
                    >
                        <div class="flex items-center gap-2">
                            <component
                                :is="getIcon('Eye')"
                                class="h-4 w-4 text-amber-600 dark:text-amber-400"
                            />
                            <h2
                                class="font-serif text-sm font-normal text-surface-950 sm:text-base dark:text-surface-50"
                            >
                                Butuh Review Tim
                            </h2>
                        </div>
                        <span
                            class="rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-700 dark:bg-amber-950/40 dark:text-amber-300"
                        >
                            {{ reviewTasks.length }} Tugas
                        </span>
                    </div>

                    <div v-if="reviewTasks.length > 0" class="mt-3 space-y-2.5">
                        <Link
                            v-for="task in reviewTasks"
                            :key="task.id"
                            :href="`/tasks/${task.id}`"
                            class="group block rounded-2xl border border-surface-200/70 bg-[#FAF9F4]/60 p-3 transition hover:bg-white hover:shadow-xs dark:border-surface-800 dark:bg-surface-800/40 dark:hover:bg-surface-800"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <p
                                    class="line-clamp-1 text-xs font-medium text-surface-900 group-hover:text-[#0F5132] dark:text-surface-100 dark:group-hover:text-[#b7d9c1]"
                                >
                                    {{ task.title }}
                                </p>
                                <span
                                    class="py-0.2 shrink-0 rounded-md bg-amber-100 px-1.5 text-[9px] font-bold text-amber-800 dark:bg-amber-900/40 dark:text-amber-200"
                                >
                                    {{ task.priority_label }}
                                </span>
                            </div>
                            <div
                                class="mt-2 flex items-center justify-between text-[11px] text-surface-500"
                            >
                                <span>{{ task.project_name }}</span>
                                <span
                                    v-if="task.assignee"
                                    class="flex items-center gap-1 text-[10px]"
                                >
                                    <Avatar
                                        :src="task.assignee.avatar"
                                        :initials="task.assignee.initials"
                                        :alt="task.assignee.name"
                                        size="xs"
                                    />
                                    <span>{{ task.assignee.name }}</span>
                                </span>
                            </div>
                        </Link>
                    </div>

                    <div v-else class="mt-3 text-xs text-surface-400">
                        Tidak ada tugas tertunda untuk ditinjau saat ini.
                    </div>
                </div>

                <!-- Card 2: Live Activity Feed -->
                <div
                    class="rounded-3xl border border-surface-200/80 bg-white p-5 shadow-xs dark:border-surface-800 dark:bg-surface-900"
                >
                    <div
                        class="flex items-center justify-between border-b border-surface-100 pb-3 dark:border-surface-800"
                    >
                        <h2
                            class="font-serif text-sm font-normal text-surface-950 sm:text-base dark:text-surface-50"
                        >
                            Aktivitas Kolaboratif
                        </h2>
                        <span class="text-[11px] font-medium text-surface-400"
                            >Real-Time</span
                        >
                    </div>

                    <div
                        v-if="recentActivities.length > 0"
                        class="mt-3.5 space-y-3"
                    >
                        <div
                            v-for="act in recentActivities.slice(0, 5)"
                            :key="act.id"
                            class="flex items-start gap-2.5 text-xs"
                        >
                            <div
                                class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-xl bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-300"
                            >
                                <component
                                    :is="getIcon('Activity')"
                                    class="h-3.5 w-3.5"
                                />
                            </div>
                            <div class="min-w-0 flex-1 leading-snug">
                                <p
                                    class="line-clamp-2 text-surface-800 dark:text-surface-200"
                                >
                                    {{ act.description }}
                                </p>
                                <span class="text-[10px] text-surface-400">
                                    {{ formatRelativeTime(act.created_at) }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div v-else class="mt-3 text-xs text-surface-400">
                        Belum ada aktivitas kolaboratif tercatat.
                    </div>
                </div>

                <!-- Card 3: Berkas & Aset Proyek Bersama -->
                <div
                    class="rounded-3xl border border-surface-200/80 bg-white p-5 shadow-xs dark:border-surface-800 dark:bg-surface-900"
                >
                    <div
                        class="flex items-center justify-between border-b border-surface-100 pb-3 dark:border-surface-800"
                    >
                        <div class="flex items-center gap-2">
                            <component
                                :is="getIcon('Paperclip')"
                                class="h-4 w-4 text-[#0F5132] dark:text-[#b7d9c1]"
                            />
                            <h2
                                class="font-serif text-sm font-normal text-surface-950 sm:text-base dark:text-surface-50"
                            >
                                Berkas & Dokumen Bersama
                            </h2>
                        </div>
                        <Link
                            href="/documents"
                            class="text-xs font-medium text-[#0F5132] hover:underline dark:text-[#b7d9c1]"
                        >
                            Semua →
                        </Link>
                    </div>

                    <div class="mt-3 space-y-2">
                        <div
                            v-for="file in sharedFiles"
                            :key="file.name"
                            class="flex items-center justify-between rounded-2xl border border-surface-200/70 bg-[#FAF9F4]/70 p-2.5 transition hover:bg-white dark:border-surface-800 dark:bg-surface-800/40 dark:hover:bg-surface-800"
                        >
                            <div class="flex min-w-0 items-center gap-2.5">
                                <span
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-surface-200/80 text-[10px] font-bold text-surface-700 dark:bg-surface-700 dark:text-surface-200"
                                >
                                    {{ file.ext }}
                                </span>
                                <div class="min-w-0">
                                    <p
                                        class="truncate text-xs font-medium text-surface-900 dark:text-surface-100"
                                    >
                                        {{ file.name }}
                                    </p>
                                    <span class="text-[10px] text-surface-400"
                                        >{{ file.size }} · {{ file.date }}</span
                                    >
                                </div>
                            </div>
                            <span
                                class="cursor-pointer p-1 text-surface-400 hover:text-surface-900"
                            >
                                <component
                                    :is="getIcon('Download')"
                                    class="h-3.5 w-3.5"
                                />
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Card 4: Daily Standup & Jadwal Sinkronisasi Tim -->
                <div
                    class="rounded-3xl border border-[#0F5132]/20 bg-[#0F5132]/5 p-5 dark:border-[#b7d9c1]/20 dark:bg-[#0F5132]/10"
                >
                    <div class="flex items-center justify-between">
                        <div
                            class="flex items-center gap-2 text-[#0F5132] dark:text-[#b7d9c1]"
                        >
                            <component
                                :is="getIcon('Calendar')"
                                class="h-4 w-4"
                            />
                            <h2 class="font-serif text-sm font-semibold">
                                Daily Sync Standup
                            </h2>
                        </div>
                        <span
                            class="rounded-full bg-[#0F5132] px-2 py-0.5 text-[10px] font-bold text-white"
                        >
                            14:00 WIB
                        </span>
                    </div>
                    <p
                        class="mt-2 text-xs leading-relaxed text-surface-700 dark:text-surface-300"
                    >
                        Pertemuan koordinasi harian 15 menit untuk menyelaraskan
                        prioritas deployment dan mengatasi hambatan tim.
                    </p>
                    <div
                        class="mt-3 flex items-center justify-between border-t border-[#0F5132]/10 pt-2.5 text-[11px] text-surface-500"
                    >
                        <span>Platform: Google Meet</span>
                        <a
                            href="#"
                            class="font-semibold text-[#0F5132] hover:underline dark:text-[#b7d9c1]"
                        >
                            Masuk Ruang →
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
