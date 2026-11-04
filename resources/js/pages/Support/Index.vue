<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { getIcon } from '@/components/sidebar/icons';
import { useToast } from '@/composables/useToast';
import {
    HelpCircle,
    Search,
    Mail,
    Copy,
    Check,
    MessageSquare,
    FolderKanban,
    CheckSquare,
    Calendar,
    Keyboard,
    ShieldCheck,
    ChevronDown,
    ChevronUp,
    Send,
    Sparkles,
    Clock,
    FileText,
    ArrowUpRight,
    CheckCircle2,
    Paperclip,
    ThumbsUp,
    ThumbsDown,
    Activity,
    Shield,
    Users,
    ArrowRight,
    X,
    Play,
    SlidersHorizontal,
    CornerDownLeft,
    CheckCircle,
} from '@lucide/vue';

defineOptions({
    layout: AppLayout,
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const toast = useToast();

const searchQuery = ref('');
const selectedCategory = ref<string>('all');
const activeFaqIndex = ref<number | null>(0);
const copiedEmail = ref(false);
const ticketSubmitted = ref(false);
const attachedFileName = ref<string | null>(null);
const helpfulFeedback = ref<Record<number, 'yes' | 'no'>>({});

// Interactive Shortcut State
const activeKeyNotice = ref<string | null>(null);
const activeShortcutId = ref<string | null>(null);
let noticeTimer: ReturnType<typeof setTimeout> | null = null;
let pendingSequenceG = false;
let sequenceTimer: ReturnType<typeof setTimeout> | null = null;

const searchSuggestions = [
    'Tambah anggota proyek',
    'Alur Kanban',
    'Tenggat Overdue',
    'Pengaturan 2FA',
    'Lampiran berkas',
    'Pintasan keyboard',
];

const ticketForm = ref({
    category: 'Pertanyaan Teknis',
    priority: 'Normal',
    subject: '',
    message: '',
});

interface FaqItem {
    id: number;
    category: 'project' | 'task' | 'account' | 'security';
    categoryLabel: string;
    question: string;
    answer: string;
    icon: string;
    readTime: string;
}

const faqList: FaqItem[] = [
    {
        id: 1,
        category: 'project',
        categoryLabel: 'Manajemen Proyek',
        question: 'Bagaimana cara menambahkan anggota baru ke dalam proyek?',
        answer: 'Masuk ke menu Proyek melalui navigasi samping, buka detail proyek yang Anda kelola, lalu pilih tab "Anggota". Klik tombol "+ Tambah Anggota", pilih rekan kerja dari daftar pengguna, dan tentukan perannya (Project Manager, Anggota Tim, atau Pengamat). Anggota yang ditambahkan akan otomatis menerima notifikasi dan hak akses ke papan tugas terkait.',
        icon: 'FolderKanban',
        readTime: '1 mnt baca',
    },
    {
        id: 2,
        category: 'task',
        categoryLabel: 'Alur Tugas & Kanban',
        question:
            'Bagaimana cara memindahkan alur status tugas di papan Kanban?',
        answer: 'Buka menu Tugas untuk melihat papan Kanban interaktif. Setiap tugas dipetakan ke dalam 4 kolom alur: Belum Mulai (Todo), Sedang Berjalan (In Progress), Peninjauan (Review), dan Selesai (Done). Anda dapat mengklik tugas untuk memperbarui statusnya secara instan, mengubah prioritas (Rendah, Sedang, Tinggi, Mendesak), atau menambahkan komentar diskusi.',
        icon: 'CheckSquare',
        readTime: '1 mnt baca',
    },
    {
        id: 3,
        category: 'task',
        categoryLabel: 'Alur Tugas & Kanban',
        question:
            'Bagaimana sistem menentukan tenggat waktu tugas yang terlambat (Overdue)?',
        answer: 'Sistem secara otomatis membandingkan tanggal jatuh tempo (due date) tugas dengan tanggal hari ini. Jika tugas belum berstatus "Done" dan tanggal jatuh tempo telah lewat, lencana merah "Terlambat" akan otomatis disematkan dan pengingat akan dikirimkan ke penanggung jawab tugas melalui panel notifikasi.',
        icon: 'Clock',
        readTime: '1 mnt baca',
    },
    {
        id: 4,
        category: 'account',
        categoryLabel: 'Akun & Notifikasi',
        question:
            'Bagaimana cara mengatur preferensi notifikasi email dan aplikasi?',
        answer: 'Buka menu Pengaturan > Tab Notifikasi. Anda dapat menyesuaikan jenis pemberitahuan yang ingin diterima, seperti pembaruan alur tugas, penyebutan (@mentions), komentar baru, dan pengingat harian. Setiap saluran pemberitahuan dapat dinyalakan atau dimatikan secara mandiri.',
        icon: 'HelpCircle',
        readTime: '2 mnt baca',
    },
    {
        id: 5,
        category: 'project',
        categoryLabel: 'Manajemen Proyek',
        question:
            'Apakah berkas spesifikasi dan lampiran dokumen dapat diunggah?',
        answer: 'Ya, Anda dapat mengunggah berkas rancangan, dokumen kerja, dan aset langsung di menu Dokumen atau melampirkannya di setiap kartu tugas. Batas ukuran maksimal adalah 25MB per berkas dengan format PDF, gambar (PNG, JPG), spreadsheet, atau arsip ZIP.',
        icon: 'FileText',
        readTime: '1 mnt baca',
    },
    {
        id: 6,
        category: 'security',
        categoryLabel: 'Keamanan & Akses',
        question:
            'Bagaimana cara mengaktifkan Otentikasi Dua Faktor (2FA) atau Passkeys?',
        answer: 'Kunjungi Pengaturan > Tab Keamanan. Anda dapat mengaktifkan 2FA berbasis aplikasi authenticator (Google Authenticator, 1Password) atau mendaftarkan Passkeys (biometrik Face ID / Fingerprint) untuk proses masuk yang lebih cepat, aman, dan tanpa kata sandi.',
        icon: 'ShieldCheck',
        readTime: '2 mnt baca',
    },
];

const categoryTabs = [
    { key: 'all', label: 'Semua Kategori', icon: 'Sparkles' },
    { key: 'project', label: 'Manajemen Proyek', icon: 'FolderKanban' },
    { key: 'task', label: 'Alur Tugas & Kanban', icon: 'CheckSquare' },
    { key: 'account', label: 'Akun & Notifikasi', icon: 'HelpCircle' },
    { key: 'security', label: 'Keamanan & Akses', icon: 'ShieldCheck' },
];

const priorities = [
    { key: 'Normal', label: 'Normal (Pertanyaan Umum)', dot: 'bg-emerald-500' },
    {
        key: 'Tinggi',
        label: 'Tinggi (Kendala Alur Kerja)',
        dot: 'bg-amber-500',
    },
    {
        key: 'Mendesak',
        label: 'Mendesak (Kendala Kritis Sistem)',
        dot: 'bg-rose-500',
    },
];

const recentTickets = [
    {
        id: 'TK-8492',
        subject: 'Panduan konfigurasi webhook & integrasi API',
        date: '2 hari lalu',
        status: 'Selesai',
        statusClass:
            'bg-emerald-50 text-emerald-800 border-emerald-200/80 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800/40',
    },
    {
        id: 'TK-8120',
        subject: 'Pertanyaan alokasi hak akses role pengamat',
        date: '1 minggu lalu',
        status: 'Selesai',
        statusClass:
            'bg-emerald-50 text-emerald-800 border-emerald-200/80 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800/40',
    },
];

interface ShortcutAction {
    id: string;
    label: string;
    keys: string[];
    description: string;
    action: () => void;
}

const keyboardShortcuts: ShortcutAction[] = [
    {
        id: 'search',
        label: 'Pencarian Cepat',
        keys: ['Ctrl', 'K'],
        description: 'Fokus kursor langsung ke kolom pencarian bantuan',
        action: () => {
            const input = document.getElementById(
                'support-search-input',
            ) as HTMLInputElement | null;
            if (input) {
                input.focus();
                input.select();
                input.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            triggerVisualNotice(
                'search',
                'Pencarian Cepat diaktifkan (Ctrl + K)',
            );
        },
    },
    {
        id: 'dashboard',
        label: 'Ke Dashboard',
        keys: ['G', 'D'],
        description: 'Beralih ke ringkasan metrik & progres proyek',
        action: () => {
            triggerVisualNotice('dashboard', 'Membuka Dashboard (G lalu D)...');
            setTimeout(() => router.visit('/dashboard'), 250);
        },
    },
    {
        id: 'tasks',
        label: 'Ke Papan Tugas',
        keys: ['G', 'T'],
        description: 'Buka Kanban tugas dan daftar pekerjaan tim',
        action: () => {
            triggerVisualNotice('tasks', 'Membuka Papan Tugas (G lalu T)...');
            setTimeout(() => router.visit('/tasks'), 250);
        },
    },
    {
        id: 'projects',
        label: 'Ke Daftar Proyek',
        keys: ['G', 'P'],
        description: 'Akses portofolio proyek dan manajemen tim',
        action: () => {
            triggerVisualNotice(
                'projects',
                'Membuka Daftar Proyek (G lalu P)...',
            );
            setTimeout(() => router.visit('/projects'), 250);
        },
    },
    {
        id: 'calendar',
        label: 'Ke Kalender Kerja',
        keys: ['G', 'C'],
        description: 'Lihat agenda tenggat waktu dan jadwal rilis',
        action: () => {
            triggerVisualNotice(
                'calendar',
                'Membuka Kalender Kerja (G lalu C)...',
            );
            setTimeout(() => router.visit('/calendar'), 250);
        },
    },
    {
        id: 'settings',
        label: 'Ke Pengaturan Akun',
        keys: ['G', 'S'],
        description: 'Kelola profil, keamanan, 2FA, dan Passkeys',
        action: () => {
            triggerVisualNotice(
                'settings',
                'Membuka Pengaturan Akun (G lalu S)...',
            );
            setTimeout(() => router.visit('/settings'), 250);
        },
    },
    {
        id: 'escape',
        label: 'Reset / Tutup Fokus',
        keys: ['Esc'],
        description: 'Bersihkan pencarian aktif atau lepas fokus input',
        action: () => {
            searchQuery.value = '';
            (document.activeElement as HTMLElement)?.blur?.();
            triggerVisualNotice(
                'escape',
                'Pencarian dibersihkan & fokus ditutup (Esc)',
            );
        },
    },
];

function triggerVisualNotice(shortcutId: string, message: string): void {
    activeShortcutId.value = shortcutId;
    activeKeyNotice.value = message;
    if (noticeTimer) clearTimeout(noticeTimer);
    noticeTimer = setTimeout(() => {
        activeShortcutId.value = null;
        activeKeyNotice.value = null;
    }, 2800);
}

function runShortcut(shortcut: ShortcutAction): void {
    shortcut.action();
}

function isInputFocused(): boolean {
    const el = document.activeElement;
    if (!el) return false;
    const tag = el.tagName.toLowerCase();
    return (
        tag === 'input' ||
        tag === 'textarea' ||
        tag === 'select' ||
        (el as HTMLElement).isContentEditable
    );
}

function handlePageKeydown(e: KeyboardEvent): void {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        const searchShortcut = keyboardShortcuts.find((s) => s.id === 'search');
        searchShortcut?.action();
        return;
    }

    if (e.key === 'Escape') {
        pendingSequenceG = false;
        if (sequenceTimer) clearTimeout(sequenceTimer);
        const escShortcut = keyboardShortcuts.find((s) => s.id === 'escape');
        escShortcut?.action();
        return;
    }

    if (isInputFocused()) {
        return;
    }

    const key = e.key.toLowerCase();

    if (pendingSequenceG) {
        pendingSequenceG = false;
        if (sequenceTimer) clearTimeout(sequenceTimer);

        if (key === 'd') {
            e.preventDefault();
            keyboardShortcuts.find((s) => s.id === 'dashboard')?.action();
        } else if (key === 't') {
            e.preventDefault();
            keyboardShortcuts.find((s) => s.id === 'tasks')?.action();
        } else if (key === 'p') {
            e.preventDefault();
            keyboardShortcuts.find((s) => s.id === 'projects')?.action();
        } else if (key === 'c') {
            e.preventDefault();
            keyboardShortcuts.find((s) => s.id === 'calendar')?.action();
        } else if (key === 's') {
            e.preventDefault();
            keyboardShortcuts.find((s) => s.id === 'settings')?.action();
        } else {
            triggerVisualNotice(
                '',
                `Kombinasi 'G lalu ${e.key.toUpperCase()}' tidak dikenal`,
            );
        }
        return;
    }

    if (key === 'g' && !e.ctrlKey && !e.altKey && !e.metaKey) {
        pendingSequenceG = true;
        activeKeyNotice.value =
            "Tombol 'G' aktif! Tekan D (Dashboard), T (Tugas), P (Proyek), C (Kalender), atau S (Pengaturan)";
        if (sequenceTimer) clearTimeout(sequenceTimer);
        sequenceTimer = setTimeout(() => {
            pendingSequenceG = false;
            if (activeKeyNotice.value?.includes("Tombol 'G' aktif")) {
                activeKeyNotice.value = null;
            }
        }, 2000);
    }
}

onMounted(() => {
    window.addEventListener('keydown', handlePageKeydown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handlePageKeydown);
    if (noticeTimer) clearTimeout(noticeTimer);
    if (sequenceTimer) clearTimeout(sequenceTimer);
});

const filteredFaqs = computed(() => {
    return faqList.filter((item) => {
        const matchesCategory =
            selectedCategory.value === 'all' ||
            item.category === selectedCategory.value;
        const query = searchQuery.value.trim().toLowerCase();
        const matchesSearch =
            !query ||
            item.question.toLowerCase().includes(query) ||
            item.answer.toLowerCase().includes(query) ||
            item.categoryLabel.toLowerCase().includes(query);

        return matchesCategory && matchesSearch;
    });
});

function toggleFaq(id: number): void {
    activeFaqIndex.value = activeFaqIndex.value === id ? null : id;
}

function rateFaq(id: number, feedback: 'yes' | 'no'): void {
    helpfulFeedback.value[id] = feedback;
    toast.success(
        'Terima Kasih!',
        feedback === 'yes'
            ? 'Umpan balik positif Anda sangat berharga bagi peningkatan dokumentasi.'
            : 'Masukan Anda kami catat untuk perbaikan penjelasan topik ini.',
    );
}

function copySupportEmail(): void {
    navigator.clipboard.writeText('support@project.local');
    copiedEmail.value = true;
    toast.success(
        'Email Disalin',
        'support@project.local telah disalin ke papan klip Anda.',
    );
    setTimeout(() => {
        copiedEmail.value = false;
    }, 2000);
}

function handleFileSelect(event: Event): void {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files[0]) {
        attachedFileName.value = input.files[0].name;
    }
}

function removeAttachment(): void {
    attachedFileName.value = null;
}

function submitSupportTicket(): void {
    if (!ticketForm.value.subject.trim() || !ticketForm.value.message.trim())
        return;
    ticketSubmitted.value = true;
    toast.success(
        'Tiket Terkirim',
        'Laporan Anda telah berhasil diajukan ke tim teknis DigitalCode.',
    );
    setTimeout(() => {
        ticketSubmitted.value = false;
        attachedFileName.value = null;
        ticketForm.value = {
            category: 'Pertanyaan Teknis',
            priority: 'Normal',
            subject: '',
            message: '',
        };
    }, 4000);
}
</script>

<template>
    <div class="mx-auto max-w-7xl space-y-6 pb-16">
        <!-- 1. Hero Header Section: High-End Glassmorphism & Search Command Hub -->
        <header
            class="relative overflow-hidden rounded-3xl border border-surface-200/80 bg-white p-6 shadow-xs sm:p-8 lg:p-10 dark:border-surface-800 dark:bg-surface-900"
        >
            <!-- Background Radial Glow Pattern -->
            <div
                class="pointer-events-none absolute inset-0 bg-[radial-gradient(#e5e7eb_1px,transparent_1px)] [background-size:20px_20px] opacity-60 dark:bg-[radial-gradient(#262626_1px,transparent_1px)] dark:opacity-40"
            />
            <div
                class="pointer-events-none absolute -top-24 -right-24 h-80 w-80 rounded-full bg-emerald-500/10 blur-3xl dark:bg-emerald-500/15"
            />
            <div
                class="pointer-events-none absolute -bottom-24 -left-24 h-80 w-80 rounded-full bg-teal-500/10 blur-3xl dark:bg-teal-500/15"
            />

            <div
                class="relative z-10 flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between"
            >
                <div class="max-w-2xl">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200/90 bg-emerald-50/90 px-3 py-1 text-xs font-semibold text-emerald-800 dark:border-emerald-800/40 dark:bg-emerald-950/40 dark:text-emerald-300"
                        >
                            <span class="relative flex h-2 w-2">
                                <span
                                    class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"
                                />
                                <span
                                    class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"
                                />
                            </span>
                            Pusat Dukungan Pengguna & SLA Aktif
                        </span>
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full border border-surface-200 bg-white/90 px-3 py-1 text-xs font-medium text-surface-600 shadow-2xs dark:border-surface-700 dark:bg-surface-800/80 dark:text-surface-300"
                        >
                            <span
                                class="h-1.5 w-1.5 rounded-full bg-emerald-500"
                            />
                            3 Spesialis Teknis Online
                        </span>
                    </div>

                    <h1
                        class="mt-3 font-serif text-3xl font-normal tracking-tight text-surface-950 sm:text-4xl lg:text-5xl dark:text-surface-50"
                    >
                        Pusat Bantuan & Support
                    </h1>
                    <p
                        class="mt-2 text-sm leading-relaxed text-surface-600 sm:text-base dark:text-surface-300"
                    >
                        Solusi terintegrasi alur kerja proyek, panduan fitur
                        Kanban, pintasan navigasi cepat, dan layanan bantuan
                        langsung DigitalCode.
                    </p>
                </div>

                <!-- Live Service Metrics Bento Box (Tightly Aligned) -->
                <div
                    class="grid grid-cols-2 gap-2.5 rounded-2xl border border-surface-200/90 bg-surface-50/70 p-3 shadow-2xs backdrop-blur-md sm:min-w-[320px] dark:border-surface-800 dark:bg-surface-950/60"
                >
                    <div
                        class="rounded-xl border border-surface-200/70 bg-white p-3 dark:border-surface-800 dark:bg-surface-900"
                    >
                        <span
                            class="text-[10px] font-bold tracking-wider text-surface-400 uppercase"
                            >Status Sistem</span
                        >
                        <p
                            class="mt-0.5 font-serif text-base font-bold text-emerald-700 dark:text-emerald-400"
                        >
                            100% Online
                        </p>
                        <p class="text-[10px] text-surface-400">
                            Seluruh modul aktif
                        </p>
                    </div>

                    <div
                        class="rounded-xl border border-surface-200/70 bg-white p-3 dark:border-surface-800 dark:bg-surface-900"
                    >
                        <span
                            class="text-[10px] font-bold tracking-wider text-surface-400 uppercase"
                            >Respon Tim</span
                        >
                        <p
                            class="mt-0.5 font-serif text-base font-bold text-surface-950 dark:text-surface-50"
                        >
                            &lt; 30 Menit
                        </p>
                        <p class="text-[10px] text-surface-400">
                            Jam kerja aktif
                        </p>
                    </div>

                    <div
                        class="rounded-xl border border-surface-200/70 bg-white p-3 dark:border-surface-800 dark:bg-surface-900"
                    >
                        <span
                            class="text-[10px] font-bold tracking-wider text-surface-400 uppercase"
                            >Layanan Resmi</span
                        >
                        <p
                            class="mt-0.5 font-serif text-base font-bold text-surface-950 dark:text-surface-50"
                        >
                            Sen – Jum
                        </p>
                        <p class="text-[10px] text-surface-400">
                            08:00 – 18:00 WIB
                        </p>
                    </div>

                    <div
                        class="rounded-xl border border-surface-200/70 bg-white p-3 dark:border-surface-800 dark:bg-surface-900"
                    >
                        <span
                            class="text-[10px] font-bold tracking-wider text-surface-400 uppercase"
                            >Enkripsi Data</span
                        >
                        <p
                            class="mt-0.5 font-serif text-base font-bold text-indigo-700 dark:text-indigo-400"
                        >
                            TLS 1.3
                        </p>
                        <p class="text-[10px] text-surface-400">
                            End-to-end terenkripsi
                        </p>
                    </div>
                </div>
            </div>

            <!-- Integrated Live Search Bar with Instant Shortcut Indicator -->
            <div
                class="relative z-10 mt-6 border-t border-surface-100 pt-5 dark:border-surface-800"
            >
                <div class="relative flex items-center">
                    <Search
                        class="pointer-events-none absolute left-4 h-5 w-5 text-surface-400 transition-colors"
                    />
                    <input
                        id="support-search-input"
                        v-model="searchQuery"
                        type="text"
                        placeholder="Ketik topik bantuan, kata kunci, pertanyaan, atau kendala alur kerja..."
                        class="h-13 w-full rounded-2xl border border-surface-200/90 bg-white pr-28 pl-12 text-sm text-surface-900 shadow-2xs transition-all placeholder:text-surface-400 focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10 focus:outline-hidden dark:border-surface-700 dark:bg-surface-800 dark:text-surface-50 dark:placeholder:text-surface-500"
                    />

                    <div class="absolute right-3.5 flex items-center gap-2">
                        <button
                            v-if="searchQuery"
                            type="button"
                            class="rounded-lg p-1.5 text-surface-400 hover:bg-surface-100 hover:text-surface-700 dark:hover:bg-surface-700 dark:hover:text-surface-200"
                            title="Bersihkan pencarian"
                            @click="searchQuery = ''"
                        >
                            <X class="h-4 w-4" />
                        </button>
                        <div
                            class="hidden items-center gap-1 rounded-lg border border-surface-200 bg-surface-100/80 px-2 py-1 font-mono text-[10px] font-semibold text-surface-500 sm:inline-flex dark:border-surface-700 dark:bg-surface-900/80 dark:text-surface-400"
                            title="Tekan Ctrl+K untuk fokus pencarian kapan saja"
                        >
                            <span>Ctrl</span>
                            <span>+</span>
                            <span>K</span>
                        </div>
                    </div>
                </div>

                <!-- Suggestion Chips -->
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <span class="text-xs font-medium text-surface-400"
                        >Pencarian Cepat:</span
                    >
                    <button
                        v-for="sugg in searchSuggestions"
                        :key="sugg"
                        type="button"
                        class="rounded-full border border-surface-200/80 bg-surface-50/80 px-2.5 py-1 text-xs text-surface-600 transition hover:border-emerald-500/40 hover:bg-white hover:text-emerald-700 dark:border-surface-700 dark:bg-surface-800/70 dark:text-surface-300 dark:hover:border-emerald-500/40 dark:hover:bg-surface-800 dark:hover:text-emerald-300"
                        @click="searchQuery = sugg"
                    >
                        {{ sugg }}
                    </button>
                </div>
            </div>
        </header>

        <!-- 2. Fast-Track Workflow Hubs (Interactive Bento Grid) -->
        <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Hub 1: Proyek -->
            <Link
                :href="'/projects'"
                class="group relative flex flex-col justify-between overflow-hidden rounded-2xl border border-surface-200/80 bg-white p-5 shadow-2xs transition-all duration-300 hover:-translate-y-1 hover:border-indigo-500/40 hover:shadow-md dark:border-surface-800 dark:bg-surface-900 dark:hover:border-indigo-500/40"
            >
                <div class="flex items-start justify-between">
                    <div
                        class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500/15 to-blue-500/25 text-indigo-700 shadow-2xs transition-transform duration-300 group-hover:scale-105 dark:from-indigo-500/25 dark:to-blue-500/35 dark:text-indigo-300"
                    >
                        <FolderKanban class="h-5 w-5 stroke-[2]" />
                    </div>
                    <span
                        class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2.5 py-0.5 text-[10px] font-bold text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300"
                    >
                        Portofolio
                    </span>
                </div>
                <div class="mt-4">
                    <div class="flex items-center justify-between">
                        <h3
                            class="font-serif text-base font-semibold text-surface-950 dark:text-surface-50"
                        >
                            Manajemen Proyek
                        </h3>
                        <ArrowUpRight
                            class="h-4 w-4 text-surface-400 transition-transform duration-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-indigo-600"
                        />
                    </div>
                    <p
                        class="mt-1 text-xs leading-relaxed text-surface-500 dark:text-surface-400"
                    >
                        Inisiasi proyek, penetapan tim, target milestone, dan
                        evaluasi hasil.
                    </p>
                </div>
            </Link>

            <!-- Hub 2: Papan Tugas -->
            <Link
                :href="'/tasks'"
                class="group relative flex flex-col justify-between overflow-hidden rounded-2xl border border-surface-200/80 bg-white p-5 shadow-2xs transition-all duration-300 hover:-translate-y-1 hover:border-emerald-500/40 hover:shadow-md dark:border-surface-800 dark:bg-surface-900 dark:hover:border-emerald-500/40"
            >
                <div class="flex items-start justify-between">
                    <div
                        class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-emerald-500/15 to-teal-500/25 text-emerald-700 shadow-2xs transition-transform duration-300 group-hover:scale-105 dark:from-emerald-500/25 dark:to-teal-500/35 dark:text-emerald-300"
                    >
                        <CheckSquare class="h-5 w-5 stroke-[2]" />
                    </div>
                    <span
                        class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-[10px] font-bold text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300"
                    >
                        Kanban Flow
                    </span>
                </div>
                <div class="mt-4">
                    <div class="flex items-center justify-between">
                        <h3
                            class="font-serif text-base font-semibold text-surface-950 dark:text-surface-50"
                        >
                            Alur Tugas Kanban
                        </h3>
                        <ArrowUpRight
                            class="h-4 w-4 text-surface-400 transition-transform duration-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-emerald-600"
                        />
                    </div>
                    <p
                        class="mt-1 text-xs leading-relaxed text-surface-500 dark:text-surface-400"
                    >
                        Papan visual 4 fase: Todo, In Progress, Review, dan Done
                        tersinkron.
                    </p>
                </div>
            </Link>

            <!-- Hub 3: Kalender & Tenggat -->
            <Link
                :href="'/calendar'"
                class="group relative flex flex-col justify-between overflow-hidden rounded-2xl border border-surface-200/80 bg-white p-5 shadow-2xs transition-all duration-300 hover:-translate-y-1 hover:border-amber-500/40 hover:shadow-md dark:border-surface-800 dark:bg-surface-900 dark:hover:border-amber-500/40"
            >
                <div class="flex items-start justify-between">
                    <div
                        class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-amber-500/15 to-orange-500/25 text-amber-700 shadow-2xs transition-transform duration-300 group-hover:scale-105 dark:from-amber-500/25 dark:to-orange-500/35 dark:text-amber-300"
                    >
                        <Calendar class="h-5 w-5 stroke-[2]" />
                    </div>
                    <span
                        class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-0.5 text-[10px] font-bold text-amber-700 dark:bg-amber-950/60 dark:text-amber-300"
                    >
                        Jadwal & Agenda
                    </span>
                </div>
                <div class="mt-4">
                    <div class="flex items-center justify-between">
                        <h3
                            class="font-serif text-base font-semibold text-surface-950 dark:text-surface-50"
                        >
                            Kalender Kerja
                        </h3>
                        <ArrowUpRight
                            class="h-4 w-4 text-surface-400 transition-transform duration-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-amber-600"
                        />
                    </div>
                    <p
                        class="mt-1 text-xs leading-relaxed text-surface-500 dark:text-surface-400"
                    >
                        Pemetaan tanggal jatuh tempo, penyortiran prioritas, &
                        pencegahan overdue.
                    </p>
                </div>
            </Link>

            <!-- Hub 4: Ruang Kolaborasi -->
            <Link
                :href="'/collaboration'"
                class="group relative flex flex-col justify-between overflow-hidden rounded-2xl border border-surface-200/80 bg-white p-5 shadow-2xs transition-all duration-300 hover:-translate-y-1 hover:border-purple-500/40 hover:shadow-md dark:border-surface-800 dark:bg-surface-900 dark:hover:border-purple-500/40"
            >
                <div class="flex items-start justify-between">
                    <div
                        class="flex h-11 w-11 items-center justify-center rounded-xl bg-gradient-to-br from-purple-500/15 to-pink-500/25 text-purple-700 shadow-2xs transition-transform duration-300 group-hover:scale-105 dark:from-purple-500/25 dark:to-pink-500/35 dark:text-purple-300"
                    >
                        <MessageSquare class="h-5 w-5 stroke-[2]" />
                    </div>
                    <span
                        class="inline-flex items-center gap-1 rounded-full bg-purple-50 px-2.5 py-0.5 text-[10px] font-bold text-purple-700 dark:bg-purple-950/60 dark:text-purple-300"
                    >
                        Ruang Chat
                    </span>
                </div>
                <div class="mt-4">
                    <div class="flex items-center justify-between">
                        <h3
                            class="font-serif text-base font-semibold text-surface-950 dark:text-surface-50"
                        >
                            Ruang Kolaborasi
                        </h3>
                        <ArrowUpRight
                            class="h-4 w-4 text-surface-400 transition-transform duration-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-purple-600"
                        />
                    </div>
                    <p
                        class="mt-1 text-xs leading-relaxed text-surface-500 dark:text-surface-400"
                    >
                        Diskusi langsung tim pengembang, berbagi catatan, dan
                        sinkronisasi kerja.
                    </p>
                </div>
            </Link>
        </section>

        <!-- 3. BAGIAN A: FAQ & PINTASAN KEYBOARD INTERAKTIF (Balanced 7 : 5 Grid - No Whitespace Gap) -->
        <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-12">
            <!-- Kolom Kiri: FAQ & Panduan Solusi Cepat (7 Cols) -->
            <section
                class="rounded-3xl border border-surface-200/80 bg-white p-6 shadow-xs sm:p-7 lg:col-span-7 dark:border-surface-800 dark:bg-surface-900"
            >
                <div
                    class="flex flex-col gap-2 border-b border-surface-100 pb-4 sm:flex-row sm:items-center sm:justify-between dark:border-surface-800"
                >
                    <div>
                        <h2
                            class="font-serif text-xl font-normal text-surface-950 dark:text-surface-50"
                        >
                            Tanya Jawab Populer (FAQ)
                        </h2>
                        <p
                            class="text-xs text-surface-500 dark:text-surface-400"
                        >
                            Solusi praktis untuk pertanyaan yang sering
                            ditanyakan
                        </p>
                    </div>
                    <span
                        class="self-start rounded-full bg-surface-100 px-3 py-1 text-xs font-semibold text-surface-600 sm:self-auto dark:bg-surface-800 dark:text-surface-300"
                    >
                        {{ filteredFaqs.length }} Solusi
                    </span>
                </div>

                <!-- Category Filter Tabs -->
                <div class="mt-4 flex flex-wrap gap-1.5">
                    <button
                        v-for="cat in categoryTabs"
                        :key="cat.key"
                        type="button"
                        class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition"
                        :class="
                            selectedCategory === cat.key
                                ? 'bg-surface-900 text-white shadow-2xs dark:bg-white dark:text-surface-900'
                                : 'bg-surface-100/80 text-surface-600 hover:bg-surface-200/70 hover:text-surface-900 dark:bg-surface-800/80 dark:text-surface-300 dark:hover:bg-surface-700 dark:hover:text-white'
                        "
                        @click="selectedCategory = cat.key"
                    >
                        <component
                            :is="getIcon(cat.icon)"
                            class="h-3.5 w-3.5"
                        />
                        <span>{{ cat.label }}</span>
                    </button>
                </div>

                <!-- FAQ Accordion List -->
                <div class="mt-5 space-y-3">
                    <div
                        v-if="filteredFaqs.length === 0"
                        class="rounded-2xl border border-dashed border-surface-200 p-8 text-center dark:border-surface-800"
                    >
                        <p
                            class="font-serif text-base font-semibold text-surface-800 dark:text-surface-200"
                        >
                            Tidak ada jawaban yang cocok dengan "{{
                                searchQuery
                            }}"
                        </p>
                        <p class="mt-1 text-xs text-surface-400">
                            Coba kata kunci lain atau kirimkan pertanyaan Anda
                            melalui formulir tiket di bawah.
                        </p>
                        <button
                            type="button"
                            class="mt-4 inline-flex items-center gap-1.5 rounded-full bg-surface-100 px-4 py-1.5 text-xs font-bold text-surface-800 hover:bg-surface-200 dark:bg-surface-800 dark:text-surface-200 dark:hover:bg-surface-700"
                            @click="
                                searchQuery = '';
                                selectedCategory = 'all';
                            "
                        >
                            Reset Filter Pencarian
                        </button>
                    </div>

                    <div
                        v-for="faq in filteredFaqs"
                        :key="faq.id"
                        class="overflow-hidden rounded-2xl border transition-all duration-200"
                        :class="
                            activeFaqIndex === faq.id
                                ? 'border-emerald-500/60 bg-white shadow-2xs ring-1 ring-emerald-500/20 dark:border-emerald-500/60 dark:bg-surface-900'
                                : 'border-surface-200/80 bg-surface-50/40 hover:border-surface-300 dark:border-surface-800 dark:bg-surface-950/20 dark:hover:border-surface-700'
                        "
                    >
                        <button
                            type="button"
                            class="flex w-full items-center justify-between gap-3 p-4 text-left transition hover:text-emerald-700 dark:hover:text-emerald-400"
                            @click="toggleFaq(faq.id)"
                        >
                            <div class="flex items-center gap-3">
                                <span
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-white shadow-2xs dark:bg-surface-800"
                                >
                                    <component
                                        :is="getIcon(faq.icon)"
                                        class="h-4 w-4 text-emerald-700 dark:text-emerald-400"
                                    />
                                </span>
                                <div>
                                    <span
                                        class="block text-xs font-semibold text-surface-950 sm:text-sm dark:text-surface-50"
                                    >
                                        {{ faq.question }}
                                    </span>
                                    <div
                                        class="mt-0.5 flex items-center gap-2 text-[11px] text-surface-400"
                                    >
                                        <span>{{ faq.categoryLabel }}</span>
                                        <span>·</span>
                                        <span>{{ faq.readTime }}</span>
                                    </div>
                                </div>
                            </div>
                            <component
                                :is="
                                    activeFaqIndex === faq.id
                                        ? ChevronUp
                                        : ChevronDown
                                "
                                class="h-4 w-4 shrink-0 text-surface-400 transition-transform duration-200"
                            />
                        </button>

                        <div
                            v-show="activeFaqIndex === faq.id"
                            class="border-t border-surface-100 bg-[#FAF9F4]/40 px-4.5 pt-3 pb-4 text-xs leading-relaxed text-surface-600 sm:text-sm dark:border-surface-800 dark:bg-surface-950/40 dark:text-surface-300"
                        >
                            <p>{{ faq.answer }}</p>

                            <!-- Helpful Rating Indicator -->
                            <div
                                class="mt-3.5 flex items-center justify-between border-t border-surface-200/60 pt-3 dark:border-surface-800"
                            >
                                <span class="text-[11px] text-surface-400">
                                    Apakah panduan ini membantu Anda?
                                </span>
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-lg border px-2.5 py-1 text-[11px] font-semibold transition active:scale-95"
                                        :class="
                                            helpfulFeedback[faq.id] === 'yes'
                                                ? 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300'
                                                : 'border-surface-200 bg-white text-surface-600 hover:bg-surface-50 dark:border-surface-700 dark:bg-surface-800 dark:text-surface-300'
                                        "
                                        @click="rateFaq(faq.id, 'yes')"
                                    >
                                        <ThumbsUp class="h-3 w-3" />
                                        <span>Membantu</span>
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex items-center gap-1 rounded-lg border px-2.5 py-1 text-[11px] font-semibold transition active:scale-95"
                                        :class="
                                            helpfulFeedback[faq.id] === 'no'
                                                ? 'border-rose-300 bg-rose-50 text-rose-800 dark:border-rose-800 dark:bg-rose-950/50 dark:text-rose-300'
                                                : 'border-surface-200 bg-white text-surface-600 hover:bg-surface-50 dark:border-surface-700 dark:bg-surface-800 dark:text-surface-300'
                                        "
                                        @click="rateFaq(faq.id, 'no')"
                                    >
                                        <ThumbsDown class="h-3 w-3" />
                                        <span>Kurang Jelas</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Kolom Kanan: Pintasan Keyboard 100% Berfungsi & Kontak Langsung (5 Cols) -->
            <div class="space-y-4 lg:col-span-5">
                <!-- Card 1: Pintasan Keyboard Navigasi Cepat (100% Interaktif & Berfungsi) -->
                <div
                    class="rounded-3xl border border-surface-200/80 bg-white p-6 shadow-xs sm:p-7 dark:border-surface-800 dark:bg-surface-900"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <div
                                class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-500/10 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300"
                            >
                                <Keyboard class="h-5 w-5" />
                            </div>
                            <div>
                                <h3
                                    class="font-serif text-base font-semibold text-surface-950 dark:text-surface-50"
                                >
                                    Pintasan Keyboard
                                </h3>
                                <p
                                    class="text-[11px] text-surface-500 dark:text-surface-400"
                                >
                                    Navigasi instan keyboard (Aktif & Berfungsi)
                                </p>
                            </div>
                        </div>
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full border border-emerald-200/80 bg-emerald-50 px-2.5 py-1 text-[10px] font-bold text-emerald-800 dark:border-emerald-800/40 dark:bg-emerald-950/40 dark:text-emerald-300"
                        >
                            <span
                                class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"
                            />
                            Aktif
                        </span>
                    </div>

                    <!-- Live Key Indicator HUD -->
                    <div
                        v-if="activeKeyNotice"
                        class="mt-4 flex items-center justify-between rounded-xl border border-emerald-300 bg-emerald-50/90 p-2.5 text-xs font-semibold text-emerald-900 transition-all dark:border-emerald-800 dark:bg-emerald-950/80 dark:text-emerald-200"
                    >
                        <div class="flex items-center gap-2">
                            <Sparkles
                                class="h-4 w-4 animate-spin text-emerald-600 dark:text-emerald-400"
                            />
                            <span class="truncate">{{ activeKeyNotice }}</span>
                        </div>
                        <span
                            class="font-mono text-[10px] font-bold text-emerald-700 dark:text-emerald-300"
                            >Live</span
                        >
                    </div>

                    <!-- Interactive Shortcut List -->
                    <div class="mt-4 space-y-2">
                        <div
                            v-for="shortcut in keyboardShortcuts"
                            :key="shortcut.id"
                            class="group flex items-center justify-between rounded-xl border p-2.5 text-xs transition-all duration-200"
                            :class="
                                activeShortcutId === shortcut.id
                                    ? 'border-emerald-500 bg-emerald-50/80 shadow-2xs ring-2 ring-emerald-500/30 dark:border-emerald-500 dark:bg-emerald-950/60'
                                    : 'border-surface-200/70 bg-surface-50/50 hover:border-surface-300 hover:bg-surface-100/70 dark:border-surface-800 dark:bg-surface-950/30 dark:hover:border-surface-700 dark:hover:bg-surface-800/60'
                            "
                        >
                            <div class="min-w-0 pr-2">
                                <span
                                    class="font-medium text-surface-900 dark:text-surface-100"
                                >
                                    {{ shortcut.label }}
                                </span>
                                <span
                                    class="block truncate text-[10px] text-surface-400"
                                >
                                    {{ shortcut.description }}
                                </span>
                            </div>

                            <div class="flex shrink-0 items-center gap-2">
                                <!-- Keyboard badges -->
                                <div
                                    class="flex items-center gap-1 font-mono text-[10px]"
                                >
                                    <template
                                        v-for="(k, idx) in shortcut.keys"
                                        :key="idx"
                                    >
                                        <kbd
                                            class="rounded border border-surface-300 bg-white px-1.5 py-0.5 font-bold shadow-2xs dark:border-surface-700 dark:bg-surface-800 dark:text-surface-200"
                                        >
                                            {{ k }}
                                        </kbd>
                                        <span
                                            v-if="
                                                idx < shortcut.keys.length - 1
                                            "
                                            class="text-[9px] text-surface-400"
                                        >
                                            {{
                                                shortcut.keys.length > 2
                                                    ? '+'
                                                    : 'lalu'
                                            }}
                                        </span>
                                    </template>
                                </div>

                                <!-- Run / Try Button -->
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1 rounded-lg border border-surface-200 bg-white px-2 py-1 text-[10px] font-bold text-surface-700 shadow-2xs transition hover:border-emerald-500 hover:bg-emerald-50 hover:text-emerald-800 active:scale-95 dark:border-surface-700 dark:bg-surface-800 dark:text-surface-200 dark:hover:border-emerald-500 dark:hover:bg-emerald-950/60 dark:hover:text-emerald-300"
                                    title="Klik untuk langsung menjalankan tindakan pintasan ini"
                                    @click="runShortcut(shortcut)"
                                >
                                    <Play class="h-2.5 w-2.5 fill-current" />
                                    <span>Jalankan</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Saluran Dukungan Resmi & Obrolan Langsung -->
                <div
                    class="rounded-3xl border border-surface-200/80 bg-white p-6 shadow-xs sm:p-7 dark:border-surface-800 dark:bg-surface-900"
                >
                    <div class="flex items-center gap-2.5">
                        <div
                            class="flex h-9 w-9 items-center justify-center rounded-xl bg-teal-500/10 text-teal-700 dark:bg-teal-500/20 dark:text-teal-300"
                        >
                            <Mail class="h-5 w-5" />
                        </div>
                        <div>
                            <h3
                                class="font-serif text-base font-semibold text-surface-950 dark:text-surface-50"
                            >
                                Saluran Kontak Langsung
                            </h3>
                            <p
                                class="text-[11px] text-surface-500 dark:text-surface-400"
                            >
                                Email resmi & ruang obrolan internal tim
                            </p>
                        </div>
                    </div>

                    <div
                        class="mt-4 rounded-2xl border border-surface-200/90 bg-surface-50/70 p-3.5 dark:border-surface-800 dark:bg-surface-950/40"
                    >
                        <span
                            class="block text-[11px] font-semibold text-surface-500 dark:text-surface-400"
                        >
                            Email Dukungan Resmi:
                        </span>
                        <div
                            class="mt-1.5 flex items-center justify-between gap-2"
                        >
                            <span
                                class="font-mono text-xs font-bold text-surface-900 dark:text-surface-100"
                            >
                                support@project.local
                            </span>
                            <button
                                type="button"
                                class="inline-flex items-center gap-1 rounded-xl border border-surface-200 bg-white px-2.5 py-1 text-xs font-semibold text-surface-700 shadow-2xs transition hover:border-emerald-500 hover:text-emerald-700 active:scale-95 dark:border-surface-700 dark:bg-surface-800 dark:text-surface-200 dark:hover:border-emerald-500 dark:hover:text-emerald-300"
                                @click="copySupportEmail"
                            >
                                <component
                                    :is="copiedEmail ? Check : Copy"
                                    class="h-3.5 w-3.5 text-emerald-600"
                                />
                                <span>{{
                                    copiedEmail ? 'Tersalin' : 'Salin'
                                }}</span>
                            </button>
                        </div>
                    </div>

                    <div
                        class="mt-3 flex items-center justify-between rounded-2xl border border-surface-200/90 bg-surface-50/70 p-3.5 dark:border-surface-800 dark:bg-surface-950/40"
                    >
                        <div>
                            <span
                                class="block text-xs font-bold text-surface-900 dark:text-surface-100"
                            >
                                Ruang Obrolan Kolaborasi
                            </span>
                            <span
                                class="text-[11px] text-surface-500 dark:text-surface-400"
                            >
                                Diskusi instan dengan rekan kerja
                            </span>
                        </div>
                        <Link
                            :href="'/collaboration'"
                            class="inline-flex items-center gap-1 rounded-xl bg-surface-900 px-3 py-1.5 text-xs font-bold text-white transition hover:bg-black active:scale-95 dark:bg-white dark:text-surface-900 dark:hover:bg-surface-100"
                        >
                            <span>Masuk Chat</span>
                            <span>→</span>
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- 4. BAGIAN B: FORMULIR TIKET DUKUNGAN & STATUS LAYANAN (Balanced 7 : 5 Grid - No Whitespace Gap) -->
        <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-12">
            <!-- Kolom Kiri: Formulir Pengajuan Tiket Dukungan Teknis (7 Cols) -->
            <section
                class="rounded-3xl border border-surface-200/80 bg-white p-6 shadow-xs sm:p-7 lg:col-span-7 dark:border-surface-800 dark:bg-surface-900"
            >
                <div
                    class="flex items-center justify-between border-b border-surface-100 pb-4 dark:border-surface-800"
                >
                    <div class="flex items-center gap-3">
                        <span
                            class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300"
                        >
                            <Send class="h-4.5 w-4.5" />
                        </span>
                        <div>
                            <h2
                                class="font-serif text-xl font-normal text-surface-950 dark:text-surface-50"
                            >
                                Kirim Tiket Dukungan Teknis
                            </h2>
                            <p
                                class="text-xs text-surface-500 dark:text-surface-400"
                            >
                                Respon langsung ke email akun terdaftar Anda
                                dalam hitungan menit
                            </p>
                        </div>
                    </div>
                    <span
                        class="rounded-full bg-surface-100 px-3 py-1 text-xs font-semibold text-surface-600 dark:bg-surface-800 dark:text-surface-300"
                    >
                        Tiket Baru
                    </span>
                </div>

                <!-- Success Alert Banner -->
                <div
                    v-if="ticketSubmitted"
                    class="mt-5 flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50/90 p-4 text-emerald-900 dark:border-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-200"
                >
                    <CheckCircle2 class="h-5 w-5 shrink-0 text-emerald-600" />
                    <div>
                        <h4 class="text-xs font-bold">
                            Tiket Dukungan Berhasil Diajukan!
                        </h4>
                        <p
                            class="mt-0.5 text-xs text-emerald-800 dark:text-emerald-300"
                        >
                            Nomor referensi tiket Anda:
                            <strong
                                >#TK-{{
                                    Math.floor(1000 + Math.random() * 9000)
                                }}</strong
                            >. Tim teknis kami akan merespon selambat-lambatnya
                            30 menit ke email terdaftar Anda.
                        </p>
                    </div>
                </div>

                <!-- Ticket Form -->
                <form
                    class="mt-5 space-y-4"
                    @submit.prevent="submitSupportTicket"
                >
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label
                                class="block text-xs font-bold tracking-wider text-surface-500 uppercase dark:text-surface-400"
                            >
                                Kategori Masalah
                            </label>
                            <select
                                v-model="ticketForm.category"
                                class="mt-1.5 h-10 w-full rounded-xl border border-surface-200 bg-surface-50/60 px-3 text-xs font-medium text-surface-900 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/10 focus:outline-hidden dark:border-surface-700 dark:bg-surface-800 dark:text-surface-100"
                            >
                                <option>Pertanyaan Teknis</option>
                                <option>Laporan Bug / Error</option>
                                <option>Hak Akses & Peran</option>
                                <option>Permintaan Fitur Baru</option>
                                <option>Lainnya</option>
                            </select>
                        </div>

                        <div>
                            <label
                                class="block text-xs font-bold tracking-wider text-surface-500 uppercase dark:text-surface-400"
                            >
                                Tingkat Prioritas
                            </label>
                            <div class="mt-1.5 flex gap-2">
                                <button
                                    v-for="p in priorities"
                                    :key="p.key"
                                    type="button"
                                    class="flex flex-1 items-center justify-center gap-1.5 rounded-xl border px-2 py-2 text-xs font-semibold transition"
                                    :class="
                                        ticketForm.priority === p.key
                                            ? 'border-emerald-500 bg-emerald-50/80 text-emerald-900 ring-1 ring-emerald-500 dark:border-emerald-500 dark:bg-emerald-950/60 dark:text-emerald-200'
                                            : 'border-surface-200 bg-white text-surface-600 hover:bg-surface-50 dark:border-surface-700 dark:bg-surface-800 dark:text-surface-300'
                                    "
                                    @click="ticketForm.priority = p.key"
                                >
                                    <span
                                        class="h-2 w-2 rounded-full"
                                        :class="p.dot"
                                    />
                                    <span>{{ p.key }}</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label
                            class="block text-xs font-bold tracking-wider text-surface-500 uppercase dark:text-surface-400"
                        >
                            Subjek Pertanyaan / Kendala
                        </label>
                        <input
                            v-model="ticketForm.subject"
                            type="text"
                            placeholder="Contoh: Terjadi kendala saat mengubah status tugas di Kanban"
                            required
                            class="mt-1.5 h-10 w-full rounded-xl border border-surface-200 bg-surface-50/60 px-3 text-xs font-medium text-surface-900 placeholder:text-surface-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/10 focus:outline-hidden dark:border-surface-700 dark:bg-surface-800 dark:text-surface-100"
                        />
                    </div>

                    <div>
                        <div class="flex items-center justify-between">
                            <label
                                class="block text-xs font-bold tracking-wider text-surface-500 uppercase dark:text-surface-400"
                            >
                                Rincian Kendala & Kronologi
                            </label>
                            <span class="text-[11px] text-surface-400">
                                {{ ticketForm.message.length }} karakter
                            </span>
                        </div>
                        <textarea
                            v-model="ticketForm.message"
                            rows="4"
                            placeholder="Jelaskan secara rinci kendala yang dialami, pesan kesalahan yang muncul, atau pertanyaan yang ingin disampaikan..."
                            required
                            class="mt-1.5 w-full rounded-xl border border-surface-200 bg-surface-50/60 p-3 text-xs leading-relaxed text-surface-900 placeholder:text-surface-400 focus:border-emerald-500 focus:ring-2 focus:ring-emerald-500/10 focus:outline-hidden dark:border-surface-700 dark:bg-surface-800 dark:text-surface-100"
                        />
                    </div>

                    <!-- Attachment Zone -->
                    <div>
                        <label
                            class="block text-xs font-bold tracking-wider text-surface-500 uppercase dark:text-surface-400"
                        >
                            Lampiran Bukti / Tangkapan Layar (Opsional)
                        </label>
                        <div
                            v-if="attachedFileName"
                            class="mt-1.5 flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50/60 px-3 py-2 text-xs font-semibold text-emerald-800 dark:border-emerald-800/40 dark:bg-emerald-950/40 dark:text-emerald-300"
                        >
                            <div class="flex items-center gap-2">
                                <Paperclip class="h-4 w-4" />
                                <span>{{ attachedFileName }}</span>
                            </div>
                            <button
                                type="button"
                                class="text-surface-400 hover:text-rose-600"
                                @click="removeAttachment"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>

                        <label
                            v-else
                            class="mt-1.5 flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-surface-300 bg-surface-50/40 py-2.5 text-xs font-medium text-surface-600 transition hover:border-emerald-500 hover:bg-surface-50 dark:border-surface-700 dark:bg-surface-950/20 dark:text-surface-400"
                        >
                            <Paperclip class="h-4 w-4 text-surface-400" />
                            <span
                                >Pilih gambar / tangkapan layar pendukung (PNG,
                                JPG, PDF)</span
                            >
                            <input
                                type="file"
                                class="hidden"
                                accept="image/*,.pdf"
                                @change="handleFileSelect"
                            />
                        </label>
                    </div>

                    <div
                        class="flex flex-col gap-3 pt-2 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <span
                            class="text-[11px] text-surface-500 dark:text-surface-400"
                        >
                            Akun Pelapor: <strong>{{ user?.name }}</strong> ({{
                                user?.email
                            }})
                        </span>

                        <button
                            type="submit"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-700 px-5 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-emerald-800 active:scale-95 disabled:opacity-50 dark:bg-emerald-600 dark:hover:bg-emerald-500"
                            :disabled="
                                !ticketForm.subject.trim() ||
                                !ticketForm.message.trim()
                            "
                        >
                            <Send class="h-4 w-4" />
                            <span>Kirim Tiket Sekarang</span>
                        </button>
                    </div>
                </form>
            </section>

            <!-- Kolom Kanan: Status Layanan & Riwayat Tiket (5 Cols - Matching Height) -->
            <div class="space-y-4 lg:col-span-5">
                <!-- Card 1: Riwayat Tiket Terkini -->
                <div
                    class="rounded-3xl border border-surface-200/80 bg-white p-6 shadow-xs sm:p-7 dark:border-surface-800 dark:bg-surface-900"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <div
                                class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300"
                            >
                                <Clock class="h-5 w-5" />
                            </div>
                            <div>
                                <h3
                                    class="font-serif text-base font-semibold text-surface-950 dark:text-surface-50"
                                >
                                    Riwayat Tiket Terkini
                                </h3>
                                <p
                                    class="text-[11px] text-surface-500 dark:text-surface-400"
                                >
                                    Status tiket yang pernah Anda ajukan
                                </p>
                            </div>
                        </div>
                        <span
                            class="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300"
                        >
                            2 Selesai
                        </span>
                    </div>

                    <div class="mt-4 space-y-2.5">
                        <div
                            v-for="tk in recentTickets"
                            :key="tk.id"
                            class="rounded-2xl border border-surface-200/70 bg-surface-50/50 p-3 text-xs dark:border-surface-800 dark:bg-surface-950/30"
                        >
                            <div class="flex items-center justify-between">
                                <span
                                    class="font-mono text-[11px] font-bold text-surface-700 dark:text-surface-300"
                                >
                                    {{ tk.id }}
                                </span>
                                <span
                                    class="rounded-full border px-2 py-0.5 text-[10px] font-bold"
                                    :class="tk.statusClass"
                                >
                                    {{ tk.status }}
                                </span>
                            </div>
                            <p
                                class="mt-1 font-medium text-surface-900 dark:text-surface-100"
                            >
                                {{ tk.subject }}
                            </p>
                            <span
                                class="mt-1 block text-[10px] text-surface-400"
                                >{{ tk.date }}</span
                            >
                        </div>
                    </div>
                </div>

                <!-- Card 2: Komitmen Jaminan SLA Layanan -->
                <div
                    class="rounded-3xl border border-surface-200/80 bg-white p-6 shadow-xs sm:p-7 dark:border-surface-800 dark:bg-surface-900"
                >
                    <div class="flex items-center gap-2.5">
                        <div
                            class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-300"
                        >
                            <ShieldCheck class="h-5 w-5" />
                        </div>
                        <div>
                            <h3
                                class="font-serif text-base font-semibold text-surface-950 dark:text-surface-50"
                            >
                                Jaminan Layanan & SLA
                            </h3>
                            <p
                                class="text-[11px] text-surface-500 dark:text-surface-400"
                            >
                                Standar keandalan platform DigitalCode
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 space-y-2 text-xs">
                        <div
                            class="flex items-start gap-2.5 rounded-xl bg-surface-50/60 p-2.5 dark:bg-surface-950/30"
                        >
                            <CheckCircle
                                class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600"
                            />
                            <div>
                                <strong
                                    class="text-surface-900 dark:text-surface-100"
                                    >Respon Cepat Prioritas</strong
                                >
                                <p
                                    class="text-[11px] text-surface-500 dark:text-surface-400"
                                >
                                    Maksimal 30 menit respon teknis pada jam
                                    kerja operasional aktif.
                                </p>
                            </div>
                        </div>

                        <div
                            class="flex items-start gap-2.5 rounded-xl bg-surface-50/60 p-2.5 dark:bg-surface-950/30"
                        >
                            <CheckCircle
                                class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600"
                            />
                            <div>
                                <strong
                                    class="text-surface-900 dark:text-surface-100"
                                    >Enkripsi Data Mutakhir</strong
                                >
                                <p
                                    class="text-[11px] text-surface-500 dark:text-surface-400"
                                >
                                    Seluruh berkas dokumen dan riwayat tiket
                                    dilindungi standar TLS 1.3.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Akses Cepat Pengaturan Akun & Keamanan -->
                <div
                    class="rounded-3xl border border-surface-200/80 bg-gradient-to-br from-[#FAF9F4] to-surface-100/60 p-5 shadow-2xs dark:border-surface-800 dark:from-surface-900 dark:to-surface-950"
                >
                    <div class="flex items-center gap-2">
                        <Sparkles
                            class="h-4 w-4 text-emerald-600 dark:text-emerald-400"
                        />
                        <h4
                            class="text-xs font-bold text-surface-900 dark:text-surface-100"
                        >
                            Pengaturan & Keamanan Akun
                        </h4>
                    </div>
                    <p
                        class="mt-1 text-xs leading-relaxed text-surface-500 dark:text-surface-400"
                    >
                        Atur profil pengguna, aktifkan Passkeys biometrik,
                        otentikasi dua faktor, dan preferensi tema.
                    </p>
                    <Link
                        :href="'/settings'"
                        class="mt-3 inline-flex items-center gap-1.5 text-xs font-bold text-emerald-700 hover:underline dark:text-emerald-400"
                    >
                        <span>Buka Pengaturan Akun</span>
                        <span>→</span>
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
