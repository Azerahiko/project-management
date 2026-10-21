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
                                    class="h-4 w-4 text-surface-400 dark:text-surface-500"
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
                    <td :colspan="columns.length" class="table-cell text-center text-surface-500 dark:text-surface-400 py-8">
                        {{ emptyMessage }}
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