<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import UserDropdown from './UserDropdown.vue';
import NotificationDropdown from './NotificationDropdown.vue';
import { getIcon } from '@/components/sidebar/icons';
import { useTheme } from '@/composables/useTheme';

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
const { currentTheme, toggleTheme } = useTheme();

function getThemeIcon(): string {
    return currentTheme.value === 'dark' ? 'Moon' : 'Sun';
}

function getThemeLabel(): string {
    return currentTheme.value === 'dark' ? 'Tema gelap' : 'Tema terang';
}

function cycleTheme(): void {
    toggleTheme();
}
</script>

<template>
    <header
        class="glass sticky top-0 z-40 flex h-16 items-center gap-4 border-b border-white/20 px-4 dark:border-surface-700/30"
    >
        <!-- Mobile menu button -->
        <button
            type="button"
            class="rounded-lg p-2 text-surface-500 hover:bg-surface-100 lg:hidden dark:text-surface-400 dark:hover:bg-surface-800"
            aria-label="Buka menu"
            @click="$emit('toggle-sidebar')"
        >
            <component :is="getIcon('Menu')" class="h-6 w-6" />
        </button>

        <!-- Page title / breadcrumb area -->
        <h1
            class="flex-1 truncate text-lg font-semibold text-surface-900 dark:text-surface-100"
        >
            {{ page.props.app.name }}
        </h1>

        <!-- Right side actions -->
        <div class="flex items-center gap-2">
            <!-- Theme toggle -->
            <button
                type="button"
                class="relative rounded-lg p-2 text-surface-500 transition-colors duration-150 hover:bg-surface-100 dark:text-surface-400 dark:hover:bg-surface-800"
                :aria-label="getThemeLabel()"
                @click="cycleTheme"
                title="Ganti tema (Light/Dark)"
            >
                <component :is="getIcon(getThemeIcon())" class="h-5 w-5" />
            </button>

            <!-- Notifications dropdown -->
            <NotificationDropdown v-if="user" :user="user" />

            <!-- User dropdown -->
            <UserDropdown v-if="user" :user="user" />
        </div>
    </header>
</template>
