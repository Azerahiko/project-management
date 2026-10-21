<script setup lang="ts">
interface Props {
    currentPage: number;
    lastPage: number;
    from: number | null;
    to: number | null;
    total: number;
}

const props = defineProps<Props>();

const emit = defineEmits<{ change: [page: number] }>();

function pages(): (number | string)[] {
    const last = props.lastPage;
    const current = props.currentPage;

    if (last <= 7) {
        return Array.from({ length: last }, (_, i) => i + 1);
    }

    const items: (number | string)[] = [1];

    const start = Math.max(2, current - 1);
    const end = Math.min(last - 1, current + 1);

    if (start > 2) items.push('...');
    for (let i = start; i <= end; i++) items.push(i);
    if (end < last - 1) items.push('...');
    items.push(last);

    return items;
}
</script>

<template>
    <div
        v-if="total > 0"
        class="flex flex-col items-center justify-between gap-3 border-t border-surface-200 px-4 py-3 sm:flex-row dark:border-surface-700"
    >
        <p class="text-sm text-surface-600 dark:text-surface-400">
            Menampilkan
            <span class="font-medium"> {{ from ?? 0 }} </span>-
            <span class="font-medium"> {{ to ?? 0 }} </span>dari
            <span class="font-medium"> {{ total }} </span>data
        </p>

        <nav class="flex items-center gap-1" aria-label="Navigasi halaman">
            <button
                type="button"
                class="btn-secondary btn-sm"
                :disabled="currentPage <= 1"
                @click="emit('change', currentPage - 1)"
            >
                Sebelumnya
            </button>

            <template v-for="(page, index) in pages()" :key="`${page}-${index}`">
                <span v-if="page === '...'" class="px-2 text-sm text-surface-400">...</span>
                <button
                    v-else
                    type="button"
                    class="rounded-lg px-3 py-1.5 text-sm font-medium transition-colors"
                    :class="
                        page === currentPage
                            ? 'bg-primary-600 text-white'
                            : 'text-surface-700 hover:bg-surface-100 dark:text-surface-300 dark:hover:bg-surface-800'
                    "
                    :aria-current="page === currentPage ? 'page' : undefined"
                    @click="emit('change', page as number)"
                >
                    {{ page }}
                </button>
            </template>

            <button
                type="button"
                class="btn-secondary btn-sm"
                :disabled="currentPage >= lastPage"
                @click="emit('change', currentPage + 1)"
            >
                Berikutnya
            </button>
        </nav>
    </div>
</template>