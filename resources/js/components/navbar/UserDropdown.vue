<script setup lang="ts">
import { ref, watch, nextTick, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { logout, settings } from '@/routes';
import { getIcon } from '@/components/sidebar/icons';
import { gsap } from 'gsap';
import type { User } from '@/types';

interface Props {
    user: User;
}

const props = defineProps<Props>();

const open = ref(false);
const root = ref<HTMLElement | null>(null);
let dropdownCtx: gsap.Context | null = null;

function toggle(): void {
    open.value = !open.value;
}

function close(): void {
    open.value = false;
}

function onDocumentClick(event: MouseEvent): void {
    if (root.value && !root.value.contains(event.target as Node)) {
        close();
    }
}

function onEscape(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        close();
    }
}

function handleLogout(): void {
    router.post(logout.url(), {}, { preserveScroll: true });
}

function animateOpen(): void {
    if (!root.value) return;
    const content = root.value.querySelector<HTMLElement>(
        '[data-dropdown-content]',
    );
    if (!content) return;

    dropdownCtx = gsap.context(() => {
        gsap.fromTo(
            content,
            { opacity: 0, scale: 0.95, y: -8 },
            { opacity: 1, scale: 1, y: 0, duration: 0.18, ease: 'power2.out' },
        );
    });
}

function animateClose(): void {
    if (!root.value) return;
    const content = root.value.querySelector<HTMLElement>(
        '[data-dropdown-content]',
    );
    if (!content) return;

    gsap.to(content, {
        opacity: 0,
        scale: 0.95,
        y: -4,
        duration: 0.12,
        ease: 'power2.in',
        onComplete: close,
    });
}

watch(
    () => open.value,
    (isOpen) => {
        if (isOpen) {
            nextTick(() => animateOpen());
        } else {
            animateClose();
        }
    },
);

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onEscape);
});

onUnmounted(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onEscape);
    dropdownCtx?.revert();
});
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            class="flex items-center gap-2 rounded-full p-1 pr-2 transition-colors duration-150 hover:bg-surface-100 dark:hover:bg-surface-800"
            :aria-expanded="open"
            aria-haspopup="menu"
            @click="toggle"
        >
            <img
                v-if="props.user.avatar"
                :src="props.user.avatar"
                :alt="props.user.name"
                class="h-8 w-8 rounded-full object-cover ring-2 ring-primary-500/20"
            />
            <span
                v-else
                class="flex h-8 w-8 items-center justify-center rounded-full bg-primary-100 text-xs font-semibold text-primary-700 dark:bg-primary-900/30 dark:text-primary-300"
            >
                {{ props.user.initials }}
            </span>
            <span
                class="hidden text-sm font-medium text-surface-700 sm:block dark:text-surface-300"
            >
                {{ props.user.name }}
            </span>
            <component
                :is="getIcon('ChevronDown')"
                class="h-4 w-4 text-surface-400 transition-transform duration-200"
                :class="{ 'rotate-180': open }"
            />
        </button>

        <div
            v-if="open"
            role="menu"
            data-dropdown-content
            class="animate-scale-in dropdown absolute right-0 z-50 mt-2.5 min-w-[220px] origin-top-right"
        >
            <div
                class="border-b border-white/20 p-3 dark:border-surface-700/30"
            >
                <p
                    class="truncate text-sm font-semibold text-surface-900 dark:text-surface-100"
                >
                    {{ props.user.name }}
                </p>
                <p
                    class="truncate text-xs text-surface-500 dark:text-surface-400"
                >
                    {{ props.user.email }}
                </p>
                <div
                    v-if="props.user.roles.length"
                    class="mt-2 flex flex-wrap gap-1"
                >
                    <span
                        v-for="role in props.user.roles"
                        :key="role"
                        class="badge-primary"
                    >
                        {{ role }}
                    </span>
                </div>
            </div>

            <div class="dropdown-divider" />

            <button
                type="button"
                role="menuitem"
                class="dropdown-item w-full justify-start"
                @click="router.visit(settings.url())"
            >
                <component :is="getIcon('Settings')" class="h-4 w-4" />
                Pengaturan
            </button>

            <button
                type="button"
                role="menuitem"
                class="dropdown-item w-full justify-start text-red-600 dark:text-red-400"
                @click="handleLogout"
            >
                <component :is="getIcon('LogOut')" class="h-4 w-4" />
                Keluar
            </button>
        </div>
    </div>
</template>
