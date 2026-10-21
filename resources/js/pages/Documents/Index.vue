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
                Dokumen
            </h1>
            <p class="mt-1 text-surface-600 dark:text-surface-400">
                Kelola dokumen proyek
            </p>
        </div>

        <EmptyState
            icon="FileText"
            title="Fitur belum diimplementasikan"
            description="Modul Dokumen sedang dalam pengerjaan. Untuk saat ini belum ada data yang dapat ditampilkan."
        />
    </div>
</template>
