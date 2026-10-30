<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Table from '@/components/ui/Table.vue';

defineOptions({
    layout: AppLayout,
});

interface ProjectNote {
    id: number;
    name: string;
    description: string | null;
}

const page = usePage();
const projectNotes = (page.props.project_notes as ProjectNote[]) ?? [];

const columns = [
    { key: 'name', label: 'Proyek', class: 'min-w-[200px]' },
    { key: 'description', label: 'Catatan', class: 'w-96' },
];
</script>

<template>
    <div class="space-y-6">
        <div>
            <h1
                class="text-2xl font-semibold tracking-tight text-surface-950 dark:text-surface-50"
            >
                Dokumen
            </h1>
            <p
                class="mt-1 text-sm leading-6 text-surface-700 dark:text-surface-200"
            >
                Catatan dan deskripsi proyek dalam satu tempat
            </p>
        </div>

        <Table
            :columns="columns"
            :data="projectNotes"
            rowKey="id"
            emptyMessage="Belum ada catatan proyek. Tambahkan deskripsi pada halaman detail Proyek."
        >
            <template #name="{ row }">
                <span
                    class="font-medium text-surface-700 dark:text-surface-200"
                >
                    {{ row.name }}
                </span>
            </template>
            <template #description="{ row }">
                <p
                    v-if="row.description"
                    class="line-clamp-2 text-surface-700 dark:text-surface-200"
                >
                    {{ row.description }}
                </p>
                <p
                    v-else
                    class="text-xs text-surface-400 italic dark:text-surface-500"
                >
                    Belum ada catatan
                </p>
            </template>
        </Table>
    </div>
</template>
