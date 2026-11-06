<script setup lang="ts">
import { computed } from 'vue';
import { getIcon } from '@/components/sidebar/icons';

const props = withDefaults(
    defineProps<{
        dueDate: string | null;
        status?: string;
        isOverdue?: boolean;
        showDate?: boolean;
        size?: 'xs' | 'sm' | 'md';
        showIcon?: boolean;
    }>(),
    {
        status: 'todo',
        isOverdue: false,
        showDate: true,
        size: 'sm',
        showIcon: true,
    },
);

const formattedDate = computed(() => {
    if (!props.dueDate) return '';
    const date = new Date(props.dueDate);
    return date.toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
});

const info = computed(() => {
    if (props.status === 'done') {
        return {
            tone: 'completed',
            statusLabel: 'Selesai',
            icon: 'CheckCircle2',
            badgeClass:
                'border-surface-200/80 bg-surface-50 text-surface-600 dark:border-surface-700/60 dark:bg-surface-800/40 dark:text-surface-300',
            iconClass: 'text-primary-700 dark:text-primary-400',
            dotClass: 'bg-primary-600',
        };
    }

    if (!props.dueDate) {
        return {
            tone: 'none',
            statusLabel: 'Tanpa tenggat',
            icon: 'Calendar',
            badgeClass:
                'border-surface-200/60 bg-surface-50/50 text-surface-400 dark:border-surface-800 dark:bg-surface-800/30 dark:text-surface-500',
            iconClass: 'text-surface-400 dark:text-surface-500',
            dotClass: 'bg-surface-400',
        };
    }

    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const due = new Date(props.dueDate);
    due.setHours(0, 0, 0, 0);

    const diffMs = due.getTime() - today.getTime();
    const diffDays = Math.round(diffMs / 86_400_000);

    if (diffDays < 0 || props.isOverdue) {
        const absDays = Math.abs(diffDays);
        return {
            tone: 'overdue',
            statusLabel:
                diffDays === -1 ? 'Lewat kemarin' : `Lewat ${absDays} hari`,
            icon: 'AlertTriangle',
            badgeClass:
                'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-800/50 dark:bg-rose-950/40 dark:text-rose-300 font-medium',
            iconClass: 'text-rose-600 dark:text-rose-400',
            dotClass: 'bg-rose-500',
        };
    }

    if (diffDays === 0) {
        return {
            tone: 'today',
            statusLabel: 'Hari ini',
            icon: 'Clock',
            badgeClass:
                'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800/50 dark:bg-emerald-950/40 dark:text-emerald-300 font-semibold shadow-2xs',
            iconClass: 'text-emerald-600 dark:text-emerald-400',
            dotClass: 'bg-emerald-500',
        };
    }

    if (diffDays <= 3) {
        return {
            tone: 'approaching',
            statusLabel: diffDays === 1 ? 'Besok' : `${diffDays} hari lagi`,
            icon: 'Clock',
            badgeClass:
                'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800/50 dark:bg-amber-950/40 dark:text-amber-300 font-medium',
            iconClass: 'text-amber-600 dark:text-amber-400',
            dotClass: 'bg-amber-500',
        };
    }

    return {
        tone: 'upcoming',
        statusLabel: formattedDate.value,
        icon: 'Calendar',
        badgeClass:
            'border-surface-200/80 bg-surface-50 text-surface-600 dark:border-surface-700/60 dark:bg-surface-800/50 dark:text-surface-300',
        iconClass: 'text-surface-400 dark:text-surface-500',
        dotClass: 'bg-surface-400',
    };
});
</script>

<template>
    <div
        class="inline-flex items-center gap-1.5 rounded-lg border transition-colors"
        :class="[
            info.badgeClass,
            size === 'xs' ? 'px-2 py-0.5 text-[11px]' : '',
            size === 'sm' ? 'px-2.5 py-1 text-xs' : '',
            size === 'md' ? 'px-3 py-1.5 text-sm' : '',
        ]"
    >
        <component
            v-if="showIcon"
            :is="getIcon(info.icon)"
            class="shrink-0"
            :class="[
                info.iconClass,
                size === 'xs' ? 'h-3 w-3' : '',
                size === 'sm' ? 'h-3.5 w-3.5' : '',
                size === 'md' ? 'h-4 w-4' : '',
            ]"
        />

        <div class="flex items-center gap-1.5">
            <span
                v-if="showDate && dueDate && info.tone !== 'upcoming'"
                class="opacity-80"
            >
                {{ formattedDate }} ·
            </span>
            <span>{{ info.statusLabel }}</span>
        </div>
    </div>
</template>
