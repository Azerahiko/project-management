<script setup lang="ts">
import { computed, ref } from 'vue';
import { usePage, Link, router } from '@inertiajs/vue3';
import UserDropdown from './UserDropdown.vue';
import NotificationDropdown from './NotificationDropdown.vue';
import { getIcon } from '@/components/sidebar/icons';
import { useTheme, type Theme } from '@/composables/useTheme';
import tasks from '@/routes/tasks';
import projects from '@/routes/projects';

interface Props {
    sidebarOpen: boolean;
}

interface Emits {
    'toggle-sidebar': [];
}

defineProps<Props>();
defineEmits<Emits>();

const page = usePage();
const user = computed(() => page.props.auth.user);
const { currentTheme, setTheme } = useTheme();

const currentPath = computed(() => {
    if (typeof window !== 'undefined') {
        return window.location.pathname;
    }
    return new URL(page.url).pathname;
});

const searchQuery = ref('');

function handleSearch(e: Event): void {
    e.preventDefault();
    if (!searchQuery.value.trim()) return;
    router.visit(tasks.index.url({ query: { search: searchQuery.value } }));
}

function handleSwitchTheme(theme: Theme): void {
    setTheme(theme);
}
</script>

<template>
    <header
        class="sticky top-0 z-40 flex h-20 items-center justify-between gap-4 border-b border-gray-100/80 bg-white/80 px-4 backdrop-blur-md sm:px-6 lg:px-8 dark:border-white/[0.06] dark:bg-[#141628]/80"
    >
        <!-- Left: Mobile Menu + Navigation Tabs -->
        <div class="flex items-center gap-3 sm:gap-6">
            <!-- Mobile Menu Toggle Button -->
            <button
                type="button"
                class="rounded-xl p-2 text-surface-500 transition-colors hover:bg-surface-100 lg:hidden dark:text-surface-400 dark:hover:bg-surface-800"
                aria-label="Buka menu"
                @click="$emit('toggle-sidebar')"
            >
                <component :is="getIcon('Menu')" class="h-6 w-6" />
            </button>

            <!-- Navigation Tabs (Inspired by Reference Design) -->
            <nav class="hidden items-center gap-1 sm:flex md:gap-2">
                <Link
                    :href="'/dashboard'"
                    class="group relative flex items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-semibold transition-all"
                    :class="
                        currentPath === '/dashboard'
                            ? 'text-primary-600 dark:text-primary-400'
                            : 'text-surface-500 hover:text-surface-900 dark:text-surface-400 dark:hover:text-surface-100'
                    "
                >
                    <component
                        :is="getIcon('LayoutDashboard')"
                        class="h-4 w-4"
                        :class="
                            currentPath === '/dashboard'
                                ? 'text-primary-600 dark:text-primary-400'
                                : 'text-surface-400'
                        "
                    />
                    <span>Dashboard</span>
                    <!-- Active Tab Underline -->
                    <span
                        v-if="currentPath === '/dashboard'"
                        class="absolute -bottom-[21px] left-3 right-3 h-0.5 rounded-full bg-primary-600 dark:bg-primary-400"
                    />
                </Link>

                <Link
                    :href="projects.index.url()"
                    class="group relative flex items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-semibold transition-all"
                    :class="
                        currentPath.startsWith('/projects')
                            ? 'text-primary-600 dark:text-primary-400'
                            : 'text-surface-500 hover:text-surface-900 dark:text-surface-400 dark:hover:text-surface-100'
                    "
                >
                    <component
                        :is="getIcon('FolderKanban')"
                        class="h-4 w-4"
                        :class="
                            currentPath.startsWith('/projects')
                                ? 'text-primary-600 dark:text-primary-400'
                                : 'text-surface-400'
                        "
                    />
                    <span>Projects</span>
                    <span
                        v-if="currentPath.startsWith('/projects')"
                        class="absolute -bottom-[21px] left-3 right-3 h-0.5 rounded-full bg-primary-600 dark:bg-primary-400"
                    />
                </Link>

                <Link
                    :href="tasks.index.url()"
                    class="group relative flex items-center gap-2 rounded-xl px-3.5 py-2 text-sm font-semibold transition-all"
                    :class="
                        currentPath.startsWith('/tasks')
                            ? 'text-primary-600 dark:text-primary-400'
                            : 'text-surface-500 hover:text-surface-900 dark:text-surface-400 dark:hover:text-surface-100'
                    "
                >
                    <component
                        :is="getIcon('CheckSquare')"
                        class="h-4 w-4"
                        :class="
                            currentPath.startsWith('/tasks')
                                ? 'text-primary-600 dark:text-primary-400'
                                : 'text-surface-400'
                        "
                    />
                    <span>Tasks</span>
                    <span
                        v-if="currentPath.startsWith('/tasks')"
                        class="absolute -bottom-[21px] left-3 right-3 h-0.5 rounded-full bg-primary-600 dark:bg-primary-400"
                    />
                </Link>
            </nav>
        </div>

        <!-- Center: Search Pill Bar -->
        <div class="hidden flex-1 max-w-xs md:max-w-md md:block">
            <form @submit="handleSearch" class="relative">
                <div
                    class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5"
                >
                    <component
                        :is="getIcon('Search')"
                        class="h-4 w-4 text-surface-400"
                    />
                </div>
                <input
                    v-model="searchQuery"
                    type="search"
                    placeholder="Search or type command..."
                    class="w-full rounded-2xl border border-gray-200/80 bg-surface-50/80 py-2 pr-10 pl-9 text-xs text-surface-900 transition-all placeholder:text-surface-400 focus:border-primary-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-500/20 dark:border-white/10 dark:bg-[#1C1F37] dark:text-surface-100 dark:focus:bg-[#202444]"
                />
                <div
                    class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3"
                >
                    <kbd
                        class="rounded-md border border-gray-200 bg-white px-1.5 py-0.5 text-[10px] font-medium text-surface-400 shadow-2xs dark:border-white/10 dark:bg-surface-800"
                    >
                        ⌘K
                    </kbd>
                </div>
            </form>
        </div>

        <!-- Right Side: Theme Switcher, Notifications, User & Primary CTA -->
        <div class="flex items-center gap-2.5 sm:gap-3.5">
            <!-- Sleek Light / Dark Pill Toggle -->
            <div
                class="flex items-center rounded-2xl bg-surface-100 p-1 shadow-inner-xs dark:bg-[#1C1F37]"
            >
                <button
                    type="button"
                    class="flex items-center gap-1.5 rounded-xl px-2.5 py-1 text-xs font-semibold transition-all"
                    :class="
                        currentTheme === 'light'
                            ? 'bg-white text-surface-900 shadow-xs'
                            : 'text-surface-500 hover:text-surface-900 dark:text-surface-400 dark:hover:text-surface-100'
                    "
                    @click="handleSwitchTheme('light')"
                >
                    <component :is="getIcon('Sun')" class="h-3.5 w-3.5" />
                    <span class="hidden sm:inline">Light</span>
                </button>
                <button
                    type="button"
                    class="flex items-center gap-1.5 rounded-xl px-2.5 py-1 text-xs font-semibold transition-all"
                    :class="
                        currentTheme === 'dark'
                            ? 'bg-[#292D5A] text-white shadow-xs'
                            : 'text-surface-500 hover:text-surface-900 dark:text-surface-400 dark:hover:text-surface-100'
                    "
                    @click="handleSwitchTheme('dark')"
                >
                    <component :is="getIcon('Moon')" class="h-3.5 w-3.5" />
                    <span class="hidden sm:inline">Dark</span>
                </button>
            </div>

            <!-- Notifications Dropdown -->
            <NotificationDropdown v-if="user" :user="user" />

            <!-- User Dropdown -->
            <UserDropdown v-if="user" :user="user" />

            <!-- Primary Action Pill Button -->
            <Link
                :href="tasks.create.url()"
                class="flex items-center gap-2 rounded-2xl bg-[#0D0E1A] px-4 py-2.5 text-xs font-bold text-white shadow-md transition-all hover:bg-slate-800 hover:shadow-lg active:scale-95 dark:bg-white dark:text-[#0D0E1A] dark:hover:bg-slate-100"
            >
                <component :is="getIcon('Plus')" class="h-3.5 w-3.5" />
                <span class="hidden sm:inline">Add new task</span>
            </Link>
        </div>
    </header>
</template>
