<script setup lang="ts">
import { ref, computed } from 'vue';
import { usePage, router } from '@inertiajs/vue3';
import web from '@/lib/web';
import { useToast } from '@/composables/useToast';
import Button from '@/components/ui/Button.vue';
import OnboardingLayout from './Layout.vue';

defineOptions({
    layout: OnboardingLayout,
});

const page = usePage<{
    step: string;
    nextStep: string;
    passkeys?: Array<{ id: number; name: string; last_used_at: string | null; created_at?: string }>;
}>();

const { success, error: toastError } = useToast();

const processing = ref(false);
const passkeyName = ref('');
const passkeys = ref<Array<{ id: number; name: string; last_used_at: string | null }>>(
    page.props.passkeys || [],
);

const passkeyRegistered = computed(() => passkeys.value.length > 0);

function base64UrlToBuffer(base64url: string): ArrayBuffer {
    const base64 = base64url.replace(/-/g, '+').replace(/_/g, '/');
    const pad = base64.length % 4;
    const padded = pad ? base64 + '='.repeat(4 - pad) : base64;
    const binary = atob(padded);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
    }
    return bytes.buffer;
}

function bufferToBase64(buffer: ArrayBuffer): string {
    const bytes = new Uint8Array(buffer);
    let binary = '';
    for (let i = 0; i < bytes.length; i++) {
        binary += String.fromCharCode(bytes[i]);
    }
    return btoa(binary);
}

function bufferToBase64Url(buffer: ArrayBuffer): string {
    return bufferToBase64(buffer)
        .replace(/\+/g, '-')
        .replace(/\//g, '_')
        .replace(/=+$/, '');
}

async function registerPasskey(): Promise<void> {
    if (!passkeyName.value.trim()) {
        toastError('Masukkan nama untuk passkey.');
        return;
    }

    processing.value = true;

    try {
        const optionsResponse = await web.get('/user/passkeys/options');
        const options = optionsResponse.data.options || optionsResponse.data;

        const credential = await navigator.credentials.create({
            publicKey: {
                challenge: base64UrlToBuffer(options.challenge),
                rp: options.rp,
                user: {
                    id: base64UrlToBuffer(options.user.id),
                    name: options.user.name,
                    displayName: options.user.displayName,
                },
                pubKeyCredParams: options.pubKeyCredParams,
                authenticatorSelection: options.authenticatorSelection,
                timeout: options.timeout,
            },
        });

        if (!credential) {
            throw new Error('Gagal membuat credential');
        }

        const cred = credential as unknown as {
            id: string;
            rawId: ArrayBuffer;
            type: string;
            response: {
                clientDataJSON: ArrayBuffer;
                attestationObject: ArrayBuffer;
            };
        };

        const storeResponse = await web.post('/user/passkeys', {
            name: passkeyName.value,
            credential: {
                id: cred.id,
                rawId: bufferToBase64(cred.rawId),
                type: cred.type,
                response: {
                    clientDataJSON: bufferToBase64Url(cred.response.clientDataJSON),
                    attestationObject: bufferToBase64(cred.response.attestationObject),
                },
            },
        });

        success('Passkey Didaftarkan', 'Passkey berhasil ditambahkan ke akun Anda.');
        passkeys.value.push(storeResponse.data);
        passkeyName.value = '';
    } catch (err: any) {
        if (err.name === 'NotAllowedError') {
            return;
        }
        toastError(err.response?.data?.message || 'Gagal mendaftarkan passkey.');
    } finally {
        processing.value = false;
    }
}

function completeOnboarding(): void {
    processing.value = true;
    router.post('/onboarding/complete', {}, {
        onFinish: () => {
            processing.value = false;
        },
    });
}
</script>

<template>
    <div class="space-y-6">
        <div class="text-center">
            <div
                class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-teal-100 dark:bg-teal-900"
            >
                <svg
                    class="h-6 w-6 text-teal-600 dark:text-teal-400"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"
                    />
                </svg>
            </div>
            <h2
                class="mt-4 text-lg font-semibold text-surface-900 dark:text-surface-50"
            >
                Daftar Passkey (Opsional)
            </h2>
            <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">
                Passkey memungkinkan Anda masuk tanpa password menggunakan
                biometric atau perangkat yang aman.
            </p>
        </div>

        <!-- Already registered passkeys -->
        <div v-if="passkeyRegistered" class="space-y-3">
            <h3
                class="text-sm font-medium text-surface-900 dark:text-surface-50"
            >
                Passkey Terdaftar:
            </h3>
            <div
                v-for="pk in passkeys"
                :key="pk.id"
                class="flex items-center justify-between rounded-lg border border-surface-200 bg-surface-50 p-3 dark:border-surface-700 dark:bg-surface-800"
            >
                <div class="flex items-center gap-3">
                    <svg
                        class="h-5 w-5 text-surface-500"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"
                        />
                    </svg>
                    <span
                        class="text-sm font-medium text-surface-900 dark:text-surface-50"
                        >{{ pk.name }}</span
                    >
                </div>
                <span v-if="pk.last_used_at" class="text-xs text-surface-500">
                    Terakhir:
                    {{ new Date(pk.last_used_at).toLocaleDateString('id-ID') }}
                </span>
            </div>
        </div>

        <!-- Registration form -->
        <div class="space-y-4">
            <div>
                <label
                    class="block text-sm font-medium text-surface-700 dark:text-surface-300"
                >
                    Nama Passkey
                </label>
                <input
                    v-model="passkeyName"
                    type="text"
                    placeholder="Contoh: Laptop Kerja, iPhone"
                    class="mt-1 block w-full rounded-lg border border-surface-300 bg-white px-3 py-2 text-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 focus:outline-none dark:border-surface-600 dark:bg-surface-800 dark:text-surface-50"
                />
            </div>

            <Button
                variant="primary"
                class="w-full"
                @click="registerPasskey"
                :loading="processing"
                :disabled="!passkeyName.trim()"
            >
                Daftar Passkey
            </Button>
        </div>

        <!-- Skip / Complete -->
        <div class="border-t border-surface-200 pt-6 dark:border-surface-700">
            <p class="mb-4 text-center text-sm text-surface-500">
                Anda dapat melewati langkah ini dan menyelesaikan onboarding.
            </p>

            <div class="flex gap-3">
                <Button
                    v-if="!passkeyRegistered"
                    variant="secondary"
                    class="flex-1"
                    @click="completeOnboarding"
                    :loading="processing"
                >
                    Lewati & Selesaikan
                </Button>
                <Button
                    v-else
                    variant="primary"
                    class="flex-1"
                    @click="completeOnboarding"
                    :loading="processing"
                >
                    Selesaikan Onboarding
                </Button>
            </div>
        </div>
    </div>
</template>
