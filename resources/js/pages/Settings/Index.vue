<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import settingsRoutes from '@/routes/settings';
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

interface AccountInfo {
    name: string;
    email: string;
    is_active: boolean;
    email_verified: boolean;
    two_factor_enabled: boolean;
    passkeys_count: number;
    member_since: string | null;
    roles: string[];
}

interface NotificationOption {
    label: string;
    description: string;
    group: string;
    icon: string;
}

type PanelKey =
    | 'profile'
    | 'appearance'
    | 'notifications'
    | 'security'
    | 'help';

interface NavItem {
    key: PanelKey;
    label: string;
    icon: string;
    description: string;
}

const props = defineProps<{
    account: AccountInfo;
    notification_preferences: Record<string, boolean>;
    notification_options: Record<string, NotificationOption>;
}>();

const page = usePage();
const { currentTheme, resolvedTheme, setTheme } = useTheme();
const toast = useToast();

const activePanel = ref<PanelKey>('profile');
const copiedValue = ref<string | null>(null);

const user = computed(() => page.props.auth?.user);

const navItems: NavItem[] = [
    {
        key: 'profile',
        label: 'Profil Akun',
        icon: 'User',
        description: 'Identitas dan data akun',
    },
    {
        key: 'appearance',
        label: 'Tampilan',
        icon: 'Palette',
        description: 'Tema terang, gelap, atau sistem',
    },
    {
        key: 'notifications',
        label: 'Notifikasi',
        icon: 'Bell',
        description: 'Preferensi pemberitahuan',
    },
    {
        key: 'security',
        label: 'Keamanan',
        icon: 'Shield',
        description: 'Kata sandi dan perlindungan akun',
    },
    {
        key: 'help',
        label: 'Bantuan & Pintasan',
        icon: 'HelpCircle',
        description: 'Panduan dan pintasan keyboard',
    },
];

const panelMeta = computed(() => {
    switch (activePanel.value) {
        case 'profile':
            return {
                eyebrow: 'Pengaturan Akun',
                title: 'Profil Pengguna',
                description:
                    'Perbarui nama dan alamat email yang digunakan untuk masuk ke ruang kerja.',
            };
        case 'appearance':
            return {
                eyebrow: 'Preferensi Visual',
                title: 'Tampilan Aplikasi',
                description:
                    'Pilih tema yang nyaman untuk mata Anda. Preferensi ini tersimpan di perangkat ini.',
            };
        case 'notifications':
            return {
                eyebrow: 'Preferensi Pemberitahuan',
                title: 'Pusat Notifikasi',
                description:
                    'Atur jenis pemberitahuan yang ingin Anda terima. Pilihan disimpan ke akun Anda, bukan hanya browser.',
            };
        case 'security':
            return {
                eyebrow: 'Perlindungan Akun',
                title: 'Keamanan & Kata Sandi',
                description:
                    'Perbarui kata sandi dan tinjau lapisan perlindungan yang aktif pada akun Anda.',
            };
        default:
            return {
                eyebrow: 'Pusat Bantuan',
                title: 'Bantuan & Pintasan Keyboard',
                description:
                    'Daftar pintasan yang benar-benar aktif dan panduan singkat penggunaan akun.',
            };
    }
});

const roleDisplayNames: Record<string, string> = {
    administrator: 'Administrator',
    project_manager: 'Manajer Proyek',
    team_leader: 'Ketua Tim',
    member: 'Anggota',
    reviewer: 'Peninjau',
    client: 'Klien',
};

const roleLabel = computed(
    () =>
        props.account.roles
            ?.map((role) => roleDisplayNames[role] ?? role)
            .join(', ') || 'Anggota ruang kerja',
);

const formattedMemberSince = computed(() => {
    if (!props.account.member_since) {
        return '—';
    }

    return new Date(props.account.member_since).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
});

const profileForm = useForm({
    name: props.account.name,
    email: props.account.email,
});

const emailChanged = computed(
    () => profileForm.email.trim().toLowerCase() !== props.account.email,
);

const profileDirty = computed(
    () =>
        profileForm.name.trim() !== props.account.name ||
        profileForm.email.trim().toLowerCase() !== props.account.email,
);

watch(
    () => props.account.name,
    (name) => {
        if (!profileForm.processing) {
            profileForm.name = name;
        }
    },
);

watch(
    () => props.account.email,
    (email) => {
        if (!profileForm.processing) {
            profileForm.email = email;
        }
    },
);

function submitProfile(): void {
    profileForm.put(settingsRoutes.profile.update.url(), {
        preserveScroll: true,
        onSuccess: () => {
            toast.success(
                'Profil Diperbarui',
                'Perubahan data profil berhasil disimpan.',
            );
        },
        onError: () => {
            toast.error(
                'Gagal Menyimpan Profil',
                'Periksa kembali data yang Anda masukkan.',
            );
        },
    });
}

/* ------------------------------------------------------------------ */
/* Appearance                                                          */
/* ------------------------------------------------------------------ */

const themeOptions = computed(() => [
    {
        value: 'light' as const,
        label: 'Mode Terang',
        description:
            'Latar terang dengan kontras tinggi, cocok untuk ruangan bercahaya.',
        icon: 'Sun',
        active: currentTheme.value === 'light',
    },
    {
        value: 'dark' as const,
        label: 'Mode Gelap',
        description:
            'Latar gelap yang lembut, nyaman untuk bekerja di malam hari.',
        icon: 'Moon',
        active: currentTheme.value === 'dark',
    },
    {
        value: 'system' as const,
        label: 'Ikuti Sistem',
        description:
            'Tema menyesuaikan pengaturan perangkat Anda secara otomatis.',
        icon: 'Monitor',
        active: currentTheme.value === 'system',
    },
]);

function selectTheme(theme: 'light' | 'dark' | 'system'): void {
    setTheme(theme);
    toast.success(
        'Tema Diperbarui',
        `Tampilan beralih ke ${theme === 'dark' ? 'mode gelap' : theme === 'light' ? 'mode terang' : 'tema sistem'}.`,
    );
}

const activeThemeLabel = computed(() =>
    currentTheme.value === 'system'
        ? `Sistem (${resolvedTheme.value === 'dark' ? 'gelap' : 'terang'})`
        : currentTheme.value === 'dark'
          ? 'Gelap'
          : 'Terang',
);

/* ------------------------------------------------------------------ */
/* Notifications                                                       */
/* ------------------------------------------------------------------ */

const fallbackNotificationOptions: Record<string, NotificationOption> = {
    task_updates: {
        label: 'Pembaruan tugas',
        description: 'Kabar saat status, tenggat, atau detail tugas berubah.',
        group: 'Aktivitas Utama',
        icon: 'Bell',
    },
    deadline_reminders: {
        label: 'Pengingat tenggat waktu',
        description: 'Peringatan saat tugas mendekati batas akhir pengerjaan.',
        group: 'Aktivitas Utama',
        icon: 'Clock',
    },
    daily_digest: {
        label: 'Ringkasan harian',
        description: 'Rangkuman progres pekerjaan harian di ruang kerja.',
        group: 'Aktivitas Utama',
        icon: 'FileText',
    },
    mentions: {
        label: 'Sebutan (@mention)',
        description:
            'Pemberitahuan saat rekan tim menandai atau membalas Anda.',
        group: 'Interaksi & Suara',
        icon: 'AtSign',
    },
    comments: {
        label: 'Aktivitas kolaborasi tim',
        description:
            'Diskusi dan aktivitas terbaru pada proyek yang Anda ikuti.',
        group: 'Interaksi & Suara',
        icon: 'MessageSquare',
    },
    sound: {
        label: 'Bunyi notifikasi',
        description: 'Mainkan suara lembut saat pemberitahuan baru diterima.',
        group: 'Interaksi & Suara',
        icon: 'Volume2',
    },
};

const notificationOptions = computed<Record<string, NotificationOption>>(() =>
    Object.keys(props.notification_options ?? {}).length > 0
        ? props.notification_options
        : fallbackNotificationOptions,
);

const defaultNotificationValues = computed<Record<string, boolean>>(() => {
    const values: Record<string, boolean> = {};

    for (const key of Object.keys(notificationOptions.value)) {
        values[key] = props.notification_preferences?.[key] ?? true;
    }

    return values;
});

const notificationBaseline = ref<Record<string, boolean>>({
    ...defaultNotificationValues.value,
});

const notificationForm = useForm({
    preferences: { ...defaultNotificationValues.value },
});

watch(
    () => props.notification_preferences,
    () => {
        const values = { ...defaultNotificationValues.value };
        notificationBaseline.value = values;
        notificationForm.preferences = { ...values };
    },
    { deep: true },
);

const notificationGroups = computed(() => {
    const groups = new Map<
        string,
        Array<{ key: string; option: NotificationOption }>
    >();

    for (const [key, option] of Object.entries(notificationOptions.value)) {
        const group = option.group || 'Lainnya';
        const items = groups.get(group) ?? [];
        items.push({ key, option });
        groups.set(group, items);
    }

    return Array.from(groups.entries()).map(([label, items]) => ({
        label,
        items,
    }));
});

const hasNotificationChanges = computed(
    () =>
        JSON.stringify(notificationForm.preferences) !==
        JSON.stringify(notificationBaseline.value),
);

const enabledNotificationCount = computed(
    () => Object.values(notificationForm.preferences).filter(Boolean).length,
);

const totalNotificationCount = computed(
    () => Object.keys(notificationForm.preferences).length,
);

function submitNotifications(): void {
    notificationForm.put(settingsRoutes.notifications.update.url(), {
        preserveScroll: true,
        onSuccess: () => {
            notificationBaseline.value = {
                ...notificationForm.preferences,
            };
            toast.success(
                'Preferensi Disimpan',
                'Pengaturan notifikasi berhasil diperbarui di akun Anda.',
            );
        },
        onError: () => {
            toast.error(
                'Gagal Menyimpan',
                'Preferensi notifikasi tidak dapat disimpan. Coba lagi.',
            );
        },
    });
}

function resetNotificationChanges(): void {
    notificationForm.preferences = { ...notificationBaseline.value };
}

/* ------------------------------------------------------------------ */
/* Security                                                            */
/* ------------------------------------------------------------------ */

const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const showCurrentPassword = ref(false);
const showNewPassword = ref(false);
const showConfirmPassword = ref(false);

const passwordStrength = computed(() => {
    const value = passwordForm.password;
    let score = 0;

    if (value.length >= 8) score += 1;
    if (value.length >= 12) score += 1;
    if (/[A-Z]/.test(value) && /[a-z]/.test(value)) score += 1;
    if (/\d/.test(value)) score += 1;
    if (/[^A-Za-z0-9]/.test(value)) score += 1;

    return Math.min(score, 4);
});

const passwordStrengthLabel = computed(() => {
    switch (passwordStrength.value) {
        case 0:
            return 'Belum diisi';
        case 1:
            return 'Lemah';
        case 2:
            return 'Cukup';
        case 3:
            return 'Kuat';
        default:
            return 'Sangat kuat';
    }
});

const passwordStrengthColor = computed(() => {
    switch (passwordStrength.value) {
        case 1:
            return 'bg-rose-500';
        case 2:
            return 'bg-amber-500';
        case 3:
            return 'bg-primary-500';
        case 4:
            return 'bg-emerald-500';
        default:
            return 'bg-surface-200 dark:bg-surface-700';
    }
});

function submitPassword(): void {
    passwordForm.put(settingsRoutes.password.update.url(), {
        preserveScroll: true,
        onSuccess: () => {
            passwordForm.reset();
            showCurrentPassword.value = false;
            showNewPassword.value = false;
            showConfirmPassword.value = false;
            toast.success(
                'Kata Sandi Diperbarui',
                'Kata sandi baru langsung berlaku untuk sesi berikutnya.',
            );
        },
        onError: () => {
            toast.error(
                'Gagal Mengubah Kata Sandi',
                'Periksa kata sandi saat ini dan konfirmasi kata sandi baru.',
            );
        },
    });
}

const securityStatus = computed(() => [
    {
        icon: 'Lock',
        title: 'Kata Sandi',
        description:
            'Gunakan kata sandi yang kuat dan unik. Anda dapat menggantinya kapan saja di halaman ini.',
        status: 'Dapat diubah',
        tone: 'emerald' as const,
    },
    {
        icon: 'Mail',
        title: 'Verifikasi Email',
        description: props.account.email_verified
            ? 'Alamat email akun Anda sudah tercatat sebagai terverifikasi.'
            : 'Email belum diverifikasi. Hubungi administrator ruang kerja untuk proses verifikasi.',
        status: props.account.email_verified
            ? 'Terverifikasi'
            : 'Belum terverifikasi',
        tone: props.account.email_verified
            ? ('emerald' as const)
            : ('amber' as const),
    },
    {
        icon: 'Shield',
        title: 'Autentikasi Dua Faktor (2FA)',
        description: props.account.two_factor_enabled
            ? 'Akun Anda sudah dikonfigurasi dengan autentikasi dua faktor.'
            : 'Pendaftaran 2FA belum tersedia dari antarmuka ini. Fitur tidak diaktifkan agar Anda tidak terkunci dari akun.',
        status: props.account.two_factor_enabled ? 'Aktif' : 'Belum tersedia',
        tone: props.account.two_factor_enabled
            ? ('emerald' as const)
            : ('surface' as const),
    },
    {
        icon: 'UserCheck',
        title: 'Passkey',
        description:
            props.account.passkeys_count > 0
                ? `${props.account.passkeys_count} passkey terdaftar pada akun ini.`
                : 'Belum ada passkey. Pendaftaran passkey belum tersedia dari antarmuka ini.',
        status:
            props.account.passkeys_count > 0
                ? `${props.account.passkeys_count} terdaftar`
                : 'Belum ada',
        tone:
            props.account.passkeys_count > 0
                ? ('emerald' as const)
                : ('surface' as const),
    },
]);

/* ------------------------------------------------------------------ */
/* Help                                                                */
/* ------------------------------------------------------------------ */

const shortcuts = [
    {
        keys: ['Ctrl', 'K'],
        label: 'Fokus pencarian',
        description: 'Langsung menuju kolom pencarian di halaman aktif.',
    },
    {
        keys: ['G', 'D'],
        label: 'Dashboard',
        description: 'Membuka ringkasan ruang kerja.',
    },
    {
        keys: ['G', 'P'],
        label: 'Daftar proyek',
        description: 'Membuka halaman seluruh proyek.',
    },
    {
        keys: ['G', 'T'],
        label: 'Papan tugas',
        description: 'Membuka daftar dan papan tugas.',
    },
    {
        keys: ['G', 'C'],
        label: 'Kalender kerja',
        description: 'Membuka kalender tenggat dan jadwal.',
    },
    {
        keys: ['G', 'S'],
        label: 'Pengaturan',
        description: 'Membuka halaman pengaturan akun ini.',
    },
    {
        keys: ['G', 'I'],
        label: 'Integrasi API',
        description: 'Membuka pusat integrasi dan webhook.',
    },
    {
        keys: ['G', 'H'],
        label: 'Pusat bantuan',
        description: 'Membuka halaman dukungan dan panduan.',
    },
];

const faqs = [
    {
        question: 'Apakah perubahan notifikasi berlaku di semua perangkat?',
        answer: 'Ya. Preferensi notifikasi kini disimpan pada akun Anda di server, sehingga pilihan yang sama digunakan ketika Anda masuk dari perangkat lain.',
    },
    {
        question: 'Apa yang terjadi saat saya mengubah alamat email?',
        answer: 'Alamat email pada akun langsung diperbarui dan digunakan untuk proses masuk berikutnya. Pastikan alamat tersebut aktif karena semua pemberitahuan akun akan dikirim ke sana.',
    },
    {
        question: 'Apakah nama pengguna bisa diubah sendiri?',
        answer: 'Bisa. Gunakan formulir Profil Akun di halaman ini, lalu tekan Simpan Perubahan. Perubahan langsung tersimpan pada akun Anda.',
    },
    {
        question: 'Mengapa tombol pendaftaran 2FA atau passkey tidak ada?',
        answer: 'Halaman tantangan autentikasi belum tersedia di aplikasi ini. Agar akun tidak terkunci, pendaftaran 2FA dan passkey sengaja tidak disediakan sampai alur tersebut selesai dibangun.',
    },
];

const openedFaq = ref<number | null>(0);

function toggleFaq(index: number): void {
    openedFaq.value = openedFaq.value === index ? null : index;
}

function openPanel(panel: PanelKey): void {
    activePanel.value = panel;
}

async function copyText(value: string, label: string): Promise<void> {
    try {
        await navigator.clipboard.writeText(value);
        copiedValue.value = label;
        toast.success('Tersalin', `${label} disalin ke clipboard.`);
        setTimeout(() => {
            if (copiedValue.value === label) {
                copiedValue.value = null;
            }
        }, 2500);
    } catch {
        toast.error('Gagal Menyalin', 'Silakan salin teks secara manual.');
    }
}

onMounted(() => {
    const flash = page.props.flash as unknown as
        | { success?: string | null; error?: string | null }
        | undefined;

    if (flash?.success) {
        toast.success('Berhasil', flash.success);
    }

    if (flash?.error) {
        toast.error('Gagal', flash.error);
    }
});
</script>

<template>
    <div class="space-y-6 pb-16">
        <!-- Header -->
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
                    Kelola profil, keamanan, tampilan, dan notifikasi akun Anda
                    dalam satu tempat.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <span
                    class="inline-flex items-center gap-2 rounded-xl border border-surface-200/80 bg-white px-3 py-1.5 text-xs font-semibold text-surface-700 shadow-2xs dark:border-surface-800 dark:bg-surface-900 dark:text-surface-200"
                >
                    <span
                        class="h-2 w-2 rounded-full"
                        :class="
                            account.is_active
                                ? 'bg-emerald-500'
                                : 'bg-surface-400'
                        "
                    />
                    {{ account.is_active ? 'Akun Aktif' : 'Akun Nonaktif' }}
                </span>
                <span
                    class="inline-flex items-center gap-2 rounded-xl border border-surface-200/80 bg-white px-3 py-1.5 text-xs font-semibold text-surface-700 shadow-2xs dark:border-surface-800 dark:bg-surface-900 dark:text-surface-200"
                >
                    <component
                        :is="getIcon('ShieldCheck')"
                        class="h-3.5 w-3.5 text-surface-400"
                    />
                    {{ roleLabel }}
                </span>
            </div>
        </header>

        <!-- Mobile nav -->
        <div
            class="no-scrollbar flex items-center gap-1.5 overflow-x-auto pb-1 lg:hidden"
        >
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

        <div
            class="grid gap-6 lg:grid-cols-[290px_minmax(0,1fr)] lg:items-start"
        >
            <!-- Sidebar -->
            <aside class="hidden space-y-4 lg:sticky lg:top-24 lg:block">
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
                                {{ account.name }}
                            </p>
                            <p
                                class="truncate text-xs text-surface-500 dark:text-surface-400"
                            >
                                {{ account.email }}
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
                                :class="
                                    account.is_active
                                        ? 'bg-emerald-500'
                                        : 'bg-surface-400'
                                "
                            />
                            {{ account.is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </div>

                    <dl
                        class="mt-4 space-y-2 border-t border-surface-100 pt-4 text-[11px] dark:border-surface-800"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <dt class="text-surface-500 dark:text-surface-400">
                                Bergabung
                            </dt>
                            <dd
                                class="font-semibold text-surface-800 dark:text-surface-200"
                            >
                                {{ formattedMemberSince }}
                            </dd>
                        </div>
                        <div class="flex items-center justify-between gap-2">
                            <dt class="text-surface-500 dark:text-surface-400">
                                ID akun
                            </dt>
                            <dd class="flex items-center gap-1">
                                <span
                                    class="font-mono font-semibold text-surface-800 dark:text-surface-200"
                                >
                                    #{{ user?.id }}
                                </span>
                                <button
                                    type="button"
                                    class="rounded-md p-1 text-surface-400 transition hover:bg-surface-100 hover:text-surface-700 dark:hover:bg-surface-800 dark:hover:text-surface-200"
                                    aria-label="Salin ID akun"
                                    @click="
                                        copyText(
                                            String(user?.id ?? ''),
                                            'ID akun',
                                        )
                                    "
                                >
                                    <component
                                        :is="
                                            getIcon(
                                                copiedValue === 'ID akun'
                                                    ? 'Check'
                                                    : 'Copy',
                                            )
                                        "
                                        class="h-3 w-3"
                                    />
                                </button>
                            </dd>
                        </div>
                    </dl>
                </div>

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
                            <span class="min-w-0 flex-1">
                                <span class="block truncate">{{
                                    item.label
                                }}</span>
                                <span
                                    class="mt-0.5 block truncate text-[10px] font-normal"
                                    :class="
                                        activePanel === item.key
                                            ? 'text-white/70'
                                            : 'text-surface-400 dark:text-surface-500'
                                    "
                                >
                                    {{ item.description }}
                                </span>
                            </span>
                            <component
                                :is="getIcon('ChevronRight')"
                                class="h-3.5 w-3.5 transition-transform"
                                :class="
                                    activePanel === item.key
                                        ? 'translate-x-0.5 text-white/80'
                                        : 'text-surface-300 dark:text-surface-600'
                                "
                            />
                        </button>
                    </div>
                </nav>

                <div
                    class="rounded-3xl border border-surface-200/80 bg-surface-50/70 p-4 text-xs text-surface-600 dark:border-surface-800 dark:bg-surface-900/50 dark:text-surface-400"
                >
                    <div
                        class="flex items-center gap-2 font-semibold text-surface-800 dark:text-surface-200"
                    >
                        <component
                            :is="getIcon('HelpCircle')"
                            class="h-4 w-4 text-primary-600 dark:text-primary-400"
                        />
                        <span>Butuh bantuan?</span>
                    </div>
                    <p
                        class="mt-1.5 text-[11px] leading-relaxed text-surface-500 dark:text-surface-400"
                    >
                        Buka halaman Bantuan & Pintasan untuk melihat panduan
                        lengkap penggunaan aplikasi.
                    </p>
                    <button
                        type="button"
                        class="mt-3 inline-flex items-center gap-1 text-[11px] font-semibold text-primary-700 hover:underline dark:text-primary-300"
                        @click="openPanel('help')"
                    >
                        Lihat panduan
                        <component
                            :is="getIcon('ArrowRight')"
                            class="h-3 w-3"
                        />
                    </button>
                </div>
            </aside>

            <!-- Main panel -->
            <main
                class="min-w-0 overflow-hidden rounded-3xl border border-surface-200/80 bg-white shadow-xs dark:border-surface-800 dark:bg-surface-900"
            >
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
                        class="mt-1 max-w-2xl text-xs leading-relaxed text-surface-500 sm:text-sm dark:text-surface-400"
                    >
                        {{ panelMeta.description }}
                    </p>
                </div>

                <div class="p-5 sm:p-7">
                    <!-- PROFILE -->
                    <div v-if="activePanel === 'profile'" class="space-y-6">
                        <div
                            class="flex flex-col gap-4 rounded-2xl border border-surface-200/80 bg-surface-50/50 p-5 sm:flex-row sm:items-center dark:border-surface-800 dark:bg-surface-950/30"
                        >
                            <Avatar
                                :src="user?.avatar"
                                :initials="user?.initials"
                                :alt="account.name"
                                size="lg"
                            />
                            <div class="min-w-0 flex-1">
                                <h3
                                    class="truncate text-lg font-semibold text-surface-950 dark:text-surface-50"
                                >
                                    {{ account.name }}
                                </h3>
                                <p
                                    class="truncate text-xs text-surface-600 sm:text-sm dark:text-surface-400"
                                >
                                    {{ account.email }}
                                </p>
                                <div
                                    class="mt-2 flex flex-wrap items-center gap-2"
                                >
                                    <span
                                        class="inline-flex items-center rounded-full bg-white px-2.5 py-0.5 text-xs font-semibold text-surface-700 ring-1 ring-surface-200 dark:bg-surface-800 dark:text-surface-200 dark:ring-surface-700"
                                    >
                                        {{ roleLabel }}
                                    </span>
                                    <span
                                        class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                        :class="
                                            account.email_verified
                                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                                                : 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300'
                                        "
                                    >
                                        <component
                                            :is="
                                                getIcon(
                                                    account.email_verified
                                                        ? 'CheckCircle2'
                                                        : 'AlertCircle',
                                                )
                                            "
                                            class="h-3 w-3"
                                        />
                                        {{
                                            account.email_verified
                                                ? 'Email terverifikasi'
                                                : 'Email belum terverifikasi'
                                        }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <form class="space-y-5" @submit.prevent="submitProfile">
                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label
                                        for="profile-name"
                                        class="mb-1.5 block text-xs font-semibold text-surface-700 dark:text-surface-200"
                                    >
                                        Nama Lengkap
                                        <span class="text-rose-500">*</span>
                                    </label>
                                    <input
                                        id="profile-name"
                                        v-model="profileForm.name"
                                        type="text"
                                        required
                                        autocomplete="name"
                                        class="input"
                                        placeholder="Nama lengkap Anda"
                                    />
                                    <p
                                        v-if="profileForm.errors.name"
                                        class="mt-1 text-[11px] font-medium text-rose-600 dark:text-rose-400"
                                    >
                                        {{ profileForm.errors.name }}
                                    </p>
                                </div>

                                <div>
                                    <label
                                        for="profile-email"
                                        class="mb-1.5 block text-xs font-semibold text-surface-700 dark:text-surface-200"
                                    >
                                        Alamat Email
                                        <span class="text-rose-500">*</span>
                                    </label>
                                    <input
                                        id="profile-email"
                                        v-model="profileForm.email"
                                        type="email"
                                        required
                                        autocomplete="email"
                                        class="input"
                                        placeholder="nama@perusahaan.com"
                                    />
                                    <p
                                        v-if="profileForm.errors.email"
                                        class="mt-1 text-[11px] font-medium text-rose-600 dark:text-rose-400"
                                    >
                                        {{ profileForm.errors.email }}
                                    </p>
                                </div>
                            </div>

                            <div
                                v-if="emailChanged"
                                class="flex items-start gap-3 rounded-2xl border border-primary-200/70 bg-primary-50/60 p-4 text-xs text-primary-900 dark:border-primary-800/40 dark:bg-primary-950/20 dark:text-primary-200"
                            >
                                <component
                                    :is="getIcon('Info')"
                                    class="mt-0.5 h-4 w-4 shrink-0"
                                />
                                <div>
                                    <p class="font-semibold">
                                        Alamat email akan diperbarui
                                    </p>
                                    <p
                                        class="mt-0.5 leading-relaxed opacity-90"
                                    >
                                        Pastikan alamat baru aktif. Email ini
                                        akan digunakan untuk masuk dan menerima
                                        pemberitahuan akun.
                                    </p>
                                </div>
                            </div>

                            <div
                                class="flex flex-col gap-3 border-t border-surface-100 pt-5 sm:flex-row sm:items-center sm:justify-between dark:border-surface-800"
                            >
                                <p
                                    class="text-[11px] text-surface-500 dark:text-surface-400"
                                >
                                    Perubahan langsung tersimpan ke akun Anda
                                    setelah tombol ditekan.
                                </p>
                                <div class="flex items-center gap-2">
                                    <Button
                                        v-if="profileDirty"
                                        type="button"
                                        variant="secondary"
                                        size="sm"
                                        :disabled="profileForm.processing"
                                        @click="
                                            profileForm.reset();
                                            profileForm.clearErrors();
                                        "
                                    >
                                        Batalkan
                                    </Button>
                                    <Button
                                        type="submit"
                                        variant="primary"
                                        size="sm"
                                        :loading="profileForm.processing"
                                        :disabled="!profileDirty"
                                    >
                                        <component
                                            :is="getIcon('Save')"
                                            class="h-3.5 w-3.5"
                                        />
                                        Simpan Profil
                                    </Button>
                                </div>
                            </div>
                        </form>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div
                                class="rounded-2xl border border-surface-200/80 bg-white p-4 dark:border-surface-800 dark:bg-surface-900"
                            >
                                <div
                                    class="flex items-center gap-2 text-surface-400 dark:text-surface-500"
                                >
                                    <component
                                        :is="getIcon('Shield')"
                                        class="h-4 w-4"
                                    />
                                    <span
                                        class="text-xs font-medium text-surface-500 dark:text-surface-400"
                                    >
                                        Peran ruang kerja
                                    </span>
                                </div>
                                <p
                                    class="mt-1.5 text-sm font-semibold text-surface-900 dark:text-surface-100"
                                >
                                    {{ roleLabel }}
                                </p>
                            </div>
                            <div
                                class="rounded-2xl border border-surface-200/80 bg-white p-4 dark:border-surface-800 dark:bg-surface-900"
                            >
                                <div
                                    class="flex items-center gap-2 text-surface-400 dark:text-surface-500"
                                >
                                    <component
                                        :is="getIcon('Calendar')"
                                        class="h-4 w-4"
                                    />
                                    <span
                                        class="text-xs font-medium text-surface-500 dark:text-surface-400"
                                    >
                                        Terdaftar sejak
                                    </span>
                                </div>
                                <p
                                    class="mt-1.5 text-sm font-semibold text-surface-900 dark:text-surface-100"
                                >
                                    {{ formattedMemberSince }}
                                </p>
                            </div>
                        </div>

                        <div
                            class="flex items-start gap-3 rounded-2xl border border-primary-200/70 bg-primary-50/50 p-4 text-xs leading-relaxed text-primary-900 dark:border-primary-800/40 dark:bg-primary-950/20 dark:text-primary-200"
                        >
                            <component
                                :is="getIcon('Info')"
                                class="mt-0.5 h-4 w-4 shrink-0 text-primary-600 dark:text-primary-400"
                            />
                            <div>
                                <p class="font-semibold">
                                    Foto profil dan peran
                                </p>
                                <p
                                    class="mt-0.5 text-primary-800/90 dark:text-primary-300/80"
                                >
                                    Foto profil menggunakan inisial nama Anda.
                                    Perubahan peran pengguna hanya dapat
                                    dilakukan oleh administrator melalui halaman
                                    Role & Hak Akses.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- APPEARANCE -->
                    <div
                        v-else-if="activePanel === 'appearance'"
                        class="space-y-6"
                    >
                        <div>
                            <h3
                                class="text-sm font-semibold text-surface-950 dark:text-surface-50"
                            >
                                Mode Tampilan
                            </h3>
                            <p
                                class="mt-0.5 text-xs text-surface-500 dark:text-surface-400"
                            >
                                Tema diterapkan seketika dan tersimpan di
                                perangkat ini melalui preferensi browser.
                            </p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <button
                                v-for="option in themeOptions"
                                :key="option.value"
                                type="button"
                                class="relative flex flex-col justify-between rounded-2xl border p-5 text-left transition-all"
                                :class="
                                    option.active
                                        ? 'border-[#0F5132] bg-primary-50/40 ring-2 ring-[#0F5132]/20 dark:border-primary-500 dark:bg-primary-950/30'
                                        : 'border-surface-200 bg-white hover:border-surface-300 dark:border-surface-800 dark:bg-surface-900 dark:hover:border-surface-700'
                                "
                                :aria-pressed="option.active"
                                @click="selectTheme(option.value)"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div
                                        class="grid h-11 w-11 place-items-center rounded-xl transition-colors"
                                        :class="
                                            option.active
                                                ? 'bg-[#0F5132] text-white'
                                                : 'bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-300'
                                        "
                                    >
                                        <component
                                            :is="getIcon(option.icon)"
                                            class="h-5 w-5"
                                        />
                                    </div>
                                    <span
                                        v-if="option.active"
                                        class="inline-flex items-center gap-1 rounded-full bg-[#0F5132] px-2 py-0.5 text-[10px] font-bold text-white dark:bg-primary-500 dark:text-surface-950"
                                    >
                                        <component
                                            :is="getIcon('Check')"
                                            class="h-3 w-3"
                                        />
                                        Aktif
                                    </span>
                                </div>
                                <div class="mt-4">
                                    <h4
                                        class="text-sm font-semibold text-surface-950 dark:text-surface-50"
                                    >
                                        {{ option.label }}
                                    </h4>
                                    <p
                                        class="mt-1 text-xs leading-relaxed text-surface-500 dark:text-surface-400"
                                    >
                                        {{ option.description }}
                                    </p>
                                </div>
                            </button>
                        </div>

                        <div
                            class="flex items-center justify-between gap-3 rounded-2xl border border-surface-200/80 bg-surface-50/50 p-4 text-xs dark:border-surface-800 dark:bg-surface-950/20"
                        >
                            <div class="flex items-center gap-2.5">
                                <span
                                    class="grid h-8 w-8 place-items-center rounded-lg bg-white text-primary-700 shadow-2xs dark:bg-surface-800 dark:text-primary-300"
                                >
                                    <component
                                        :is="getIcon('Palette')"
                                        class="h-4 w-4"
                                    />
                                </span>
                                <div>
                                    <p
                                        class="font-semibold text-surface-800 dark:text-surface-200"
                                    >
                                        Tema aktif
                                    </p>
                                    <p
                                        class="text-surface-500 dark:text-surface-400"
                                    >
                                        {{ activeThemeLabel }}
                                    </p>
                                </div>
                            </div>
                            <span
                                class="rounded-full bg-white px-2.5 py-1 text-[10px] font-semibold text-surface-600 ring-1 ring-surface-200 dark:bg-surface-800 dark:text-surface-300 dark:ring-surface-700"
                            >
                                Tersimpan lokal
                            </span>
                        </div>
                    </div>

                    <!-- NOTIFICATIONS -->
                    <div
                        v-else-if="activePanel === 'notifications'"
                        class="space-y-6"
                    >
                        <div
                            class="flex flex-col gap-3 rounded-2xl border border-surface-200/80 bg-surface-50/50 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-surface-800 dark:bg-surface-950/20"
                        >
                            <div class="flex items-center gap-3">
                                <span
                                    class="grid h-9 w-9 place-items-center rounded-xl bg-white text-primary-700 shadow-2xs dark:bg-surface-800 dark:text-primary-300"
                                >
                                    <component
                                        :is="getIcon('BellRing')"
                                        class="h-4 w-4"
                                    />
                                </span>
                                <div>
                                    <p
                                        class="text-xs font-semibold text-surface-900 dark:text-surface-100"
                                    >
                                        {{ enabledNotificationCount }} dari
                                        {{ totalNotificationCount }}
                                        notifikasi aktif
                                    </p>
                                    <p
                                        class="text-[11px] text-surface-500 dark:text-surface-400"
                                    >
                                        Preferensi tersimpan di akun Anda.
                                    </p>
                                </div>
                            </div>
                            <span
                                v-if="hasNotificationChanges"
                                class="inline-flex w-fit items-center gap-1.5 rounded-full border border-amber-200/80 bg-amber-50 px-2.5 py-1 text-[10px] font-bold text-amber-800 dark:border-amber-800/40 dark:bg-amber-950/30 dark:text-amber-300"
                            >
                                <span
                                    class="h-1.5 w-1.5 animate-pulse rounded-full bg-amber-500"
                                />
                                Ada perubahan belum disimpan
                            </span>
                            <span
                                v-else
                                class="inline-flex w-fit items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-[10px] font-bold text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-300"
                            >
                                <component
                                    :is="getIcon('Check')"
                                    class="h-3 w-3"
                                />
                                Tersimpan
                            </span>
                        </div>

                        <div class="space-y-5">
                            <section
                                v-for="group in notificationGroups"
                                :key="group.label"
                                class="space-y-2.5"
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
                                            notificationForm.preferences[
                                                item.key
                                            ]
                                                ? 'border-primary-200/80 bg-primary-50/40 dark:border-primary-800/40 dark:bg-primary-950/20'
                                                : 'border-surface-200/80 bg-white dark:border-surface-800 dark:bg-surface-900'
                                        "
                                    >
                                        <span
                                            class="grid h-10 w-10 shrink-0 place-items-center rounded-xl transition-colors"
                                            :class="
                                                notificationForm.preferences[
                                                    item.key
                                                ]
                                                    ? 'bg-primary-100 text-primary-800 dark:bg-primary-900/60 dark:text-primary-200'
                                                    : 'bg-surface-100 text-surface-500 dark:bg-surface-800 dark:text-surface-400'
                                            "
                                        >
                                            <component
                                                :is="getIcon(item.option.icon)"
                                                class="h-4.5 w-4.5"
                                            />
                                        </span>

                                        <div class="min-w-0 flex-1">
                                            <p
                                                class="text-xs font-semibold text-surface-900 sm:text-sm dark:text-surface-100"
                                            >
                                                {{ item.option.label }}
                                            </p>
                                            <p
                                                class="mt-0.5 text-xs leading-relaxed text-surface-500 dark:text-surface-400"
                                            >
                                                {{ item.option.description }}
                                            </p>
                                        </div>

                                        <Toggle
                                            v-model="
                                                notificationForm.preferences[
                                                    item.key
                                                ]
                                            "
                                            :label="item.option.label"
                                            :disabled="
                                                notificationForm.processing
                                            "
                                        />
                                    </div>
                                </div>
                            </section>
                        </div>

                        <div
                            class="flex flex-col gap-3 border-t border-surface-100 pt-5 sm:flex-row sm:items-center sm:justify-between dark:border-surface-800"
                        >
                            <p
                                class="text-xs text-surface-500 dark:text-surface-400"
                            >
                                Preferensi disimpan ke akun Anda dan tersinkron
                                di semua perangkat. Email transaksional penting
                                tetap dikirim.
                            </p>
                            <div class="flex items-center gap-2">
                                <Button
                                    v-if="hasNotificationChanges"
                                    type="button"
                                    variant="secondary"
                                    size="sm"
                                    :disabled="notificationForm.processing"
                                    @click="resetNotificationChanges"
                                >
                                    Batalkan
                                </Button>
                                <Button
                                    type="button"
                                    variant="primary"
                                    size="sm"
                                    :loading="notificationForm.processing"
                                    :disabled="!hasNotificationChanges"
                                    @click="submitNotifications"
                                >
                                    <component
                                        :is="getIcon('Save')"
                                        class="h-3.5 w-3.5"
                                    />
                                    Simpan Preferensi
                                </Button>
                            </div>
                        </div>
                    </div>

                    <!-- SECURITY -->
                    <div
                        v-else-if="activePanel === 'security'"
                        class="space-y-6"
                    >
                        <form
                            class="rounded-2xl border border-surface-200/80 p-5 dark:border-surface-800"
                            @submit.prevent="submitPassword"
                        >
                            <div class="flex items-start gap-3">
                                <span
                                    class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-primary-50 text-primary-700 dark:bg-primary-950/40 dark:text-primary-300"
                                >
                                    <component
                                        :is="getIcon('KeyRound')"
                                        class="h-4.5 w-4.5"
                                    />
                                </span>
                                <div>
                                    <h3
                                        class="text-sm font-semibold text-surface-950 dark:text-surface-50"
                                    >
                                        Ubah Kata Sandi
                                    </h3>
                                    <p
                                        class="mt-0.5 text-xs text-surface-500 dark:text-surface-400"
                                    >
                                        Masukkan kata sandi saat ini untuk
                                        mengonfirmasi perubahan.
                                    </p>
                                </div>
                            </div>

                            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                                <div class="sm:col-span-2">
                                    <label
                                        for="current-password"
                                        class="mb-1.5 block text-xs font-semibold text-surface-700 dark:text-surface-200"
                                    >
                                        Kata Sandi Saat Ini
                                        <span class="text-rose-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <input
                                            id="current-password"
                                            v-model="
                                                passwordForm.current_password
                                            "
                                            :type="
                                                showCurrentPassword
                                                    ? 'text'
                                                    : 'password'
                                            "
                                            required
                                            autocomplete="current-password"
                                            class="input pr-12"
                                            placeholder="••••••••"
                                        />
                                        <button
                                            type="button"
                                            class="absolute inset-y-0 right-0 flex items-center px-3 text-surface-400 transition hover:text-surface-700 dark:hover:text-surface-200"
                                            :aria-label="
                                                showCurrentPassword
                                                    ? 'Sembunyikan kata sandi saat ini'
                                                    : 'Tampilkan kata sandi saat ini'
                                            "
                                            @click="
                                                showCurrentPassword =
                                                    !showCurrentPassword
                                            "
                                        >
                                            <component
                                                :is="
                                                    getIcon(
                                                        showCurrentPassword
                                                            ? 'EyeOff'
                                                            : 'Eye',
                                                    )
                                                "
                                                class="h-4 w-4"
                                            />
                                        </button>
                                    </div>
                                    <p
                                        v-if="
                                            passwordForm.errors.current_password
                                        "
                                        class="mt-1 text-[11px] font-medium text-rose-600 dark:text-rose-400"
                                    >
                                        {{
                                            passwordForm.errors.current_password
                                        }}
                                    </p>
                                </div>

                                <div>
                                    <label
                                        for="new-password"
                                        class="mb-1.5 block text-xs font-semibold text-surface-700 dark:text-surface-200"
                                    >
                                        Kata Sandi Baru
                                        <span class="text-rose-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <input
                                            id="new-password"
                                            v-model="passwordForm.password"
                                            :type="
                                                showNewPassword
                                                    ? 'text'
                                                    : 'password'
                                            "
                                            required
                                            autocomplete="new-password"
                                            class="input pr-12"
                                            placeholder="Minimal 8 karakter"
                                        />
                                        <button
                                            type="button"
                                            class="absolute inset-y-0 right-0 flex items-center px-3 text-surface-400 transition hover:text-surface-700 dark:hover:text-surface-200"
                                            :aria-label="
                                                showNewPassword
                                                    ? 'Sembunyikan kata sandi baru'
                                                    : 'Tampilkan kata sandi baru'
                                            "
                                            @click="
                                                showNewPassword =
                                                    !showNewPassword
                                            "
                                        >
                                            <component
                                                :is="
                                                    getIcon(
                                                        showNewPassword
                                                            ? 'EyeOff'
                                                            : 'Eye',
                                                    )
                                                "
                                                class="h-4 w-4"
                                            />
                                        </button>
                                    </div>
                                    <div class="mt-2 flex items-center gap-2">
                                        <div
                                            class="flex h-1.5 flex-1 gap-1 overflow-hidden"
                                        >
                                            <span
                                                v-for="index in 4"
                                                :key="index"
                                                class="h-full flex-1 rounded-full transition-colors"
                                                :class="
                                                    index <= passwordStrength
                                                        ? passwordStrengthColor
                                                        : 'bg-surface-200 dark:bg-surface-700'
                                                "
                                            />
                                        </div>
                                        <span
                                            class="w-20 text-right text-[10px] font-semibold text-surface-500 dark:text-surface-400"
                                        >
                                            {{ passwordStrengthLabel }}
                                        </span>
                                    </div>
                                    <p
                                        v-if="passwordForm.errors.password"
                                        class="mt-1 text-[11px] font-medium text-rose-600 dark:text-rose-400"
                                    >
                                        {{ passwordForm.errors.password }}
                                    </p>
                                </div>

                                <div>
                                    <label
                                        for="confirm-password"
                                        class="mb-1.5 block text-xs font-semibold text-surface-700 dark:text-surface-200"
                                    >
                                        Konfirmasi Kata Sandi Baru
                                        <span class="text-rose-500">*</span>
                                    </label>
                                    <div class="relative">
                                        <input
                                            id="confirm-password"
                                            v-model="
                                                passwordForm.password_confirmation
                                            "
                                            :type="
                                                showConfirmPassword
                                                    ? 'text'
                                                    : 'password'
                                            "
                                            required
                                            autocomplete="new-password"
                                            class="input pr-12"
                                            placeholder="Ulangi kata sandi baru"
                                        />
                                        <button
                                            type="button"
                                            class="absolute inset-y-0 right-0 flex items-center px-3 text-surface-400 transition hover:text-surface-700 dark:hover:text-surface-200"
                                            :aria-label="
                                                showConfirmPassword
                                                    ? 'Sembunyikan konfirmasi kata sandi'
                                                    : 'Tampilkan konfirmasi kata sandi'
                                            "
                                            @click="
                                                showConfirmPassword =
                                                    !showConfirmPassword
                                            "
                                        >
                                            <component
                                                :is="
                                                    getIcon(
                                                        showConfirmPassword
                                                            ? 'EyeOff'
                                                            : 'Eye',
                                                    )
                                                "
                                                class="h-4 w-4"
                                            />
                                        </button>
                                    </div>
                                    <p
                                        v-if="
                                            passwordForm.password_confirmation &&
                                            passwordForm.password !==
                                                passwordForm.password_confirmation
                                        "
                                        class="mt-1 text-[11px] font-medium text-amber-600 dark:text-amber-400"
                                    >
                                        Konfirmasi belum sama dengan kata sandi
                                        baru.
                                    </p>
                                </div>
                            </div>

                            <div class="mt-5 flex justify-end">
                                <Button
                                    type="submit"
                                    variant="primary"
                                    size="sm"
                                    :loading="passwordForm.processing"
                                    :disabled="
                                        !passwordForm.current_password ||
                                        !passwordForm.password ||
                                        !passwordForm.password_confirmation
                                    "
                                >
                                    <component
                                        :is="getIcon('ShieldCheck')"
                                        class="h-3.5 w-3.5"
                                    />
                                    Perbarui Kata Sandi
                                </Button>
                            </div>
                        </form>

                        <div class="space-y-2.5">
                            <h3
                                class="text-[10px] font-bold tracking-[0.16em] text-surface-400 uppercase dark:text-surface-500"
                            >
                                Status Perlindungan Akun
                            </h3>
                            <div
                                v-for="item in securityStatus"
                                :key="item.title"
                                class="flex items-start gap-3.5 rounded-2xl border border-surface-200/80 bg-white p-4 dark:border-surface-800 dark:bg-surface-900"
                            >
                                <span
                                    class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-300"
                                >
                                    <component
                                        :is="getIcon(item.icon)"
                                        class="h-4.5 w-4.5"
                                    />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div
                                        class="flex flex-wrap items-center justify-between gap-2"
                                    >
                                        <p
                                            class="text-sm font-semibold text-surface-900 dark:text-surface-100"
                                        >
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
                                    <p
                                        class="mt-1 text-xs leading-relaxed text-surface-500 dark:text-surface-400"
                                    >
                                        {{ item.description }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div
                            class="rounded-2xl border border-primary-200/70 bg-primary-50/50 p-4 text-xs text-primary-900 dark:border-primary-800/40 dark:bg-primary-950/20 dark:text-primary-200"
                        >
                            <p class="font-semibold">Catatan keamanan</p>
                            <p
                                class="mt-1 leading-relaxed text-primary-800/90 dark:text-primary-300/80"
                            >
                                Sesi login dilindungi enkripsi standar industri.
                                Jika Anda mencurigai aktivitas tidak sah, segera
                                ubah kata sandi dan hubungi administrator ruang
                                kerja.
                            </p>
                        </div>
                    </div>

                    <!-- HELP -->
                    <div v-else class="space-y-6">
                        <div
                            class="rounded-2xl border border-surface-200/80 bg-surface-50/50 p-5 sm:p-6 dark:border-surface-800 dark:bg-surface-950/20"
                        >
                            <div class="flex items-center gap-2.5">
                                <span
                                    class="grid h-9 w-9 place-items-center rounded-xl bg-white shadow-2xs dark:bg-surface-800"
                                >
                                    <component
                                        :is="getIcon('Keyboard')"
                                        class="h-4.5 w-4.5 text-primary-600 dark:text-primary-400"
                                    />
                                </span>
                                <div>
                                    <h3
                                        class="text-sm font-semibold text-surface-950 dark:text-surface-50"
                                    >
                                        Pintasan Keyboard Aktif
                                    </h3>
                                    <p
                                        class="text-xs text-surface-500 dark:text-surface-400"
                                    >
                                        Tekan tombol berurutan saat tidak sedang
                                        mengetik di kolom input.
                                    </p>
                                </div>
                            </div>

                            <div class="mt-5 grid gap-2.5 sm:grid-cols-2">
                                <div
                                    v-for="shortcut in shortcuts"
                                    :key="shortcut.label"
                                    class="flex items-center justify-between gap-3 rounded-xl bg-white p-3 shadow-2xs dark:bg-surface-900"
                                >
                                    <div class="min-w-0">
                                        <p
                                            class="text-xs font-semibold text-surface-800 dark:text-surface-200"
                                        >
                                            {{ shortcut.label }}
                                        </p>
                                        <p
                                            class="mt-0.5 text-[10px] text-surface-400 dark:text-surface-500"
                                        >
                                            {{ shortcut.description }}
                                        </p>
                                    </div>
                                    <div
                                        class="flex shrink-0 items-center gap-1 font-mono text-[10px]"
                                    >
                                        <template
                                            v-for="(
                                                key, index
                                            ) in shortcut.keys"
                                            :key="key"
                                        >
                                            <span
                                                v-if="index > 0"
                                                class="text-surface-400"
                                            >
                                                +
                                            </span>
                                            <kbd
                                                class="rounded border border-surface-200 bg-surface-50 px-1.5 py-0.5 font-bold dark:border-surface-700 dark:bg-surface-800"
                                            >
                                                {{ key }}
                                            </kbd>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h3
                                class="mb-3 text-xs font-bold tracking-wider text-surface-500 uppercase dark:text-surface-400"
                            >
                                Pertanyaan yang Sering Diajukan
                            </h3>
                            <div class="space-y-2.5">
                                <div
                                    v-for="(faq, index) in faqs"
                                    :key="faq.question"
                                    class="rounded-2xl border border-surface-200/80 bg-white transition-all dark:border-surface-800 dark:bg-surface-900"
                                >
                                    <button
                                        type="button"
                                        class="flex w-full items-center justify-between gap-3 p-4 text-left"
                                        :aria-expanded="openedFaq === index"
                                        @click="toggleFaq(index)"
                                    >
                                        <span
                                            class="text-xs font-semibold text-surface-900 sm:text-sm dark:text-surface-100"
                                        >
                                            {{ faq.question }}
                                        </span>
                                        <component
                                            :is="
                                                getIcon(
                                                    openedFaq === index
                                                        ? 'ChevronUp'
                                                        : 'ChevronDown',
                                                )
                                            "
                                            class="h-4 w-4 shrink-0 text-surface-400 transition-transform"
                                        />
                                    </button>
                                    <div
                                        v-show="openedFaq === index"
                                        class="border-t border-surface-100 px-4 pt-2.5 pb-4 text-xs leading-relaxed text-surface-600 dark:border-surface-800 dark:text-surface-300"
                                    >
                                        {{ faq.answer }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            class="flex flex-col gap-3 rounded-2xl border border-surface-200/80 p-4 sm:flex-row sm:items-center sm:justify-between dark:border-surface-800"
                        >
                            <div>
                                <p
                                    class="text-xs font-semibold text-surface-800 dark:text-surface-200"
                                >
                                    Butuh bantuan lebih lanjut?
                                </p>
                                <p
                                    class="text-[11px] text-surface-500 dark:text-surface-400"
                                >
                                    Pusat bantuan menyediakan panduan lengkap
                                    dan informasi kontak tim.
                                </p>
                            </div>
                            <a
                                href="/support"
                                class="btn btn-secondary btn-sm shrink-0"
                            >
                                <component
                                    :is="getIcon('LifeBuoy')"
                                    class="h-3.5 w-3.5"
                                />
                                Buka Pusat Bantuan
                            </a>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
</template>
