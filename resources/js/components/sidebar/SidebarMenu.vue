<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { getIcon } from './icons';
import type { MenuSection } from './menu';

interface Props {
    section: MenuSection;
    isActiveRoute: (href: string) => boolean;
}

const props = defineProps<Props>();
const page = usePage();

const permissions = computed(() => page.props.auth.user?.permissions || []);

const can = (perm: string) => permissions.value.includes(perm);

const filteredItems = computed(() =>
    props.section.items.filter(
        (item) => !item.permission || can(item.permission),
    ),
);
</script>

<template>
    <div v-if="filteredItems.length > 0" class="space-y-1">
        <h3
            class="px-3 py-1 text-[11px] font-semibold tracking-wider text-surface-400 uppercase dark:text-surface-500"
        >
            {{ section.label }}
        </h3>
        <Link
            v-for="item in filteredItems"
            :key="item.href"
            :href="item.href"
            class="group relative flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition-all duration-200"
            :class="
                isActiveRoute(item.href)
                    ? 'bg-primary-600/10 font-medium text-primary-700 dark:bg-primary-500/15 dark:text-primary-300'
                    : 'text-surface-600 hover:bg-surface-100 dark:text-surface-400 dark:hover:bg-surface-800/60'
            "
        >
            <!-- Active indicator bar -->
            <span
                class="absolute top-1/2 left-0 h-5 w-1 -translate-y-1/2 rounded-r-full bg-primary-600 transition-all duration-200 dark:bg-primary-400"
                :class="
                    isActiveRoute(item.href)
                        ? 'opacity-100'
                        : 'scale-y-0 opacity-0 group-hover:scale-y-50 group-hover:opacity-50'
                "
            />
            <component
                :is="getIcon(item.icon)"
                class="h-5 w-5 flex-shrink-0 transition-transform duration-200 group-hover:scale-110"
            />
            <span class="truncate">{{ item.label }}</span>
        </Link>
    </div>
</template>
