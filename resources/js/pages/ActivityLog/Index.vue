<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Avatar from '@/components/ui/Avatar.vue';
import Pagination from '@/components/ui/Pagination.vue';
import { getIcon } from '@/components/sidebar/icons';
import tasksRoute from '@/routes/tasks';

defineOptions({
    layout: AppLayout,
});

interface ActivityLogItem {
    id: number;
    activity_type: string;
    description: string;
    created_at: string;
    user_name: string;
    user_avatar: string | null;
    user_initials: string | null;
    task_id: number;
    task_title: string;
}

interface ActivityStats {
    total: number;
    today: number;
    contributors: number;
    tasks: number;
    task_activities: number;
    comment_activities: number;
}

interface ActivityTypeMeta {
    label: string;
    icon: string;
    badge: string;
    marker: string;
}

interface Contributor {
    name: string;
    avatar: string | null;
    initials: string;
    count: number;
}

interface ActivityGroup {
    key: string;
    label: string;
    items: ActivityLogItem[];
}

type ActivityTypeFilter = 'all' | 'task' | 'comment';

const page = usePage();

const rawActivities = computed(() => (page.props.activities as any) ?? {});

const activities = computed<ActivityLogItem[]>(() => {
    if (Array.isArray(rawActivities.value.data)) {
        return rawActivities.value.data;
    }

    if (Array.isArray(rawActivities.value)) {
        return rawActivities.value;
    }

    return [];
});

const meta = computed(() => ({
    current_page: Number(
        rawActivities.value.meta?.current_page ??
            rawActivities.value.current_page ??
            1,
    ),
    last_page: Number(
        rawActivities.value.meta?.last_page ??
            rawActivities.value.last_page ??
            1,
    ),
    from: rawActivities.value.meta?.from ?? rawActivities.value.from ?? null,
    to: rawActivities.value.meta?.to ?? rawActivities.value.to ?? null,
    total: Number(
        rawActivities.value.meta?.total ??
            rawActivities.value.total ??
            activities.value.length,
    ),
}));

const defaultStats: ActivityStats = {
    total: 0,
    today: 0,
    contributors: 0,
    tasks: 0,
    task_activities: 0,
    comment_activities: 0,
};

const stats = computed<ActivityStats>(() => ({
    ...defaultStats,
    ...(page.props.stats as Partial<ActivityStats>),
}));

const serverFilters = computed(() => {
    const filters = page.props.filters as
        | { search?: string | null; type?: string | null }
        | undefined;

    return {
        search: filters?.search ?? '',
        type: filters?.type ?? null,
    };
});

const canViewTasks = computed(() => Boolean(page.props.can_view_tasks));

const search = ref(serverFilters.value.search);
const activeType = ref<ActivityTypeFilter>(
    serverFilters.value.type === 'task' ||
        serverFilters.value.type === 'comment'
        ? serverFilters.value.type
        : 'all',
);

const hasActiveFilters = computed(
    () => search.value.trim() !== '' || activeType.value !== 'all',
);

const typeFilters = computed<
    { value: ActivityTypeFilter; label: string; icon: string; count: number }[]
>(() => [
    {
        value: 'all',
        label: 'Semua',
        icon: 'Activity',
        count: stats.value.total,
    },
    {
        value: 'task',
        label: 'Perubahan tugas',
        icon: 'CheckSquare',
        count: stats.value.task_activities,
    },
    {
        value: 'comment',
        label: 'Komentar',
        icon: 'MessageSquare',
        count: stats.value.comment_activities,
    },
]);

const typeSummary = computed(() => {
    const total = stats.value.total;

    return [
        {
            key: 'task',
            label: 'Perubahan tugas',
            description: 'Status, prioritas, dan detail tugas',
            count: stats.value.task_activities,
            icon: 'CheckSquare',
            bar: 'bg-primary-600',
            iconClass:
                'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300',
        },
        {
            key: 'comment',
            label: 'Komentar & diskusi',
            description: 'Catatan dan feedback tim',
            count: stats.value.comment_activities,
            icon: 'MessageSquare',
            bar: 'bg-[#6f9bc0]',
            iconClass:
                'bg-[#e7f0f9] text-[#28506f] dark:bg-sky-950/40 dark:text-sky-200',
        },
    ].map((item) => ({
        ...item,
        percent: total > 0 ? Math.round((item.count / total) * 100) : 0,
    }));
});

const contributors = computed<Contributor[]>(() => {
    const map = new Map<string, Contributor>();

    for (const activity of activities.value) {
        const existing = map.get(activity.user_name);

        if (existing) {
            existing.count += 1;
            continue;
        }

        map.set(activity.user_name, {
            name: activity.user_name,
            avatar: activity.user_avatar,
            initials:
                activity.user_initials ?? initialsFromName(activity.user_name),
            count: 1,
        });
    }

    return [...map.values()]
        .sort((first, second) => second.count - first.count)
        .slice(0, 5);
});

const maxContributorCount = computed(() => contributors.value[0]?.count ?? 1);

const groupedActivities = computed<ActivityGroup[]>(() => {
    const groups: ActivityGroup[] = [];

    for (const activity of activities.value) {
        const key = localDateKey(activity.created_at);
        const existing = groups.find((group) => group.key === key);

        if (existing) {
            existing.items.push(activity);
            continue;
        }

        groups.push({
            key,
            label: dateGroupLabel(key),
            items: [activity],
        });
    }

    return groups;
});

let searchTimeout: ReturnType<typeof setTimeout> | null = null;

watch(search, () => {
    if (searchTimeout !== null) {
        clearTimeout(searchTimeout);
    }

    searchTimeout = setTimeout(() => {
        searchTimeout = null;
        applyFilters();
    }, 350);
});

onBeforeUnmount(() => {
    if (searchTimeout !== null) {
        clearTimeout(searchTimeout);
    }
});

function applyFilters(): void {
    if (searchTimeout !== null) {
        clearTimeout(searchTimeout);
        searchTimeout = null;
    }

    const params: Record<string, string> = {};
    const keyword = search.value.trim();

    if (keyword !== '') {
        params.search = keyword;
    }

    if (activeType.value !== 'all') {
        params.type = activeType.value;
    }

    router.get('/activity-log', params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function setType(type: ActivityTypeFilter): void {
    if (activeType.value === type) {
        return;
    }

    activeType.value = type;
    applyFilters();
}

function clearSearch(): void {
    search.value = '';
}

function resetFilters(): void {
    search.value = '';
    activeType.value = 'all';
    applyFilters();
}

function refreshActivities(): void {
    router.reload();
}

function changePage(pageNumber: number): void {
    const url = new URL(window.location.href);
    url.searchParams.set('page', String(pageNumber));
    router.get(
        url.toString(),
        {},
        {
            replace: true,
            preserveScroll: true,
            preserveState: true,
        },
    );
}

function typeMeta(type: string): ActivityTypeMeta {
    switch (type) {
        case 'task.created':
            return {
                label: 'Tugas dibuat',
                icon: 'Plus',
                badge: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200',
                marker: 'bg-emerald-600 text-white',
            };
        case 'task.updated':
            return {
                label: 'Tugas diperbarui',
                icon: 'Pencil',
                badge: 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-200',
                marker: 'bg-primary-600 text-white',
            };
        case 'task.deleted':
            return {
                label: 'Tugas dihapus',
                icon: 'Trash2',
                badge: 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-200',
                marker: 'bg-rose-600 text-white',
            };
        case 'comment.created':
            return {
                label: 'Komentar baru',
                icon: 'MessageSquare',
                badge: 'bg-[#e7f0f9] text-[#28506f] dark:bg-sky-950/40 dark:text-sky-200',
                marker: 'bg-[#3f6f95] text-white',
            };
        case 'comment.updated':
            return {
                label: 'Komentar diperbarui',
                icon: 'MessageSquare',
                badge: 'bg-[#f7eed6] text-[#7a5a14] dark:bg-amber-950/40 dark:text-amber-200',
                marker: 'bg-amber-500 text-white',
            };
        case 'comment.deleted':
            return {
                label: 'Komentar dihapus',
                icon: 'MessageSquare',
                badge: 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-200',
                marker: 'bg-rose-600 text-white',
            };
        default:
            return {
                label: 'Aktivitas',
                icon: 'Activity',
                badge: 'bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-300',
                marker: 'bg-surface-500 text-white',
            };
    }
}

function actionText(activity: ActivityLogItem): string {
    const prefix = `${activity.user_name} `;
    const text = activity.description.startsWith(prefix)
        ? activity.description.slice(prefix.length)
        : activity.description;

    return text.charAt(0).toUpperCase() + text.slice(1);
}

function initialsFromName(name: string): string {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join('');
}

function pad(value: number): string {
    return String(value).padStart(2, '0');
}

function localDateKey(value: string): string {
    const date = new Date(value);

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

function dateGroupLabel(key: string): string {
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const target = new Date(`${key}T00:00:00`);
    const difference = Math.round(
        (today.getTime() - target.getTime()) / 86_400_000,
    );

    if (difference === 0) {
        return 'Hari ini';
    }

    if (difference === 1) {
        return 'Kemarin';
    }

    return target.toLocaleDateString('id-ID', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

function formatTime(value: string): string {
    return new Date(value).toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
    });
}

function contributorPercent(count: number): number {
    return Math.round((count / Math.max(maxContributorCount.value, 1)) * 100);
}
</script>

<template>
    <div class="space-y-5">
        <!-- Header -->
        <div
            class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
        >
            <div>
                <span
                    class="inline-flex h-6 items-center gap-1.5 rounded-full border border-primary-200 bg-primary-50 px-2.5 text-[10px] font-bold tracking-wider text-primary-700 uppercase dark:border-primary-700/40 dark:bg-primary-900/25 dark:text-primary-200"
                >
                    <component :is="getIcon('Sparkles')" class="h-3 w-3" />
                    Jejak Aktivitas
                </span>
                <h1
                    class="mt-2 text-2xl font-semibold tracking-tight text-surface-950 sm:text-3xl dark:text-surface-50"
                >
                    Log Aktivitas
                </h1>
                <p
                    class="mt-1 max-w-2xl text-sm leading-6 text-surface-700 dark:text-surface-200"
                >
                    Pantau setiap perubahan tugas dan diskusi tim dalam satu
                    alur kronologis yang mudah ditelusuri.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span
                    class="inline-flex items-center gap-2 rounded-xl border border-surface-200 bg-white px-3 py-2 text-xs font-semibold text-surface-600 dark:border-surface-700 dark:bg-surface-900 dark:text-surface-300"
                >
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                    Log aktif
                </span>
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl border border-surface-200 bg-white px-3 py-2 text-xs font-semibold text-surface-700 transition hover:border-surface-300 hover:bg-surface-50 active:scale-95 dark:border-surface-700 dark:bg-surface-900 dark:text-surface-200 dark:hover:border-surface-600 dark:hover:bg-surface-800"
                    @click="refreshActivities"
                >
                    <component :is="getIcon('RefreshCw')" class="h-3.5 w-3.5" />
                    Segarkan
                </button>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="card flex items-start gap-4 !p-4">
                <span
                    class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300"
                >
                    <component :is="getIcon('Sparkles')" class="h-5 w-5" />
                </span>
                <div class="min-w-0">
                    <p
                        class="text-xs font-medium text-surface-500 dark:text-surface-400"
                    >
                        Total aktivitas
                    </p>
                    <p
                        class="mt-1 text-2xl font-bold text-surface-950 dark:text-surface-50"
                    >
                        {{ stats.total }}
                    </p>
                    <p class="mt-0.5 text-[11px] text-surface-400">
                        seluruh riwayat
                    </p>
                </div>
            </div>

            <div class="card flex items-start gap-4 !p-4">
                <span
                    class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-[#e7f0f9] text-[#28506f] dark:bg-sky-950/40 dark:text-sky-200"
                >
                    <component :is="getIcon('Clock')" class="h-5 w-5" />
                </span>
                <div class="min-w-0">
                    <p
                        class="text-xs font-medium text-surface-500 dark:text-surface-400"
                    >
                        Hari ini
                    </p>
                    <p
                        class="mt-1 text-2xl font-bold text-surface-950 dark:text-surface-50"
                    >
                        {{ stats.today }}
                    </p>
                    <p class="mt-0.5 text-[11px] text-surface-400">
                        aktivitas tercatat
                    </p>
                </div>
            </div>

            <div class="card flex items-start gap-4 !p-4">
                <span
                    class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-[#f7eed6] text-[#7a5a14] dark:bg-amber-950/40 dark:text-amber-200"
                >
                    <component :is="getIcon('Users')" class="h-5 w-5" />
                </span>
                <div class="min-w-0">
                    <p
                        class="text-xs font-medium text-surface-500 dark:text-surface-400"
                    >
                        Kontributor
                    </p>
                    <p
                        class="mt-1 text-2xl font-bold text-surface-950 dark:text-surface-50"
                    >
                        {{ stats.contributors }}
                    </p>
                    <p class="mt-0.5 text-[11px] text-surface-400">
                        anggota terlibat
                    </p>
                </div>
            </div>

            <div class="card flex items-start gap-4 !p-4">
                <span
                    class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-accent-100 text-accent-700 dark:bg-accent-900/30 dark:text-accent-300"
                >
                    <component :is="getIcon('CheckSquare')" class="h-5 w-5" />
                </span>
                <div class="min-w-0">
                    <p
                        class="text-xs font-medium text-surface-500 dark:text-surface-400"
                    >
                        Tugas terdampak
                    </p>
                    <p
                        class="mt-1 text-2xl font-bold text-surface-950 dark:text-surface-50"
                    >
                        {{ stats.tasks }}
                    </p>
                    <p class="mt-0.5 text-[11px] text-surface-400">
                        tugas dalam log
                    </p>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card !p-3 sm:!p-4">
            <div
                class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between"
            >
                <form
                    class="relative w-full xl:max-w-md"
                    role="search"
                    @submit.prevent="applyFilters"
                >
                    <span
                        class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-surface-400"
                    >
                        <component :is="getIcon('Search')" class="h-4 w-4" />
                    </span>
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Cari aktivitas, pengguna, atau tugas..."
                        class="h-10 w-full rounded-xl border border-surface-200 bg-surface-50/80 pr-10 pl-9 text-sm text-surface-900 placeholder-surface-400 transition focus:border-primary-600 focus:bg-white focus:ring-2 focus:ring-primary-600/15 focus:outline-hidden dark:border-surface-700 dark:bg-surface-800/60 dark:text-surface-100 dark:placeholder-surface-400 dark:focus:border-primary-400"
                        aria-label="Cari aktivitas"
                    />
                    <button
                        v-if="search.length > 0"
                        type="button"
                        class="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-surface-400 transition hover:text-surface-700 dark:hover:text-surface-200"
                        aria-label="Hapus pencarian"
                        @click="clearSearch"
                    >
                        <component :is="getIcon('X')" class="h-4 w-4" />
                    </button>
                </form>

                <div class="flex flex-wrap items-center gap-1.5">
                    <button
                        v-for="filter in typeFilters"
                        :key="filter.value"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-xl border px-3 py-2 text-xs font-semibold transition"
                        :class="
                            activeType === filter.value
                                ? 'border-primary-200 bg-primary-50 text-primary-800 shadow-2xs dark:border-primary-700/50 dark:bg-primary-900/30 dark:text-primary-200'
                                : 'border-surface-200 bg-white text-surface-600 hover:border-surface-300 hover:bg-surface-50 dark:border-surface-700 dark:bg-surface-900 dark:text-surface-300 dark:hover:bg-surface-800'
                        "
                        @click="setType(filter.value)"
                    >
                        <component
                            :is="getIcon(filter.icon)"
                            class="h-3.5 w-3.5"
                        />
                        {{ filter.label }}
                        <span
                            class="rounded-md px-1.5 py-0.5 text-[10px] font-bold"
                            :class="
                                activeType === filter.value
                                    ? 'bg-primary-600 text-white dark:bg-primary-500 dark:text-surface-950'
                                    : 'bg-surface-100 text-surface-500 dark:bg-surface-800 dark:text-surface-300'
                            "
                        >
                            {{ filter.count }}
                        </span>
                    </button>
                </div>
            </div>
        </div>

        <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_340px]">
            <!-- Timeline feed -->
            <div
                class="overflow-hidden rounded-[24px] border border-surface-200 bg-white shadow-sm dark:border-surface-700 dark:bg-surface-900"
            >
                <div
                    class="flex flex-col gap-2 border-b border-surface-200 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5 dark:border-surface-800"
                >
                    <div>
                        <h2
                            class="text-base font-semibold text-surface-950 dark:text-surface-50"
                        >
                            Riwayat aktivitas
                        </h2>
                        <p
                            class="mt-0.5 text-xs text-surface-500 dark:text-surface-400"
                        >
                            Aktivitas terbaru dari proyek yang dapat Anda akses.
                        </p>
                    </div>
                    <span
                        class="inline-flex w-fit items-center gap-1.5 rounded-full bg-surface-100 px-2.5 py-1 text-[10px] font-bold text-surface-500 dark:bg-surface-800 dark:text-surface-300"
                    >
                        {{ meta.total }} aktivitas
                    </span>
                </div>

                <div v-if="activities.length > 0" class="space-y-6 p-4 sm:p-5">
                    <section
                        v-for="group in groupedActivities"
                        :key="group.key"
                    >
                        <div class="flex items-center gap-3">
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full bg-surface-100 px-2.5 py-1 text-[10px] font-bold tracking-wider text-surface-600 uppercase dark:bg-surface-800 dark:text-surface-300"
                            >
                                <component
                                    :is="getIcon('Calendar')"
                                    class="h-3 w-3"
                                />
                                {{ group.label }}
                            </span>
                            <span
                                class="text-[11px] font-medium text-surface-400"
                            >
                                {{ group.items.length }} aktivitas
                            </span>
                            <span
                                class="h-px flex-1 bg-surface-100 dark:bg-surface-800"
                            />
                        </div>

                        <ol class="mt-3">
                            <li
                                v-for="(activity, index) in group.items"
                                :key="activity.id"
                                class="relative flex gap-3 sm:gap-4"
                            >
                                <div
                                    class="flex w-8 shrink-0 flex-col items-center"
                                >
                                    <div class="relative mt-1">
                                        <Avatar
                                            :src="activity.user_avatar"
                                            :initials="
                                                activity.user_initials ??
                                                initialsFromName(
                                                    activity.user_name,
                                                )
                                            "
                                            size="sm"
                                            :alt="activity.user_name"
                                        />
                                        <span
                                            class="absolute -right-1 -bottom-1 grid h-5 w-5 place-items-center rounded-full ring-2 ring-white dark:ring-surface-900"
                                            :class="
                                                typeMeta(activity.activity_type)
                                                    .marker
                                            "
                                        >
                                            <component
                                                :is="
                                                    getIcon(
                                                        typeMeta(
                                                            activity.activity_type,
                                                        ).icon,
                                                    )
                                                "
                                                class="h-2.5 w-2.5"
                                            />
                                        </span>
                                    </div>
                                    <span
                                        v-if="index < group.items.length - 1"
                                        class="mt-1 w-px flex-1 bg-surface-200 dark:bg-surface-700"
                                        aria-hidden="true"
                                    />
                                </div>

                                <div
                                    class="mb-3 min-w-0 flex-1 rounded-2xl border border-surface-100 bg-surface-50/60 p-3.5 transition-colors hover:border-surface-200 hover:bg-surface-50 sm:p-4 dark:border-surface-800 dark:bg-surface-800/20 dark:hover:border-surface-700 dark:hover:bg-surface-800/40"
                                >
                                    <div
                                        class="flex flex-wrap items-center gap-x-2 gap-y-1"
                                    >
                                        <span
                                            class="text-sm font-semibold text-surface-900 dark:text-surface-100"
                                        >
                                            {{ activity.user_name }}
                                        </span>
                                        <span
                                            class="rounded-full px-2 py-0.5 text-[9.5px] font-bold tracking-wide uppercase"
                                            :class="
                                                typeMeta(activity.activity_type)
                                                    .badge
                                            "
                                        >
                                            {{
                                                typeMeta(activity.activity_type)
                                                    .label
                                            }}
                                        </span>
                                        <span
                                            class="ml-auto inline-flex items-center gap-1 text-[11px] text-surface-400"
                                        >
                                            <component
                                                :is="getIcon('Clock')"
                                                class="h-3 w-3"
                                            />
                                            {{
                                                formatTime(activity.created_at)
                                            }}
                                        </span>
                                    </div>

                                    <p
                                        class="mt-1.5 text-sm leading-6 text-surface-700 dark:text-surface-200"
                                    >
                                        {{ actionText(activity) }}
                                    </p>

                                    <Link
                                        v-if="canViewTasks"
                                        :href="
                                            tasksRoute.show.url({
                                                task: activity.task_id,
                                            })
                                        "
                                        class="mt-2.5 inline-flex max-w-full items-center gap-2 rounded-xl border border-surface-200 bg-white px-2.5 py-1.5 text-xs font-medium text-surface-700 transition hover:border-primary-300 hover:text-primary-700 dark:border-surface-700 dark:bg-surface-900 dark:text-surface-200 dark:hover:border-primary-700 dark:hover:text-primary-300"
                                    >
                                        <component
                                            :is="getIcon('CheckSquare')"
                                            class="h-3.5 w-3.5 shrink-0 text-primary-600 dark:text-primary-300"
                                        />
                                        <span class="truncate">{{
                                            activity.task_title
                                        }}</span>
                                        <component
                                            :is="getIcon('ArrowUpRight')"
                                            class="h-3.5 w-3.5 shrink-0 opacity-60"
                                        />
                                    </Link>
                                    <span
                                        v-else
                                        class="mt-2.5 inline-flex max-w-full items-center gap-2 rounded-xl border border-surface-200 bg-white px-2.5 py-1.5 text-xs font-medium text-surface-700 dark:border-surface-700 dark:bg-surface-900 dark:text-surface-200"
                                    >
                                        <component
                                            :is="getIcon('CheckSquare')"
                                            class="h-3.5 w-3.5 shrink-0 text-primary-600 dark:text-primary-300"
                                        />
                                        <span class="truncate">{{
                                            activity.task_title
                                        }}</span>
                                    </span>
                                </div>
                            </li>
                        </ol>
                    </section>
                </div>

                <div
                    v-else
                    class="flex flex-col items-center justify-center px-6 py-16 text-center"
                >
                    <span
                        class="grid h-14 w-14 place-items-center rounded-2xl bg-surface-100 text-surface-400 dark:bg-surface-800 dark:text-surface-500"
                    >
                        <component
                            :is="
                                getIcon(
                                    hasActiveFilters ? 'Search' : 'Sparkles',
                                )
                            "
                            class="h-6 w-6"
                        />
                    </span>
                    <h3
                        class="mt-4 text-base font-semibold text-surface-900 dark:text-surface-100"
                    >
                        {{
                            hasActiveFilters
                                ? 'Tidak ada aktivitas yang cocok'
                                : 'Belum ada aktivitas'
                        }}
                    </h3>
                    <p
                        class="mt-1 max-w-sm text-sm leading-6 text-surface-500 dark:text-surface-400"
                    >
                        {{
                            hasActiveFilters
                                ? 'Coba kata kunci lain atau hapus filter untuk melihat seluruh riwayat aktivitas.'
                                : 'Perubahan tugas dan komentar tim akan muncul di sini setelah aktivitas pertama tercatat.'
                        }}
                    </p>
                    <button
                        v-if="hasActiveFilters"
                        type="button"
                        class="mt-4 inline-flex items-center gap-2 rounded-xl bg-primary-700 px-4 py-2 text-xs font-semibold text-white transition hover:bg-primary-800 active:scale-95 dark:bg-primary-600 dark:hover:bg-primary-500"
                        @click="resetFilters"
                    >
                        <component
                            :is="getIcon('RefreshCw')"
                            class="h-3.5 w-3.5"
                        />
                        Hapus filter
                    </button>
                    <Link
                        v-else-if="canViewTasks"
                        :href="tasksRoute.index.url()"
                        class="mt-4 inline-flex items-center gap-2 rounded-xl bg-primary-700 px-4 py-2 text-xs font-semibold text-white transition hover:bg-primary-800 active:scale-95 dark:bg-primary-600 dark:hover:bg-primary-500"
                    >
                        <component
                            :is="getIcon('CheckSquare')"
                            class="h-3.5 w-3.5"
                        />
                        Lihat tugas
                    </Link>
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
            </div>

            <!-- Side summaries -->
            <div class="space-y-5 xl:sticky xl:top-24 xl:self-start">
                <div class="card">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2
                                class="text-base font-semibold text-surface-950 dark:text-surface-50"
                            >
                                Komposisi aktivitas
                            </h2>
                            <p
                                class="mt-0.5 text-xs text-surface-500 dark:text-surface-400"
                            >
                                Perbandingan jenis aktivitas yang tercatat.
                            </p>
                        </div>
                        <span
                            class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-surface-100 text-surface-500 dark:bg-surface-800 dark:text-surface-300"
                        >
                            <component
                                :is="getIcon('BarChart3')"
                                class="h-4 w-4"
                            />
                        </span>
                    </div>

                    <div class="mt-5 space-y-4">
                        <div v-for="item in typeSummary" :key="item.key">
                            <div class="flex items-center gap-2.5">
                                <span
                                    class="grid h-8 w-8 shrink-0 place-items-center rounded-lg"
                                    :class="item.iconClass"
                                >
                                    <component
                                        :is="getIcon(item.icon)"
                                        class="h-4 w-4"
                                    />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="truncate text-xs font-semibold text-surface-800 dark:text-surface-100"
                                    >
                                        {{ item.label }}
                                    </p>
                                    <p
                                        class="truncate text-[10px] text-surface-400"
                                    >
                                        {{ item.description }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p
                                        class="text-sm font-bold text-surface-900 dark:text-surface-100"
                                    >
                                        {{ item.count }}
                                    </p>
                                    <p class="text-[10px] text-surface-400">
                                        {{ item.percent }}%
                                    </p>
                                </div>
                            </div>
                            <div
                                class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-surface-100 dark:bg-surface-800"
                            >
                                <div
                                    class="h-full rounded-full transition-all"
                                    :class="item.bar"
                                    :style="{ width: `${item.percent}%` }"
                                />
                            </div>
                        </div>
                    </div>

                    <p
                        class="mt-4 border-t border-surface-100 pt-3 text-[11px] leading-5 text-surface-400 dark:border-surface-800 dark:text-surface-500"
                    >
                        Dihitung dari aktivitas yang sesuai dengan pencarian
                        saat ini.
                    </p>
                </div>

                <div class="card">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2
                                class="text-base font-semibold text-surface-950 dark:text-surface-50"
                            >
                                Kontributor
                            </h2>
                            <p
                                class="mt-0.5 text-xs text-surface-500 dark:text-surface-400"
                            >
                                Anggota paling aktif di halaman ini.
                            </p>
                        </div>
                        <span
                            class="rounded-full bg-surface-100 px-2 py-0.5 text-[10px] font-bold text-surface-500 dark:bg-surface-800 dark:text-surface-300"
                        >
                            {{ contributors.length }} orang
                        </span>
                    </div>

                    <div
                        v-if="contributors.length > 0"
                        class="mt-4 space-y-3.5"
                    >
                        <div
                            v-for="contributor in contributors"
                            :key="contributor.name"
                            class="flex items-center gap-3"
                        >
                            <Avatar
                                :src="contributor.avatar"
                                :initials="contributor.initials"
                                size="sm"
                                :alt="contributor.name"
                            />
                            <div class="min-w-0 flex-1">
                                <div
                                    class="flex items-center justify-between gap-2"
                                >
                                    <p
                                        class="truncate text-xs font-semibold text-surface-800 dark:text-surface-100"
                                    >
                                        {{ contributor.name }}
                                    </p>
                                    <span
                                        class="shrink-0 text-[10px] font-bold text-surface-500 dark:text-surface-400"
                                    >
                                        {{ contributor.count }} aktivitas
                                    </span>
                                </div>
                                <div
                                    class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-surface-100 dark:bg-surface-800"
                                >
                                    <div
                                        class="h-full rounded-full bg-primary-500 transition-all dark:bg-primary-400"
                                        :style="{
                                            width: `${contributorPercent(contributor.count)}%`,
                                        }"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <p
                        v-else
                        class="mt-4 rounded-xl bg-surface-50 px-3 py-4 text-center text-xs text-surface-500 dark:bg-surface-800/40 dark:text-surface-400"
                    >
                        Belum ada kontributor pada halaman ini.
                    </p>
                </div>

                <div
                    class="rounded-[24px] border border-primary-200/70 bg-primary-50/70 p-4 dark:border-primary-700/40 dark:bg-primary-900/20"
                >
                    <div
                        class="flex items-center gap-2 text-primary-800 dark:text-primary-200"
                    >
                        <component :is="getIcon('Shield')" class="h-4 w-4" />
                        <p class="text-xs font-bold">Akses terbatas</p>
                    </div>
                    <p
                        class="mt-1.5 text-[11px] leading-5 text-primary-700/80 dark:text-primary-200/70"
                    >
                        Log hanya menampilkan aktivitas dari proyek yang dapat
                        Anda akses. Aktivitas baru muncul otomatis setelah
                        perubahan tersimpan.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
