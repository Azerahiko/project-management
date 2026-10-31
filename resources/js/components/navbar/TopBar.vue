<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { usePage, Link, router } from '@inertiajs/vue3';
import UserDropdown from './UserDropdown.vue';
import NotificationDropdown from './NotificationDropdown.vue';
import { getIcon } from '@/components/sidebar/icons';
import { useTheme } from '@/composables/useTheme';
import tasks from '@/routes/tasks';

interface Props {
    sidebarOpen: boolean;
}

interface Emits {
    'toggle-sidebar': [];
}

defineProps<Props>();
defineEmits<Emits>();

const page = usePage();
const user = computed(() => page.props.auth?.user);
const { currentTheme, toggleTheme } = useTheme();

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
    if (p.startsWith('/timeline')) return 'Timeline Proyek';
    if (p.startsWith('/documents')) return 'Berkas Dokumen';
    if (p.startsWith('/reports')) return 'Laporan & KPI';
    if (p.startsWith('/activity-log')) return 'Log Aktivitas';
    if (p.startsWith('/notifications')) return 'Notifikasi';
    if (p.startsWith('/teams') || p.startsWith('/users')) return 'Anggota Tim';
    if (p.startsWith('/collaboration')) return 'Papan Kolaborasi';
    if (p.startsWith('/settings')) return 'Pengaturan Akun';
    return 'Ruang Kerja';
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
        class="sticky top-0 z-30 flex h-16 w-full items-center justify-between border-b border-surface-200/80 bg-white/85 px-4 backdrop-blur-md transition-colors sm:px-6 lg:px-8 dark:border-surface-800/20 dark:bg-surface-950/85"
    >
        <!-- Left: Mobile Toggle & Minimalist Breadcrumbs -->
        <div class="flex min-w-0 flex-1 items-center gap-3">
            <button
                type="button"
                class="rounded-xl p-2 text-surface-500 transition hover:bg-surface-100 hover:text-surface-800 active:scale-95 lg:hidden dark:text-surface-400 dark:hover:bg-white/10 dark:hover:text-white"
                aria-label="Buka menu"
                @click="$emit('toggle-sidebar')"
            >
                <component :is="getIcon('Menu')" class="h-5 w-5" />
            </button>

            <!-- Breadcrumbs (Clean Calm System typography, no pill badges) -->
            <nav class="flex min-w-0 items-center gap-2.5 text-xs">
                <Link
                    href="/dashboard"
                    class="hidden items-center gap-1.5 font-serif text-sm font-normal text-surface-800 transition hover:text-primary-700 sm:flex dark:text-surface-200 dark:hover:text-primary-300"
                >
                    <span
                        class="h-1.5 w-1.5 rounded-full bg-primary-700 dark:bg-primary-300"
                    />
                    Tasks Flow
                </Link>
                <span
                    class="hidden text-surface-300 sm:inline dark:text-surface-700"
                >
                    /
                </span>
                <span
                    class="truncate font-sans text-xs font-semibold tracking-tight text-surface-900 dark:text-white"
                >
                    {{ pageTitle }}
                </span>
            </nav>
        </div>

        <!-- Center / Right: Search & Actions -->
        <div class="flex shrink-0 items-center gap-2 sm:gap-3">
            <!-- Sleek Global Search Pill with Kbd shortcut badge (Calm System 04 INPUT FIELD) -->
            <form
                @submit.prevent="handleSearchSubmit"
                class="relative hidden sm:block"
            >
                <div
                    class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-surface-400"
                >
                    <component :is="getIcon('Search')" class="h-3.5 w-3.5" />
                </div>
                <input
                    ref="searchInputRef"
                    v-model="searchQuery"
                    type="search"
                    placeholder="Cari tugas, proyek..."
                    class="h-9 w-48 rounded-full border border-surface-300 bg-surface-50/90 pr-10 pl-8 text-xs text-surface-900 placeholder-surface-400 shadow-2xs transition-all duration-200 focus:w-64 focus:border-primary-600 focus:bg-white focus:ring-2 focus:ring-primary-600/15 focus:outline-hidden md:w-60 md:focus:w-72 dark:border-surface-700 dark:bg-surface-900 dark:text-surface-100 dark:placeholder-surface-400 dark:focus:border-primary-400"
                />
                <div
                    class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2.5"
                >
                    <kbd
                        class="rounded-md border border-surface-200 bg-surface-100 px-1.5 py-0.5 text-[9px] font-semibold text-surface-500 dark:border-surface-700 dark:bg-surface-800 dark:text-surface-400"
                    >
                        ⌘K
                    </kbd>
                </div>
            </form>

            <!-- Quick action: Tugas Baru (Calm System 03 BUTTONS) -->
            <Link
                :href="tasks.create.url()"
                class="flex items-center gap-1.5 rounded-full bg-[#0F5132] px-4 py-2 text-xs font-medium text-white shadow-xs transition hover:bg-[#0c4329] active:scale-95 dark:bg-[#0F5132] dark:hover:bg-[#14643f]"
                title="Buat Tugas Baru"
            >
                <component
                    :is="getIcon('Plus')"
                    class="h-3.5 w-3.5 stroke-[2.5]"
                />
                <span class="hidden sm:inline">Tugas Baru</span>
            </Link>

            <!-- Theme Toggle Icon Button -->
            <button
                type="button"
                class="flex h-9 w-9 items-center justify-center rounded-full border border-surface-200/80 bg-white text-surface-500 shadow-2xs transition hover:border-surface-300 hover:bg-surface-50 hover:text-surface-900 active:scale-95 dark:border-surface-800/20 dark:bg-surface-900 dark:text-surface-400 dark:hover:border-surface-700 dark:hover:bg-white/10 dark:hover:text-white"
                :title="
                    currentTheme === 'light'
                        ? 'Ubah ke Mode Gelap'
                        : 'Ubah ke Mode Terang'
                "
                @click="toggleTheme"
            >
                <component
                    :is="getIcon(currentTheme === 'light' ? 'Moon' : 'Sun')"
                    class="h-4 w-4"
                    :class="
                        currentTheme === 'light'
                            ? 'text-surface-600'
                            : 'text-amber-400'
                    "
                />
            </button>

            <!-- Notifications Dropdown -->
            <NotificationDropdown v-if="user" :user="user" />

            <!-- Divider -->
            <div
                class="hidden h-5 w-px bg-surface-200 sm:block dark:bg-surface-800/10"
            />

            <!-- User Profile Dropdown -->
            <UserDropdown v-if="user" :user="user" />
        </div>
    </header>
</template>
