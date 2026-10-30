<script setup lang="ts" generic="T extends Record<string, any>">
interface Column {
    key: string;
    label: string;
    sortable?: boolean;
    class?: string;
}

interface Props {
    columns: Column[];
    data: T[];
    emptyMessage?: string;
    loading?: boolean;
    sortBy?: string;
    sortDirection?: 'asc' | 'desc';
    onSort?: (key: string) => void;
    rowKey: keyof T | ((row: T) => string);
    rowClass?: (row: T) => string;
}

const props = withDefaults(defineProps<Props>(), {
    emptyMessage: 'Tidak ada data',
    loading: false,
    sortDirection: 'asc',
});

defineSlots<{
    [key: string]: (props: { row: T }) => any;
}>();

function rowId(row: T): string | number {
    return typeof props.rowKey === 'function'
        ? props.rowKey(row)
        : (row[props.rowKey] as string | number);
}
</script>

<template>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th
                        v-for="col in columns"
                        :key="col.key"
                        scope="col"
                        class="table-header-cell"
                        :class="col.class"
                        :style="{ cursor: col.sortable ? 'pointer' : 'default' }"
                        @click="col.sortable && onSort && onSort(col.key)"
                    >
                        <div class="flex items-center gap-1">
                            <span>{{ col.label }}</span>
                            <span
                                v-if="col.sortable && sortBy === col.key"
                                class="inline-flex"
                            >
                                <svg
                                    class="h-4 w-4 text-surface-600 dark:text-surface-300"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        :d="sortDirection === 'asc' ? 'M5 15l7-7 7 7' : 'M19 9l-7 7-7-7'"
                                    />
                                </svg>
                            </span>
                        </div>
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-if="loading" class="animate-pulse">
                    <td :colspan="columns.length" class="table-cell text-center">
                        <div class="h-4 w-24 bg-surface-200 rounded mx-auto dark:bg-surface-700" />
                    </td>
                </tr>
                <tr v-else-if="data.length === 0">
                    <td :colspan="columns.length" class="table-cell !py-12 text-center">
                        <div class="mx-auto flex h-11 w-11 items-center justify-center rounded-xl bg-surface-100 dark:bg-surface-800" aria-hidden="true">
                            <svg class="h-5 w-5 text-primary-700 dark:text-primary-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="18" height="18" rx="3" />
                                <path d="M3 9h18M8 14h8M8 17h5" />
                            </svg>
                        </div>
                        <p class="mt-3 text-sm font-medium text-surface-700 dark:text-surface-200">{{ emptyMessage }}</p>
                    </td>
                </tr>
                <tr
                    v-else
                    v-for="row in data"
                    :key="rowId(row)"
                    :class="[
                        'transition-colors duration-150',
                        rowClass ? rowClass(row) : '',
                    ]"
                >
                    <td
                        v-for="col in columns"
                        :key="col.key"
                        class="table-cell"
                        :class="col.class"
                    >
                        <slot :name="col.key" :row="row">
                            {{ row[col.key] }}
                        </slot>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
