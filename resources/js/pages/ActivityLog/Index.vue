<script setup lang="ts">
import { usePage, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import Table from '@/components/ui/Table.vue';
import Pagination from '@/components/ui/Pagination.vue';

defineOptions({
    layout: AppLayout,
});

interface ActivityLogItem {
    id: number;
    description: string;
    created_at: string;
    user_name: string;
    task_id: number;
    task_title: string;
}

interface ActivityPaginator {
    data: ActivityLogItem[];
    meta: {
        current_page: number;
        last_page: number;
        from: number | null;
        to: number | null;
        total: number;
    };
}

const page = usePage();
const rawActivities = (page.props.activities as any) ?? {};
const activities = computed<ActivityLogItem[]>(() => {
    if (Array.isArray(rawActivities.data)) return rawActivities.data;
    if (Array.isArray(rawActivities)) return rawActivities;
    return [];
});

const meta = computed(() => ({
    current_page: Number(
        rawActivities.meta?.current_page ?? rawActivities.current_page ?? 1,
    ),
    last_page: Number(
        rawActivities.meta?.last_page ?? rawActivities.last_page ?? 1,
    ),
    from: rawActivities.meta?.from ?? rawActivities.from ?? null,
    to: rawActivities.meta?.to ?? rawActivities.to ?? null,
    total: Number(
        rawActivities.meta?.total ??
            rawActivities.total ??
            activities.value.length,
    ),
}));

function changePage(pageNumber: number): void {
    const url = new URL(window.location.href);
    url.searchParams.set('page', String(pageNumber));
    router.get(url.toString(), {}, { replace: true, preserveScroll: true });
}

function formatDateTime(value: string): string {
    return new Date(value).toLocaleString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

const columns = [
    { key: 'when', label: 'Waktu', class: 'w-64' },
    { key: 'user', label: 'Pengguna', class: 'w-48' },
    { key: 'activity', label: 'Aktivitas', class: 'min-w-[220px]' },
    { key: 'task', label: 'Tugas', class: 'w-64' },
];
</script>

<template>
    <div class="space-y-6">
        <div>
            <h1
                class="text-2xl font-semibold tracking-tight text-surface-950 dark:text-surface-50"
            >
                Log Aktivitas
            </h1>
            <p
                class="mt-1 text-sm leading-6 text-surface-700 dark:text-surface-200"
            >
                Riwayat aktivitas tugas di sistem
            </p>
        </div>

        <Table
            :columns="columns"
            :data="activities"
            rowKey="id"
            emptyMessage="Belum ada aktivitas yang tercatat."
        >
            <template #when="{ row }">
                <span class="text-sm text-surface-700 dark:text-surface-200">
                    {{ formatDateTime(row.created_at) }}
                </span>
            </template>
            <template #user="{ row }">
                <span class="text-surface-700 dark:text-surface-200">
                    {{ row.user_name }}
                </span>
            </template>
            <template #activity="{ row }">
                <span class="text-surface-700 dark:text-surface-200">
                    {{ row.description }}
                </span>
            </template>
            <template #task="{ row }">
                <span
                    class="max-w-[150px] truncate text-sm text-surface-600 dark:text-surface-300"
                    :title="row.task_title"
                >
                    {{ row.task_title }}
                </span>
            </template>
        </Table>

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
</template>
