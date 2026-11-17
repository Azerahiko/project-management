<script setup lang="ts">
import { computed } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Avatar from '@/components/ui/Avatar.vue';
import { Users, FolderKanban, ArrowUpRight, ShieldCheck, UserCheck } from '@lucide/vue';
import projectsRoute from '@/routes/projects';

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
    owner: 'bg-primary-100 text-primary-800 dark:bg-primary-950/40 dark:text-primary-300',
    manager: 'bg-blue-100 text-blue-800 dark:bg-blue-950/40 dark:text-blue-300',
    member: 'bg-surface-100 text-surface-700 dark:bg-surface-800 dark:text-surface-300',
    viewer: 'bg-surface-100 text-surface-500 dark:bg-surface-800 dark:text-surface-400',
};

const roleLabels: Record<string, string> = {
    owner: 'Pemilik',
    manager: 'Manajer',
    member: 'Anggota',
    viewer: 'Peninjau',
};

const totalMembersAllocated = computed(() => {
    const uniqueIds = new Set<number>();
    projects.forEach((p) => p.members?.forEach((m) => uniqueIds.add(m.id)));
    return uniqueIds.size;
});
</script>

<template>
    <div class="mx-auto max-w-7xl space-y-6">
        <!-- Header -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="space-y-1">
                <div class="flex items-center gap-2">
                    <span class="inline-flex h-2 w-2 rounded-full bg-emerald-500" />
                    <span class="text-xs font-semibold uppercase tracking-wider text-surface-500 dark:text-surface-400">
                        Ruang Kolaborasi Tim
                    </span>
                </div>
                <h1 class="text-xl sm:text-2xl font-bold tracking-tight text-surface-900 dark:text-surface-50">
                    Tim & Alokasi Proyek
                </h1>
                <p class="text-xs text-surface-500 dark:text-surface-400">
                    Distribusi anggota tim, struktur kepemilikan, dan peran kolaborator di setiap proyek.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <Link
                    :href="projectsRoute.index.url()"
                    class="inline-flex h-9 items-center gap-1.5 rounded-lg border border-surface-200 bg-white px-3 text-xs font-medium text-surface-700 shadow-2xs hover:bg-surface-50 dark:border-surface-800 dark:bg-surface-900 dark:text-surface-300 dark:hover:bg-surface-800 transition"
                >
                    <FolderKanban class="h-3.5 w-3.5 text-surface-400" />
                    <span>Daftar Proyek</span>
                </Link>
            </div>
        </div>

        <!-- Telemetry Strip -->
        <div class="grid grid-cols-1 sm:grid-cols-3 rounded-xl border border-surface-200/90 bg-white divide-y divide-surface-200/80 sm:divide-y-0 sm:divide-x dark:border-surface-800 dark:bg-surface-900/90 dark:divide-surface-800/80 shadow-card overflow-hidden">
            <div class="p-4">
                <div class="flex items-center justify-between text-surface-500 dark:text-surface-400">
                    <span class="font-mono text-[11px] font-semibold tracking-wider uppercase">Total Proyek Tim</span>
                    <FolderKanban class="h-3.5 w-3.5 text-primary-600 dark:text-primary-400" />
                </div>
                <div class="mt-2 font-mono text-2xl font-bold text-surface-900 dark:text-surface-50">
                    {{ projects.length }}
                </div>
                <p class="mt-1 text-[11px] text-surface-400">Proyek terdaftar dalam workspace</p>
            </div>

            <div class="p-4">
                <div class="flex items-center justify-between text-surface-500 dark:text-surface-400">
                    <span class="font-mono text-[11px] font-semibold tracking-wider uppercase">Kolaborator Unik</span>
                    <Users class="h-3.5 w-3.5 text-emerald-600 dark:text-emerald-400" />
                </div>
                <div class="mt-2 font-mono text-2xl font-bold text-emerald-600 dark:text-emerald-400">
                    {{ totalMembersAllocated }}
                </div>
                <p class="mt-1 text-[11px] text-surface-400">Anggota aktif teralokasi</p>
            </div>

            <div class="p-4">
                <div class="flex items-center justify-between text-surface-500 dark:text-surface-400">
                    <span class="font-mono text-[11px] font-semibold tracking-wider uppercase">Rata-rata Tim</span>
                    <UserCheck class="h-3.5 w-3.5 text-blue-500" />
                </div>
                <div class="mt-2 font-mono text-2xl font-bold text-surface-900 dark:text-surface-50">
                    {{ projects.length ? (projects.reduce((acc, p) => acc + p.members.length, 0) / projects.length).toFixed(1) : 0 }}
                </div>
                <p class="mt-1 text-[11px] text-surface-400">Anggota per proyek</p>
            </div>
        </div>

        <!-- Empty State -->
        <div
            v-if="projects.length === 0"
            class="rounded-2xl border border-dashed border-surface-300 bg-white px-6 py-16 text-center dark:border-surface-700 dark:bg-surface-900/40"
        >
            <Users class="mx-auto h-10 w-10 text-surface-400" />
            <h2 class="mt-3 text-base font-bold text-surface-900 dark:text-surface-100">
                Belum ada proyek aktif
            </h2>
            <p class="mt-1 text-xs text-surface-500 max-w-sm mx-auto">
                Buat proyek terlebih dahulu di halaman Proyek untuk mulai menyusun tim kerja.
            </p>
        </div>

        <!-- Project Teams Grid -->
        <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div
                v-for="project in projects"
                :key="project.id"
                class="group flex flex-col justify-between rounded-xl border border-surface-200/90 bg-white p-5 shadow-card hover:border-surface-300 hover:shadow-panel hover:-translate-y-0.5 dark:border-surface-800 dark:bg-surface-900/90 dark:hover:border-surface-700 transition-all duration-180"
            >
                <div>
                    <!-- Card Top -->
                    <div class="flex items-start justify-between gap-2 mb-3">
                        <Link
                            :href="projectsRoute.show.url({ project: project.id })"
                            class="text-sm font-bold text-surface-900 hover:text-primary-600 dark:text-surface-100 dark:hover:text-primary-400 line-clamp-1 transition-colors"
                        >
                            {{ project.name }}
                        </Link>
                        <span
                            class="inline-flex shrink-0 items-center rounded bg-surface-100 px-2 py-0.5 text-[10px] font-semibold text-surface-600 dark:bg-surface-800 dark:text-surface-300"
                        >
                            {{ project.members.length }} anggota
                        </span>
                    </div>

                    <!-- Members List -->
                    <div v-if="project.members.length > 0" class="divide-y divide-surface-100 dark:divide-surface-800/60">
                        <div
                            v-for="member in project.members"
                            :key="member.id"
                            class="flex items-center justify-between py-2 first:pt-0 last:pb-0"
                        >
                            <div class="flex items-center gap-2.5 min-w-0">
                                <Avatar
                                    :initials="initials(member.name)"
                                    :src="member.avatar"
                                    :alt="member.name"
                                    size="sm"
                                />
                                <span class="truncate text-xs font-medium text-surface-800 dark:text-surface-200">
                                    {{ member.name }}
                                </span>
                            </div>

                            <span
                                class="rounded px-1.5 py-0.2 text-[9.5px] font-semibold shrink-0"
                                :class="roleColors[member.role] || roleColors.member"
                            >
                                {{ roleLabels[member.role] ?? member.role }}
                            </span>
                        </div>
                    </div>

                    <div
                        v-else
                        class="py-4 text-center text-xs text-surface-400 italic"
                    >
                        Belum ada anggota yang ditugaskan.
                    </div>
                </div>

                <!-- Card Footer Link -->
                <div class="mt-4 pt-3 border-t border-surface-100 dark:border-surface-800/60 flex justify-end">
                    <Link
                        :href="projectsRoute.show.url({ project: project.id })"
                        class="inline-flex items-center gap-1 text-[11px] font-medium text-primary-600 hover:text-primary-700 dark:text-primary-400 transition"
                    >
                        <span>Kelola Anggota</span>
                        <ArrowUpRight class="h-3 w-3" />
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
