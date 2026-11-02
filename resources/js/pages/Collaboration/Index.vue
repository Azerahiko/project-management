<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Avatar from '@/components/ui/Avatar.vue';
import Pagination from '@/components/ui/Pagination.vue';
import Select from '@/components/ui/Select.vue';
import { getIcon } from '@/components/sidebar/icons';
import { useToast } from '@/composables/useToast';
import api from '@/lib/api';
import { collaboration as collaborationRoute } from '@/routes';
import projectRoutes from '@/routes/projects';
import taskRoutes from '@/routes/tasks';
import users from '@/routes/users';
import {
    Activity,
    ArrowRight,
    ArrowUpRight,
    Check,
    ClipboardCheck,
    Copy,
    ExternalLink,
    FolderKanban,
    Hash,
    Inbox,
    Info,
    ListChecks,
    MessageSquare,
    Search,
    Send,
    Sparkles,
    Users,
} from '@lucide/vue';

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

interface DiscussionTaskItem {
    id: number;
    title: string;
    project_name: string;
    status: string;
    status_label: string;
}

const page = usePage();
const { success, error } = useToast();

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
const discussionTasks = computed(
    () => (page.props.discussion_tasks as DiscussionTaskItem[]) ?? [],
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
const canViewTasks = computed(() => Boolean(page.props.can_view_tasks));
const canViewProjects = computed(() => Boolean(page.props.can_view_projects));

const initialSearch = (page.props.filters as { search?: string } | undefined)
    ?.search;
const searchQuery = ref(initialSearch ?? '');
const activeFeed = ref<'discussions' | 'activity'>('discussions');

const composerTaskId = ref('');
const composerContent = ref('');
const composerError = ref<string | null>(null);
const postingComment = ref(false);

const selectedComposerTask = computed(() =>
    discussionTasks.value.find(
        (task) => String(task.id) === composerTaskId.value,
    ),
);

const discussionTaskOptions = computed(() => [
    { value: '', label: 'Pilih tugas tujuan...' },
    ...discussionTasks.value.map((task) => ({
        value: String(task.id),
        label: `${task.project_name} — ${task.title}`,
    })),
]);

const visibleTeamMembers = computed(() => teamMembers.value.slice(0, 7));
const remainingTeamMembers = computed(() =>
    Math.max(0, teamMembers.value.length - visibleTeamMembers.value.length),
);

const summaryCards = computed(() => [
    {
        label: 'Diskusi',
        value: stats.value.total_discussions,
        hint: 'Catatan di seluruh tugas',
        icon: MessageSquare,
        tone: 'text-primary-700 dark:text-primary-300',
        surface:
            'bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300',
    },
    {
        label: 'Rekan kerja',
        value: teamMembers.value.length,
        hint: 'Anggota aktif di proyek',
        icon: Users,
        tone: 'text-accent-700 dark:text-accent-300',
        surface:
            'bg-accent-50 dark:bg-accent-900/30 text-accent-700 dark:text-accent-300',
    },
    {
        label: 'Proyek',
        value: activeProjects.value.length,
        hint: 'Ruang kerja terhubung',
        icon: FolderKanban,
        tone: 'text-primary-700 dark:text-primary-300',
        surface:
            'bg-primary-50 dark:bg-primary-900/30 text-primary-700 dark:text-primary-300',
    },
    {
        label: 'Perlu review',
        value: stats.value.pending_reviews,
        hint: 'Menunggu persetujuan',
        icon: ClipboardCheck,
        tone: 'text-rose-600 dark:text-rose-300',
        surface:
            'bg-rose-50 dark:bg-rose-950/30 text-rose-600 dark:text-rose-300',
    },
]);

let searchTimer: ReturnType<typeof setTimeout> | null = null;

watch(searchQuery, (value) => {
    if (searchTimer) {
        clearTimeout(searchTimer);
    }

    searchTimer = setTimeout(() => {
        router.get(
            collaborationRoute.url({
                query: {
                    search: value.trim() || undefined,
                },
            }),
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                only: ['comments', 'filters'],
            },
        );
    }, 350);
});

onBeforeUnmount(() => {
    if (searchTimer) {
        clearTimeout(searchTimer);
    }
});

async function submitDiscussion(): Promise<void> {
    composerError.value = null;

    const task = selectedComposerTask.value;
    const content = composerContent.value.trim();

    if (!task) {
        composerError.value =
            'Pilih tugas yang ingin didiskusikan terlebih dahulu.';
        return;
    }

    if (!content) {
        composerError.value = 'Tulis isi diskusi sebelum mengirim.';
        return;
    }

    postingComment.value = true;

    try {
        await api.post(`/tasks/${task.id}/comments`, { content });
        success(
            'Diskusi terkirim',
            `Catatan Anda sudah dibagikan di ${task.project_name}.`,
        );
        composerContent.value = '';
        router.reload({
            only: ['comments', 'stats', 'recent_activities'],
        });
    } catch (requestError: any) {
        composerError.value =
            requestError?.response?.data?.message ??
            'Diskusi gagal dikirim. Periksa koneksi lalu coba lagi.';
    } finally {
        postingComment.value = false;
    }
}

async function copyTaskLink(comment: CommentItem): Promise<void> {
    const url = `${window.location.origin}/tasks/${comment.task_id}`;

    try {
        await navigator.clipboard.writeText(url);
        success('Tautan disalin', 'Tautan thread tugas siap dibagikan ke tim.');
    } catch {
        error(
            'Gagal menyalin tautan',
            'Salin tautan langsung dari bilah alamat browser.',
        );
    }
}

function changePage(pageNumber: number): void {
    router.get(
        collaborationRoute.url({
            query: {
                page: pageNumber,
                search: searchQuery.value.trim() || undefined,
            },
        }),
        {},
        { preserveScroll: true },
    );
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

function activityIcon(type: string): string {
    if (type.startsWith('comment')) {
        return 'MessageSquare';
    }

    if (type === 'task.created') {
        return 'Plus';
    }

    if (type === 'task.updated') {
        return 'Pencil';
    }

    if (type === 'task.deleted') {
        return 'Trash2';
    }

    return 'Activity';
}

function priorityDot(priority: string): string {
    switch (priority) {
        case 'urgent':
            return 'bg-rose-500';
        case 'high':
            return 'bg-accent-500';
        case 'medium':
            return 'bg-primary-500';
        default:
            return 'bg-surface-300 dark:bg-surface-500';
    }
}

function projectInitials(name: string): string {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((word) => word[0])
        .join('')
        .toUpperCase();
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
                        Ruang kolaborasi
                    </span>
                </div>
                <h1
                    class="mt-3 font-serif text-3xl font-normal tracking-tight text-surface-950 sm:text-4xl dark:text-surface-50"
                >
                    Kolaborasi Tim
                </h1>
                <p
                    class="mt-2 max-w-xl text-sm leading-6 text-surface-600 dark:text-surface-300"
                >
                    Satu ruang untuk diskusi tugas, keputusan proyek, dan
                    koordinasi harian. Semua percakapan tetap menempel pada
                    pekerjaan yang relevan.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Link
                    :href="taskRoutes.index.url()"
                    class="inline-flex items-center gap-1.5 rounded-full border border-surface-200 bg-white px-3.5 py-2 text-xs font-semibold text-surface-700 shadow-2xs transition hover:border-surface-300 hover:bg-surface-50 active:scale-95 dark:border-surface-700 dark:bg-surface-900 dark:text-surface-200 dark:hover:bg-surface-800"
                >
                    <ListChecks class="h-3.5 w-3.5" />
                    Daftar tugas
                </Link>
                <Link
                    :href="projectRoutes.index.url()"
                    class="inline-flex items-center gap-1.5 rounded-full bg-primary-950 px-3.5 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-primary-900 active:scale-95 dark:bg-white dark:text-surface-950 dark:hover:bg-surface-100"
                >
                    <FolderKanban class="h-3.5 w-3.5" />
                    Semua proyek
                </Link>
            </div>
        </div>

        <!-- Pulse strip -->
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
                    class="mt-2 font-serif text-2xl leading-none text-surface-950 dark:text-surface-50"
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

        <div class="grid gap-6 lg:grid-cols-12">
            <!-- Main column -->
            <div class="space-y-5 lg:col-span-8">
                <!-- Composer -->
                <section
                    class="overflow-hidden rounded-[28px] border border-surface-200/80 bg-white shadow-xs dark:border-surface-800 dark:bg-surface-900"
                >
                    <div
                        class="flex flex-col gap-2 border-b border-surface-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-surface-800"
                    >
                        <div class="flex items-center gap-2.5">
                            <span
                                class="grid h-8 w-8 place-items-center rounded-xl bg-primary-50 text-primary-700 ring-1 ring-primary-100 dark:bg-primary-900/30 dark:text-primary-300 dark:ring-primary-800/50"
                            >
                                <MessageSquare class="h-4 w-4" />
                            </span>
                            <div>
                                <p
                                    class="font-serif text-base text-surface-950 dark:text-surface-50"
                                >
                                    Mulai diskusi
                                </p>
                                <p
                                    class="text-[11px] text-surface-400 dark:text-surface-500"
                                >
                                    Diskusi akan tersimpan di thread tugas
                                    terkait.
                                </p>
                            </div>
                        </div>
                        <span
                            class="inline-flex w-fit items-center gap-1.5 rounded-full bg-surface-100 px-2.5 py-1 text-[10px] font-semibold text-surface-500 dark:bg-surface-800 dark:text-surface-400"
                        >
                            <span
                                class="h-1.5 w-1.5 rounded-full bg-primary-500"
                            />
                            Tersinkron dengan tugas
                        </span>
                    </div>

                    <form
                        v-if="canViewTasks && discussionTasks.length > 0"
                        class="p-5"
                        @submit.prevent="submitDiscussion"
                    >
                        <div
                            class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_15rem] sm:items-end"
                        >
                            <Select
                                v-model="composerTaskId"
                                :options="discussionTaskOptions"
                                label="Tugas tujuan"
                            />

                            <div
                                v-if="selectedComposerTask"
                                class="rounded-2xl border border-surface-200 bg-surface-50/80 px-3 py-2 dark:border-surface-700 dark:bg-surface-800/50"
                            >
                                <div class="flex items-center gap-1.5">
                                    <span
                                        class="h-1.5 w-1.5 rounded-full bg-primary-600 dark:bg-primary-300"
                                    />
                                    <p
                                        class="truncate text-[10px] font-bold tracking-[0.12em] text-surface-500 uppercase dark:text-surface-400"
                                    >
                                        {{ selectedComposerTask.project_name }}
                                    </p>
                                </div>
                                <p
                                    class="mt-1 truncate text-[11px] font-medium text-surface-700 dark:text-surface-200"
                                >
                                    {{ selectedComposerTask.status_label }}
                                </p>
                            </div>
                            <div
                                v-else
                                class="hidden rounded-2xl border border-dashed border-surface-200 px-3 py-2 text-[11px] text-surface-400 sm:block dark:border-surface-700 dark:text-surface-500"
                            >
                                Pilih tugas agar diskusi punya konteks yang
                                jelas.
                            </div>
                        </div>

                        <textarea
                            v-model="composerContent"
                            rows="3"
                            maxlength="1000"
                            placeholder="Tulis pembaruan progres, pertanyaan blocker, atau keputusan yang perlu diketahui tim..."
                            class="mt-3 w-full resize-none rounded-2xl border border-surface-200 bg-surface-50/60 p-3.5 text-sm leading-6 text-surface-900 placeholder-surface-400 transition focus:border-primary-600 focus:bg-white focus:ring-2 focus:ring-primary-600/15 focus:outline-hidden dark:border-surface-700 dark:bg-surface-800/50 dark:text-surface-100 dark:placeholder-surface-500 dark:focus:border-primary-400 dark:focus:bg-surface-800"
                        />

                        <div
                            class="mt-1.5 flex items-center justify-between text-[10px] text-surface-400 dark:text-surface-500"
                        >
                            <span>Maksimal 1000 karakter.</span>
                            <span>{{ composerContent.length }}/1000</span>
                        </div>

                        <div
                            v-if="composerError"
                            class="mt-2 rounded-xl bg-rose-50 px-3 py-2 text-xs font-medium text-rose-700 dark:bg-rose-950/40 dark:text-rose-200"
                        >
                            {{ composerError }}
                        </div>

                        <div
                            class="mt-3 flex flex-wrap items-center justify-between gap-3 border-t border-surface-100 pt-3 dark:border-surface-800"
                        >
                            <p
                                class="inline-flex items-center gap-1.5 text-[10px] text-surface-400 dark:text-surface-500"
                            >
                                <Info class="h-3.5 w-3.5" />
                                Anggota proyek akan menerima notifikasi.
                            </p>
                            <button
                                type="submit"
                                :disabled="
                                    postingComment ||
                                    !composerTaskId ||
                                    !composerContent.trim()
                                "
                                class="inline-flex items-center gap-1.5 rounded-full bg-primary-950 px-4 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-primary-900 active:scale-95 disabled:cursor-not-allowed disabled:opacity-40 dark:bg-white dark:text-surface-950 dark:hover:bg-surface-100"
                            >
                                <Send class="h-3.5 w-3.5" />
                                {{
                                    postingComment
                                        ? 'Mengirim...'
                                        : 'Kirim diskusi'
                                }}
                            </button>
                        </div>
                    </form>

                    <div v-else class="px-5 py-8 text-center">
                        <p
                            class="text-xs leading-6 text-surface-500 dark:text-surface-400"
                        >
                            {{
                                canViewTasks
                                    ? 'Belum ada tugas yang bisa didiskusikan. Buat tugas terlebih dahulu agar percakapan punya konteks.'
                                    : 'Anda belum memiliki akses untuk memulai diskusi tugas.'
                            }}
                        </p>
                        <Link
                            v-if="canViewTasks"
                            :href="taskRoutes.index.url()"
                            class="mt-2 inline-flex items-center gap-1 text-[11px] font-semibold text-primary-700 hover:underline dark:text-primary-300"
                        >
                            Buka daftar tugas
                            <ArrowRight class="h-3 w-3" />
                        </Link>
                    </div>
                </section>

                <!-- Feed -->
                <section
                    class="overflow-hidden rounded-[28px] border border-surface-200/80 bg-white shadow-xs dark:border-surface-800 dark:bg-surface-900"
                >
                    <div
                        class="flex flex-col gap-3 border-b border-surface-100 px-5 py-4 xl:flex-row xl:items-center xl:justify-between dark:border-surface-800"
                    >
                        <div class="flex items-center gap-2.5">
                            <span
                                class="grid h-8 w-8 place-items-center rounded-xl bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-300"
                            >
                                <Activity class="h-4 w-4" />
                            </span>
                            <div>
                                <p
                                    class="font-serif text-base text-surface-950 dark:text-surface-50"
                                >
                                    Ruang diskusi
                                </p>
                                <p
                                    class="text-[11px] text-surface-400 dark:text-surface-500"
                                >
                                    {{ meta.total }} percakapan tercatat
                                </p>
                            </div>
                        </div>

                        <div
                            class="flex flex-col gap-2 sm:flex-row sm:items-center"
                        >
                            <div
                                class="inline-flex rounded-full border border-surface-200 bg-surface-100 p-1 dark:border-surface-700 dark:bg-surface-800"
                            >
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-[11px] font-semibold transition"
                                    :class="
                                        activeFeed === 'discussions'
                                            ? 'bg-white text-surface-900 shadow-2xs dark:bg-surface-900 dark:text-white'
                                            : 'text-surface-500 hover:text-surface-800 dark:text-surface-400 dark:hover:text-surface-200'
                                    "
                                    :aria-pressed="activeFeed === 'discussions'"
                                    @click="activeFeed = 'discussions'"
                                >
                                    <MessageSquare class="h-3 w-3" />
                                    Diskusi
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-[11px] font-semibold transition"
                                    :class="
                                        activeFeed === 'activity'
                                            ? 'bg-white text-surface-900 shadow-2xs dark:bg-surface-900 dark:text-white'
                                            : 'text-surface-500 hover:text-surface-800 dark:text-surface-400 dark:hover:text-surface-200'
                                    "
                                    :aria-pressed="activeFeed === 'activity'"
                                    @click="activeFeed = 'activity'"
                                >
                                    <Activity class="h-3 w-3" />
                                    Aktivitas
                                </button>
                            </div>

                            <div
                                v-if="activeFeed === 'discussions'"
                                class="relative w-full sm:w-56"
                            >
                                <Search
                                    class="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-surface-400"
                                />
                                <input
                                    v-model="searchQuery"
                                    type="search"
                                    placeholder="Cari diskusi..."
                                    class="h-8 w-full rounded-full border border-surface-200 bg-surface-50/80 pr-3 pl-8 text-xs text-surface-900 placeholder-surface-400 transition focus:border-primary-600 focus:bg-white focus:ring-2 focus:ring-primary-600/15 focus:outline-hidden dark:border-surface-700 dark:bg-surface-800 dark:text-surface-100 dark:placeholder-surface-500 dark:focus:border-primary-400 dark:focus:bg-surface-800"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Discussions -->
                    <div v-if="activeFeed === 'discussions'">
                        <div v-if="comments.length > 0">
                            <article
                                v-for="comment in comments"
                                :key="comment.id"
                                class="border-b border-surface-100 px-5 py-5 last:border-b-0 dark:border-surface-800"
                            >
                                <div class="flex gap-3.5">
                                    <Avatar
                                        :initials="comment.author_initials"
                                        :src="comment.author_avatar"
                                        :alt="comment.author_name"
                                        size="md"
                                        class="shrink-0"
                                    />

                                    <div class="min-w-0 flex-1">
                                        <div
                                            class="flex flex-wrap items-start justify-between gap-2"
                                        >
                                            <div class="min-w-0">
                                                <div
                                                    class="flex flex-wrap items-center gap-2"
                                                >
                                                    <h3
                                                        class="text-sm font-semibold text-surface-950 dark:text-surface-50"
                                                    >
                                                        {{
                                                            comment.author_name
                                                        }}
                                                    </h3>
                                                    <span
                                                        class="text-[10px] text-surface-400 dark:text-surface-500"
                                                    >
                                                        ·
                                                        {{
                                                            formatRelativeTime(
                                                                comment.created_at,
                                                            )
                                                        }}
                                                    </span>
                                                </div>

                                                <div
                                                    class="mt-1.5 flex flex-wrap items-center gap-1.5 text-[10px]"
                                                >
                                                    <span
                                                        v-if="
                                                            comment.project_name
                                                        "
                                                        class="rounded-full bg-primary-50 px-2 py-0.5 font-semibold text-primary-700 dark:bg-primary-900/30 dark:text-primary-300"
                                                    >
                                                        {{
                                                            comment.project_name
                                                        }}
                                                    </span>
                                                    <span
                                                        class="text-surface-300 dark:text-surface-600"
                                                        >/</span
                                                    >
                                                    <Link
                                                        :href="
                                                            taskRoutes.show.url(
                                                                comment.task_id,
                                                            )
                                                        "
                                                        class="inline-flex min-w-0 items-center gap-1 text-surface-500 transition hover:text-primary-700 dark:text-surface-400 dark:hover:text-primary-300"
                                                    >
                                                        <Hash
                                                            class="h-3 w-3 shrink-0"
                                                        />
                                                        <span
                                                            class="max-w-[180px] truncate sm:max-w-[280px]"
                                                            >{{
                                                                comment.task_title
                                                            }}</span
                                                        >
                                                    </Link>
                                                </div>
                                            </div>

                                            <Link
                                                :href="
                                                    taskRoutes.show.url(
                                                        comment.task_id,
                                                    )
                                                "
                                                class="inline-flex shrink-0 items-center gap-1 rounded-full border border-surface-200 bg-white px-2.5 py-1 text-[10px] font-semibold text-surface-600 transition hover:border-surface-300 hover:bg-surface-50 hover:text-surface-900 dark:border-surface-700 dark:bg-surface-900 dark:text-surface-300 dark:hover:bg-surface-800 dark:hover:text-white"
                                            >
                                                Buka tugas
                                                <ArrowUpRight class="h-3 w-3" />
                                            </Link>
                                        </div>

                                        <div
                                            class="mt-3 rounded-r-2xl border-l-2 border-primary-300 bg-surface-50/70 py-3 pr-3 pl-3.5 dark:border-primary-600 dark:bg-surface-800/40"
                                        >
                                            <p
                                                class="text-[13px] leading-relaxed whitespace-pre-line text-surface-700 dark:text-surface-200"
                                            >
                                                {{ comment.content }}
                                            </p>
                                        </div>

                                        <div
                                            class="mt-2.5 flex flex-wrap items-center gap-3 text-[10px] font-medium text-surface-400 dark:text-surface-500"
                                        >
                                            <button
                                                type="button"
                                                class="inline-flex items-center gap-1 transition hover:text-primary-700 dark:hover:text-primary-300"
                                                @click="copyTaskLink(comment)"
                                            >
                                                <Copy class="h-3 w-3" />
                                                Salin tautan
                                            </button>
                                            <span
                                                class="h-3 w-px bg-surface-200 dark:bg-surface-700"
                                            />
                                            <Link
                                                :href="
                                                    taskRoutes.show.url(
                                                        comment.task_id,
                                                    )
                                                "
                                                class="inline-flex items-center gap-1 transition hover:text-primary-700 dark:hover:text-primary-300"
                                            >
                                                <MessageSquare
                                                    class="h-3 w-3"
                                                />
                                                Buka thread komentar
                                            </Link>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        </div>

                        <div v-else class="px-5 py-12 text-center">
                            <span
                                class="mx-auto grid h-11 w-11 place-items-center rounded-2xl bg-surface-100 text-surface-400 dark:bg-surface-800 dark:text-surface-500"
                            >
                                <Inbox class="h-5 w-5" />
                            </span>
                            <p
                                class="mt-3 font-serif text-lg text-surface-900 dark:text-surface-100"
                            >
                                {{
                                    searchQuery
                                        ? 'Diskusi tidak ditemukan'
                                        : 'Belum ada diskusi'
                                }}
                            </p>
                            <p
                                class="mx-auto mt-1 max-w-sm text-xs leading-5 text-surface-500 dark:text-surface-400"
                            >
                                {{
                                    searchQuery
                                        ? 'Coba kata kunci lain atau hapus pencarian untuk melihat semua percakapan.'
                                        : 'Mulai percakapan pertama melalui kolom diskusi di atas.'
                                }}
                            </p>
                            <button
                                v-if="searchQuery"
                                type="button"
                                class="mt-3 rounded-full border border-surface-200 bg-white px-3 py-1.5 text-[11px] font-semibold text-surface-600 transition hover:bg-surface-50 dark:border-surface-700 dark:bg-surface-900 dark:text-surface-300 dark:hover:bg-surface-800"
                                @click="searchQuery = ''"
                            >
                                Hapus pencarian
                            </button>
                        </div>

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

                    <!-- Activity -->
                    <div v-else>
                        <div
                            v-if="recentActivities.length > 0"
                            class="relative px-5 py-5"
                        >
                            <div
                                class="absolute top-8 bottom-8 left-[2.6rem] w-px bg-surface-200 dark:bg-surface-700"
                                aria-hidden="true"
                            />

                            <div
                                v-for="activity in recentActivities"
                                :key="activity.id"
                                class="relative flex gap-3.5 pb-5 last:pb-0"
                            >
                                <div class="relative z-10 shrink-0">
                                    <Avatar
                                        v-if="activity.user"
                                        :initials="activity.user.initials"
                                        :src="activity.user.avatar"
                                        :alt="activity.user.name"
                                        size="sm"
                                    />
                                    <span
                                        v-else
                                        class="grid h-8 w-8 place-items-center rounded-full border border-surface-200 bg-white text-surface-500 dark:border-surface-700 dark:bg-surface-900 dark:text-surface-400"
                                    >
                                        <component
                                            :is="
                                                getIcon(
                                                    activityIcon(
                                                        activity.activity_type,
                                                    ),
                                                )
                                            "
                                            class="h-3.5 w-3.5"
                                        />
                                    </span>
                                    <span
                                        class="absolute -right-0.5 -bottom-0.5 grid h-4 w-4 place-items-center rounded-full border border-surface-200 bg-white text-surface-500 dark:border-surface-700 dark:bg-surface-900 dark:text-surface-400"
                                    >
                                        <component
                                            :is="
                                                getIcon(
                                                    activityIcon(
                                                        activity.activity_type,
                                                    ),
                                                )
                                            "
                                            class="h-2.5 w-2.5"
                                        />
                                    </span>
                                </div>

                                <div class="min-w-0 pt-0.5">
                                    <p
                                        class="text-xs leading-5 text-surface-700 dark:text-surface-200"
                                    >
                                        {{ activity.description }}
                                    </p>
                                    <div
                                        class="mt-1 flex flex-wrap items-center gap-2 text-[10px] text-surface-400 dark:text-surface-500"
                                    >
                                        <span>{{
                                            formatRelativeTime(
                                                activity.created_at,
                                            )
                                        }}</span>
                                        <template v-if="activity.task">
                                            <span>·</span>
                                            <Link
                                                :href="
                                                    taskRoutes.show.url(
                                                        activity.task.id,
                                                    )
                                                "
                                                class="inline-flex min-w-0 items-center gap-1 transition hover:text-primary-700 dark:hover:text-primary-300"
                                            >
                                                <Hash
                                                    class="h-3 w-3 shrink-0"
                                                />
                                                <span class="truncate">{{
                                                    activity.task.title
                                                }}</span>
                                            </Link>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-else class="px-5 py-12 text-center">
                            <span
                                class="mx-auto grid h-11 w-11 place-items-center rounded-2xl bg-surface-100 text-surface-400 dark:bg-surface-800 dark:text-surface-500"
                            >
                                <Activity class="h-5 w-5" />
                            </span>
                            <p
                                class="mt-3 font-serif text-lg text-surface-900 dark:text-surface-100"
                            >
                                Belum ada aktivitas
                            </p>
                            <p
                                class="mx-auto mt-1 max-w-sm text-xs leading-5 text-surface-500 dark:text-surface-400"
                            >
                                Aktivitas tugas dan komentar terbaru akan muncul
                                di sini.
                            </p>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Sidebar -->
            <aside class="space-y-5 lg:col-span-4">
                <!-- Review queue -->
                <section
                    class="rounded-[28px] border border-surface-200/80 bg-white p-5 shadow-xs dark:border-surface-800 dark:bg-surface-900"
                >
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span
                                class="grid h-7 w-7 place-items-center rounded-lg bg-rose-50 text-rose-600 dark:bg-rose-950/40 dark:text-rose-300"
                            >
                                <ClipboardCheck class="h-3.5 w-3.5" />
                            </span>
                            <h2
                                class="font-serif text-base text-surface-950 dark:text-surface-50"
                            >
                                Antrean review
                            </h2>
                        </div>
                        <span
                            class="rounded-full bg-rose-50 px-2 py-0.5 text-[10px] font-bold text-rose-600 dark:bg-rose-950/40 dark:text-rose-300"
                        >
                            {{ reviewTasks.length }}
                        </span>
                    </div>

                    <div v-if="reviewTasks.length > 0" class="mt-3 space-y-2">
                        <Link
                            v-for="task in reviewTasks"
                            :key="task.id"
                            :href="taskRoutes.show.url(task.id)"
                            class="group block rounded-2xl border border-surface-100 bg-surface-50/60 p-3 transition hover:border-surface-200 hover:bg-white hover:shadow-xs dark:border-surface-800 dark:bg-surface-800/30 dark:hover:border-surface-700 dark:hover:bg-surface-800/60"
                        >
                            <div class="flex items-start gap-2">
                                <span
                                    class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full"
                                    :class="priorityDot(task.priority)"
                                />
                                <p
                                    class="line-clamp-2 text-xs leading-5 font-semibold text-surface-800 group-hover:text-surface-950 dark:text-surface-100 dark:group-hover:text-white"
                                >
                                    {{ task.title }}
                                </p>
                            </div>

                            <div
                                class="mt-2 flex items-center justify-between gap-2 text-[10px] text-surface-400 dark:text-surface-500"
                            >
                                <span class="truncate">{{
                                    task.project_name
                                }}</span>
                                <div
                                    v-if="task.assignee"
                                    class="flex shrink-0 items-center gap-1.5"
                                >
                                    <Avatar
                                        :initials="task.assignee.initials"
                                        :src="task.assignee.avatar"
                                        :alt="task.assignee.name"
                                        size="xs"
                                    />
                                    <span class="max-w-20 truncate">{{
                                        task.assignee.name
                                    }}</span>
                                </div>
                                <span v-else>Belum ditugaskan</span>
                            </div>
                        </Link>
                    </div>

                    <p
                        v-else
                        class="mt-3 rounded-2xl border border-dashed border-surface-200 px-3 py-4 text-center text-[11px] text-surface-400 dark:border-surface-700 dark:text-surface-500"
                    >
                        Tidak ada tugas yang menunggu review.
                    </p>
                </section>

                <!-- Active projects -->
                <section
                    class="rounded-[28px] border border-surface-200/80 bg-white p-5 shadow-xs dark:border-surface-800 dark:bg-surface-900"
                >
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span
                                class="grid h-7 w-7 place-items-center rounded-lg bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300"
                            >
                                <FolderKanban class="h-3.5 w-3.5" />
                            </span>
                            <h2
                                class="font-serif text-base text-surface-950 dark:text-surface-50"
                            >
                                Proyek terhubung
                            </h2>
                        </div>
                        <Link
                            v-if="canViewProjects"
                            :href="projectRoutes.index.url()"
                            class="text-[10px] font-semibold text-primary-700 hover:underline dark:text-primary-300"
                        >
                            Lihat semua
                        </Link>
                    </div>

                    <div
                        v-if="activeProjects.length > 0"
                        class="mt-2 divide-y divide-surface-100 dark:divide-surface-800"
                    >
                        <component
                            :is="canViewProjects ? Link : 'div'"
                            v-for="project in activeProjects"
                            :key="project.id"
                            :href="
                                canViewProjects
                                    ? projectRoutes.show.url(project.id)
                                    : undefined
                            "
                            class="group flex items-center gap-3 py-3 transition"
                        >
                            <span
                                class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-surface-100 font-serif text-xs text-surface-700 transition group-hover:bg-primary-50 group-hover:text-primary-700 dark:bg-surface-800 dark:text-surface-200 dark:group-hover:bg-primary-900/30 dark:group-hover:text-primary-300"
                            >
                                {{ projectInitials(project.name) }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <p
                                    class="truncate text-xs font-semibold text-surface-800 transition group-hover:text-surface-950 dark:text-surface-100 dark:group-hover:text-white"
                                >
                                    {{ project.name }}
                                </p>
                                <p
                                    class="mt-0.5 text-[10px] text-surface-400 dark:text-surface-500"
                                >
                                    {{ project.tasks_count }} tugas ·
                                    {{ project.members_count }} anggota
                                </p>
                            </div>
                            <div
                                v-if="project.members.length > 0"
                                class="flex shrink-0 -space-x-2"
                            >
                                <Avatar
                                    v-for="member in project.members"
                                    :key="member.id"
                                    :initials="member.initials"
                                    :src="member.avatar"
                                    :alt="member.name"
                                    size="xs"
                                    class="ring-2 ring-white dark:ring-surface-900"
                                />
                            </div>
                        </component>
                    </div>

                    <p
                        v-else
                        class="mt-3 rounded-2xl border border-dashed border-surface-200 px-3 py-4 text-center text-[11px] text-surface-400 dark:border-surface-700 dark:text-surface-500"
                    >
                        Belum ada proyek yang bisa diakses.
                    </p>
                </section>

                <!-- Team -->
                <section
                    class="rounded-[28px] border border-surface-200/80 bg-white p-5 shadow-xs dark:border-surface-800 dark:bg-surface-900"
                >
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2">
                            <span
                                class="grid h-7 w-7 place-items-center rounded-lg bg-accent-50 text-accent-700 dark:bg-accent-900/30 dark:text-accent-300"
                            >
                                <Users class="h-3.5 w-3.5" />
                            </span>
                            <h2
                                class="font-serif text-base text-surface-950 dark:text-surface-50"
                            >
                                Rekan kerja
                            </h2>
                        </div>
                        <Link
                            :href="users.index.url()"
                            class="text-[10px] font-semibold text-primary-700 hover:underline dark:text-primary-300"
                        >
                            Direktori
                        </Link>
                    </div>

                    <div v-if="teamMembers.length > 0" class="mt-3 space-y-1">
                        <div
                            v-for="member in visibleTeamMembers"
                            :key="member.id"
                            class="flex items-center gap-2.5 rounded-xl px-1.5 py-1.5 transition hover:bg-surface-50 dark:hover:bg-surface-800/50"
                        >
                            <Avatar
                                :initials="member.initials"
                                :src="member.avatar"
                                :alt="member.name"
                                size="sm"
                            />
                            <div class="min-w-0 flex-1">
                                <p
                                    class="truncate text-xs font-medium text-surface-800 dark:text-surface-100"
                                >
                                    {{ member.name }}
                                </p>
                                <p
                                    class="text-[10px] text-surface-400 dark:text-surface-500"
                                >
                                    Anggota tim
                                </p>
                            </div>
                            <span
                                class="h-1.5 w-1.5 shrink-0 rounded-full bg-primary-400"
                                title="Terhubung ke proyek"
                            />
                        </div>
                    </div>

                    <p
                        v-else
                        class="mt-3 text-[11px] text-surface-400 dark:text-surface-500"
                    >
                        Belum ada anggota tim terdaftar.
                    </p>

                    <Link
                        v-if="remainingTeamMembers > 0"
                        :href="users.index.url()"
                        class="mt-2 inline-flex items-center gap-1 text-[10px] font-semibold text-surface-500 transition hover:text-primary-700 dark:text-surface-400 dark:hover:text-primary-300"
                    >
                        +{{ remainingTeamMembers }} rekan lainnya
                        <ArrowRight class="h-3 w-3" />
                    </Link>
                </section>

                <!-- Collaboration tip -->
                <section
                    class="relative overflow-hidden rounded-[28px] bg-primary-950 p-5 text-white shadow-sm dark:bg-primary-950"
                >
                    <div
                        class="pointer-events-none absolute -top-16 -right-12 h-40 w-40 rounded-full bg-white/10 blur-2xl"
                    />
                    <div
                        class="pointer-events-none absolute -bottom-16 -left-10 h-36 w-36 rounded-full bg-accent-400/20 blur-2xl"
                    />

                    <div class="relative">
                        <span
                            class="grid h-9 w-9 place-items-center rounded-xl bg-white/10 text-accent-200 ring-1 ring-white/15"
                        >
                            <Sparkles class="h-4 w-4" />
                        </span>
                        <p
                            class="mt-4 font-serif text-xl leading-snug text-white"
                        >
                            Ritme kolaborasi yang sehat.
                        </p>
                        <ul class="mt-3 space-y-2">
                            <li
                                class="flex items-start gap-2 text-[11px] leading-5 text-primary-100/90"
                            >
                                <Check
                                    class="mt-0.5 h-3 w-3 shrink-0 text-accent-300"
                                />
                                Tulis konteks, bukan hanya status.
                            </li>
                            <li
                                class="flex items-start gap-2 text-[11px] leading-5 text-primary-100/90"
                            >
                                <Check
                                    class="mt-0.5 h-3 w-3 shrink-0 text-accent-300"
                                />
                                Tutup diskusi dengan keputusan yang jelas.
                            </li>
                            <li
                                class="flex items-start gap-2 text-[11px] leading-5 text-primary-100/90"
                            >
                                <Check
                                    class="mt-0.5 h-3 w-3 shrink-0 text-accent-300"
                                />
                                Kembalikan percakapan ke tugas terkait.
                            </li>
                        </ul>
                        <Link
                            :href="taskRoutes.index.url()"
                            class="mt-4 inline-flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1.5 text-[10px] font-semibold text-white ring-1 ring-white/15 transition hover:bg-white/15"
                        >
                            Lihat tugas tim
                            <ExternalLink class="h-3 w-3" />
                        </Link>
                    </div>
                </section>
            </aside>
        </div>
    </div>
</template>
