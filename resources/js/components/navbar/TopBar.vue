<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { usePage, Link, router } from '@inertiajs/vue3';
import UserDropdown from './UserDropdown.vue';
import NotificationDropdown from './NotificationDropdown.vue';
import { getIcon } from '@/components/sidebar/icons';
import { useTheme } from '@/composables/useTheme';
import { useToast } from '@/composables/useToast';
import { Plus, Search, Moon, Sun, Menu } from '@lucide/vue';
import tasks from '@/routes/tasks';
import type { User } from '@/types';

interface Props {
    sidebarOpen: boolean;
}

interface Emits {
    'toggle-sidebar': [];
}

defineProps<Props>();
defineEmits<Emits>();

const page = usePage();
const user = computed(() => page.props.auth?.user as User | null);
const { nextTheme, toggleTheme } = useTheme();

const searchQuery = ref('');
const searchInputRef = ref<HTMLInputElement | null>(null);

const currentPath = computed(() => {
    const raw = page.url || '';
    return raw.split('?')[0].split('#')[0];
});

const pageTitle = computed(() => {
    const p = currentPath.value;
    if (p === '/dashboard' || p === '/') return 'Dashboard';
    if (p.startsWith('/tasks')) return 'Tugas Tim';
    if (p.startsWith('/projects')) return 'Proyek Tim';
    if (p.startsWith('/calendar')) return 'Jadwal & Kalender';
    if (p.startsWith('/timeline')) return 'Timeline & Roadmap';
    if (p.startsWith('/documents')) return 'Berkas Dokumen';
    if (p.startsWith('/reports')) return 'Laporan & KPI';
    if (p.startsWith('/activity-log')) return 'Log Aktivitas';
    if (p.startsWith('/notifications')) return 'Notifikasi';
    if (p.startsWith('/announcements')) return 'Pengumuman Tim';
    if (p.startsWith('/roles')) return 'Role & Permissions';
    if (p.startsWith('/teams') || p.startsWith('/users')) return 'Anggota Tim';
    if (p.startsWith('/collaboration')) return 'Papan Kolaborasi';
    if (p.startsWith('/settings')) return 'Pengaturan Akun';
    return 'Ruang Kerja';
});

const toast = useToast();

const canCreateTask = computed(() => {
    const roles = user.value?.roles ?? [];
    const perms = user.value?.permissions ?? [];
    return (
        roles.some((r: string) =>
            ['administrator', 'project_manager', 'team_leader'].includes(r),
        ) || perms.includes('tasks.create')
    );
});

function handleSearchSubmit(): void {
    if (!searchQuery.value.trim()) return;
    router.visit(
        tasks.index.url({ query: { search: searchQuery.value.trim() } }),
    );
}

function handleGlobalKeydown(e: KeyboardEvent): void {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        searchInputRef.value?.focus();
        searchInputRef.value?.select();
    }
}

onMounted(() => {
    window.addEventListener('keydown', handleGlobalKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleGlobalKeydown);
});
</script>

<template>
    <header
        class="sticky top-0 z-30 flex h-14 w-full items-center justify-between border-b border-surface-200/90 bg-white/90 px-4 backdrop-blur-md transition-colors sm:px-6 lg:px-8 dark:border-surface-800/80 dark:bg-surface-950/85"
    >
        <!-- Left: Mobile Toggle & Clean Breadcrumb -->
        <div class="flex min-w-0 items-center gap-3">
            <button
                type="button"
                class="rounded-lg p-1.5 text-surface-500 hover:bg-surface-100 hover:text-surface-800 active:scale-95 lg:hidden dark:text-surface-400 dark:hover:bg-surface-800 dark:hover:text-white"
                aria-label="Buka menu"
                @click="$emit('toggle-sidebar')"
            >
                <Menu class="h-4 w-4" />
            </button>

            <!-- Minimalist Linear-style Breadcrumb -->
            <nav class="flex min-w-0 items-center gap-2 text-xs">
                <Link
                    href="/dashboard"
                    class="hidden items-center gap-1.5 font-medium text-surface-500 transition-colors hover:text-surface-900 lg:flex dark:text-surface-400 dark:hover:text-surface-100"
                >
                    <span>{{
                        page.props.app.shortName || page.props.app.name
                    }}</span>
                </Link>
                <span
                    class="hidden text-surface-300 lg:inline dark:text-surface-700"
                    >/</span
                >
                <span
                    class="truncate font-semibold text-surface-900 dark:text-surface-100"
                >
                    {{ pageTitle }}
                </span>
            </nav>
        </div>

        <!-- Center: Command Search Bar -->
        <div class="flex hidden max-w-md flex-1 justify-center px-4 md:flex">
            <form @submit.prevent="handleSearchSubmit" class="relative w-full">
                <div
                    class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-surface-400"
                >
                    <Search class="h-3.5 w-3.5" />
                </div>
                <input
                    ref="searchInputRef"
                    v-model="searchQuery"
                    type="search"
                    placeholder="Cari tugas, proyek, aktivitas..."
                    class="h-8 w-full rounded-lg border border-surface-200/90 bg-surface-50/80 pr-9 pl-8 text-xs text-surface-900 placeholder-surface-400 shadow-2xs transition-all focus:border-primary-500 focus:bg-white focus:ring-1 focus:ring-primary-500 focus:outline-hidden dark:border-surface-800 dark:bg-surface-900/60 dark:text-surface-100 dark:placeholder-surface-400 dark:focus:border-primary-400"
                />
                <div
                    class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2"
                >
                    <kbd
                        class="py-0.2 rounded border border-surface-200 bg-surface-100 px-1 text-[9px] font-semibold text-surface-400 dark:border-surface-700 dark:bg-surface-800"
                    >
                        ⌘K
                    </kbd>
                </div>
            </form>
        </div>

        <!-- Right: Actions Toolbar -->
        <div class="flex shrink-0 items-center gap-2 sm:gap-2.5">
            <!-- + New Task Primary Action (Linear Style) -->
            <Link
                :href="tasks.index.url()"
                v-if="canCreateTask"
                class="inline-flex h-8 items-center gap-1.5 rounded-lg bg-primary-600 px-2.5 text-xs font-medium text-white shadow-xs transition-all hover:bg-primary-700 active:scale-98 dark:bg-primary-500 dark:text-surface-950 dark:hover:bg-primary-400"
            >
                <Plus class="h-3.5 w-3.5 stroke-[2.5]" />
                <span class="hidden sm:inline">Tugas Baru</span>
            </Link>

            <div class="mx-0.5 h-4 w-px bg-surface-200 dark:bg-surface-800" />

            <!-- Theme Toggle -->
            <button
                type="button"
                class="flex h-8 w-8 items-center justify-center rounded-lg text-surface-500 transition-colors hover:bg-surface-100 hover:text-surface-900 dark:text-surface-400 dark:hover:bg-surface-800 dark:hover:text-white"
                :title="nextTheme === 'dark' ? 'Mode Gelap' : 'Mode Terang'"
                @click="toggleTheme"
            >
                <Moon v-if="nextTheme === 'dark'" class="h-4 w-4" />
                <Sun v-else class="h-4 w-4" />
            </button>

            <!-- Notifications Dropdown -->
            <NotificationDropdown v-if="user" :user="user" />

            <!-- User Dropdown Menu -->
            <UserDropdown v-if="user" :user="user" />
        </div>
    </header>
</template>
