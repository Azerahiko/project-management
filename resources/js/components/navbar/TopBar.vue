<script setup lang="ts">
import { computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import UserDropdown from './UserDropdown.vue';
import NotificationDropdown from './NotificationDropdown.vue';
import { getIcon } from '@/components/sidebar/icons';
import { useTheme, type Theme } from '@/composables/useTheme';

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
const { currentTheme, setTheme } = useTheme();

const currentDateString = computed(() => {
    const now = new Date();
    return now.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
});

function handleRefresh(): void {
    router.reload();
}

function handleSwitchTheme(theme: Theme): void {
    setTheme(theme);
}
</script>

<template>
    <header
        class="flex items-center justify-between gap-4 px-4 py-4 sm:px-6 sm:py-5 lg:px-8"
    >
        <!-- Left: Mobile Menu Toggle & Title -->
        <div class="flex items-center gap-3">
            <button
                type="button"
                class="rounded-xl p-2 text-slate-600 hover:bg-slate-200/60 lg:hidden dark:text-slate-300 dark:hover:bg-white/10"
                aria-label="Buka menu"
                @click="$emit('toggle-sidebar')"
            >
                <component :is="getIcon('Menu')" class="h-6 w-6" />
            </button>
        </div>

        <!-- Right: Action Bar (Exact Match to Reference Mockup) -->
        <div class="flex items-center gap-2.5 sm:gap-3">
            <!-- Calendar Date Pill (e.g. "January 2025") -->
            <div
                class="hidden items-center gap-2 rounded-2xl border border-slate-200/80 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-2xs sm:flex dark:border-white/10 dark:bg-[#151829] dark:text-slate-200"
            >
                <component
                    :is="getIcon('Calendar')"
                    class="h-3.5 w-3.5 text-slate-500 dark:text-slate-400"
                />
                <span>{{ currentDateString }}</span>
            </div>

            <!-- Export Pill Button -->
            <button
                type="button"
                class="hidden items-center gap-2 rounded-2xl border border-slate-200/80 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-2xs transition hover:bg-slate-50 sm:flex dark:border-white/10 dark:bg-[#151829] dark:text-slate-200 dark:hover:bg-white/5"
            >
                <component
                    :is="getIcon('FileText')"
                    class="h-3.5 w-3.5 text-slate-500 dark:text-slate-400"
                />
                <span>Export</span>
            </button>

            <!-- Circular Neon Lime Refresh Button -->
            <button
                type="button"
                class="flex h-9 w-9 items-center justify-center rounded-full bg-[#D2F87A] text-slate-900 shadow-2xs transition-transform hover:scale-105 active:scale-95"
                title="Refresh data"
                @click="handleRefresh"
            >
                <component
                    :is="getIcon('Activity')"
                    class="h-4 w-4 stroke-[2.3]"
                />
            </button>

            <!-- Theme Toggle Pill (Light / Dark) -->
            <div
                class="flex items-center rounded-2xl border border-slate-200/80 bg-white p-0.5 shadow-2xs dark:border-white/10 dark:bg-[#151829]"
            >
                <button
                    type="button"
                    class="rounded-xl p-1.5 transition-colors"
                    :class="
                        currentTheme === 'light'
                            ? 'bg-slate-100 text-slate-900 shadow-2xs'
                            : 'text-slate-400 hover:text-slate-700'
                    "
                    @click="handleSwitchTheme('light')"
                    title="Light Mode"
                >
                    <component
                        :is="getIcon('Sun')"
                        class="h-3.5 w-3.5 text-amber-500"
                    />
                </button>
                <button
                    type="button"
                    class="rounded-xl p-1.5 transition-colors"
                    :class="
                        currentTheme === 'dark'
                            ? 'bg-slate-800 text-white shadow-2xs'
                            : 'text-slate-400 hover:text-slate-200'
                    "
                    @click="handleSwitchTheme('dark')"
                    title="Dark Mode"
                >
                    <component
                        :is="getIcon('Moon')"
                        class="h-3.5 w-3.5 text-indigo-400"
                    />
                </button>
            </div>

            <!-- Notifications Dropdown -->
            <NotificationDropdown v-if="user" :user="user" />

            <!-- User Profile Avatar Dropdown -->
            <UserDropdown v-if="user" :user="user" />
        </div>
    </header>
</template>
