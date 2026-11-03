<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
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

type PanelKey =
    | 'profile'
    | 'appearance'
    | 'notifications'
    | 'security'
    | 'help';

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
    description: string;
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
const { currentTheme, resolvedTheme, setTheme } = useTheme();
const { success } = useToast();

const user = computed(() => page.props.auth?.user);
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

const activePanel = ref<PanelKey>('profile');
const search = ref('');

const navItems: NavItem[] = [
    {
        key: 'profile',
        label: 'Profil Akun',
        icon: 'User',
        description: 'Informasi identitas dan status akun',
    },
    {
        key: 'appearance',
        label: 'Tampilan',
        icon: 'Palette',
        description: 'Pilihan mode terang dan gelap',
    },
    {
        key: 'notifications',
        label: 'Notifikasi',
        icon: 'Bell',
        description: 'Preferensi pemberitahuan & pengingat',
    },
    {
        key: 'security',
        label: 'Keamanan',
        icon: 'Shield',
        description: 'Lapisan perlindungan dan autentikasi',
    },
    {
        key: 'help',
        label: 'Bantuan & Pintasan',
        icon: 'HelpCircle',
        description: 'Pintasan keyboard dan panduan sistem',
    },
];

const activeTheme = computed<'light' | 'dark'>(() =>
    currentTheme.value === 'system' ? resolvedTheme.value : currentTheme.value,
);

const panelMeta = computed(() => {
    switch (activePanel.value) {
        case 'profile':
            return {
                eyebrow: 'Pengaturan Akun',
                title: 'Profil Pengguna',
                description:
                    'Informasi akun dan status peran Anda di ruang kerja ini.',
            };
        case 'appearance':
            return {
                eyebrow: 'Preferensi Visual',
                title: 'Tampilan Aplikasi',
                description:
                    'Pilih tema terang atau gelap yang paling nyaman untuk Anda.',
            };
        case 'notifications':
            return {
                eyebrow: 'Preferensi Pemberitahuan',
                title: 'Pusat Notifikasi',
                description:
                    'Atur notifikasi mana yang ingin Anda terima agar tetap fokus.',
            };
        case 'security':
            return {
                eyebrow: 'Perlindungan Akun',
                title: 'Keamanan & Autentikasi',
                description:
                    'Ringkasan status keamanan dan kredensial akun Anda.',
            };
        default:
            return {
                eyebrow: 'Pusat Dukungan',
                title: 'Bantuan & Pintasan Keyboard',
                description:
                    'Pintasan tombol cepat dan informasi panduan penggunaan.',
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
        label: 'Aktivitas Utama',
        items: [
            {
                key: 'taskUpdates',
                title: 'Pembaruan tugas',
                description:
                    'Kabar saat status, tenggat, atau detail tugas dalam proyek berubah.',
                icon: 'Bell',
            },
            {
                key: 'deadlineReminders',
                title: 'Pengingat tenggat waktu',
                description:
                    'Peringatan otomatis saat tugas mendekati batas akhir pengerjaan.',
                icon: 'Clock',
            },
            {
                key: 'dailyDigest',
                title: 'Ringkasan harian',
                description:
                    'Rangkuman singkat progres pekerjaan harian di ruang kerja.',
                icon: 'FileText',
            },
        ],
    },
    {
        label: 'Interaksi & Suara',
        items: [
            {
                key: 'mentions',
                title: 'Sebutan (@mention) & Komentar',
                description:
                    'Pemberitahuan ketika rekan tim menandai Anda atau membalas pesan.',
                icon: 'MessageSquare',
            },
            {
                key: 'comments',
                title: 'Aktivitas kolaborasi tim',
                description:
                    'Diskusi dan aktivitas terbaru pada proyek yang Anda ikuti.',
                icon: 'Users',
            },
            {
                key: 'sound',
                title: 'Bunyi notifikasi audio',
                description:
                    'Mainkan suara lembut saat pemberitahuan baru diterima.',
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

const accountDetails = computed(() => [
    {
        label: 'Nama Lengkap',
        value: user.value?.name ?? '—',
        icon: 'User',
    },
    {
        label: 'Email Akun',
        value: user.value?.email ?? '—',
        icon: 'Mail',
    },
    {
        label: 'Peran Ruang Kerja',
        value: roleLabel.value,
        icon: 'Shield',
    },
    {
        label: 'Status Akun',
        value: account.value.is_active ? 'Aktif' : 'Nonaktif',
        icon: 'CheckCircle2',
        highlight: account.value.is_active ? 'active' : 'inactive',
    },
    {
        label: 'Terdaftar Sejak',
        value: formatDate(account.value.member_since),
        icon: 'Calendar',
    },
]);

const securityItems = computed(() => [
    {
        icon: 'Lock',
        title: 'Kata Sandi Akun',
        description:
            'Kata sandi aktif digunakan untuk autentikasi masuk ke ruang kerja.',
        status: 'Terproteksi',
        tone: 'emerald',
    },
    {
        icon: 'Shield',
        title: 'Verifikasi Dua Faktor (2FA)',
        description:
            'Lapisan perlindungan tambahan dengan kode autentikasi sekali pakai.',
        status: account.value.two_factor_enabled ? 'Aktif' : 'Belum Aktif',
        tone: account.value.two_factor_enabled ? 'emerald' : 'surface',
    },
    {
        icon: 'CheckCircle2',
        title: 'Verifikasi Email',
        description:
            'Email terverifikasi memastikan Anda menerima pemulihan akun & notifikasi resmi.',
        status: account.value.email_verified ? 'Terverifikasi' : 'Belum Terverifikasi',
        tone: account.value.email_verified ? 'emerald' : 'amber',
    },
    {
        icon: 'UserCheck',
        title: 'Kunci Masuk (Passkey)',
        description:
            'Masuk lebih aman dan cepat menggunakan biometrik perangkat Anda.',
        status:
            account.value.passkeys_count > 0
                ? `${account.value.passkeys_count} Terdaftar`
                : 'Belum Ada',
        tone: account.value.passkeys_count > 0 ? 'emerald' : 'surface',
    },
]);

// FAQ item accordion state
const openedFaq = ref<number | null>(null);

function toggleFaq(index: number): void {
    openedFaq.value = openedFaq.value === index ? null : index;
}

const faqs = [
    {
        question: 'Bagaimana cara mengubah tema aplikasi ke mode gelap atau terang?',
        answer: 'Buka menu Pengaturan → Tampilan, lalu cukup klik pada pilihan Mode Terang atau Mode Gelap. Pilihan Anda akan langsung aktif dan tersimpan otomatis di perangkat ini.',
    },
    {
        question: 'Bagaimana cara mengubah profil dan nama pengguna?',
        answer: 'Data identitas dasar akun dikelola terpusat oleh administrator sistem. Jika terdapat kesalahan penulisan nama atau email, silakan hubungi admin tim Anda.',
    },
    {
        question: 'Apakah preferensi notifikasi disimpan di server?',
        answer: 'Preferensi notifikasi Anda saat ini disimpan di penyimpanan lokal browser perangkat ini, sehingga Anda dapat menyesuaikan preferensi berbeda antar perangkat.',
    },
    {
        question: 'Bagaimana cara mengaktifkan verifikasi 2 faktor (2FA)?',
        answer: 'Konfigurasi autentikasi lanjutan dikontrol oleh kebijakan administrator ruang kerja Anda. Hubungi administrator jika fitur 2FA ingin diaktifkan.',
    },
];

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

function selectTheme(theme: 'light' | 'dark'): void {
    setTheme(theme);
    success(
        'Tema Diperbarui',
        `Tampilan berhasil dialihkan ke mode ${theme === 'dark' ? 'Gelap' : 'Terang'}.`,
    );
}

function savePreferences(): void {
    try {
        localStorage.setItem(
            NOTIFICATION_STORAGE_KEY,
            JSON.stringify(preferences.value),
        );
    } catch {
        // localStorage may be unavailable
    }

    success(
        'Preferensi Disimpan',
        'Pengaturan notifikasi berhasil diperbarui.',
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
        // Keep defaults
    }
});
</script>

<template>
    <div class="space-y-6 pb-12">
        <!-- Page Header -->
        <header
            class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <span
                    class="inline-flex h-6 items-center gap-1.5 rounded-full border border-primary-200/80 bg-primary-50 px-2.5 text-[10px] font-bold tracking-wider text-primary-800 uppercase dark:border-primary-800/40 dark:bg-primary-950/40 dark:text-primary-300"
                >
                    <component :is="getIcon('Settings')" class="h-3 w-3" />
                    Pusat Pengaturan
                </span>
                <h1
                    class="mt-2 text-2xl font-semibold tracking-tight text-surface-950 sm:text-3xl dark:text-surface-50"
                >
                    Pengaturan
                </h1>
                <p
                    class="mt-1 max-w-2xl text-sm leading-relaxed text-surface-600 dark:text-surface-300"
                >
                    Kelola profil, tampilan aplikasi, notifikasi, dan preferensi akun Anda.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <span
                    class="inline-flex items-center gap-2 rounded-xl border border-surface-200/80 bg-white px-3 py-1.5 text-xs font-semibold text-surface-700 shadow-2xs dark:border-surface-800 dark:bg-surface-900 dark:text-surface-200"
                >
                    <span
                        class="h-2 w-2 rounded-full"
                        :class="account.is_active ? 'bg-emerald-500' : 'bg-surface-400'"
                    />
                    {{ account.is_active ? 'Akun Aktif' : 'Akun Nonaktif' }}
                </span>
            </div>
        </header>

        <!-- Mobile Horizontal Tab Switcher -->
        <div class="no-scrollbar flex items-center gap-1.5 overflow-x-auto pb-1 lg:hidden">
            <button
                v-for="item in navItems"
                :key="`mobile-${item.key}`"
                type="button"
                class="inline-flex shrink-0 items-center gap-2 rounded-xl border px-3.5 py-2 text-xs font-semibold transition"
                :class="
                    activePanel === item.key
                        ? 'border-primary-600 bg-primary-700 text-white shadow-xs dark:bg-primary-600'
                        : 'border-surface-200 bg-white text-surface-700 hover:bg-surface-50 dark:border-surface-800 dark:bg-surface-900 dark:text-surface-300'
                "
                @click="openPanel(item.key)"
            >
                <component :is="getIcon(item.icon)" class="h-3.5 w-3.5" />
                <span>{{ item.label }}</span>
            </button>
        </div>

        <!-- Main Layout: Sidebar + Panel -->
        <div class="grid gap-6 lg:grid-cols-[280px_minmax(0,1fr)] lg:items-start">
            <!-- Left Navigation Sidebar -->
            <aside class="hidden space-y-4 lg:sticky lg:top-24 lg:block">
                <!-- User Compact Card -->
                <div
                    class="rounded-3xl border border-surface-200/80 bg-white p-5 shadow-xs dark:border-surface-800 dark:bg-surface-900"
                >
                    <div class="flex items-center gap-3.5">
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
                                {{ user?.email }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center gap-1.5">
                        <span
                            class="inline-flex items-center rounded-full bg-primary-50 px-2.5 py-0.5 text-[10px] font-bold text-primary-800 uppercase dark:bg-primary-950/50 dark:text-primary-300"
                        >
                            {{ roleLabel }}
                        </span>
                        <span
                            class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[10px] font-semibold"
                            :class="
                                account.is_active
                                    ? 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300'
                                    : 'bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-400'
                            "
                        >
                            <span
                                class="h-1.5 w-1.5 rounded-full"
                                :class="account.is_active ? 'bg-emerald-500' : 'bg-surface-400'"
                            />
                            {{ account.is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </div>
                </div>

                <!-- Vertical Tabs Menu -->
                <nav
                    class="rounded-3xl border border-surface-200/80 bg-white p-2 shadow-xs dark:border-surface-800 dark:bg-surface-900"
                >
                    <div class="space-y-1">
                        <button
                            v-for="item in navItems"
                            :key="item.key"
                            type="button"
                            class="group flex w-full items-center gap-3 rounded-2xl px-3.5 py-2.5 text-left text-xs font-semibold transition"
                            :class="
                                activePanel === item.key
                                    ? 'bg-[#0F5132] text-white shadow-xs dark:bg-primary-600'
                                    : 'text-surface-700 hover:bg-surface-100/70 hover:text-surface-950 dark:text-surface-300 dark:hover:bg-surface-800/60 dark:hover:text-surface-50'
                            "
                            @click="openPanel(item.key)"
                        >
                            <component
                                :is="getIcon(item.icon)"
                                class="h-4 w-4 shrink-0"
                                :class="
                                    activePanel === item.key
                                        ? 'text-white'
                                        : 'text-surface-400 group-hover:text-surface-600 dark:text-surface-500 dark:group-hover:text-surface-300'
                                "
                            />
                            <span class="flex-1 truncate">{{ item.label }}</span>
                            <component
                                :is="getIcon('ChevronRight')"
                                class="h-3.5 w-3.5 transition-transform"
                                :class="
                                    activePanel === item.key
                                        ? 'text-white/80 translate-x-0.5'
                                        : 'text-surface-300 dark:text-surface-600'
                                "
                            />
                        </button>
                    </div>
                </nav>

                <!-- Quick Help / Note Box -->
                <div
                    class="rounded-3xl border border-surface-200/80 bg-surface-50/70 p-4.5 text-xs text-surface-600 dark:border-surface-800 dark:bg-surface-900/50 dark:text-surface-400"
                >
                    <div class="flex items-center gap-2 font-semibold text-surface-800 dark:text-surface-200">
                        <component :is="getIcon('HelpCircle')" class="h-4 w-4 text-primary-600 dark:text-primary-400" />
                        <span>Pusat Bantuan</span>
                    </div>
                    <p class="mt-1.5 text-[11px] leading-relaxed text-surface-500 dark:text-surface-400">
                        Tekan <kbd class="rounded border border-surface-300 bg-white px-1 py-0.5 text-[10px] font-semibold dark:border-surface-700 dark:bg-surface-800">?</kbd> kapan saja untuk melihat seluruh pintasan tombol aplikasi.
                    </p>
                </div>
            </aside>

            <!-- Main Panel Content -->
            <main
                class="min-w-0 overflow-hidden rounded-3xl border border-surface-200/80 bg-white shadow-xs dark:border-surface-800 dark:bg-surface-900"
            >
                <!-- Panel Header -->
                <div
                    class="border-b border-surface-200/80 px-5 py-5 sm:px-7 sm:py-6 dark:border-surface-800"
                >
                    <p
                        class="text-[10px] font-bold tracking-[0.16em] text-primary-700 uppercase dark:text-primary-400"
                    >
                        {{ panelMeta.eyebrow }}
                    </p>
                    <h2
                        class="mt-1 text-xl font-semibold tracking-tight text-surface-950 sm:text-2xl dark:text-surface-50"
                    >
                        {{ panelMeta.title }}
                    </h2>
                    <p
                        class="mt-1 text-xs leading-relaxed text-surface-500 sm:text-sm dark:text-surface-400"
                    >
                        {{ panelMeta.description }}
                    </p>
                </div>

                <div class="p-5 sm:p-7">
                    <!-- ============================================== -->
                    <!-- 1. PROFILE PANEL                               -->
                    <!-- ============================================== -->
                    <div v-if="activePanel === 'profile'" class="space-y-6">
                        <!-- User Card -->
                        <div
                            class="flex flex-col gap-4 rounded-2xl border border-surface-200/80 bg-surface-50/50 p-5 sm:flex-row sm:items-center sm:gap-6 dark:border-surface-800 dark:bg-surface-950/30"
                        >
                            <Avatar
                                :src="user?.avatar"
                                :initials="user?.initials"
                                :alt="user?.name"
                                size="lg"
                            />
                            <div class="min-w-0 flex-1">
                                <h3
                                    class="truncate text-lg font-semibold text-surface-950 sm:text-xl dark:text-surface-50"
                                >
                                    {{ user?.name }}
                                </h3>
                                <p
                                    class="truncate text-xs text-surface-600 sm:text-sm dark:text-surface-400"
                                >
                                    {{ user?.email }}
                                </p>
                                <div class="mt-2.5 flex flex-wrap items-center gap-2">
                                    <span
                                        class="inline-flex items-center rounded-full bg-white px-2.5 py-0.5 text-xs font-semibold text-surface-700 ring-1 ring-surface-200 dark:bg-surface-800 dark:text-surface-200 dark:ring-surface-700"
                                    >
                                        {{ roleLabel }}
                                    </span>
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                        :class="
                                            account.is_active
                                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                                                : 'bg-surface-200 text-surface-700 dark:bg-surface-800 dark:text-surface-300'
                                        "
                                    >
                                        <span
                                            class="h-1.5 w-1.5 rounded-full"
                                            :class="account.is_active ? 'bg-emerald-500' : 'bg-surface-400'"
                                        />
                                        {{ account.is_active ? 'Akun Aktif' : 'Akun Nonaktif' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Details Grid -->
                        <div>
                            <h4 class="mb-3 text-xs font-bold tracking-wider text-surface-500 uppercase dark:text-surface-400">
                                Detail Data Akun
                            </h4>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div
                                    v-for="item in accountDetails"
                                    :key="item.label"
                                    class="rounded-2xl border border-surface-200/80 bg-white p-4 transition-colors hover:border-surface-300 dark:border-surface-800 dark:bg-surface-900 dark:hover:border-surface-700"
                                >
                                    <div class="flex items-center gap-2 text-surface-400 dark:text-surface-500">
                                        <component :is="getIcon(item.icon)" class="h-4 w-4" />
                                        <span class="text-xs font-medium text-surface-500 dark:text-surface-400">
                                            {{ item.label }}
                                        </span>
                                    </div>
                                    <p
                                        class="mt-1.5 truncate text-sm font-semibold text-surface-900 dark:text-surface-100"
                                    >
                                        {{ item.value }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Notice Note -->
                        <div
                            class="flex items-start gap-3 rounded-2xl border border-primary-200/70 bg-primary-50/50 p-4 text-xs leading-relaxed text-primary-900 dark:border-primary-800/40 dark:bg-primary-950/20 dark:text-primary-200"
                        >
                            <component
                                :is="getIcon('Info')"
                                class="mt-0.5 h-4 w-4 shrink-0 text-primary-600 dark:text-primary-400"
                            />
                            <div>
                                <p class="font-semibold">Informasi Sinkronisasi Akun</p>
                                <p class="mt-0.5 text-primary-800/90 dark:text-primary-300/80">
                                    Perubahan nama lengkap, alamat email resmi, serta peran pengguna dikelola langsung oleh administrator sistem.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- ============================================== -->
                    <!-- 2. APPEARANCE PANEL (Clean, Simple, Sleek)      -->
                    <!-- ============================================== -->
                    <div v-else-if="activePanel === 'appearance'" class="space-y-6">
                        <div>
                            <h3 class="text-sm font-semibold text-surface-950 dark:text-surface-50">
                                Mode Tampilan
                            </h3>
                            <p class="mt-0.5 text-xs text-surface-500 dark:text-surface-400">
                                Pilih tema warna yang sesuai dengan preferensi kenyamanan mata Anda.
                            </p>
                        </div>

                        <!-- 2 Simple, Elegant Choice Cards -->
                        <div class="grid gap-4 sm:grid-cols-2">
                            <!-- Light Mode Card -->
                            <button
                                type="button"
                                class="relative flex flex-col justify-between rounded-2xl border p-5 text-left transition-all"
                                :class="
                                    activeTheme === 'light'
                                        ? 'border-[#0F5132] bg-primary-50/40 ring-2 ring-[#0F5132]/20 dark:border-primary-500 dark:bg-primary-950/30'
                                        : 'border-surface-200 bg-white hover:border-surface-300 dark:border-surface-800 dark:bg-surface-900 dark:hover:border-surface-700'
                                "
                                @click="selectTheme('light')"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div
                                        class="grid h-11 w-11 place-items-center rounded-xl transition-colors"
                                        :class="
                                            activeTheme === 'light'
                                                ? 'bg-[#0F5132] text-white'
                                                : 'bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-300'
                                        "
                                    >
                                        <component :is="getIcon('Sun')" class="h-5 w-5" />
                                    </div>

                                    <span
                                        v-if="activeTheme === 'light'"
                                        class="inline-flex items-center gap-1 rounded-full bg-[#0F5132] px-2 py-0.5 text-[10px] font-bold text-white shadow-2xs"
                                    >
                                        <component :is="getIcon('Check')" class="h-3 w-3" />
                                        Aktif
                                    </span>
                                </div>

                                <div class="mt-4">
                                    <h4 class="text-sm font-semibold text-surface-950 dark:text-surface-50">
                                        Mode Terang
                                    </h4>
                                    <p class="mt-1 text-xs text-surface-500 dark:text-surface-400">
                                        Tampilan bersih, terang, dan kontras tinggi untuk suasana kerja siang hari.
                                    </p>
                                </div>
                            </button>

                            <!-- Dark Mode Card -->
                            <button
                                type="button"
                                class="relative flex flex-col justify-between rounded-2xl border p-5 text-left transition-all"
                                :class="
                                    activeTheme === 'dark'
                                        ? 'border-primary-500 bg-primary-950/30 ring-2 ring-primary-500/20'
                                        : 'border-surface-200 bg-white hover:border-surface-300 dark:border-surface-800 dark:bg-surface-900 dark:hover:border-surface-700'
                                "
                                @click="selectTheme('dark')"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div
                                        class="grid h-11 w-11 place-items-center rounded-xl transition-colors"
                                        :class="
                                            activeTheme === 'dark'
                                                ? 'bg-primary-600 text-white'
                                                : 'bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-300'
                                        "
                                    >
                                        <component :is="getIcon('Moon')" class="h-5 w-5" />
                                    </div>

                                    <span
                                        v-if="activeTheme === 'dark'"
                                        class="inline-flex items-center gap-1 rounded-full bg-primary-600 px-2 py-0.5 text-[10px] font-bold text-white shadow-2xs"
                                    >
                                        <component :is="getIcon('Check')" class="h-3 w-3" />
                                        Aktif
                                    </span>
                                </div>

                                <div class="mt-4">
                                    <h4 class="text-sm font-semibold text-surface-950 dark:text-surface-50">
                                        Mode Gelap
                                    </h4>
                                    <p class="mt-1 text-xs text-surface-500 dark:text-surface-400">
                                        Tampilan gelap yang ramah di mata untuk kondisi pencahayaan rendah.
                                    </p>
                                </div>
                            </button>
                        </div>

                        <!-- Brief info -->
                        <div
                            class="flex items-center gap-2.5 rounded-2xl border border-surface-200/80 bg-surface-50/50 p-4 text-xs text-surface-600 dark:border-surface-800 dark:bg-surface-950/20 dark:text-surface-400"
                        >
                            <component :is="getIcon('Info')" class="h-4 w-4 shrink-0 text-surface-400" />
                            <span>
                                Tema langsung diterapkan dan tersimpan di perangkat Anda.
                            </span>
                        </div>
                    </div>

                    <!-- ============================================== -->
                    <!-- 3. NOTIFICATIONS PANEL                         -->
                    <!-- ============================================== -->
                    <div v-else-if="activePanel === 'notifications'" class="space-y-6">
                        <!-- Search Box -->
                        <div class="relative">
                            <span
                                class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-surface-400"
                            >
                                <component :is="getIcon('Search')" class="h-4 w-4" />
                            </span>
                            <input
                                v-model="search"
                                type="search"
                                class="input pl-10 text-xs sm:text-sm"
                                placeholder="Cari preferensi notifikasi..."
                                aria-label="Cari preferensi notifikasi"
                            />
                        </div>

                        <!-- Notification Groups -->
                        <div
                            v-for="group in filteredNotificationGroups"
                            :key="group.label"
                            class="space-y-3"
                        >
                            <h4
                                class="text-[10px] font-bold tracking-[0.16em] text-surface-400 uppercase dark:text-surface-500"
                            >
                                {{ group.label }}
                            </h4>

                            <div class="space-y-2.5">
                                <div
                                    v-for="item in group.items"
                                    :key="item.key"
                                    class="flex items-center gap-3.5 rounded-2xl border p-4 transition-colors"
                                    :class="
                                        preferences[item.key]
                                            ? 'border-primary-200/80 bg-primary-50/40 dark:border-primary-800/40 dark:bg-primary-950/20'
                                            : 'border-surface-200/80 bg-white dark:border-surface-800 dark:bg-surface-900'
                                    "
                                >
                                    <span
                                        class="grid h-10 w-10 shrink-0 place-items-center rounded-xl transition-colors"
                                        :class="
                                            preferences[item.key]
                                                ? 'bg-primary-100 text-primary-800 dark:bg-primary-900/60 dark:text-primary-200'
                                                : 'bg-surface-100 text-surface-500 dark:bg-surface-800 dark:text-surface-400'
                                        "
                                    >
                                        <component :is="getIcon(item.icon)" class="h-4.5 w-4.5" />
                                    </span>

                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs font-semibold text-surface-900 sm:text-sm dark:text-surface-100">
                                            {{ item.title }}
                                        </p>
                                        <p class="mt-0.5 text-xs text-surface-500 dark:text-surface-400">
                                            {{ item.description }}
                                        </p>
                                    </div>

                                    <Toggle
                                        v-model="preferences[item.key]"
                                        :label="item.title"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Empty state if search yields no results -->
                        <div
                            v-if="!hasNotificationResults"
                            class="rounded-2xl border border-dashed border-surface-200 p-8 text-center dark:border-surface-800"
                        >
                            <component
                                :is="getIcon('Search')"
                                class="mx-auto h-6 w-6 text-surface-400"
                            />
                            <p class="mt-2 text-sm font-semibold text-surface-800 dark:text-surface-200">
                                Tidak ada notifikasi yang cocok
                            </p>
                            <p class="mt-1 text-xs text-surface-500 dark:text-surface-400">
                                Coba kata kunci lain atau kosongkan kolom pencarian.
                            </p>
                        </div>

                        <!-- Save action -->
                        <div
                            class="flex flex-col gap-3 border-t border-surface-100 pt-5 sm:flex-row sm:items-center sm:justify-between dark:border-surface-800"
                        >
                            <p class="text-xs text-surface-500 dark:text-surface-400">
                                Pengaturan notifikasi aktif di browser ini.
                            </p>
                            <Button
                                variant="primary"
                                class="w-full sm:w-auto"
                                @click="savePreferences"
                            >
                                <component :is="getIcon('Save')" class="h-4 w-4" />
                                <span>Simpan Perubahan</span>
                            </Button>
                        </div>
                    </div>

                    <!-- ============================================== -->
                    <!-- 4. SECURITY PANEL                              -->
                    <!-- ============================================== -->
                    <div v-else-if="activePanel === 'security'" class="space-y-6">
                        <div class="space-y-3">
                            <div
                                v-for="item in securityItems"
                                :key="item.title"
                                class="flex items-start gap-3.5 rounded-2xl border border-surface-200/80 bg-white p-4.5 dark:border-surface-800 dark:bg-surface-900"
                            >
                                <span
                                    class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-300"
                                >
                                    <component :is="getIcon(item.icon)" class="h-4.5 w-4.5" />
                                </span>

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <p class="text-sm font-semibold text-surface-900 dark:text-surface-100">
                                            {{ item.title }}
                                        </p>
                                        <span
                                            class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-bold"
                                            :class="
                                                item.tone === 'emerald'
                                                    ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                                                    : item.tone === 'amber'
                                                      ? 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300'
                                                      : 'bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-300'
                                            "
                                        >
                                            {{ item.status }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-xs leading-relaxed text-surface-500 dark:text-surface-400">
                                        {{ item.description }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- System Security Assurance Note -->
                        <div
                            class="rounded-2xl border border-primary-200/70 bg-primary-50/50 p-4 text-xs text-primary-900 dark:border-primary-800/40 dark:bg-primary-950/20 dark:text-primary-200"
                        >
                            <p class="font-semibold">Perlindungan Akun Aktif</p>
                            <p class="mt-1 text-primary-800/90 dark:text-primary-300/80">
                                Sesi login dilindungi enkripsi standar industri. Hubungi tim administrator jika Anda mencurigai adanya aktivitas tidak sah pada akun Anda.
                            </p>
                        </div>
                    </div>

                    <!-- ============================================== -->
                    <!-- 5. HELP & SHORTCUTS PANEL                      -->
                    <!-- ============================================== -->
                    <div v-else class="space-y-6">
                        <!-- Shortcuts Box -->
                        <div
                            class="rounded-2xl border border-surface-200/80 bg-surface-50/50 p-5 sm:p-6 dark:border-surface-800 dark:bg-surface-950/20"
                        >
                            <div class="flex items-center gap-2.5">
                                <span class="grid h-9 w-9 place-items-center rounded-xl bg-white shadow-2xs dark:bg-surface-800">
                                    <component :is="getIcon('Keyboard')" class="h-4.5 w-4.5 text-primary-600 dark:text-primary-400" />
                                </span>
                                <div>
                                    <h3 class="text-sm font-semibold text-surface-950 dark:text-surface-50">
                                        Pintasan Keyboard Cepat
                                    </h3>
                                    <p class="text-xs text-surface-500 dark:text-surface-400">
                                        Navigasi lebih cepat tanpa menyentuh mouse.
                                    </p>
                                </div>
                            </div>

                            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                                <div class="flex items-center justify-between rounded-xl bg-white p-3 shadow-2xs dark:bg-surface-900">
                                    <span class="text-xs text-surface-700 dark:text-surface-300">Pencarian global</span>
                                    <div class="flex items-center gap-1 font-mono text-[10px]">
                                        <kbd class="rounded border border-surface-200 bg-surface-50 px-1.5 py-0.5 font-bold dark:border-surface-700 dark:bg-surface-800">Ctrl</kbd>
                                        <span class="text-surface-400">+</span>
                                        <kbd class="rounded border border-surface-200 bg-surface-50 px-1.5 py-0.5 font-bold dark:border-surface-700 dark:bg-surface-800">K</kbd>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between rounded-xl bg-white p-3 shadow-2xs dark:bg-surface-900">
                                    <span class="text-xs text-surface-700 dark:text-surface-300">Buka Dashboard</span>
                                    <div class="flex items-center gap-1 font-mono text-[10px]">
                                        <kbd class="rounded border border-surface-200 bg-surface-50 px-1.5 py-0.5 font-bold dark:border-surface-700 dark:bg-surface-800">G</kbd>
                                        <span class="text-surface-400">lalu</span>
                                        <kbd class="rounded border border-surface-200 bg-surface-50 px-1.5 py-0.5 font-bold dark:border-surface-700 dark:bg-surface-800">D</kbd>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between rounded-xl bg-white p-3 shadow-2xs dark:bg-surface-900">
                                    <span class="text-xs text-surface-700 dark:text-surface-300">Buka Daftar Proyek</span>
                                    <div class="flex items-center gap-1 font-mono text-[10px]">
                                        <kbd class="rounded border border-surface-200 bg-surface-50 px-1.5 py-0.5 font-bold dark:border-surface-700 dark:bg-surface-800">G</kbd>
                                        <span class="text-surface-400">lalu</span>
                                        <kbd class="rounded border border-surface-200 bg-surface-50 px-1.5 py-0.5 font-bold dark:border-surface-700 dark:bg-surface-800">P</kbd>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between rounded-xl bg-white p-3 shadow-2xs dark:bg-surface-900">
                                    <span class="text-xs text-surface-700 dark:text-surface-300">Buka Daftar Tugas</span>
                                    <div class="flex items-center gap-1 font-mono text-[10px]">
                                        <kbd class="rounded border border-surface-200 bg-surface-50 px-1.5 py-0.5 font-bold dark:border-surface-700 dark:bg-surface-800">G</kbd>
                                        <span class="text-surface-400">lalu</span>
                                        <kbd class="rounded border border-surface-200 bg-surface-50 px-1.5 py-0.5 font-bold dark:border-surface-700 dark:bg-surface-800">T</kbd>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- FAQ Accordion -->
                        <div>
                            <h3 class="mb-3 text-xs font-bold tracking-wider text-surface-500 uppercase dark:text-surface-400">
                                Pertanyaan yang Sering Diajukan
                            </h3>
                            <div class="space-y-2.5">
                                <div
                                    v-for="(faq, idx) in faqs"
                                    :key="idx"
                                    class="rounded-2xl border border-surface-200/80 bg-white transition-all dark:border-surface-800 dark:bg-surface-900"
                                >
                                    <button
                                        type="button"
                                        class="flex w-full items-center justify-between gap-3 p-4 text-left"
                                        @click="toggleFaq(idx)"
                                    >
                                        <span class="text-xs font-semibold text-surface-900 sm:text-sm dark:text-surface-100">
                                            {{ faq.question }}
                                        </span>
                                        <component
                                            :is="getIcon(openedFaq === idx ? 'ChevronUp' : 'ChevronDown')"
                                            class="h-4 w-4 shrink-0 text-surface-400 transition-transform"
                                        />
                                    </button>
                                    <div
                                        v-show="openedFaq === idx"
                                        class="border-t border-surface-100 px-4 pt-2.5 pb-4 text-xs leading-relaxed text-surface-600 dark:border-surface-800 dark:text-surface-300"
                                    >
                                        {{ faq.answer }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Return to dashboard -->
                        <div class="pt-2">
                            <Link
                                href="/dashboard"
                                class="btn btn-secondary w-full sm:w-auto"
                            >
                                <component :is="getIcon('ArrowLeft')" class="h-4 w-4" />
                                <span>Kembali ke Dashboard</span>
                            </Link>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>
