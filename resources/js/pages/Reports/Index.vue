<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import EmptyState from '@/components/ui/EmptyState.vue';

defineOptions({
    layout: AppLayout,
});
</script>

<template>
    <div class="space-y-6">
        <div>
            <h1
                class="text-2xl font-bold text-surface-900 dark:text-surface-100"
            >
                Laporan
            </h1>
            <p class="mt-1 text-surface-600 dark:text-surface-400">
                Analitik dan laporan proyek
            </p>
        </div>

        <EmptyState
            icon="BarChart3"
            title="Fitur belum diimplementasikan"
            description="Modul Laporan sedang dalam pengerjaan. Untuk saat ini belum ada data yang dapat ditampilkan."
        />
    </div>
</template>
