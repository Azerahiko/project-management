<script setup lang="ts">
import { computed, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import integrations from '@/routes/integrations';
import AppLayout from '@/layouts/AppLayout.vue';
import Button from '@/components/ui/Button.vue';
import Modal from '@/components/ui/Modal.vue';
import Toggle from '@/components/ui/Toggle.vue';
import { getIcon } from '@/components/sidebar/icons';
import { useToast } from '@/composables/useToast';

defineOptions({
    layout: AppLayout,
});

interface TokenItem {
    id: number;
    name: string;
    abilities: string[];
    last_used_at: string | null;
    last_used_at_iso: string | null;
    expires_at: string | null;
    expires_at_iso: string | null;
    is_expired: boolean;
    created_at: string | null;
}

interface NewTokenData {
    name: string;
    plain_text_token: string;
    expires_at: string;
}

interface ScopeInfo {
    label: string;
    description: string;
}

interface EndpointSummary {
    method: string;
    path: string;
    description: string;
    sample_body: string;
    permission: string;
}

interface WebhookItem {
    id: number;
    name: string;
    url: string;
    secret: string | null;
    events: string[];
    is_active: boolean;
    last_delivery_status: string | null;
    last_delivery_code: number | null;
    last_delivery_latency_ms: number | null;
    last_delivery_error: string | null;
    last_delivery_at: string | null;
    last_delivery_at_iso: string | null;
    created_by: string | null;
    created_at: string | null;
}

interface IntegrationStats {
    tokens_count: number;
    active_tokens_count: number;
    expired_tokens_count: number;
    webhooks_count: number;
    active_webhooks_count: number;
    successful_deliveries_count: number;
    failed_deliveries_count: number;
    delivery_success_rate: number | null;
}

interface Props {
    tokens: TokenItem[];
    new_token: NewTokenData | null;
    base_url: string;
    available_scopes: Record<string, ScopeInfo>;
    endpoints_summary: EndpointSummary[];
    webhooks: WebhookItem[];
    webhook_events: Record<string, string>;
    can_manage_webhooks: boolean;
    stats: IntegrationStats;
}

const props = defineProps<Props>();
const page = usePage();
const toast = useToast();

type TabKey = 'tokens' | 'webhooks' | 'console' | 'docs';

interface IntegrationTab {
    key: TabKey;
    label: string;
    icon: string;
    count?: number;
}

const integrationTabs: IntegrationTab[] = [
    { key: 'tokens', label: 'Kunci API', icon: 'KeyRound' },
    { key: 'webhooks', label: 'Webhook', icon: 'Radio' },
    { key: 'console', label: 'API Console', icon: 'Terminal' },
    { key: 'docs', label: 'Dokumentasi', icon: 'BookOpen' },
];

const activeTab = ref<TabKey>('tokens');
const copiedLabel = ref<string | null>(null);

function flashMessage(): { success?: string; error?: string } {
    return (page.props.flash ?? {}) as unknown as {
        success?: string;
        error?: string;
    };
}

function notifyFlash(fallbackTitle: string): void {
    const flash = flashMessage();

    if (flash.error) {
        toast.error(fallbackTitle, flash.error);
        return;
    }

    toast.success(fallbackTitle, flash.success ?? 'Permintaan berhasil.');
}

async function copyText(value: string, label: string): Promise<void> {
    try {
        await navigator.clipboard.writeText(value);
        copiedLabel.value = label;
        toast.success('Tersalin', `${label} disalin ke clipboard.`);
        setTimeout(() => {
            if (copiedLabel.value === label) {
                copiedLabel.value = null;
            }
        }, 2500);
    } catch {
        toast.error('Gagal Menyalin', 'Silakan salin teks secara manual.');
    }
}

function switchTab(tab: TabKey): void {
    activeTab.value = tab;
}

/* ------------------------------------------------------------------ */
/* Stats                                                               */
/* ------------------------------------------------------------------ */

const averageLatency = computed<number | null>(() => {
    const values = props.webhooks
        .map((webhook) => webhook.last_delivery_latency_ms)
        .filter((value): value is number => value !== null);

    if (values.length === 0) return null;

    return Math.round(
        values.reduce((total, value) => total + value, 0) / values.length,
    );
});

const activeWebhookCount = computed(
    () => props.webhooks.filter((webhook) => webhook.is_active).length,
);

const tabCounts = computed<Record<string, number>>(() => ({
    tokens: props.stats.tokens_count,
    webhooks: props.stats.webhooks_count,
}));

/* ------------------------------------------------------------------ */
/* API tokens                                                          */
/* ------------------------------------------------------------------ */

const tokenSearch = ref('');

const filteredTokens = computed(() => {
    const query = tokenSearch.value.trim().toLowerCase();

    if (!query) return props.tokens;

    return props.tokens.filter(
        (token) =>
            token.name.toLowerCase().includes(query) ||
            token.abilities.some((ability) => ability.includes(query)),
    );
});

const createTokenModal = ref(false);
const revokeTokenModal = ref(false);
const tokenToRevoke = ref<TokenItem | null>(null);

const tokenForm = useForm({
    name: '',
    abilities: ['*'] as string[],
    expires_in_days: 30,
});

function openCreateToken(): void {
    tokenForm.reset();
    tokenForm.clearErrors();
    tokenForm.name = '';
    tokenForm.abilities = ['*'];
    tokenForm.expires_in_days = 30;
    createTokenModal.value = true;
}

function closeCreateToken(): void {
    createTokenModal.value = false;
    tokenForm.clearErrors();
}

function toggleScope(scope: string): void {
    if (scope === '*') {
        tokenForm.abilities = ['*'];
        return;
    }

    let selected = tokenForm.abilities.filter((value) => value !== '*');

    if (selected.includes(scope)) {
        selected = selected.filter((value) => value !== scope);
    } else {
        selected.push(scope);
    }

    tokenForm.abilities = selected.length > 0 ? selected : ['*'];
}

function submitToken(): void {
    tokenForm.post(integrations.tokens.store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            createTokenModal.value = false;
            notifyFlash('Kunci API Dibuat');
        },
        onError: () => {
            toast.error(
                'Gagal Membuat Kunci',
                'Periksa nama token, masa berlaku, dan pilihan izin.',
            );
        },
    });
}

function confirmRevokeToken(token: TokenItem): void {
    tokenToRevoke.value = token;
    revokeTokenModal.value = true;
}

function revokeToken(): void {
    if (!tokenToRevoke.value) return;

    router.delete(
        integrations.tokens.destroy.url({ token: tokenToRevoke.value.id }),
        {
            preserveScroll: true,
            onSuccess: () => {
                revokeTokenModal.value = false;
                tokenToRevoke.value = null;
                notifyFlash('Kunci API Dicabut');
            },
            onError: () => {
                toast.error(
                    'Gagal Mencabut Kunci',
                    'Terjadi kesalahan sistem.',
                );
            },
        },
    );
}

/* ------------------------------------------------------------------ */
/* Webhooks                                                            */
/* ------------------------------------------------------------------ */

const webhookModal = ref(false);
const editingWebhook = ref<WebhookItem | null>(null);
const deleteWebhookModal = ref(false);
const webhookToDelete = ref<WebhookItem | null>(null);
const testingWebhookId = ref<number | null>(null);
const togglingWebhookId = ref<number | null>(null);
const revealedSecrets = ref<number[]>([]);

const webhookForm = useForm({
    name: '',
    url: '',
    events: [] as string[],
    is_active: true,
});

const webhookEventEntries = computed(() =>
    Object.entries(props.webhook_events).map(([slug, label]) => ({
        slug,
        label,
    })),
);

function openCreateWebhook(presetName = ''): void {
    editingWebhook.value = null;
    webhookForm.reset();
    webhookForm.clearErrors();
    webhookForm.name = presetName;
    webhookForm.url = '';
    webhookForm.events = webhookEventEntries.value
        .slice(0, 2)
        .map((event) => event.slug);
    webhookForm.is_active = true;
    webhookModal.value = true;
}

function openEditWebhook(webhook: WebhookItem): void {
    editingWebhook.value = webhook;
    webhookForm.clearErrors();
    webhookForm.name = webhook.name;
    webhookForm.url = webhook.url;
    webhookForm.events = [...webhook.events];
    webhookForm.is_active = webhook.is_active;
    webhookModal.value = true;
}

function closeWebhookModal(): void {
    webhookModal.value = false;
    editingWebhook.value = null;
    webhookForm.clearErrors();
}

function toggleWebhookEvent(event: string): void {
    if (webhookForm.events.includes(event)) {
        webhookForm.events = webhookForm.events.filter(
            (value) => value !== event,
        );
    } else {
        webhookForm.events = [...webhookForm.events, event];
    }
}

function submitWebhook(): void {
    if (webhookForm.events.length === 0) {
        toast.error(
            'Event Belum Dipilih',
            'Pilih minimal satu event yang ingin dikirim.',
        );
        return;
    }

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            const wasEditing = editingWebhook.value !== null;
            webhookModal.value = false;
            editingWebhook.value = null;
            notifyFlash(wasEditing ? 'Webhook Diperbarui' : 'Webhook Dibuat');
        },
        onError: () => {
            toast.error(
                'Gagal Menyimpan Webhook',
                'Periksa nama, URL, dan event yang dipilih.',
            );
        },
    };

    if (editingWebhook.value) {
        webhookForm.put(
            integrations.webhooks.update.url({
                webhook: editingWebhook.value.id,
            }),
            options,
        );
        return;
    }

    webhookForm.post(integrations.webhooks.store.url(), options);
}

function toggleWebhook(webhook: WebhookItem): void {
    togglingWebhookId.value = webhook.id;

    router.patch(
        integrations.webhooks.toggle.url({ webhook: webhook.id }),
        {},
        {
            preserveScroll: true,
            onSuccess: () => notifyFlash('Status Webhook Diperbarui'),
            onError: () => {
                toast.error('Gagal Mengubah Status', 'Coba lagi.');
            },
            onFinish: () => {
                togglingWebhookId.value = null;
            },
        },
    );
}

function testWebhook(webhook: WebhookItem): void {
    testingWebhookId.value = webhook.id;

    router.post(
        integrations.webhooks.test.url({ webhook: webhook.id }),
        {},
        {
            preserveScroll: true,
            onSuccess: () => notifyFlash('Uji Webhook Selesai'),
            onError: () => {
                toast.error('Gagal Menguji Webhook', 'Coba lagi.');
            },
            onFinish: () => {
                testingWebhookId.value = null;
            },
        },
    );
}

function confirmDeleteWebhook(webhook: WebhookItem): void {
    webhookToDelete.value = webhook;
    deleteWebhookModal.value = true;
}

function deleteWebhook(): void {
    if (!webhookToDelete.value) return;

    router.delete(
        integrations.webhooks.destroy.url({
            webhook: webhookToDelete.value.id,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                deleteWebhookModal.value = false;
                webhookToDelete.value = null;
                notifyFlash('Webhook Dihapus');
            },
            onError: () => {
                toast.error('Gagal Menghapus Webhook', 'Coba lagi.');
            },
        },
    );
}

function revealSecret(webhook: WebhookItem): void {
    revealedSecrets.value = revealedSecrets.value.includes(webhook.id)
        ? revealedSecrets.value.filter((id) => id !== webhook.id)
        : [...revealedSecrets.value, webhook.id];
}

function deliveryTone(webhook: WebhookItem): string {
    if (!webhook.last_delivery_status) {
        return 'bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-300';
    }

    return webhook.last_delivery_status === 'success'
        ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
        : 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300';
}

/* ------------------------------------------------------------------ */
/* API console                                                         */
/* ------------------------------------------------------------------ */

const consoleEndpointIndex = ref(0);
const consoleToken = ref(props.new_token?.plain_text_token ?? '');
const consoleBody = ref('');
const consoleRunning = ref(false);
const consoleStatus = ref<number | null>(null);
const consoleDuration = ref<number | null>(null);
const consoleResponse = ref<string | null>(null);

const consoleEndpoint = computed(
    () => props.endpoints_summary[consoleEndpointIndex.value] ?? null,
);

const consoleUrl = computed(() => {
    if (!consoleEndpoint.value) return props.base_url;

    return `${props.base_url}${consoleEndpoint.value.path}`;
});

const consoleHasBody = computed(
    () =>
        consoleEndpoint.value !== null &&
        ['POST', 'PUT', 'PATCH'].includes(
            consoleEndpoint.value.method.toUpperCase(),
        ),
);

function selectConsoleEndpoint(index: number): void {
    consoleEndpointIndex.value = index;
    const endpoint = props.endpoints_summary[index];

    consoleBody.value = endpoint?.sample_body ?? '';
    consoleStatus.value = null;
    consoleResponse.value = null;
    consoleDuration.value = null;
}

function resetConsoleResponse(): void {
    consoleStatus.value = null;
    consoleResponse.value = null;
    consoleDuration.value = null;
}

async function runConsoleRequest(): Promise<void> {
    const endpoint = consoleEndpoint.value;

    if (!endpoint) return;

    let body: string | undefined;

    if (consoleHasBody.value && consoleBody.value.trim()) {
        try {
            JSON.parse(consoleBody.value);
        } catch {
            consoleStatus.value = 400;
            consoleDuration.value = 0;
            consoleResponse.value = JSON.stringify(
                {
                    error: 'JSON tidak valid',
                    detail: 'Perbaiki format request body sebelum mengirim.',
                },
                null,
                2,
            );
            toast.error(
                'JSON Tidak Valid',
                'Request body harus berupa JSON yang valid.',
            );
            return;
        }

        body = consoleBody.value.trim();
    }

    consoleRunning.value = true;
    resetConsoleResponse();

    const startedAt = performance.now();

    try {
        const headers: Record<string, string> = {
            Accept: 'application/json',
        };

        if (body) {
            headers['Content-Type'] = 'application/json';
        }

        if (consoleToken.value.trim()) {
            headers.Authorization = `Bearer ${consoleToken.value.trim()}`;
        }

        const response = await fetch(consoleUrl.value, {
            method: endpoint.method.toUpperCase(),
            headers,
            body,
        });

        const duration = Math.round(performance.now() - startedAt);
        const text = await response.text();

        consoleDuration.value = duration;
        consoleStatus.value = response.status;

        if (text.trim() === '') {
            consoleResponse.value =
                response.status === 204
                    ? '(204 No Content) Permintaan berhasil tanpa isi respons.'
                    : '(Respons kosong)';
        } else {
            try {
                consoleResponse.value = JSON.stringify(
                    JSON.parse(text),
                    null,
                    2,
                );
            } catch {
                consoleResponse.value = text;
            }
        }

        if (response.ok) {
            toast.success(
                `HTTP ${response.status}`,
                `Respons diterima dalam ${duration}ms.`,
            );
        } else {
            toast.error(
                `HTTP ${response.status}`,
                'Endpoint menolak permintaan. Periksa token dan hak akses.',
            );
        }
    } catch (error) {
        consoleDuration.value = Math.round(performance.now() - startedAt);
        consoleStatus.value = null;
        consoleResponse.value = JSON.stringify(
            {
                error: 'Gagal menghubungi endpoint',
                detail: error instanceof Error ? error.message : String(error),
            },
            null,
            2,
        );
        toast.error(
            'Koneksi Gagal',
            'Periksa URL, koneksi jaringan, atau konfigurasi CORS.',
        );
    } finally {
        consoleRunning.value = false;
    }
}

/* ------------------------------------------------------------------ */
/* Documentation & samples                                             */
/* ------------------------------------------------------------------ */

const sampleEvent = ref(Object.keys(props.webhook_events)[0] ?? 'task.created');

const samplePayload = computed(() => {
    let data: Record<string, unknown>;

    switch (sampleEvent.value) {
        case 'task.created':
            data = {
                task_id: 42,
                title: 'Contoh tugas baru dari API',
                project: { id: 7, name: 'Contoh Proyek' },
                status: 'todo',
                priority: 'medium',
                assignee: { id: 3, name: 'Budi Santoso' },
                creator: { id: 1, name: 'Administrator' },
            };
            break;
        case 'task.status_changed':
            data = {
                task_id: 42,
                title: 'Contoh tugas baru dari API',
                project: { id: 7, name: 'Contoh Proyek' },
                previous_status: 'in_progress',
                new_status: 'done',
                changed_by: { id: 1, name: 'Administrator' },
            };
            break;
        case 'comment.added':
            data = {
                comment_id: 128,
                content:
                    'Progres sudah saya periksa, lanjutkan ke tahap review.',
                task: { id: 42, title: 'Contoh tugas baru dari API' },
                project: { id: 7, name: 'Contoh Proyek' },
                author: { id: 3, name: 'Budi Santoso' },
            };
            break;
        case 'project.created':
            data = {
                project_id: 7,
                name: 'Contoh Proyek',
                status: 'active',
                priority: 'medium',
                owner: { id: 1, name: 'Administrator' },
                created_by: { id: 1, name: 'Administrator' },
            };
            break;
        default:
            data = {
                project_id: 7,
                name: 'Contoh Proyek',
                previous_status: 'active',
                new_status: 'completed',
                completed_by: { id: 1, name: 'Administrator' },
            };
    }

    return JSON.stringify(
        {
            id: '8f14e45f-ea0a-4c3b-9f21-2f0d9c7b6a51',
            event: sampleEvent.value,
            created_at: new Date().toISOString(),
            data,
        },
        null,
        2,
    );
});

const signatureSnippet = computed(
    () => `$payload = file_get_contents('php://input');
$secret = 'secret-dari-halaman-webhook'; // salin dari detail webhook
$expected = 'sha256=' . hash_hmac('sha256', $payload, $secret);
$received = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';

if (!hash_equals($expected, $received)) {
    http_response_code(401);
    exit('Signature tidak valid');
}

// Lanjutkan memproses payload JSON.`,
);

const integrationRecipes = [
    {
        name: 'Slack',
        icon: 'MessageSquare',
        description:
            'Kirim ringkasan aktivitas ke channel Slack melalui Incoming Webhook.',
        steps: [
            'Buat Incoming Webhook di Slack dan salin URL-nya.',
            'Daftarkan URL tersebut di tab Webhook aplikasi ini.',
            'Pilih event yang ingin dikirim, lalu simpan.',
            'Gunakan tombol Uji untuk memastikan Slack menerima payload.',
        ],
    },
    {
        name: 'Discord',
        icon: 'MessagesSquare',
        description:
            'Teruskan event proyek ke channel Discord menggunakan webhook channel.',
        steps: [
            'Salin Webhook URL dari pengaturan channel Discord.',
            'Buat webhook baru di aplikasi ini dengan URL tersebut.',
            'Aktifkan event tugas atau komentar yang relevan.',
            'Kirim payload uji untuk memverifikasi koneksi.',
        ],
    },
    {
        name: 'Zapier / Make',
        icon: 'Sparkles',
        description:
            'Gunakan Catch Hook atau Webhook trigger untuk menghubungkan ribuan aplikasi.',
        steps: [
            'Buat Zap dengan trigger Webhooks → Catch Hook.',
            'Salin URL catch hook yang diberikan.',
            'Daftarkan URL itu sebagai webhook di aplikasi ini.',
            'Uji pengiriman, lalu lanjutkan langkah otomatisasi di Zapier.',
        ],
    },
    {
        name: 'Server Sendiri',
        icon: 'Server',
        description:
            'Terima payload JSON bertanda tangan HMAC pada endpoint HTTP milik Anda.',
        steps: [
            'Siapkan endpoint HTTPS yang menerima POST JSON.',
            'Verifikasi header X-Webhook-Signature dengan secret webhook.',
            'Balas dengan status 2xx untuk menandai pengiriman berhasil.',
            'Gunakan pengujian bawaan untuk melihat status dan latensi.',
        ],
    },
];

function curlForEndpoint(endpoint: EndpointSummary): string {
    const lines = [
        `curl -X ${endpoint.method} '${props.base_url}${endpoint.path}' \\`,
        `  -H 'Accept: application/json' \\`,
        `  -H 'Authorization: Bearer YOUR_API_TOKEN'`,
    ];

    if (endpoint.sample_body) {
        lines[2] += ' \\';
        lines.push(`  -H 'Content-Type: application/json' \\`);
        lines.push(`  -d '${endpoint.sample_body.replaceAll("'", "'\\''")}'`);
    }

    return lines.join('\n');
}
</script>

<template>
    <div class="space-y-6 pb-20">
        <!-- Header -->
        <header
            class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between"
        >
            <div>
                <span
                    class="inline-flex h-6 items-center gap-1.5 rounded-full border border-primary-200/80 bg-primary-50 px-2.5 text-[10px] font-bold tracking-wider text-primary-800 uppercase dark:border-primary-800/40 dark:bg-primary-950/40 dark:text-primary-300"
                >
                    <component :is="getIcon('Cpu')" class="h-3 w-3" />
                    Developer Hub
                </span>
                <h1
                    class="mt-2 text-2xl font-semibold tracking-tight text-surface-950 sm:text-3xl dark:text-surface-50"
                >
                    Integrasi API & Webhook
                </h1>
                <p
                    class="mt-1 max-w-2xl text-sm leading-relaxed text-surface-600 dark:text-surface-300"
                >
                    Kelola kunci akses personal, uji endpoint langsung, dan
                    kirim event ruang kerja ke sistem lain melalui webhook
                    bertanda tangan.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Button
                    variant="secondary"
                    size="sm"
                    class="text-xs"
                    @click="switchTab('console')"
                >
                    <component :is="getIcon('Terminal')" class="h-3.5 w-3.5" />
                    API Console
                </Button>
                <Button
                    variant="primary"
                    size="sm"
                    class="text-xs"
                    @click="openCreateToken"
                >
                    <component :is="getIcon('Plus')" class="h-3.5 w-3.5" />
                    Kunci API Baru
                </Button>
            </div>
        </header>

        <!-- Stats -->
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div
                class="rounded-2xl border border-surface-200/80 bg-white p-4 shadow-xs dark:border-surface-800 dark:bg-surface-900"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-[10px] font-bold tracking-wider text-surface-500 uppercase dark:text-surface-400"
                    >
                        Kunci API Aktif
                    </span>
                    <span
                        class="grid h-8 w-8 place-items-center rounded-lg bg-primary-50 text-primary-700 dark:bg-primary-950/40 dark:text-primary-300"
                    >
                        <component :is="getIcon('KeyRound')" class="h-4 w-4" />
                    </span>
                </div>
                <p
                    class="mt-2 font-serif text-3xl text-surface-950 dark:text-surface-50"
                >
                    {{ stats.active_tokens_count }}
                </p>
                <p
                    class="mt-0.5 text-[11px] text-surface-500 dark:text-surface-400"
                >
                    {{ stats.tokens_count }} total ·
                    {{ stats.expired_tokens_count }} kedaluwarsa
                </p>
            </div>

            <div
                class="rounded-2xl border border-surface-200/80 bg-white p-4 shadow-xs dark:border-surface-800 dark:bg-surface-900"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-[10px] font-bold tracking-wider text-surface-500 uppercase dark:text-surface-400"
                    >
                        Webhook Aktif
                    </span>
                    <span
                        class="grid h-8 w-8 place-items-center rounded-lg bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300"
                    >
                        <component :is="getIcon('Radio')" class="h-4 w-4" />
                    </span>
                </div>
                <p
                    class="mt-2 font-serif text-3xl text-surface-950 dark:text-surface-50"
                >
                    {{ stats.active_webhooks_count }}
                </p>
                <p
                    class="mt-0.5 text-[11px] text-surface-500 dark:text-surface-400"
                >
                    dari {{ stats.webhooks_count }} webhook terdaftar
                </p>
            </div>

            <div
                class="rounded-2xl border border-surface-200/80 bg-white p-4 shadow-xs dark:border-surface-800 dark:bg-surface-900"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-[10px] font-bold tracking-wider text-surface-500 uppercase dark:text-surface-400"
                    >
                        Tingkat Sukses
                    </span>
                    <span
                        class="grid h-8 w-8 place-items-center rounded-lg bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300"
                    >
                        <component :is="getIcon('Activity')" class="h-4 w-4" />
                    </span>
                </div>
                <p
                    class="mt-2 font-serif text-3xl text-surface-950 dark:text-surface-50"
                >
                    {{
                        stats.delivery_success_rate === null
                            ? '—'
                            : `${stats.delivery_success_rate}%`
                    }}
                </p>
                <p
                    class="mt-0.5 text-[11px] text-surface-500 dark:text-surface-400"
                >
                    Berdasarkan pengiriman terakhir tiap webhook
                </p>
            </div>

            <div
                class="rounded-2xl border border-surface-200/80 bg-white p-4 shadow-xs dark:border-surface-800 dark:bg-surface-900"
            >
                <div class="flex items-center justify-between">
                    <span
                        class="text-[10px] font-bold tracking-wider text-surface-500 uppercase dark:text-surface-400"
                    >
                        Latensi Pengiriman
                    </span>
                    <span
                        class="grid h-8 w-8 place-items-center rounded-lg bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-300"
                    >
                        <component :is="getIcon('Clock')" class="h-4 w-4" />
                    </span>
                </div>
                <p
                    class="mt-2 font-serif text-3xl text-surface-950 dark:text-surface-50"
                >
                    {{ averageLatency === null ? '—' : `${averageLatency}ms` }}
                </p>
                <p
                    class="mt-0.5 text-[11px] text-surface-500 dark:text-surface-400"
                >
                    Rata-rata dari pengiriman terakhir
                </p>
            </div>
        </div>

        <!-- New token banner -->
        <div
            v-if="new_token"
            class="relative overflow-hidden rounded-3xl border border-emerald-300 bg-emerald-50/95 p-5 shadow-sm dark:border-emerald-800 dark:bg-emerald-950/40"
        >
            <div class="flex items-start gap-4">
                <div
                    class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl bg-emerald-600 text-white shadow-xs dark:bg-emerald-500"
                >
                    <component
                        :is="getIcon('Check')"
                        class="h-5 w-5 stroke-[2.5]"
                    />
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2
                            class="text-sm font-bold text-emerald-950 dark:text-emerald-100"
                        >
                            Kunci API dibuat: {{ new_token.name }}
                        </h2>
                        <span
                            class="rounded-full bg-emerald-200/80 px-2 py-0.5 text-[10px] font-semibold text-emerald-900 dark:bg-emerald-800 dark:text-emerald-100"
                        >
                            {{ new_token.expires_at }}
                        </span>
                    </div>
                    <p
                        class="mt-1 text-xs text-emerald-800 dark:text-emerald-200"
                    >
                        Salin sekarang. Demi keamanan, kunci tidak ditampilkan
                        lagi setelah Anda meninggalkan halaman ini.
                    </p>

                    <div
                        class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center"
                    >
                        <div
                            class="flex-1 overflow-x-auto rounded-xl border border-emerald-200 bg-white/95 px-3.5 py-2 font-mono text-xs font-semibold text-emerald-950 select-all dark:border-emerald-800/80 dark:bg-surface-900 dark:text-emerald-300"
                        >
                            {{ new_token.plain_text_token }}
                        </div>
                        <Button
                            variant="secondary"
                            size="sm"
                            class="shrink-0 bg-white hover:bg-emerald-50 dark:bg-surface-800"
                            @click="
                                copyText(
                                    new_token.plain_text_token,
                                    'Kunci API',
                                )
                            "
                        >
                            <component
                                :is="
                                    getIcon(
                                        copiedLabel === 'Kunci API'
                                            ? 'Check'
                                            : 'Copy',
                                    )
                                "
                                class="h-3.5 w-3.5"
                            />
                            {{
                                copiedLabel === 'Kunci API'
                                    ? 'Tersalin'
                                    : 'Salin Kunci'
                            }}
                        </Button>
                        <Button
                            variant="secondary"
                            size="sm"
                            class="shrink-0 bg-white dark:bg-surface-800"
                            @click="
                                consoleToken = new_token.plain_text_token;
                                switchTab('console');
                            "
                        >
                            <component
                                :is="getIcon('Terminal')"
                                class="h-3.5 w-3.5"
                            />
                            Coba di Console
                        </Button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="border-b border-surface-200 dark:border-surface-800">
            <nav
                class="no-scrollbar flex gap-1 overflow-x-auto sm:gap-4"
                role="tablist"
                aria-label="Bagian integrasi"
            >
                <button
                    v-for="tab in integrationTabs"
                    :key="tab.key"
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === tab.key"
                    class="flex shrink-0 items-center gap-2 border-b-2 px-3 py-3 text-xs font-semibold whitespace-nowrap transition-colors sm:text-sm"
                    :class="
                        activeTab === tab.key
                            ? 'border-[#0F5132] text-[#0F5132] dark:border-primary-400 dark:text-primary-300'
                            : 'border-transparent text-surface-500 hover:text-surface-800 dark:text-surface-400 dark:hover:text-surface-200'
                    "
                    @click="switchTab(tab.key)"
                >
                    <component :is="getIcon(tab.icon)" class="h-4 w-4" />
                    {{ tab.label }}
                    <span
                        v-if="tabCounts[tab.key] !== undefined"
                        class="rounded-full bg-surface-100 px-2 py-0.5 text-[10px] font-bold text-surface-600 dark:bg-surface-800 dark:text-surface-300"
                    >
                        {{ tabCounts[tab.key] }}
                    </span>
                </button>
            </nav>
        </div>

        <!-- TOKENS -->
        <section v-if="activeTab === 'tokens'" class="space-y-4">
            <div
                class="overflow-hidden rounded-3xl border border-surface-200/80 bg-white shadow-xs dark:border-surface-800 dark:bg-surface-900"
            >
                <div
                    class="flex flex-col gap-3 border-b border-surface-100 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6 dark:border-surface-800"
                >
                    <div>
                        <h2
                            class="text-base font-semibold text-surface-950 dark:text-surface-50"
                        >
                            Kunci Akses Personal
                        </h2>
                        <p
                            class="mt-0.5 text-xs text-surface-500 dark:text-surface-400"
                        >
                            Kunci ini hanya dapat digunakan oleh akun Anda dan
                            memiliki cakupan izin yang Anda pilih.
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <div class="relative w-44 sm:w-60">
                            <span
                                class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-surface-400"
                            >
                                <component
                                    :is="getIcon('Search')"
                                    class="h-3.5 w-3.5"
                                />
                            </span>
                            <input
                                v-model="tokenSearch"
                                type="search"
                                placeholder="Cari token..."
                                class="input py-1.5 pl-9 text-xs"
                            />
                        </div>
                        <Button
                            variant="primary"
                            size="sm"
                            class="shrink-0 text-xs"
                            @click="openCreateToken"
                        >
                            <component
                                :is="getIcon('Plus')"
                                class="h-3.5 w-3.5"
                            />
                            Buat
                        </Button>
                    </div>
                </div>

                <div
                    v-if="filteredTokens.length > 0"
                    class="divide-y divide-surface-100 dark:divide-surface-800"
                >
                    <div
                        v-for="token in filteredTokens"
                        :key="token.id"
                        class="flex flex-col gap-4 p-5 transition-colors hover:bg-surface-50/50 sm:flex-row sm:items-center sm:justify-between dark:hover:bg-surface-800/30"
                    >
                        <div class="flex min-w-0 flex-1 items-start gap-3.5">
                            <span
                                class="grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-surface-100 text-surface-700 dark:bg-surface-800 dark:text-surface-300"
                            >
                                <component
                                    :is="getIcon('KeyRound')"
                                    class="h-5 w-5"
                                />
                            </span>

                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3
                                        class="truncate text-sm font-semibold text-surface-950 dark:text-surface-50"
                                    >
                                        {{ token.name }}
                                    </h3>
                                    <span
                                        v-if="token.is_expired"
                                        class="inline-flex items-center rounded-full border border-rose-200 bg-rose-50 px-2 py-0.5 text-[10px] font-semibold text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300"
                                    >
                                        Kedaluwarsa
                                    </span>
                                    <span
                                        v-else-if="token.expires_at"
                                        class="inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300"
                                    >
                                        Berlaku s/d {{ token.expires_at }}
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300"
                                    >
                                        Tanpa kedaluwarsa
                                    </span>
                                </div>

                                <div
                                    class="mt-2 flex flex-wrap items-center gap-2 text-xs text-surface-500 dark:text-surface-400"
                                >
                                    <span
                                        >Dibuat
                                        {{ token.created_at ?? '—' }}</span
                                    >
                                    <span>•</span>
                                    <span>
                                        Terakhir dipakai:
                                        <strong
                                            class="font-medium text-surface-700 dark:text-surface-300"
                                        >
                                            {{
                                                token.last_used_at ??
                                                'Belum pernah'
                                            }}
                                        </strong>
                                    </span>
                                </div>

                                <div class="mt-2.5 flex flex-wrap gap-1.5">
                                    <span
                                        v-for="ability in token.abilities"
                                        :key="ability"
                                        class="rounded-md bg-surface-100 px-2 py-0.5 font-mono text-[10px] font-medium text-surface-700 dark:bg-surface-800 dark:text-surface-300"
                                    >
                                        {{
                                            ability === '*'
                                                ? 'Akses Penuh (*)'
                                                : ability
                                        }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <Button
                            variant="destructive"
                            size="sm"
                            class="shrink-0 text-xs"
                            @click="confirmRevokeToken(token)"
                        >
                            <component
                                :is="getIcon('Trash2')"
                                class="h-3.5 w-3.5"
                            />
                            Cabut
                        </Button>
                    </div>
                </div>

                <div v-else class="p-12 text-center">
                    <span
                        class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-surface-100 text-surface-400 dark:bg-surface-800 dark:text-surface-500"
                    >
                        <component :is="getIcon('KeyRound')" class="h-6 w-6" />
                    </span>
                    <h3
                        class="mt-3 text-sm font-semibold text-surface-900 dark:text-surface-100"
                    >
                        {{
                            tokenSearch
                                ? 'Tidak Ada Kunci yang Cocok'
                                : 'Belum Ada Kunci API'
                        }}
                    </h3>
                    <p
                        class="mx-auto mt-1 max-w-sm text-xs text-surface-500 dark:text-surface-400"
                    >
                        {{
                            tokenSearch
                                ? 'Coba kata kunci lain atau kosongkan pencarian.'
                                : 'Buat kunci API untuk mengakses endpoint REST ruang kerja dari aplikasi atau skrip Anda.'
                        }}
                    </p>
                    <Button
                        v-if="!tokenSearch"
                        variant="primary"
                        size="sm"
                        class="mt-4"
                        @click="openCreateToken"
                    >
                        <component :is="getIcon('Plus')" class="h-4 w-4" />
                        Buat Kunci API
                    </Button>
                </div>
            </div>

            <div
                class="rounded-3xl border border-surface-200/80 bg-white p-5 shadow-xs sm:p-6 dark:border-surface-800 dark:bg-surface-900"
            >
                <div class="flex items-start gap-4">
                    <span
                        class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl bg-primary-50 text-primary-800 dark:bg-primary-950/50 dark:text-primary-300"
                    >
                        <component :is="getIcon('Shield')" class="h-5 w-5" />
                    </span>
                    <div>
                        <h3
                            class="text-sm font-semibold text-surface-950 dark:text-surface-50"
                        >
                            Praktik Aman Menggunakan Kunci API
                        </h3>
                        <p
                            class="mt-1 text-xs leading-relaxed text-surface-600 dark:text-surface-300"
                        >
                            Simpan token di environment variable, jangan
                            menuliskannya di repository publik, dan gunakan
                            cakupan izin sesempit mungkin. Jika token bocor,
                            cabut segera melalui tombol di atas.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- WEBHOOKS -->
        <section v-else-if="activeTab === 'webhooks'" class="space-y-4">
            <div
                v-if="!can_manage_webhooks"
                class="flex items-start gap-3 rounded-2xl border border-amber-200/80 bg-amber-50/70 p-4 text-xs text-amber-900 dark:border-amber-800/40 dark:bg-amber-950/20 dark:text-amber-200"
            >
                <component
                    :is="getIcon('Lock')"
                    class="mt-0.5 h-4 w-4 shrink-0"
                />
                <div>
                    <p class="font-semibold">Akses hanya baca</p>
                    <p class="mt-0.5 leading-relaxed opacity-90">
                        Hanya pengguna dengan izin mengelola role yang dapat
                        membuat, mengubah, atau menguji webhook. Anda tetap
                        dapat melihat daftar webhook yang terdaftar.
                    </p>
                </div>
            </div>

            <div
                class="overflow-hidden rounded-3xl border border-surface-200/80 bg-white shadow-xs dark:border-surface-800 dark:bg-surface-900"
            >
                <div
                    class="flex flex-col gap-3 border-b border-surface-100 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-6 dark:border-surface-800"
                >
                    <div>
                        <h2
                            class="text-base font-semibold text-surface-950 dark:text-surface-50"
                        >
                            Endpoint Webhook Keluar
                        </h2>
                        <p
                            class="mt-0.5 text-xs text-surface-500 dark:text-surface-400"
                        >
                            Sistem mengirim POST JSON bertanda tangan setiap
                            kali event terpilih terjadi.
                        </p>
                    </div>

                    <Button
                        v-if="can_manage_webhooks"
                        variant="primary"
                        size="sm"
                        class="text-xs"
                        @click="openCreateWebhook()"
                    >
                        <component :is="getIcon('Plus')" class="h-3.5 w-3.5" />
                        Daftarkan Webhook
                    </Button>
                </div>

                <div
                    v-if="props.webhooks.length > 0"
                    class="divide-y divide-surface-100 dark:divide-surface-800"
                >
                    <div
                        v-for="webhook in props.webhooks"
                        :key="webhook.id"
                        class="p-5 transition-colors hover:bg-surface-50/40 sm:p-6 dark:hover:bg-surface-800/20"
                    >
                        <div
                            class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                        >
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3
                                        class="text-sm font-semibold text-surface-950 dark:text-surface-50"
                                    >
                                        {{ webhook.name }}
                                    </h3>
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                        :class="
                                            webhook.is_active
                                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                                                : 'bg-surface-100 text-surface-600 dark:bg-surface-800 dark:text-surface-300'
                                        "
                                    >
                                        <span
                                            class="h-1.5 w-1.5 rounded-full"
                                            :class="
                                                webhook.is_active
                                                    ? 'bg-emerald-500'
                                                    : 'bg-surface-400'
                                            "
                                        />
                                        {{
                                            webhook.is_active
                                                ? 'Aktif'
                                                : 'Nonaktif'
                                        }}
                                    </span>
                                    <span
                                        v-if="webhook.last_delivery_status"
                                        class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                        :class="deliveryTone(webhook)"
                                    >
                                        <component
                                            :is="
                                                getIcon(
                                                    webhook.last_delivery_status ===
                                                        'success'
                                                        ? 'CheckCircle2'
                                                        : 'XCircle',
                                                )
                                            "
                                            class="h-3 w-3"
                                        />
                                        {{
                                            webhook.last_delivery_status ===
                                            'success'
                                                ? 'Pengiriman terakhir berhasil'
                                                : 'Pengiriman terakhir gagal'
                                        }}
                                        <template
                                            v-if="
                                                webhook.last_delivery_code !==
                                                null
                                            "
                                        >
                                            (HTTP
                                            {{ webhook.last_delivery_code }})
                                        </template>
                                    </span>
                                </div>

                                <p
                                    class="mt-1 truncate font-mono text-xs text-surface-600 dark:text-surface-300"
                                >
                                    {{ webhook.url }}
                                </p>

                                <div
                                    class="mt-3 flex flex-wrap items-center gap-1.5"
                                >
                                    <span
                                        v-for="event in webhook.events"
                                        :key="event"
                                        class="rounded-md bg-surface-100 px-2 py-0.5 font-mono text-[10px] font-semibold text-primary-800 dark:bg-surface-800 dark:text-primary-300"
                                    >
                                        {{ event }}
                                    </span>
                                </div>

                                <div
                                    class="mt-2 flex flex-wrap items-center gap-3 text-[11px] text-surface-400 dark:text-surface-500"
                                >
                                    <span>
                                        Dibuat oleh
                                        {{ webhook.created_by ?? '—' }}
                                        {{
                                            webhook.created_at
                                                ? `· ${webhook.created_at}`
                                                : ''
                                        }}
                                    </span>
                                    <span v-if="webhook.last_delivery_at">
                                        · Pengiriman terakhir
                                        {{ webhook.last_delivery_at }}
                                    </span>
                                    <span
                                        v-if="
                                            webhook.last_delivery_latency_ms !==
                                            null
                                        "
                                        class="font-mono"
                                    >
                                        ·
                                        {{ webhook.last_delivery_latency_ms }}ms
                                    </span>
                                </div>

                                <div
                                    v-if="webhook.last_delivery_error"
                                    class="mt-2 rounded-xl border border-rose-200/80 bg-rose-50/70 p-2.5 text-[11px] leading-relaxed text-rose-700 dark:border-rose-900/50 dark:bg-rose-950/20 dark:text-rose-300"
                                >
                                    <strong class="font-semibold"
                                        >Error terakhir:</strong
                                    >
                                    {{ webhook.last_delivery_error }}
                                </div>

                                <div
                                    v-if="can_manage_webhooks && webhook.secret"
                                    class="mt-3 rounded-xl border border-surface-200/80 bg-surface-50/60 p-3 dark:border-surface-800 dark:bg-surface-950/30"
                                >
                                    <div
                                        class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div class="min-w-0">
                                            <p
                                                class="text-[10px] font-bold tracking-wider text-surface-500 uppercase dark:text-surface-400"
                                            >
                                                Signing secret
                                            </p>
                                            <p
                                                class="mt-0.5 truncate font-mono text-[11px] text-surface-700 dark:text-surface-300"
                                            >
                                                {{
                                                    revealedSecrets.includes(
                                                        webhook.id,
                                                    )
                                                        ? webhook.secret
                                                        : '••••••••••••••••••••••••••••••••'
                                                }}
                                            </p>
                                        </div>
                                        <div class="flex items-center gap-1.5">
                                            <button
                                                type="button"
                                                class="rounded-lg border border-surface-200 bg-white px-2 py-1 text-[10px] font-semibold text-surface-600 transition hover:text-surface-900 dark:border-surface-700 dark:bg-surface-800 dark:text-surface-300 dark:hover:text-surface-100"
                                                @click="revealSecret(webhook)"
                                            >
                                                {{
                                                    revealedSecrets.includes(
                                                        webhook.id,
                                                    )
                                                        ? 'Sembunyikan'
                                                        : 'Tampilkan'
                                                }}
                                            </button>
                                            <button
                                                type="button"
                                                class="rounded-lg border border-surface-200 bg-white px-2 py-1 text-[10px] font-semibold text-surface-600 transition hover:text-surface-900 dark:border-surface-700 dark:bg-surface-800 dark:text-surface-300 dark:hover:text-surface-100"
                                                @click="
                                                    copyText(
                                                        webhook.secret ?? '',
                                                        `Secret ${webhook.name}`,
                                                    )
                                                "
                                            >
                                                Salin
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="can_manage_webhooks"
                                class="flex shrink-0 flex-wrap items-center gap-2"
                            >
                                <div
                                    class="flex items-center gap-2 rounded-xl border border-surface-200 bg-white px-2.5 py-1.5 dark:border-surface-700 dark:bg-surface-800"
                                >
                                    <Toggle
                                        :model-value="webhook.is_active"
                                        :label="`Aktifkan ${webhook.name}`"
                                        :disabled="
                                            togglingWebhookId === webhook.id
                                        "
                                        @update:model-value="
                                            toggleWebhook(webhook)
                                        "
                                    />
                                    <span
                                        class="text-[10px] font-semibold text-surface-500 dark:text-surface-400"
                                    >
                                        {{
                                            webhook.is_active
                                                ? 'Aktif'
                                                : 'Nonaktif'
                                        }}
                                    </span>
                                </div>
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    class="text-xs"
                                    :loading="testingWebhookId === webhook.id"
                                    @click="testWebhook(webhook)"
                                >
                                    <component
                                        :is="getIcon('Send')"
                                        class="h-3.5 w-3.5"
                                    />
                                    Uji
                                </Button>
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    class="text-xs"
                                    @click="openEditWebhook(webhook)"
                                >
                                    <component
                                        :is="getIcon('Pencil')"
                                        class="h-3.5 w-3.5"
                                    />
                                    Ubah
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    class="text-xs text-rose-600 hover:text-rose-700 dark:text-rose-400"
                                    @click="confirmDeleteWebhook(webhook)"
                                >
                                    <component
                                        :is="getIcon('Trash2')"
                                        class="h-3.5 w-3.5"
                                    />
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="p-12 text-center">
                    <span
                        class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-surface-100 text-surface-400 dark:bg-surface-800 dark:text-surface-500"
                    >
                        <component :is="getIcon('Radio')" class="h-6 w-6" />
                    </span>
                    <h3
                        class="mt-3 text-sm font-semibold text-surface-900 dark:text-surface-100"
                    >
                        Belum Ada Webhook
                    </h3>
                    <p
                        class="mx-auto mt-1 max-w-sm text-xs text-surface-500 dark:text-surface-400"
                    >
                        Daftarkan endpoint penerima event untuk menghubungkan
                        aplikasi ini dengan Slack, Discord, Zapier, atau server
                        Anda sendiri.
                    </p>
                    <Button
                        v-if="can_manage_webhooks"
                        variant="primary"
                        size="sm"
                        class="mt-4"
                        @click="openCreateWebhook()"
                    >
                        <component :is="getIcon('Plus')" class="h-4 w-4" />
                        Daftarkan Webhook Pertama
                    </Button>
                </div>
            </div>

            <div
                class="rounded-3xl border border-surface-200/80 bg-white p-5 shadow-xs sm:p-6 dark:border-surface-800 dark:bg-surface-900"
            >
                <div class="flex items-start gap-4">
                    <span
                        class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl bg-primary-50 text-primary-800 dark:bg-primary-950/50 dark:text-primary-300"
                    >
                        <component
                            :is="getIcon('ShieldCheck')"
                            class="h-5 w-5"
                        />
                    </span>
                    <div>
                        <h3
                            class="text-sm font-semibold text-surface-950 dark:text-surface-50"
                        >
                            Verifikasi Tanda Tangan Webhook
                        </h3>
                        <p
                            class="mt-1 text-xs leading-relaxed text-surface-600 dark:text-surface-300"
                        >
                            Setiap permintaan menyertakan header
                            <code
                                class="rounded bg-surface-100 px-1 py-0.5 font-mono text-[11px] dark:bg-surface-800"
                                >X-Webhook-Signature: sha256=...</code
                            >
                            yang dihitung dari body mentah menggunakan secret
                            webhook. Selalu verifikasi sebelum memproses
                            payload.
                        </p>
                        <a
                            href="#"
                            class="mt-2 inline-flex items-center gap-1 text-[11px] font-semibold text-primary-700 hover:underline dark:text-primary-300"
                            @click.prevent="switchTab('docs')"
                        >
                            Lihat contoh verifikasi
                            <component
                                :is="getIcon('ArrowRight')"
                                class="h-3 w-3"
                            />
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- API CONSOLE -->
        <section v-else-if="activeTab === 'console'" class="space-y-4">
            <div
                class="rounded-3xl border border-surface-200/80 bg-white p-5 shadow-xs sm:p-6 dark:border-surface-800 dark:bg-surface-900"
            >
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div>
                        <h2
                            class="text-base font-semibold text-surface-950 dark:text-surface-50"
                        >
                            API Console
                        </h2>
                        <p
                            class="mt-0.5 text-xs text-surface-500 dark:text-surface-400"
                        >
                            Kirim permintaan nyata ke API dan lihat respons
                            server langsung dari browser.
                        </p>
                    </div>

                    <div
                        v-if="consoleStatus !== null"
                        class="flex items-center gap-2"
                    >
                        <span
                            class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-bold"
                            :class="
                                consoleStatus >= 200 && consoleStatus < 300
                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-300'
                                    : 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-800 dark:bg-rose-950 dark:text-rose-300'
                            "
                        >
                            HTTP {{ consoleStatus }}
                        </span>
                        <span
                            v-if="consoleDuration !== null"
                            class="font-mono text-xs text-surface-400"
                        >
                            {{ consoleDuration }}ms
                        </span>
                    </div>
                </div>

                <div class="mt-5 grid gap-3 lg:grid-cols-[minmax(0,1fr)_180px]">
                    <div>
                        <label
                            for="console-endpoint"
                            class="mb-1 block text-xs font-semibold text-surface-600 dark:text-surface-300"
                        >
                            Endpoint
                        </label>
                        <select
                            id="console-endpoint"
                            class="input py-2 font-mono text-xs"
                            :value="consoleEndpointIndex"
                            @change="
                                selectConsoleEndpoint(
                                    Number(
                                        ($event.target as HTMLSelectElement)
                                            .value,
                                    ),
                                )
                            "
                        >
                            <option
                                v-for="(endpoint, index) in endpoints_summary"
                                :key="`${endpoint.method}-${endpoint.path}`"
                                :value="index"
                            >
                                [{{ endpoint.method }}] {{ endpoint.path }} —
                                {{ endpoint.description }}
                            </option>
                        </select>
                    </div>

                    <div class="flex items-end">
                        <Button
                            variant="primary"
                            size="md"
                            class="w-full text-xs"
                            :loading="consoleRunning"
                            @click="runConsoleRequest"
                        >
                            <component
                                :is="getIcon('Send')"
                                class="h-3.5 w-3.5"
                            />
                            Kirim Request
                        </Button>
                    </div>
                </div>

                <div class="mt-4">
                    <label
                        class="mb-1 block text-xs font-semibold text-surface-600 dark:text-surface-300"
                    >
                        URL Lengkap
                    </label>
                    <div
                        class="flex items-center gap-2 overflow-x-auto rounded-xl border border-surface-200 bg-surface-50 px-3 py-2 dark:border-surface-700 dark:bg-surface-950/40"
                    >
                        <span
                            class="shrink-0 rounded-md bg-primary-50 px-1.5 py-0.5 font-mono text-[10px] font-bold text-primary-700 dark:bg-primary-950/50 dark:text-primary-300"
                        >
                            {{ consoleEndpoint?.method ?? 'GET' }}
                        </span>
                        <code
                            class="font-mono text-xs text-surface-700 dark:text-surface-300"
                            >{{ consoleUrl }}</code
                        >
                        <button
                            type="button"
                            class="ml-auto shrink-0 text-surface-400 transition hover:text-surface-700 dark:hover:text-surface-200"
                            aria-label="Salin URL endpoint"
                            @click="copyText(consoleUrl, 'URL endpoint')"
                        >
                            <component
                                :is="
                                    getIcon(
                                        copiedLabel === 'URL endpoint'
                                            ? 'Check'
                                            : 'Copy',
                                    )
                                "
                                class="h-3.5 w-3.5"
                            />
                        </button>
                    </div>
                </div>

                <div class="mt-4">
                    <label
                        for="console-token"
                        class="mb-1 block text-xs font-semibold text-surface-600 dark:text-surface-300"
                    >
                        Bearer Token
                    </label>
                    <input
                        id="console-token"
                        v-model="consoleToken"
                        type="password"
                        autocomplete="off"
                        class="input font-mono text-xs"
                        placeholder="Tempel kunci API di sini..."
                    />
                    <p
                        class="mt-1 text-[11px] text-surface-400 dark:text-surface-500"
                    >
                        Permintaan tanpa token hanya berhasil jika sesi login
                        Anda diterima oleh endpoint API.
                    </p>
                </div>

                <div v-if="consoleHasBody" class="mt-4">
                    <div class="mb-1 flex items-center justify-between">
                        <label
                            for="console-body"
                            class="text-xs font-semibold text-surface-600 dark:text-surface-300"
                        >
                            Request Body (JSON)
                        </label>
                        <button
                            type="button"
                            class="text-[11px] font-semibold text-primary-700 hover:underline dark:text-primary-300"
                            @click="
                                consoleBody =
                                    consoleEndpoint?.sample_body ?? '{\n  \n}'
                            "
                        >
                            Isi contoh
                        </button>
                    </div>
                    <textarea
                        id="console-body"
                        v-model="consoleBody"
                        rows="6"
                        class="input font-mono text-xs leading-relaxed"
                        placeholder='{ "key": "value" }'
                    />
                </div>

                <div class="mt-5 space-y-2">
                    <div class="flex items-center justify-between">
                        <span
                            class="text-xs font-bold text-surface-700 dark:text-surface-200"
                        >
                            Respons Server
                        </span>
                        <button
                            v-if="consoleResponse"
                            type="button"
                            class="text-xs text-primary-700 hover:underline dark:text-primary-300"
                            @click="copyText(consoleResponse, 'Respons JSON')"
                        >
                            {{
                                copiedLabel === 'Respons JSON'
                                    ? 'Tersalin'
                                    : 'Salin Respons'
                            }}
                        </button>
                    </div>

                    <div
                        class="min-h-[150px] overflow-x-auto rounded-2xl bg-surface-950 p-4 font-mono text-xs leading-relaxed text-emerald-400 shadow-inner dark:bg-black/90"
                    >
                        <div
                            class="mb-3 flex items-center gap-1.5 border-b border-white/10 pb-3 opacity-60"
                        >
                            <span
                                class="h-2.5 w-2.5 rounded-full bg-red-500/80"
                            />
                            <span
                                class="h-2.5 w-2.5 rounded-full bg-amber-500/80"
                            />
                            <span
                                class="h-2.5 w-2.5 rounded-full bg-emerald-500/80"
                            />
                            <span
                                class="ml-2 font-sans text-[10px] text-surface-400"
                            >
                                {{ consoleEndpoint?.method }}
                                {{ consoleEndpoint?.path }}
                            </span>
                        </div>

                        <div
                            v-if="consoleRunning"
                            class="animate-pulse py-6 text-center text-surface-400"
                        >
                            Menghubungi endpoint...
                        </div>
                        <pre v-else-if="consoleResponse">{{
                            consoleResponse
                        }}</pre>
                        <div v-else class="py-6 text-center text-surface-500">
                            Tekan
                            <strong class="text-surface-300"
                                >Kirim Request</strong
                            >
                            untuk melihat respons nyata dari server.
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- DOCS -->
        <section v-else class="space-y-4">
            <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_340px]">
                <div class="space-y-4">
                    <!-- API basics -->
                    <div
                        class="rounded-3xl border border-surface-200/80 bg-white p-5 shadow-xs sm:p-6 dark:border-surface-800 dark:bg-surface-900"
                    >
                        <div class="flex items-center gap-2.5">
                            <span
                                class="grid h-9 w-9 place-items-center rounded-xl bg-primary-50 text-primary-700 dark:bg-primary-950/40 dark:text-primary-300"
                            >
                                <component
                                    :is="getIcon('BookOpen')"
                                    class="h-4 w-4"
                                />
                            </span>
                            <div>
                                <h2
                                    class="text-sm font-semibold text-surface-950 dark:text-surface-50"
                                >
                                    Dasar Penggunaan API
                                </h2>
                                <p
                                    class="text-[11px] text-surface-500 dark:text-surface-400"
                                >
                                    Autentikasi, format respons, dan cakupan
                                    izin.
                                </p>
                            </div>
                        </div>

                        <dl class="mt-5 space-y-3 text-xs">
                            <div>
                                <dt
                                    class="font-semibold text-surface-800 dark:text-surface-200"
                                >
                                    Base URL
                                </dt>
                                <dd class="mt-1 flex items-center gap-2">
                                    <code
                                        class="flex-1 overflow-x-auto rounded-lg bg-surface-100 px-2.5 py-1.5 font-mono text-[11px] text-surface-700 dark:bg-surface-800 dark:text-surface-300"
                                        >{{ base_url }}</code
                                    >
                                    <button
                                        type="button"
                                        class="shrink-0 text-surface-400 hover:text-surface-700 dark:hover:text-surface-200"
                                        aria-label="Salin base URL"
                                        @click="copyText(base_url, 'Base URL')"
                                    >
                                        <component
                                            :is="
                                                getIcon(
                                                    copiedLabel === 'Base URL'
                                                        ? 'Check'
                                                        : 'Copy',
                                                )
                                            "
                                            class="h-3.5 w-3.5"
                                        />
                                    </button>
                                </dd>
                            </div>
                            <div>
                                <dt
                                    class="font-semibold text-surface-800 dark:text-surface-200"
                                >
                                    Header Autentikasi
                                </dt>
                                <dd
                                    class="mt-1 rounded-lg bg-surface-100 px-2.5 py-1.5 font-mono text-[11px] text-surface-700 dark:bg-surface-800 dark:text-surface-300"
                                >
                                    Authorization: Bearer YOUR_API_TOKEN
                                </dd>
                            </div>
                            <div>
                                <dt
                                    class="font-semibold text-surface-800 dark:text-surface-200"
                                >
                                    Format Respons
                                </dt>
                                <dd
                                    class="mt-1 leading-relaxed text-surface-600 dark:text-surface-300"
                                >
                                    JSON. Daftar data menggunakan pembungkus
                                    <code
                                        class="rounded bg-surface-100 px-1 font-mono text-[10px] dark:bg-surface-800"
                                        >data</code
                                    >
                                    dan metadata pagination pada endpoint yang
                                    mendukung.
                                </dd>
                            </div>
                        </dl>

                        <div
                            class="mt-5 border-t border-surface-100 pt-4 dark:border-surface-800"
                        >
                            <h3
                                class="text-[10px] font-bold tracking-wider text-surface-500 uppercase dark:text-surface-400"
                            >
                                Cakupan Izin Token
                            </h3>
                            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                <div
                                    v-for="(scope, key) in available_scopes"
                                    :key="key"
                                    class="rounded-xl border border-surface-200/80 p-2.5 dark:border-surface-800"
                                >
                                    <p
                                        class="text-[11px] font-semibold text-surface-800 dark:text-surface-200"
                                    >
                                        {{ scope.label }}
                                    </p>
                                    <p
                                        class="mt-0.5 font-mono text-[10px] text-primary-700 dark:text-primary-300"
                                    >
                                        {{ key }}
                                    </p>
                                    <p
                                        class="mt-1 text-[10px] leading-relaxed text-surface-500 dark:text-surface-400"
                                    >
                                        {{ scope.description }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Endpoints -->
                    <div
                        class="overflow-hidden rounded-3xl border border-surface-200/80 bg-white shadow-xs dark:border-surface-800 dark:bg-surface-900"
                    >
                        <div
                            class="border-b border-surface-100 p-5 sm:p-6 dark:border-surface-800"
                        >
                            <h2
                                class="text-sm font-semibold text-surface-950 dark:text-surface-50"
                            >
                                Referensi Endpoint
                            </h2>
                            <p
                                class="mt-0.5 text-[11px] text-surface-500 dark:text-surface-400"
                            >
                                Endpoint utama yang tersedia untuk integrasi.
                            </p>
                        </div>

                        <div
                            class="divide-y divide-surface-100 dark:divide-surface-800"
                        >
                            <div
                                v-for="endpoint in endpoints_summary"
                                :key="`${endpoint.method}-${endpoint.path}`"
                                class="p-5 sm:p-6"
                            >
                                <div class="flex flex-wrap items-center gap-2">
                                    <span
                                        class="rounded-md px-2 py-0.5 font-mono text-[10px] font-bold"
                                        :class="
                                            endpoint.method === 'GET'
                                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300'
                                                : 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300'
                                        "
                                    >
                                        {{ endpoint.method }}
                                    </span>
                                    <code
                                        class="font-mono text-xs font-semibold text-surface-800 dark:text-surface-200"
                                        >{{ endpoint.path }}</code
                                    >
                                    <span
                                        class="rounded-full bg-surface-100 px-2 py-0.5 font-mono text-[10px] text-surface-500 dark:bg-surface-800 dark:text-surface-400"
                                    >
                                        {{ endpoint.permission }}
                                    </span>
                                    <button
                                        type="button"
                                        class="ml-auto text-[11px] font-semibold text-primary-700 hover:underline dark:text-primary-300"
                                        @click="
                                            copyText(
                                                curlForEndpoint(endpoint),
                                                `cURL ${endpoint.path}`,
                                            )
                                        "
                                    >
                                        {{
                                            copiedLabel ===
                                            `cURL ${endpoint.path}`
                                                ? 'Tersalin'
                                                : 'Salin cURL'
                                        }}
                                    </button>
                                </div>
                                <p
                                    class="mt-1.5 text-xs leading-relaxed text-surface-600 dark:text-surface-300"
                                >
                                    {{ endpoint.description }}
                                </p>
                                <div
                                    v-if="endpoint.sample_body"
                                    class="mt-2.5 overflow-x-auto rounded-xl bg-surface-950 p-3 font-mono text-[11px] leading-relaxed text-emerald-400 dark:bg-black/90"
                                >
                                    <pre>{{ endpoint.sample_body }}</pre>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Webhook payload -->
                    <div
                        class="rounded-3xl border border-surface-200/80 bg-white p-5 shadow-xs sm:p-6 dark:border-surface-800 dark:bg-surface-900"
                    >
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div>
                                <h2
                                    class="text-sm font-semibold text-surface-950 dark:text-surface-50"
                                >
                                    Skema Payload Webhook
                                </h2>
                                <p
                                    class="mt-0.5 text-[11px] text-surface-500 dark:text-surface-400"
                                >
                                    Contoh payload untuk event yang dipilih.
                                </p>
                            </div>
                            <select
                                v-model="sampleEvent"
                                class="input py-1.5 text-xs sm:w-56"
                                aria-label="Pilih event contoh"
                            >
                                <option
                                    v-for="(label, slug) in webhook_events"
                                    :key="slug"
                                    :value="slug"
                                >
                                    {{ slug }} — {{ label }}
                                </option>
                            </select>
                        </div>

                        <div
                            class="mt-4 overflow-x-auto rounded-2xl bg-surface-950 p-4 font-mono text-[11px] leading-relaxed text-emerald-400 dark:bg-black/90"
                        >
                            <pre>{{ samplePayload }}</pre>
                        </div>

                        <div class="mt-3 flex justify-end">
                            <button
                                type="button"
                                class="text-[11px] font-semibold text-primary-700 hover:underline dark:text-primary-300"
                                @click="
                                    copyText(
                                        samplePayload,
                                        'Contoh payload webhook',
                                    )
                                "
                            >
                                {{
                                    copiedLabel === 'Contoh payload webhook'
                                        ? 'Tersalin'
                                        : 'Salin Payload'
                                }}
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Sidebar docs -->
                <aside class="space-y-4">
                    <div
                        class="rounded-3xl border border-surface-200/80 bg-white p-5 shadow-xs dark:border-surface-800 dark:bg-surface-900"
                    >
                        <h2
                            class="text-sm font-semibold text-surface-950 dark:text-surface-50"
                        >
                            Verifikasi Signature
                        </h2>
                        <p
                            class="mt-1 text-[11px] leading-relaxed text-surface-500 dark:text-surface-400"
                        >
                            Hitung HMAC-SHA256 dari body mentah, lalu bandingkan
                            dengan header menggunakan perbandingan
                            waktu-konstan.
                        </p>
                        <div
                            class="mt-3 overflow-x-auto rounded-xl bg-surface-950 p-3 font-mono text-[10px] leading-relaxed text-emerald-400 dark:bg-black/90"
                        >
                            <pre>{{ signatureSnippet }}</pre>
                        </div>
                        <button
                            type="button"
                            class="mt-2 text-[11px] font-semibold text-primary-700 hover:underline dark:text-primary-300"
                            @click="
                                copyText(
                                    signatureSnippet,
                                    'Contoh verifikasi signature',
                                )
                            "
                        >
                            {{
                                copiedLabel === 'Contoh verifikasi signature'
                                    ? 'Tersalin'
                                    : 'Salin Contoh PHP'
                            }}
                        </button>
                    </div>

                    <div
                        class="rounded-3xl border border-surface-200/80 bg-white p-5 shadow-xs dark:border-surface-800 dark:bg-surface-900"
                    >
                        <h2
                            class="text-sm font-semibold text-surface-950 dark:text-surface-50"
                        >
                            Header Webhook
                        </h2>
                        <ul class="mt-3 space-y-2 text-[11px]">
                            <li
                                v-for="header in [
                                    {
                                        name: 'X-Webhook-Event',
                                        description: 'Slug event yang dikirim.',
                                    },
                                    {
                                        name: 'X-Webhook-Signature',
                                        description:
                                            'HMAC-SHA256 body dengan prefix sha256=.',
                                    },
                                    {
                                        name: 'X-Webhook-Delivery',
                                        description:
                                            'ID unik untuk setiap percobaan pengiriman.',
                                    },
                                ]"
                                :key="header.name"
                                class="rounded-lg border border-surface-200/80 p-2.5 dark:border-surface-800"
                            >
                                <code
                                    class="font-mono text-[10px] font-semibold text-primary-700 dark:text-primary-300"
                                    >{{ header.name }}</code
                                >
                                <p
                                    class="mt-0.5 text-surface-500 dark:text-surface-400"
                                >
                                    {{ header.description }}
                                </p>
                            </li>
                        </ul>
                    </div>

                    <div
                        class="rounded-3xl border border-primary-200/70 bg-primary-50/50 p-5 text-xs text-primary-900 dark:border-primary-800/40 dark:bg-primary-950/20 dark:text-primary-200"
                    >
                        <h2 class="text-sm font-semibold">Batas Pengiriman</h2>
                        <ul
                            class="mt-2 space-y-1.5 text-[11px] leading-relaxed"
                        >
                            <li>• Timeout koneksi 3 detik, total 6 detik.</li>
                            <li>
                                • Respons 2xx dianggap berhasil; lainnya dicatat
                                gagal.
                            </li>
                            <li>
                                • Status dan latensi terakhir tersimpan pada
                                setiap webhook.
                            </li>
                            <li>
                                • Pengiriman berjalan saat event terjadi, bukan
                                antrean latar.
                            </li>
                        </ul>
                    </div>
                </aside>
            </div>

            <!-- Recipes -->
            <div
                class="rounded-3xl border border-surface-200/80 bg-white p-5 shadow-xs sm:p-6 dark:border-surface-800 dark:bg-surface-900"
            >
                <div
                    class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div>
                        <h2
                            class="text-sm font-semibold text-surface-950 dark:text-surface-50"
                        >
                            Resep Integrasi
                        </h2>
                        <p
                            class="mt-0.5 text-[11px] text-surface-500 dark:text-surface-400"
                        >
                            Panduan nyata menghubungkan webhook ke layanan lain.
                            Status koneksi hanya berubah setelah webhook
                            benar-benar dibuat dan diuji.
                        </p>
                    </div>
                    <span
                        class="inline-flex w-fit items-center gap-1.5 rounded-full bg-surface-100 px-2.5 py-1 text-[10px] font-semibold text-surface-600 dark:bg-surface-800 dark:text-surface-300"
                    >
                        <component :is="getIcon('Radio')" class="h-3 w-3" />
                        {{ activeWebhookCount }} webhook aktif
                    </span>
                </div>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div
                        v-for="recipe in integrationRecipes"
                        :key="recipe.name"
                        class="flex flex-col rounded-2xl border border-surface-200/80 p-4 dark:border-surface-800"
                    >
                        <div class="flex items-center gap-2.5">
                            <span
                                class="grid h-9 w-9 place-items-center rounded-xl bg-surface-100 text-surface-700 dark:bg-surface-800 dark:text-surface-300"
                            >
                                <component
                                    :is="getIcon(recipe.icon)"
                                    class="h-4 w-4"
                                />
                            </span>
                            <div>
                                <h3
                                    class="text-xs font-semibold text-surface-900 dark:text-surface-100"
                                >
                                    {{ recipe.name }}
                                </h3>
                                <p
                                    class="text-[10px] text-surface-500 dark:text-surface-400"
                                >
                                    {{ recipe.description }}
                                </p>
                            </div>
                        </div>
                        <ol
                            class="mt-3 flex-1 list-decimal space-y-1 pl-4 text-[11px] leading-relaxed text-surface-600 dark:text-surface-300"
                        >
                            <li
                                v-for="step in recipe.steps"
                                :key="step"
                                class="pl-0.5"
                            >
                                {{ step }}
                            </li>
                        </ol>
                        <Button
                            variant="secondary"
                            size="sm"
                            class="mt-3 w-fit text-xs"
                            :disabled="!can_manage_webhooks"
                            @click="openCreateWebhook(recipe.name)"
                        >
                            <component
                                :is="getIcon('Plus')"
                                class="h-3.5 w-3.5"
                            />
                            Buat Webhook
                        </Button>
                    </div>
                </div>
            </div>
        </section>

        <!-- CREATE/EDIT TOKEN MODAL -->
        <Modal
            :open="createTokenModal"
            title="Buat Kunci API Baru"
            size="lg"
            @close="closeCreateToken"
        >
            <form class="space-y-4" @submit.prevent="submitToken">
                <div>
                    <label
                        for="token-name"
                        class="mb-1 block text-xs font-semibold text-surface-700 dark:text-surface-200"
                    >
                        Nama Kunci
                        <span class="text-rose-500">*</span>
                    </label>
                    <input
                        id="token-name"
                        v-model="tokenForm.name"
                        type="text"
                        required
                        class="input text-xs sm:text-sm"
                        placeholder="Contoh: Zapier Sync, Skrip Backup"
                    />
                    <p class="mt-1 text-[11px] text-surface-400">
                        Beri nama yang jelas agar mudah dicabut jika tidak
                        digunakan lagi.
                    </p>
                    <p
                        v-if="tokenForm.errors.name"
                        class="mt-1 text-[11px] font-medium text-rose-600 dark:text-rose-400"
                    >
                        {{ tokenForm.errors.name }}
                    </p>
                </div>

                <div>
                    <label
                        for="token-expiry"
                        class="mb-1 block text-xs font-semibold text-surface-700 dark:text-surface-200"
                    >
                        Masa Berlaku
                    </label>
                    <select
                        id="token-expiry"
                        v-model.number="tokenForm.expires_in_days"
                        class="input text-xs sm:text-sm"
                    >
                        <option :value="7">7 hari</option>
                        <option :value="30">30 hari</option>
                        <option :value="90">90 hari</option>
                        <option :value="365">1 tahun</option>
                        <option :value="0">Tidak pernah kedaluwarsa</option>
                    </select>
                </div>

                <div>
                    <p
                        class="mb-2 text-xs font-semibold text-surface-700 dark:text-surface-200"
                    >
                        Cakupan Izin
                    </p>
                    <div
                        class="grid max-h-60 gap-2 overflow-y-auto p-1 sm:grid-cols-2"
                    >
                        <button
                            v-for="(scope, key) in available_scopes"
                            :key="key"
                            type="button"
                            class="rounded-xl border p-3 text-left transition-colors"
                            :class="
                                tokenForm.abilities.includes(key)
                                    ? 'border-primary-600 bg-primary-50/50 dark:border-primary-400 dark:bg-primary-950/30'
                                    : 'border-surface-200 hover:border-surface-300 dark:border-surface-800 dark:hover:border-surface-700'
                            "
                            :aria-pressed="tokenForm.abilities.includes(key)"
                            @click="toggleScope(key)"
                        >
                            <span
                                class="flex items-center justify-between gap-2"
                            >
                                <span
                                    class="text-xs font-semibold text-surface-900 dark:text-surface-100"
                                >
                                    {{ scope.label }}
                                </span>
                                <span
                                    class="grid h-4 w-4 shrink-0 place-items-center rounded-full border"
                                    :class="
                                        tokenForm.abilities.includes(key)
                                            ? 'border-primary-600 bg-primary-600 text-white dark:border-primary-400 dark:bg-primary-400 dark:text-surface-950'
                                            : 'border-surface-300 dark:border-surface-600'
                                    "
                                >
                                    <component
                                        v-if="tokenForm.abilities.includes(key)"
                                        :is="getIcon('Check')"
                                        class="h-3 w-3 stroke-[3]"
                                    />
                                </span>
                            </span>
                            <span
                                class="mt-1 block text-[11px] leading-relaxed text-surface-500 dark:text-surface-400"
                            >
                                {{ scope.description }}
                            </span>
                        </button>
                    </div>
                    <p
                        v-if="tokenForm.errors.abilities"
                        class="mt-1 text-[11px] font-medium text-rose-600 dark:text-rose-400"
                    >
                        {{ tokenForm.errors.abilities }}
                    </p>
                </div>

                <div
                    class="flex justify-end gap-2 border-t border-surface-100 pt-3 dark:border-surface-800"
                >
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        @click="closeCreateToken"
                    >
                        Batal
                    </Button>
                    <Button
                        type="submit"
                        variant="primary"
                        size="sm"
                        :loading="tokenForm.processing"
                        :disabled="!tokenForm.name.trim()"
                    >
                        Buat Kunci
                    </Button>
                </div>
            </form>
        </Modal>

        <!-- REVOKE TOKEN MODAL -->
        <Modal
            :open="revokeTokenModal"
            title="Cabut Kunci API?"
            size="sm"
            @close="
                revokeTokenModal = false;
                tokenToRevoke = null;
            "
        >
            <p
                class="text-xs leading-relaxed text-surface-600 dark:text-surface-300"
            >
                Kunci
                <strong class="text-surface-950 dark:text-surface-100"
                    >"{{ tokenToRevoke?.name }}"</strong
                >
                akan langsung tidak berlaku. Aplikasi yang memakainya akan
                menerima respons 401 Unauthorized.
            </p>

            <template #footer>
                <Button
                    variant="secondary"
                    size="sm"
                    @click="
                        revokeTokenModal = false;
                        tokenToRevoke = null;
                    "
                >
                    Batal
                </Button>
                <Button variant="destructive" size="sm" @click="revokeToken">
                    Cabut Kunci
                </Button>
            </template>
        </Modal>

        <!-- WEBHOOK MODAL -->
        <Modal
            :open="webhookModal"
            :title="editingWebhook ? 'Ubah Webhook' : 'Daftarkan Webhook Baru'"
            size="lg"
            @close="closeWebhookModal"
        >
            <form class="space-y-4" @submit.prevent="submitWebhook">
                <div>
                    <label
                        for="webhook-name"
                        class="mb-1 block text-xs font-semibold text-surface-700 dark:text-surface-200"
                    >
                        Nama Webhook
                        <span class="text-rose-500">*</span>
                    </label>
                    <input
                        id="webhook-name"
                        v-model="webhookForm.name"
                        type="text"
                        required
                        class="input text-xs sm:text-sm"
                        placeholder="Contoh: Slack #project-alerts"
                    />
                    <p
                        v-if="webhookForm.errors.name"
                        class="mt-1 text-[11px] font-medium text-rose-600 dark:text-rose-400"
                    >
                        {{ webhookForm.errors.name }}
                    </p>
                </div>

                <div>
                    <label
                        for="webhook-url"
                        class="mb-1 block text-xs font-semibold text-surface-700 dark:text-surface-200"
                    >
                        URL Tujuan
                        <span class="text-rose-500">*</span>
                    </label>
                    <input
                        id="webhook-url"
                        v-model="webhookForm.url"
                        type="url"
                        required
                        class="input font-mono text-xs"
                        placeholder="https://hooks.slack.com/services/..."
                    />
                    <p class="mt-1 text-[11px] text-surface-400">
                        Harus berupa URL http atau https yang dapat menerima
                        POST JSON.
                    </p>
                    <p
                        v-if="webhookForm.errors.url"
                        class="mt-1 text-[11px] font-medium text-rose-600 dark:text-rose-400"
                    >
                        {{ webhookForm.errors.url }}
                    </p>
                </div>

                <div>
                    <p
                        class="mb-2 text-xs font-semibold text-surface-700 dark:text-surface-200"
                    >
                        Event yang Dikirim
                        <span class="text-rose-500">*</span>
                    </p>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <button
                            v-for="event in webhookEventEntries"
                            :key="event.slug"
                            type="button"
                            class="flex items-start gap-2.5 rounded-xl border p-3 text-left transition-colors"
                            :class="
                                webhookForm.events.includes(event.slug)
                                    ? 'border-primary-600 bg-primary-50/50 dark:border-primary-400 dark:bg-primary-950/30'
                                    : 'border-surface-200 hover:border-surface-300 dark:border-surface-800 dark:hover:border-surface-700'
                            "
                            :aria-pressed="
                                webhookForm.events.includes(event.slug)
                            "
                            @click="toggleWebhookEvent(event.slug)"
                        >
                            <span
                                class="mt-0.5 grid h-4 w-4 shrink-0 place-items-center rounded border"
                                :class="
                                    webhookForm.events.includes(event.slug)
                                        ? 'border-primary-600 bg-primary-600 text-white dark:border-primary-400 dark:bg-primary-400 dark:text-surface-950'
                                        : 'border-surface-300 dark:border-surface-600'
                                "
                            >
                                <component
                                    v-if="
                                        webhookForm.events.includes(event.slug)
                                    "
                                    :is="getIcon('Check')"
                                    class="h-3 w-3 stroke-[3]"
                                />
                            </span>
                            <span>
                                <span
                                    class="block font-mono text-[11px] font-semibold text-surface-800 dark:text-surface-200"
                                >
                                    {{ event.slug }}
                                </span>
                                <span
                                    class="mt-0.5 block text-[10px] text-surface-500 dark:text-surface-400"
                                >
                                    {{ event.label }}
                                </span>
                            </span>
                        </button>
                    </div>
                    <p
                        v-if="webhookForm.errors.events"
                        class="mt-1 text-[11px] font-medium text-rose-600 dark:text-rose-400"
                    >
                        {{ webhookForm.errors.events }}
                    </p>
                </div>

                <div
                    v-if="editingWebhook"
                    class="flex items-center justify-between rounded-xl border border-surface-200 bg-surface-50/60 p-3 dark:border-surface-800 dark:bg-surface-950/30"
                >
                    <div>
                        <p
                            class="text-xs font-semibold text-surface-800 dark:text-surface-200"
                        >
                            Webhook Aktif
                        </p>
                        <p
                            class="text-[11px] text-surface-500 dark:text-surface-400"
                        >
                            Nonaktifkan untuk berhenti menerima event tanpa
                            menghapus konfigurasi.
                        </p>
                    </div>
                    <Toggle
                        v-model="webhookForm.is_active"
                        label="Aktifkan webhook"
                    />
                </div>

                <div
                    class="flex justify-end gap-2 border-t border-surface-100 pt-3 dark:border-surface-800"
                >
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        @click="closeWebhookModal"
                    >
                        Batal
                    </Button>
                    <Button
                        type="submit"
                        variant="primary"
                        size="sm"
                        :loading="webhookForm.processing"
                        :disabled="
                            !webhookForm.name.trim() ||
                            !webhookForm.url.trim() ||
                            webhookForm.events.length === 0
                        "
                    >
                        {{ editingWebhook ? 'Simpan Perubahan' : 'Daftarkan' }}
                    </Button>
                </div>
            </form>
        </Modal>

        <!-- DELETE WEBHOOK MODAL -->
        <Modal
            :open="deleteWebhookModal"
            title="Hapus Webhook?"
            size="sm"
            @close="
                deleteWebhookModal = false;
                webhookToDelete = null;
            "
        >
            <p
                class="text-xs leading-relaxed text-surface-600 dark:text-surface-300"
            >
                Webhook
                <strong class="text-surface-950 dark:text-surface-100"
                    >"{{ webhookToDelete?.name }}"</strong
                >
                akan dihapus permanen dan berhenti menerima event. Tindakan ini
                tidak dapat dibatalkan.
            </p>

            <template #footer>
                <Button
                    variant="secondary"
                    size="sm"
                    @click="
                        deleteWebhookModal = false;
                        webhookToDelete = null;
                    "
                >
                    Batal
                </Button>
                <Button variant="destructive" size="sm" @click="deleteWebhook">
                    Hapus Webhook
                </Button>
            </template>
        </Modal>
    </div>
</template>
