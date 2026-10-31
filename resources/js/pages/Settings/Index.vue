<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Avatar from '@/components/ui/Avatar.vue';
import Button from '@/components/ui/Button.vue';
import Toggle from '@/components/ui/Toggle.vue';
import { getIcon } from '@/components/sidebar/icons';
import { useTheme } from '@/composables/useTheme';
import { useToast } from '@/composables/useToast';

defineOptions({
    layout: AppLayout,
});

interface AccountProps {
    is_active: boolean;
    email_verified: boolean;
    two_factor_enabled: boolean;
    passkeys_count: number;
    member_since: string | null;
}

type PanelKey = 'profile' | 'settings' | 'appearance' | 'help';
type SettingsTabKey = 'account' | 'preferences' | 'interface' | 'security';
type NotificationKey =
    | 'taskUpdates'
    | 'dailyDigest'
    | 'deadlineReminders'
    | 'mentions'
    | 'comments'
    | 'sound';

interface NavItem {
    key: PanelKey;
    label: string;
    icon: string;
}

interface SettingsNavItem {
    key: SettingsTabKey;
    label: string;
}

interface NotificationOption {
    key: NotificationKey;
    title: string;
    description: string;
    icon: string;
}

interface NotificationGroup {
    label: string;
    items: NotificationOption[];
}

const page = usePage();
const { currentTheme, setTheme, toggleTheme } = useTheme();
const { success, info } = useToast();

const user = computed(() => page.props.auth?.user);
const appName = computed(() => page.props.app?.shortName ?? 'DigitalCode');
const appFullName = computed(
    () => page.props.app?.name ?? 'DigitalCode Tasks Flow',
);

const account = computed<AccountProps>(() => {
    const raw = page.props.account as Partial<AccountProps> | undefined;

    return {
        is_active: raw?.is_active ?? Boolean(user.value?.is_active),
        email_verified:
            raw?.email_verified ?? Boolean(user.value?.email_verified_at),
        two_factor_enabled: raw?.two_factor_enabled ?? false,
        passkeys_count: raw?.passkeys_count ?? 0,
        member_since: raw?.member_since ?? null,
    };
});

const activePanel = ref<PanelKey>('settings');
const settingsTab = ref<SettingsTabKey>('preferences');
const search = ref('');

const navSections: { label: string; items: NavItem[] }[] = [
    {
        label: 'Kustomisasi',
        items: [
            { key: 'profile', label: 'Profil', icon: 'User' },
            { key: 'settings', label: 'Pengaturan', icon: 'Settings' },
        ],
    },
    {
        label: 'Alat',
        items: [
            { key: 'appearance', label: 'Tampilan', icon: 'Palette' },
            { key: 'help', label: 'Bantuan', icon: 'HelpCircle' },
        ],
    },
];

const settingsNav: { label: string; items: SettingsNavItem[] }[] = [
    {
        label: 'Akun',
        items: [
            { key: 'account', label: 'Ringkasan akun' },
            { key: 'security', label: 'Keamanan' },
        ],
    },
    {
        label: 'Preferensi',
        items: [
            { key: 'preferences', label: 'Notifikasi' },
            { key: 'interface', label: 'Antarmuka' },
        ],
    },
];

const themeOptions = [
    {
        value: 'light',
        label: 'Terang',
        icon: 'Sun',
        description: 'Selalu gunakan tema terang',
    },
    {
        value: 'dark',
        label: 'Gelap',
        icon: 'Moon',
        description: 'Selalu gunakan tema gelap',
    },
] as const;

const currentThemeOption = computed(() =>
    themeOptions.find((option) => option.value === currentTheme.value),
);

const panelMeta = computed(() => {
    switch (activePanel.value) {
        case 'profile':
            return {
                eyebrow: 'Kustomisasi',
                title: 'Profil',
                description:
                    'Informasi akun dan identitas Anda di ruang kerja ini.',
            };
        case 'appearance':
            return {
                eyebrow: 'Alat',
                title: 'Tampilan',
                description:
                    'Sesuaikan tema dan kenyamanan visual saat menggunakan aplikasi.',
            };
        case 'help':
            return {
                eyebrow: 'Alat',
                title: 'Bantuan',
                description:
                    'Panduan singkat dan informasi seputar ruang kerja Anda.',
            };
        default:
            return {
                eyebrow: 'Kustomisasi',
                title: 'Pengaturan',
                description:
                    'Kelola preferensi akun, notifikasi, dan keamanan Anda.',
            };
    }
});

const NOTIFICATION_STORAGE_KEY = 'projex-notification-preferences';

const defaultPreferences: Record<NotificationKey, boolean> = {
    taskUpdates: true,
    dailyDigest: false,
    deadlineReminders: true,
    mentions: true,
    comments: false,
    sound: false,
};

const preferences = ref<Record<NotificationKey, boolean>>({
    ...defaultPreferences,
});

const notificationGroups: NotificationGroup[] = [
    {
        label: 'Pengaturan utama',
        items: [
            {
                key: 'taskUpdates',
                title: 'Pembaruan tugas',
                description:
                    'Dapatkan kabar saat status atau detail tugas di proyek Anda berubah.',
                icon: 'Bell',
            },
            {
                key: 'dailyDigest',
                title: 'Ringkasan harian',
                description:
                    'Terima rangkuman progres proyek dan tugas setiap pagi.',
                icon: 'FileText',
            },
            {
                key: 'deadlineReminders',
                title: 'Pengingat tenggat',
                description:
                    'Ingatkan saya sebelum tugas atau proyek mendekati tenggat.',
                icon: 'Clock',
            },
        ],
    },
    {
        label: 'Opsi sekunder',
        items: [
            {
                key: 'mentions',
                title: 'Sebutan dan komentar',
                description:
                    'Beri tahu saya ketika rekan tim menyebut atau membalas diskusi saya.',
                icon: 'MessageSquare',
            },
            {
                key: 'comments',
                title: 'Aktivitas kolaborasi',
                description:
                    'Ikuti perkembangan diskusi terbaru di proyek yang saya ikuti.',
                icon: 'Users',
            },
            {
                key: 'sound',
                title: 'Suara notifikasi',
                description:
                    'Mainkan bunyi singkat saat notifikasi baru masuk.',
                icon: 'Radio',
            },
        ],
    },
];

const filteredNotificationGroups = computed<NotificationGroup[]>(() => {
    const keyword = search.value.trim().toLowerCase();

    if (!keyword) {
        return notificationGroups;
    }

    return notificationGroups
        .map((group) => ({
            ...group,
            items: group.items.filter(
                (item) =>
                    item.title.toLowerCase().includes(keyword) ||
                    item.description.toLowerCase().includes(keyword),
            ),
        }))
        .filter((group) => group.items.length > 0);
});

const hasNotificationResults = computed(
    () => filteredNotificationGroups.value.length > 0,
);

const roleLabel = computed(
    () => user.value?.roles?.join(', ') || 'Anggota ruang kerja',
);

const accountRows = computed(() => [
    {
        label: 'Nama lengkap',
        value: user.value?.name ?? '—',
        icon: 'User',
    },
    {
        label: 'Alamat email',
        value: user.value?.email ?? '—',
        icon: 'Inbox',
    },
    {
        label: 'Peran',
        value: roleLabel.value,
        icon: 'Shield',
    },
    {
        label: 'Status akun',
        value: account.value.is_active ? 'Aktif' : 'Nonaktif',
        icon: 'CheckCircle2',
    },
    {
        label: 'Bergabung sejak',
        value: formatDate(account.value.member_since),
        icon: 'Calendar',
    },
]);

const securityItems = computed(() => [
    {
        icon: 'Lock',
        title: 'Kata sandi',
        description:
            'Kata sandi akun aktif dan dapat digunakan untuk masuk ke ruang kerja.',
        status: 'Aktif',
        badgeClass:
            'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300',
    },
    {
        icon: 'Shield',
        title: 'Verifikasi dua faktor',
        description:
            'Lapisan keamanan tambahan saat masuk menggunakan kode autentikasi.',
        status: account.value.two_factor_enabled ? 'Aktif' : 'Nonaktif',
        badgeClass: account.value.two_factor_enabled
            ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300'
            : 'bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-300',
    },
    {
        icon: 'CheckCircle2',
        title: 'Verifikasi email',
        description:
            'Pastikan alamat email Anda terverifikasi agar notifikasi penting tidak terlewat.',
        status: account.value.email_verified ? 'Terverifikasi' : 'Belum',
        badgeClass: account.value.email_verified
            ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300'
            : 'bg-accent-100 text-accent-700 dark:bg-accent-900/30 dark:text-accent-300',
    },
    {
        icon: 'UserCheck',
        title: 'Passkey',
        description:
            'Masuk lebih cepat dan aman menggunakan perangkat yang Anda percayai.',
        status:
            account.value.passkeys_count > 0
                ? `${account.value.passkeys_count} terdaftar`
                : 'Belum ada',
        badgeClass:
            account.value.passkeys_count > 0
                ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300'
                : 'bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-300',
    },
]);

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}

function openPanel(panel: PanelKey): void {
    activePanel.value = panel;
    search.value = '';
}

function openSettingsTab(tab: SettingsTabKey): void {
    activePanel.value = 'settings';
    settingsTab.value = tab;
    search.value = '';
}

function selectTheme(theme: 'light' | 'dark'): void {
    setTheme(theme);
    success(
        'Tema diperbarui',
        `Tema diubah ke ${themeOptions.find((option) => option.value === theme)?.label}.`,
    );
}

function savePreferences(): void {
    try {
        localStorage.setItem(
            NOTIFICATION_STORAGE_KEY,
            JSON.stringify(preferences.value),
        );
    } catch {
        // localStorage may be unavailable; the UI still reflects the choice.
    }

    success(
        'Preferensi disimpan',
        'Pengaturan notifikasi Anda berhasil diperbarui.',
    );
}

function showCustomSectionInfo(): void {
    info(
        'Bagian baru',
        'Bagian pengaturan kustom akan tersedia pada pembaruan berikutnya.',
    );
}

onMounted(() => {
    try {
        const stored = localStorage.getItem(NOTIFICATION_STORAGE_KEY);

        if (stored) {
            const parsed = JSON.parse(stored) as Partial<
                Record<NotificationKey, boolean>
            >;
            preferences.value = { ...defaultPreferences, ...parsed };
        }
    } catch {
        // Keep defaults when the stored value cannot be read.
    }
});

watch(search, (value) => {
    if (
        value.trim() &&
        !(
            activePanel.value === 'settings' &&
            settingsTab.value === 'preferences'
        )
    ) {
        activePanel.value = 'settings';
        settingsTab.value = 'preferences';
    }
});
</script>

<template>
    <div class="mx-auto max-w-[1180px]">
        <div
            class="overflow-hidden rounded-[28px] border border-surface-200/80 bg-white shadow-sm dark:border-surface-800 dark:bg-surface-900"
        >
            <div class="grid lg:grid-cols-[248px_minmax(0,1fr)]">
                <!-- Sidebar -->
                <aside
                    class="border-b border-surface-200/80 bg-surface-50/60 p-4 sm:p-5 lg:border-r lg:border-b-0 dark:border-surface-800 dark:bg-surface-950/30"
                >
                    <div class="flex items-center gap-2.5 px-2 pb-6">
                        <span
                            class="grid h-9 w-9 place-items-center rounded-xl bg-surface-950 text-sm font-bold text-white dark:bg-white dark:text-surface-950"
                        >
                            {{ appName.charAt(0).toUpperCase() }}
                        </span>
                        <div class="min-w-0">
                            <p
                                class="truncate text-sm font-semibold text-surface-950 dark:text-surface-50"
                            >
                                {{ appName }}
                            </p>
                            <p
                                class="text-[11px] text-surface-500 dark:text-surface-400"
                            >
                                Ruang kerja
                            </p>
                        </div>
                    </div>

                    <nav class="space-y-6">
                        <div
                            v-for="section in navSections"
                            :key="section.label"
                        >
                            <p
                                class="px-3 text-[10px] font-bold tracking-[0.18em] text-surface-400 uppercase dark:text-surface-500"
                            >
                                {{ section.label }}
                            </p>
                            <div class="mt-2 space-y-1">
                                <button
                                    v-for="item in section.items"
                                    :key="item.key"
                                    type="button"
                                    class="flex w-full items-center gap-2.5 rounded-xl px-3 py-2.5 text-left text-sm font-medium transition-colors"
                                    :class="
                                        activePanel === item.key
                                            ? 'bg-white text-surface-950 shadow-sm ring-1 ring-surface-200/80 dark:bg-surface-800 dark:text-white dark:ring-surface-700'
                                            : 'text-surface-600 hover:bg-white/70 hover:text-surface-900 dark:text-surface-300 dark:hover:bg-surface-800/60 dark:hover:text-white'
                                    "
                                    :aria-current="
                                        activePanel === item.key
                                            ? 'page'
                                            : undefined
                                    "
                                    @click="openPanel(item.key)"
                                >
                                    <component
                                        :is="getIcon(item.icon)"
                                        class="h-4 w-4"
                                        :class="
                                            activePanel === item.key
                                                ? 'text-primary-700 dark:text-primary-300'
                                                : 'text-surface-400 dark:text-surface-500'
                                        "
                                    />
                                    {{ item.label }}
                                    <span
                                        v-if="item.key === 'settings'"
                                        class="ml-auto h-1.5 w-1.5 rounded-full bg-primary-500"
                                    />
                                </button>
                            </div>
                        </div>

                        <button
                            type="button"
                            class="flex w-full items-center gap-2 px-3 py-2 text-sm font-medium text-surface-500 transition-colors hover:text-surface-900 dark:text-surface-400 dark:hover:text-surface-100"
                            @click="showCustomSectionInfo"
                        >
                            <component :is="getIcon('Plus')" class="h-4 w-4" />
                            Bagian baru
                        </button>
                    </nav>

                    <div
                        class="mt-8 rounded-2xl border border-surface-200/80 bg-white/70 p-3.5 dark:border-surface-700/70 dark:bg-surface-900/60"
                    >
                        <div
                            class="flex items-center gap-2 text-xs font-semibold text-surface-800 dark:text-surface-100"
                        >
                            <component
                                :is="getIcon('Shield')"
                                class="h-4 w-4 text-primary-700 dark:text-primary-300"
                            />
                            Akun terlindungi
                        </div>
                        <p
                            class="mt-1.5 text-[11px] leading-5 text-surface-500 dark:text-surface-400"
                        >
                            Kelola preferensi dan keamanan akun Anda dari satu
                            tempat.
                        </p>
                    </div>
                </aside>

                <!-- Main panel -->
                <section class="min-w-0">
                    <!-- Account strip -->
                    <div
                        class="flex items-center gap-3 border-b border-surface-200/80 px-4 py-4 sm:px-6 lg:px-8 dark:border-surface-800"
                    >
                        <Avatar
                            :src="user?.avatar"
                            :initials="user?.initials"
                            :alt="user?.name"
                            size="md"
                        />
                        <div class="min-w-0 flex-1">
                            <p
                                class="truncate text-sm font-semibold text-surface-950 dark:text-surface-50"
                            >
                                {{ user?.name }}
                            </p>
                            <p
                                class="truncate text-xs text-surface-500 dark:text-surface-400"
                            >
                                Bergabung sejak
                                {{ formatDate(account.member_since) }}
                            </p>
                        </div>

                        <div class="flex items-center gap-1.5">
                            <button
                                type="button"
                                class="grid h-9 w-9 place-items-center rounded-full bg-primary-700 text-white shadow-sm transition hover:bg-primary-800 active:scale-95 dark:bg-primary-500 dark:text-primary-950 dark:hover:bg-primary-400"
                                title="Buka profil"
                                aria-label="Buka profil"
                                @click="openPanel('profile')"
                            >
                                <component
                                    :is="getIcon('Pencil')"
                                    class="h-4 w-4"
                                />
                            </button>
                            <button
                                type="button"
                                class="grid h-9 w-9 place-items-center rounded-full border border-surface-200 bg-white text-surface-600 transition hover:border-surface-300 hover:text-surface-900 active:scale-95 dark:border-surface-700 dark:bg-surface-800 dark:text-surface-300 dark:hover:text-white"
                                title="Preferensi notifikasi"
                                aria-label="Preferensi notifikasi"
                                @click="openSettingsTab('preferences')"
                            >
                                <component
                                    :is="getIcon('Bell')"
                                    class="h-4 w-4"
                                />
                            </button>
                            <button
                                type="button"
                                class="grid h-9 w-9 place-items-center rounded-full border border-surface-200 bg-white text-surface-600 transition hover:border-surface-300 hover:text-surface-900 active:scale-95 dark:border-surface-700 dark:bg-surface-800 dark:text-surface-300 dark:hover:text-white"
                                title="Keamanan akun"
                                aria-label="Keamanan akun"
                                @click="openSettingsTab('security')"
                            >
                                <component
                                    :is="getIcon('User')"
                                    class="h-4 w-4"
                                />
                            </button>
                            <button
                                type="button"
                                class="grid h-9 w-9 place-items-center rounded-full border border-surface-200 bg-white text-surface-600 transition hover:border-surface-300 hover:text-surface-900 active:scale-95 dark:border-surface-700 dark:bg-surface-800 dark:text-surface-300 dark:hover:text-white"
                                :title="
                                    currentTheme === 'dark'
                                        ? 'Gunakan tema terang'
                                        : 'Gunakan tema gelap'
                                "
                                :aria-label="
                                    currentTheme === 'dark'
                                        ? 'Gunakan tema terang'
                                        : 'Gunakan tema gelap'
                                "
                                @click="toggleTheme"
                            >
                                <component
                                    :is="
                                        getIcon(
                                            currentTheme === 'dark'
                                                ? 'Sun'
                                                : 'Moon',
                                        )
                                    "
                                    class="h-4 w-4"
                                />
                            </button>
                        </div>
                    </div>

                    <!-- Header -->
                    <div class="px-4 pt-6 sm:px-6 lg:px-8">
                        <div
                            class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
                        >
                            <div>
                                <p
                                    class="text-[10px] font-bold tracking-[0.18em] text-primary-700 uppercase dark:text-primary-300"
                                >
                                    {{ panelMeta.eyebrow }}
                                </p>
                                <h1
                                    class="mt-1 text-3xl font-bold tracking-tight text-surface-950 dark:text-surface-50"
                                >
                                    {{ panelMeta.title }}
                                </h1>
                                <p
                                    class="mt-1 max-w-xl text-sm leading-6 text-surface-500 dark:text-surface-400"
                                >
                                    {{ panelMeta.description }}
                                </p>
                            </div>

                            <div class="relative w-full sm:w-64">
                                <span
                                    class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-surface-400 dark:text-surface-500"
                                >
                                    <component
                                        :is="getIcon('Search')"
                                        class="h-4 w-4"
                                    />
                                </span>
                                <input
                                    v-model="search"
                                    type="search"
                                    class="input"
                                    style="padding-left: 2.5rem"
                                    placeholder="Cari pengaturan..."
                                    aria-label="Cari pengaturan"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Content -->
                    <div class="px-4 pt-6 pb-7 sm:px-6 lg:px-8">
                        <!-- Settings with inner navigation -->
                        <div
                            v-if="activePanel === 'settings'"
                            class="grid gap-7 lg:grid-cols-[170px_minmax(0,1fr)]"
                        >
                            <nav class="space-y-5">
                                <div
                                    v-for="group in settingsNav"
                                    :key="group.label"
                                >
                                    <p
                                        class="px-3 text-[10px] font-bold tracking-[0.16em] text-surface-400 uppercase dark:text-surface-500"
                                    >
                                        {{ group.label }}
                                    </p>
                                    <div class="mt-1.5 space-y-0.5">
                                        <button
                                            v-for="item in group.items"
                                            :key="item.key"
                                            type="button"
                                            class="relative flex w-full items-center rounded-lg px-3 py-2 text-left text-xs font-semibold transition-colors"
                                            :class="
                                                settingsTab === item.key
                                                    ? 'bg-primary-50 text-primary-700 dark:bg-primary-900/25 dark:text-primary-200'
                                                    : 'text-surface-500 hover:bg-surface-50 hover:text-surface-900 dark:text-surface-400 dark:hover:bg-surface-800/60 dark:hover:text-surface-100'
                                            "
                                            :aria-current="
                                                settingsTab === item.key
                                                    ? 'page'
                                                    : undefined
                                            "
                                            @click="openSettingsTab(item.key)"
                                        >
                                            <span
                                                v-if="settingsTab === item.key"
                                                class="absolute left-0 h-3.5 w-0.5 rounded-full bg-primary-600 dark:bg-primary-300"
                                            />
                                            {{ item.label }}
                                        </button>
                                    </div>
                                </div>
                            </nav>

                            <div class="min-w-0">
                                <!-- Preferences -->
                                <div
                                    v-if="settingsTab === 'preferences'"
                                    class="space-y-6"
                                >
                                    <div>
                                        <h2
                                            class="text-lg font-semibold text-surface-950 dark:text-surface-50"
                                        >
                                            Preferensi notifikasi
                                        </h2>
                                        <p
                                            class="mt-1 text-sm leading-6 text-surface-500 dark:text-surface-400"
                                        >
                                            Secara bawaan, Anda akan diberi tahu
                                            melalui pola pilihan ruang kerja.
                                            Anda juga dapat menyesuaikan
                                            preferensi notifikasi di bawah ini.
                                        </p>
                                    </div>

                                    <div
                                        v-for="group in filteredNotificationGroups"
                                        :key="group.label"
                                        class="space-y-3"
                                    >
                                        <p
                                            class="text-[10px] font-bold tracking-[0.16em] text-surface-400 uppercase dark:text-surface-500"
                                        >
                                            {{ group.label }}
                                        </p>

                                        <div
                                            v-for="item in group.items"
                                            :key="item.key"
                                            class="flex items-center gap-3 rounded-2xl border p-3.5 transition-colors"
                                            :class="
                                                preferences[item.key]
                                                    ? 'border-primary-200 bg-primary-50/60 dark:border-primary-700/40 dark:bg-primary-900/20'
                                                    : 'border-surface-200 bg-white dark:border-surface-700 dark:bg-surface-900'
                                            "
                                        >
                                            <span
                                                class="grid h-10 w-10 shrink-0 place-items-center rounded-xl transition-colors"
                                                :class="
                                                    preferences[item.key]
                                                        ? 'bg-primary-100 text-primary-700 dark:bg-primary-800/50 dark:text-primary-200'
                                                        : 'bg-surface-100 text-surface-500 dark:bg-surface-800 dark:text-surface-300'
                                                "
                                            >
                                                <component
                                                    :is="getIcon(item.icon)"
                                                    class="h-4 w-4"
                                                />
                                            </span>
                                            <div class="min-w-0 flex-1">
                                                <p
                                                    class="text-sm font-semibold text-surface-900 dark:text-surface-100"
                                                >
                                                    {{ item.title }}
                                                </p>
                                                <p
                                                    class="mt-0.5 text-xs leading-5 text-surface-500 dark:text-surface-400"
                                                >
                                                    {{ item.description }}
                                                </p>
                                            </div>
                                            <Toggle
                                                v-model="preferences[item.key]"
                                                :label="item.title"
                                            />
                                        </div>
                                    </div>

                                    <div
                                        v-if="!hasNotificationResults"
                                        class="rounded-2xl border border-dashed border-surface-300 p-8 text-center dark:border-surface-700"
                                    >
                                        <component
                                            :is="getIcon('Search')"
                                            class="mx-auto h-5 w-5 text-surface-400"
                                        />
                                        <p
                                            class="mt-2 text-sm font-semibold text-surface-800 dark:text-surface-100"
                                        >
                                            Tidak ada preferensi yang cocok
                                        </p>
                                        <p
                                            class="mt-1 text-xs text-surface-500 dark:text-surface-400"
                                        >
                                            Coba gunakan kata kunci lain.
                                        </p>
                                    </div>

                                    <div
                                        class="space-y-3 border-t border-surface-200 pt-5 dark:border-surface-800"
                                    >
                                        <p
                                            class="text-xs text-surface-500 dark:text-surface-400"
                                        >
                                            Perubahan preferensi disimpan pada
                                            perangkat ini.
                                        </p>
                                        <Button
                                            variant="primary"
                                            class="w-full"
                                            @click="savePreferences"
                                        >
                                            Simpan perubahan
                                        </Button>
                                    </div>
                                </div>

                                <!-- Interface -->
                                <div
                                    v-else-if="settingsTab === 'interface'"
                                    class="space-y-6"
                                >
                                    <div>
                                        <h2
                                            class="text-lg font-semibold text-surface-950 dark:text-surface-50"
                                        >
                                            Antarmuka
                                        </h2>
                                        <p
                                            class="mt-1 text-sm leading-6 text-surface-500 dark:text-surface-400"
                                        >
                                            Pilih tema yang paling nyaman untuk
                                            mata Anda. Perubahan langsung
                                            diterapkan.
                                        </p>
                                    </div>

                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <button
                                            v-for="option in themeOptions"
                                            :key="option.value"
                                            type="button"
                                            class="relative rounded-2xl border p-4 text-left transition-all"
                                            :class="
                                                currentTheme === option.value
                                                    ? 'border-primary-500 bg-primary-50/60 dark:border-primary-500/70 dark:bg-primary-900/20'
                                                    : 'border-surface-200 bg-white hover:border-surface-300 dark:border-surface-700 dark:bg-surface-900 dark:hover:border-surface-600'
                                            "
                                            :aria-pressed="
                                                currentTheme === option.value
                                            "
                                            @click="selectTheme(option.value)"
                                        >
                                            <div
                                                class="flex items-center justify-between"
                                            >
                                                <span
                                                    class="grid h-10 w-10 place-items-center rounded-xl"
                                                    :class="
                                                        currentTheme ===
                                                        option.value
                                                            ? 'bg-primary-100 text-primary-700 dark:bg-primary-800/50 dark:text-primary-200'
                                                            : 'bg-surface-100 text-surface-500 dark:bg-surface-800 dark:text-surface-300'
                                                    "
                                                >
                                                    <component
                                                        :is="
                                                            getIcon(option.icon)
                                                        "
                                                        class="h-5 w-5"
                                                    />
                                                </span>
                                                <span
                                                    v-if="
                                                        currentTheme ===
                                                        option.value
                                                    "
                                                    class="grid h-6 w-6 place-items-center rounded-full bg-primary-600 text-white"
                                                >
                                                    <component
                                                        :is="getIcon('Check')"
                                                        class="h-3.5 w-3.5"
                                                    />
                                                </span>
                                            </div>
                                            <p
                                                class="mt-4 text-sm font-semibold text-surface-900 dark:text-surface-100"
                                            >
                                                {{ option.label }}
                                            </p>
                                            <p
                                                class="mt-1 text-xs text-surface-500 dark:text-surface-400"
                                            >
                                                {{ option.description }}
                                            </p>
                                            <div
                                                class="mt-4 overflow-hidden rounded-xl border border-surface-200 p-2 dark:border-surface-700"
                                                :class="
                                                    option.value === 'light'
                                                        ? 'bg-surface-50'
                                                        : 'bg-surface-900'
                                                "
                                            >
                                                <div
                                                    class="h-2 w-2/3 rounded-full"
                                                    :class="
                                                        option.value === 'light'
                                                            ? 'bg-surface-300'
                                                            : 'bg-surface-600'
                                                    "
                                                />
                                                <div
                                                    class="mt-1.5 h-2 w-full rounded-full"
                                                    :class="
                                                        option.value === 'light'
                                                            ? 'bg-surface-200'
                                                            : 'bg-surface-700'
                                                    "
                                                />
                                                <div
                                                    class="mt-1.5 h-2 w-4/5 rounded-full"
                                                    :class="
                                                        option.value === 'light'
                                                            ? 'bg-surface-200'
                                                            : 'bg-surface-700'
                                                    "
                                                />
                                            </div>
                                        </button>
                                    </div>

                                    <p
                                        class="text-xs text-surface-500 dark:text-surface-400"
                                    >
                                        Tema saat ini:
                                        <strong
                                            class="text-surface-800 dark:text-surface-100"
                                        >
                                            {{ currentThemeOption?.label }}
                                        </strong>
                                    </p>
                                </div>

                                <!-- Account summary -->
                                <div
                                    v-else-if="settingsTab === 'account'"
                                    class="space-y-6"
                                >
                                    <div>
                                        <h2
                                            class="text-lg font-semibold text-surface-950 dark:text-surface-50"
                                        >
                                            Ringkasan akun
                                        </h2>
                                        <p
                                            class="mt-1 text-sm leading-6 text-surface-500 dark:text-surface-400"
                                        >
                                            Detail akun yang digunakan pada
                                            ruang kerja ini.
                                        </p>
                                    </div>

                                    <div
                                        class="overflow-hidden rounded-2xl border border-surface-200 dark:border-surface-700"
                                    >
                                        <div
                                            v-for="(row, index) in accountRows"
                                            :key="row.label"
                                            class="flex items-center gap-3 px-4 py-3.5"
                                            :class="
                                                index > 0
                                                    ? 'border-t border-surface-100 dark:border-surface-800'
                                                    : ''
                                            "
                                        >
                                            <span
                                                class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-surface-100 text-surface-500 dark:bg-surface-800 dark:text-surface-300"
                                            >
                                                <component
                                                    :is="getIcon(row.icon)"
                                                    class="h-4 w-4"
                                                />
                                            </span>
                                            <div class="min-w-0 flex-1">
                                                <p
                                                    class="text-xs font-medium text-surface-500 dark:text-surface-400"
                                                >
                                                    {{ row.label }}
                                                </p>
                                                <p
                                                    class="truncate text-sm font-semibold text-surface-900 capitalize dark:text-surface-100"
                                                >
                                                    {{ row.value }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        class="flex items-start gap-3 rounded-2xl border p-4"
                                        :class="
                                            account.email_verified
                                                ? 'border-primary-200 bg-primary-50/60 dark:border-primary-700/40 dark:bg-primary-900/20'
                                                : 'border-accent-200 bg-accent-50/60 dark:border-accent-700/40 dark:bg-accent-900/20'
                                        "
                                    >
                                        <component
                                            :is="
                                                getIcon(
                                                    account.email_verified
                                                        ? 'CheckCircle2'
                                                        : 'AlertTriangle',
                                                )
                                            "
                                            class="mt-0.5 h-5 w-5 shrink-0"
                                            :class="
                                                account.email_verified
                                                    ? 'text-primary-700 dark:text-primary-300'
                                                    : 'text-accent-700 dark:text-accent-300'
                                            "
                                        />
                                        <div>
                                            <p
                                                class="text-sm font-semibold text-surface-900 dark:text-surface-100"
                                            >
                                                {{
                                                    account.email_verified
                                                        ? 'Email terverifikasi'
                                                        : 'Email belum terverifikasi'
                                                }}
                                            </p>
                                            <p
                                                class="mt-0.5 text-xs leading-5 text-surface-500 dark:text-surface-400"
                                            >
                                                {{
                                                    account.email_verified
                                                        ? 'Alamat email Anda sudah terverifikasi dan siap menerima notifikasi penting.'
                                                        : 'Hubungi administrator jika Anda memerlukan bantuan verifikasi email.'
                                                }}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Security -->
                                <div v-else class="space-y-6">
                                    <div>
                                        <h2
                                            class="text-lg font-semibold text-surface-950 dark:text-surface-50"
                                        >
                                            Keamanan akun
                                        </h2>
                                        <p
                                            class="mt-1 text-sm leading-6 text-surface-500 dark:text-surface-400"
                                        >
                                            Ringkasan lapisan keamanan yang
                                            melindungi akun Anda.
                                        </p>
                                    </div>

                                    <div class="space-y-3">
                                        <div
                                            v-for="item in securityItems"
                                            :key="item.title"
                                            class="flex items-start gap-3 rounded-2xl border border-surface-200 bg-white p-4 dark:border-surface-700 dark:bg-surface-900"
                                        >
                                            <span
                                                class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-300"
                                            >
                                                <component
                                                    :is="getIcon(item.icon)"
                                                    class="h-4 w-4"
                                                />
                                            </span>
                                            <div class="min-w-0 flex-1">
                                                <div
                                                    class="flex flex-wrap items-center gap-2"
                                                >
                                                    <p
                                                        class="text-sm font-semibold text-surface-900 dark:text-surface-100"
                                                    >
                                                        {{ item.title }}
                                                    </p>
                                                    <span
                                                        class="rounded-full px-2 py-0.5 text-[10px] font-bold"
                                                        :class="item.badgeClass"
                                                    >
                                                        {{ item.status }}
                                                    </span>
                                                </div>
                                                <p
                                                    class="mt-1 text-xs leading-5 text-surface-500 dark:text-surface-400"
                                                >
                                                    {{ item.description }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <p
                                        class="rounded-2xl border border-dashed border-surface-300 p-4 text-xs leading-5 text-surface-500 dark:border-surface-700 dark:text-surface-400"
                                    >
                                        Pengelolaan kata sandi, verifikasi dua
                                        faktor, dan passkey akan tersedia
                                        langsung dari halaman ini pada pembaruan
                                        berikutnya.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Profile -->
                        <div
                            v-else-if="activePanel === 'profile'"
                            class="space-y-6"
                        >
                            <div
                                class="flex flex-col gap-4 rounded-2xl border border-surface-200 bg-surface-50/70 p-5 sm:flex-row sm:items-center dark:border-surface-700 dark:bg-surface-950/30"
                            >
                                <Avatar
                                    :src="user?.avatar"
                                    :initials="user?.initials"
                                    :alt="user?.name"
                                    size="lg"
                                />
                                <div class="min-w-0 flex-1">
                                    <h2
                                        class="truncate text-xl font-bold text-surface-950 dark:text-surface-50"
                                    >
                                        {{ user?.name }}
                                    </h2>
                                    <p
                                        class="truncate text-sm text-surface-500 dark:text-surface-400"
                                    >
                                        {{ user?.email }}
                                    </p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <span
                                            class="rounded-full bg-white px-2.5 py-1 text-[11px] font-semibold text-surface-700 ring-1 ring-surface-200 dark:bg-surface-800 dark:text-surface-200 dark:ring-surface-700"
                                        >
                                            {{ roleLabel }}
                                        </span>
                                        <span
                                            class="rounded-full px-2.5 py-1 text-[11px] font-semibold"
                                            :class="
                                                account.is_active
                                                    ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300'
                                                    : 'bg-surface-200 text-surface-600 dark:bg-surface-800 dark:text-surface-300'
                                            "
                                        >
                                            {{
                                                account.is_active
                                                    ? 'Akun aktif'
                                                    : 'Akun nonaktif'
                                            }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <div
                                    v-for="row in accountRows"
                                    :key="`profile-${row.label}`"
                                    class="rounded-2xl border border-surface-200 bg-white p-4 dark:border-surface-700 dark:bg-surface-900"
                                >
                                    <div class="flex items-center gap-2">
                                        <component
                                            :is="getIcon(row.icon)"
                                            class="h-4 w-4 text-surface-400"
                                        />
                                        <p
                                            class="text-xs font-medium text-surface-500 dark:text-surface-400"
                                        >
                                            {{ row.label }}
                                        </p>
                                    </div>
                                    <p
                                        class="mt-1.5 truncate text-sm font-semibold text-surface-900 capitalize dark:text-surface-100"
                                    >
                                        {{ row.value }}
                                    </p>
                                </div>
                            </div>

                            <p
                                class="rounded-2xl border border-dashed border-surface-300 p-4 text-xs leading-5 text-surface-500 dark:border-surface-700 dark:text-surface-400"
                            >
                                Perubahan nama, alamat email, dan foto profil
                                dikelola oleh administrator sistem. Hubungi
                                administrator jika ada data yang perlu
                                diperbarui.
                            </p>
                        </div>

                        <!-- Appearance -->
                        <div
                            v-else-if="activePanel === 'appearance'"
                            class="space-y-6"
                        >
                            <div>
                                <h2
                                    class="text-lg font-semibold text-surface-950 dark:text-surface-50"
                                >
                                    Tema aplikasi
                                </h2>
                                <p
                                    class="mt-1 text-sm leading-6 text-surface-500 dark:text-surface-400"
                                >
                                    Pilih tampilan yang paling nyaman. Tema
                                    berlaku untuk seluruh halaman dan tersimpan
                                    di perangkat ini.
                                </p>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2">
                                <button
                                    v-for="option in themeOptions"
                                    :key="`panel-${option.value}`"
                                    type="button"
                                    class="rounded-2xl border p-5 text-left transition-all"
                                    :class="
                                        currentTheme === option.value
                                            ? 'border-primary-500 bg-primary-50/60 dark:border-primary-500/70 dark:bg-primary-900/20'
                                            : 'border-surface-200 bg-white hover:border-surface-300 dark:border-surface-700 dark:bg-surface-900 dark:hover:border-surface-600'
                                    "
                                    :aria-pressed="
                                        currentTheme === option.value
                                    "
                                    @click="selectTheme(option.value)"
                                >
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <span
                                            class="grid h-11 w-11 place-items-center rounded-xl"
                                            :class="
                                                currentTheme === option.value
                                                    ? 'bg-primary-100 text-primary-700 dark:bg-primary-800/50 dark:text-primary-200'
                                                    : 'bg-surface-100 text-surface-500 dark:bg-surface-800 dark:text-surface-300'
                                            "
                                        >
                                            <component
                                                :is="getIcon(option.icon)"
                                                class="h-5 w-5"
                                            />
                                        </span>
                                        <span
                                            v-if="currentTheme === option.value"
                                            class="grid h-6 w-6 place-items-center rounded-full bg-primary-600 text-white"
                                        >
                                            <component
                                                :is="getIcon('Check')"
                                                class="h-3.5 w-3.5"
                                            />
                                        </span>
                                    </div>
                                    <p
                                        class="mt-4 font-semibold text-surface-900 dark:text-surface-100"
                                    >
                                        {{ option.label }}
                                    </p>
                                    <p
                                        class="mt-1 text-xs text-surface-500 dark:text-surface-400"
                                    >
                                        {{ option.description }}
                                    </p>
                                </button>
                            </div>

                            <div
                                class="flex items-center justify-between rounded-2xl border border-surface-200 bg-white p-4 dark:border-surface-700 dark:bg-surface-900"
                            >
                                <div>
                                    <p
                                        class="text-sm font-semibold text-surface-900 dark:text-surface-100"
                                    >
                                        Tema saat ini
                                    </p>
                                    <p
                                        class="text-xs text-surface-500 dark:text-surface-400"
                                    >
                                        {{ currentThemeOption?.description }}
                                    </p>
                                </div>
                                <span
                                    class="rounded-full bg-primary-100 px-3 py-1 text-xs font-bold text-primary-700 dark:bg-primary-900/30 dark:text-primary-300"
                                >
                                    {{ currentThemeOption?.label }}
                                </span>
                            </div>
                        </div>

                        <!-- Help -->
                        <div v-else class="space-y-5">
                            <div
                                class="rounded-2xl border border-surface-200 bg-white p-5 dark:border-surface-700 dark:bg-surface-900"
                            >
                                <div class="flex items-start gap-3">
                                    <span
                                        class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300"
                                    >
                                        <component
                                            :is="getIcon('HelpCircle')"
                                            class="h-5 w-5"
                                        />
                                    </span>
                                    <div>
                                        <h2
                                            class="text-lg font-semibold text-surface-950 dark:text-surface-50"
                                        >
                                            Butuh bantuan?
                                        </h2>
                                        <p
                                            class="mt-1 text-sm leading-6 text-surface-500 dark:text-surface-400"
                                        >
                                            Beberapa pintasan dan tips untuk
                                            mempermudah penggunaan ruang kerja.
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-5 space-y-3">
                                    <div class="flex items-start gap-2.5">
                                        <component
                                            :is="getIcon('Search')"
                                            class="mt-0.5 h-4 w-4 shrink-0 text-primary-600 dark:text-primary-300"
                                        />
                                        <p
                                            class="text-sm text-surface-600 dark:text-surface-300"
                                        >
                                            Tekan
                                            <kbd
                                                class="rounded-md border border-surface-200 bg-surface-50 px-1.5 py-0.5 text-[11px] font-semibold dark:border-surface-700 dark:bg-surface-800"
                                                >Ctrl</kbd
                                            >
                                            +
                                            <kbd
                                                class="rounded-md border border-surface-200 bg-surface-50 px-1.5 py-0.5 text-[11px] font-semibold dark:border-surface-700 dark:bg-surface-800"
                                                >K</kbd
                                            >
                                            untuk membuka pencarian global.
                                        </p>
                                    </div>
                                    <div class="flex items-start gap-2.5">
                                        <component
                                            :is="getIcon('Moon')"
                                            class="mt-0.5 h-4 w-4 shrink-0 text-primary-600 dark:text-primary-300"
                                        />
                                        <p
                                            class="text-sm text-surface-600 dark:text-surface-300"
                                        >
                                            Gunakan tombol tema di kanan atas
                                            untuk beralih terang atau gelap.
                                        </p>
                                    </div>
                                    <div class="flex items-start gap-2.5">
                                        <component
                                            :is="getIcon('CheckCircle2')"
                                            class="mt-0.5 h-4 w-4 shrink-0 text-primary-600 dark:text-primary-300"
                                        />
                                        <p
                                            class="text-sm text-surface-600 dark:text-surface-300"
                                        >
                                            Preferensi notifikasi dapat diatur
                                            pada menu Pengaturan.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="rounded-2xl border border-surface-200 bg-surface-50/60 p-5 dark:border-surface-700 dark:bg-surface-950/30"
                            >
                                <p
                                    class="text-[10px] font-bold tracking-[0.16em] text-surface-400 uppercase"
                                >
                                    Tentang aplikasi
                                </p>
                                <p
                                    class="mt-1 text-sm font-semibold text-surface-900 dark:text-surface-100"
                                >
                                    {{ appFullName }}
                                </p>
                                <p
                                    class="mt-1 text-xs leading-5 text-surface-500 dark:text-surface-400"
                                >
                                    Ruang kerja kolaborasi untuk mengelola
                                    proyek, tugas, dan aktivitas tim dalam satu
                                    tempat.
                                </p>
                            </div>

                            <Link
                                href="/dashboard"
                                class="btn btn-secondary w-full sm:w-auto"
                            >
                                <component
                                    :is="getIcon('ArrowLeft')"
                                    class="h-4 w-4"
                                />
                                Kembali ke dashboard
                            </Link>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>
