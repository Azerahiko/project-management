<script setup lang="ts">
import { computed } from 'vue';
import { getIcon } from '@/components/sidebar/icons';
import EmptyState from '@/components/ui/EmptyState.vue';
import { router } from '@inertiajs/vue3';
import projects from '@/routes/projects';
import type { ProjectProgress } from '@/types';

interface Props {
    project_progress: ProjectProgress[];
}

const props = defineProps<Props>();

function navigateToProject(projectId: number): void {
    router.visit(projects.show.url(projectId));
}

function getProgressColor(percent: number): string {
    if (percent >= 80) return 'bg-green-500';
    if (percent >= 50) return 'bg-blue-500';
    if (percent >= 25) return 'bg-yellow-500';
    return 'bg-red-500';
}

function getProgressBgColor(percent: number): string {
    if (percent >= 80) return 'bg-green-100 dark:bg-green-900/30';
    if (percent >= 50) return 'bg-blue-100 dark:bg-blue-900/30';
    if (percent >= 25) return 'bg-yellow-100 dark:bg-yellow-900/30';
    return 'bg-red-100 dark:bg-red-900/30';
}
</script>

<template>
    <section class="card" v-if="project_progress.length > 0">
        <div
            class="border-b border-white/20 px-6 py-4 dark:border-surface-700/30"
        >
            <h2
                class="text-lg font-semibold text-surface-900 dark:text-surface-100"
            >
                Progres Proyek
            </h2>
        </div>
        <div class="space-y-4 p-6">
            <div
                v-for="project in project_progress"
                :key="project.id"
                class="card-hover cursor-pointer"
                @click="navigateToProject(project.id)"
            >
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between">
                            <h3
                                class="truncate font-medium text-surface-900 dark:text-surface-100"
                            >
                                {{ project.name }}
                            </h3>
                            <span
                                class="ml-2 flex-shrink-0 text-sm font-semibold"
                                :class="
                                    getProgressBgColor(project.progress_percent)
                                "
                            >
                                {{ project.progress_percent }}%
                            </span>
                        </div>
                        <p
                            v-if="project.description"
                            class="mt-1 truncate text-sm text-surface-500 dark:text-surface-400"
                        >
                            {{ project.description }}
                        </p>
                        <div
                            class="mt-3 h-2 w-full overflow-hidden rounded-full bg-surface-100 dark:bg-surface-800"
                        >
                            <div
                                class="h-full transition-all duration-500 ease-out"
                                :class="
                                    getProgressColor(project.progress_percent)
                                "
                                :style="{
                                    width: `${project.progress_percent}%`,
                                }"
                            />
                        </div>
                        <div
                            class="mt-2 flex items-center justify-between text-xs text-surface-500 dark:text-surface-400"
                        >
                            <span
                                >{{ project.tasks_count || 0 }} tugas
                                total</span
                            >
                            <span
                                >{{
                                    project.completed_tasks_count || 0
                                }}
                                selesai</span
                            >
                        </div>
                    </div>
                    <component
                        :is="getIcon('FolderKanban')"
                        class="h-10 w-10 flex-shrink-0 text-surface-300 dark:text-surface-600"
                    />
                </div>
            </div>
        </div>
    </section>

    <section v-else class="card">
        <div
            class="border-b border-white/20 px-6 py-4 dark:border-surface-700/30"
        >
            <h2
                class="text-lg font-semibold text-surface-900 dark:text-surface-100"
            >
                Progres Proyek
            </h2>
        </div>
        <EmptyState
            icon="FolderKanban"
            title="Belum ada proyek aktif"
            description="Buat proyek baru untuk mulai melacak progres."
        />
    </section>
</template>
