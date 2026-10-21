<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { getIcon } from '@/components/sidebar/icons';
import { prefersReducedMotion } from '@/lib/motion';
import { gsap } from 'gsap';

defineOptions({
    layout: AppLayout,
});

const page = usePage();
const user = page.props.auth.user;
const stats = page.props.stats as Record<string, number> | undefined;

const cardsRef = ref<HTMLElement[]>([]);

let ctx: gsap.Context | null = null;

function animateEntrance(): void {
    if (!cardsRef.value.length) return;

    ctx?.revert();

    if (prefersReducedMotion()) {
        cardsRef.value.forEach((el) => {
            gsap.set(el, { opacity: 1, y: 0 });
        });
        return;
    }

    ctx = gsap.context(() => {
        gsap.fromTo(
            cardsRef.value,
            { opacity: 0, y: 16 },
            {
                opacity: 1,
                y: 0,
                duration: 0.4,
                ease: 'power2.out',
                stagger: 0.08,
            },
        );
    }, cardsRef.value);
}

onMounted(() => {
    animateEntrance();
});

onBeforeUnmount(() => {
    ctx?.revert();
    ctx = null;
});

const statCards = [
    {
        label: 'Proyek Aktif',
        icon: 'FolderKanban',
        iconBg: 'bg-primary-100 dark:bg-primary-900/30',
        iconColor: 'text-primary-600 dark:text-primary-400',
        value: stats?.projects ?? '—',
    },
    {
        label: 'Tugas Tertunda',
        icon: 'CheckSquare',
        iconBg: 'bg-yellow-100 dark:bg-yellow-900/30',
        iconColor: 'text-yellow-600 dark:text-yellow-400',
        value: stats?.pending_tasks ?? '—',
    },
    {
        label: 'Anggota Tim',
        icon: 'Users',
        iconBg: 'bg-green-100 dark:bg-green-900/30',
        iconColor: 'text-green-600 dark:text-green-400',
        value: stats?.team_members ?? '—',
    },
    {
        label: 'Notifikasi',
        icon: 'Bell',
        iconBg: 'bg-purple-100 dark:bg-purple-900/30',
        iconColor: 'text-purple-600 dark:text-purple-400',
        value: stats?.notifications ?? '—',
    },
] as const;
</script>

<template>
    <div class="space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <h1
                    class="text-2xl font-bold text-surface-900 dark:text-surface-100"
                >
                    Selamat datang, {{ user?.name }}
                </h1>
                <p class="mt-1 text-surface-600 dark:text-surface-400">
                    Ringkasan aktivitas proyek Anda
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
            <div
                v-for="card in statCards"
                :key="card.label"
                ref="cardsRef"
                class="card-hover"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <p
                            class="text-sm font-medium text-surface-500 dark:text-surface-400"
                        >
                            {{ card.label }}
                        </p>
                        <p
                            class="mt-1 text-2xl font-bold text-surface-900 dark:text-surface-100"
                        >
                            {{ card.value }}
                        </p>
                    </div>
                    <div
                        class="flex h-12 w-12 items-center justify-center rounded-lg"
                        :class="card.iconBg"
                    >
                        <component
                            :is="getIcon(card.icon)"
                            class="h-6 w-6"
                            :class="card.iconColor"
                        />
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div
                class="border-b border-white/20 px-6 py-4 dark:border-surface-700/30"
            >
                <h2
                    class="text-lg font-semibold text-surface-900 dark:text-surface-100"
                >
                    Aktivitas Terbaru
                </h2>
            </div>
            <div class="p-6 text-center text-surface-500 dark:text-surface-400">
                Belum ada aktivitas terbaru.
            </div>
        </div>
    </div>
</template>
