<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { getIcon } from '@/components/sidebar/icons';

defineOptions({
    layout: AppLayout,
});

interface ProjectMember {
    id: number;
    name: string;
    avatar: string | null;
    role: string;
}

interface TeamProject {
    id: number;
    name: string;
    members: ProjectMember[];
}

const page = usePage();
const projects = (page.props.projects as TeamProject[]) ?? [];

function initials(name: string): string {
    return name
        .split(/\s+/)
        .map((word) => word[0] ?? '')
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

const roleColors: Record<string, string> = {
    owner: 'bg-primary-100 text-primary-800 dark:bg-primary-900/30 dark:text-primary-200',
    manager:
        'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300',
    member: 'bg-surface-100 text-surface-700 dark:bg-surface-800 dark:text-surface-300',
    viewer: 'bg-surface-100 text-surface-500 dark:bg-surface-800 dark:text-surface-400',
};

const roleLabels: Record<string, string> = {
    owner: 'Pemilik',
    manager: 'Manajer',
    member: 'Anggota',
    viewer: 'Peninjau',
};
</script>

<template>
    <div class="space-y-6">
        <div>
            <h1
                class="text-2xl font-semibold tracking-tight text-surface-950 dark:text-surface-50"
            >
                Tim
            </h1>
            <p
                class="mt-1 text-sm leading-6 text-surface-700 dark:text-surface-200"
            >
                Kelola tim dan anggota tim
            </p>
        </div>

        <div
            v-if="projects.length === 0"
            class="card flex flex-col items-center justify-center px-6 py-16 text-center"
        >
            <div
                class="mb-4 flex h-12 w-12 items-center justify-center rounded-xl bg-surface-100 dark:bg-surface-800"
            >
                <component
                    :is="getIcon('Users')"
                    class="h-6 w-6 text-surface-600 dark:text-surface-300"
                />
            </div>
            <h2
                class="text-lg font-semibold text-surface-900 dark:text-surface-100"
            >
                Belum ada proyek
            </h2>
            <p class="mt-2 text-sm text-surface-500 dark:text-surface-400">
                Buat proyek terlebih dahulu di halaman Proyek untuk mengatur
                tim.
            </p>
        </div>

        <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="project in projects" :key="project.id" class="card p-5">
                <div class="mb-3 flex items-center justify-between">
                    <h3
                        class="text-lg font-semibold text-surface-900 dark:text-surface-100"
                    >
                        {{ project.name }}
                    </h3>
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full bg-primary-100 px-3 py-1 text-xs font-semibold text-primary-800 dark:bg-primary-900/30 dark:text-primary-200"
                    >
                        {{ project.members.length }} anggota
                    </span>
                </div>

                <div class="mb-3 flex flex-wrap gap-2">
                    <span
                        v-for="member in project.members"
                        :key="member.id"
                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium"
                        :class="roleColors[member.role]"
                    >
                        <span
                            class="flex h-5 w-5 items-center justify-center rounded-full bg-surface-200 text-[10px] font-bold text-surface-600 dark:bg-surface-700 dark:text-surface-300"
                            :aria-label="member.name"
                        >
                            {{ initials(member.name) }}
                        </span>
                        {{ roleLabels[member.role] ?? member.role }}
                    </span>
                </div>

                <div
                    v-if="project.members.length === 0"
                    class="text-xs text-surface-500 italic dark:text-surface-400"
                >
                    Belum ada anggota
                </div>
            </div>
        </div>
    </div>
</template>
