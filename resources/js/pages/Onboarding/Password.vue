<script setup lang="ts">
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { useToast } from '@/composables/useToast';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';
import OnboardingLayout from './Layout.vue';

defineOptions({
    layout: OnboardingLayout,
});

const page = usePage<{
    step: string;
    nextStep: string;
    password_min_length?: number;
}>();

const passwordMinLength = computed(() => page.props.password_min_length ?? 8);

const { success } = useToast();

const form = useForm({
    password: '',
    password_confirmation: '',
});

function handleSubmit(): void {
    form.clearErrors();
    form.put('/onboarding/password', {
        onSuccess: () => {
            success('Berhasil', 'Kata sandi berhasil diubah.');
        },
    });
}
</script>

<template>
    <div class="space-y-6">
        <div class="text-center">
            <div
                class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900"
            >
                <svg
                    class="h-6 w-6 text-blue-600 dark:text-blue-400"
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
                Ubah Kata Sandi
            </h2>
            <p class="mt-2 text-sm text-surface-600 dark:text-surface-400">
                Anda wajib mengganti password default dengan password baru yang
                aman.
            </p>
        </div>

        <form @submit.prevent="handleSubmit" class="space-y-4">
            <Input
                v-model="form.password"
                type="password"
                label="Kata Sandi Baru"
                placeholder="Masukkan kata sandi baru"
                required
                :error="form.errors.password"
                autocomplete="new-password"
            />

            <Input
                v-model="form.password_confirmation"
                type="password"
                label="Konfirmasi Kata Sandi"
                placeholder="Masukkan ulang kata sandi"
                required
                :error="form.errors.password_confirmation"
                autocomplete="new-password"
            />

            <div
                class="rounded-lg bg-blue-50 p-4 text-sm text-blue-700 dark:bg-blue-900/30 dark:text-blue-300"
            >
                <p class="font-medium">Persyaratan kata sandi:</p>
                <ul class="mt-1 list-inside list-disc">
                    <li>Minimal {{ passwordMinLength }} karakter</li>
                    <li>Mengandung huruf besar dan kecil</li>
                    <li>Mengandung angka</li>
                    <li>Mengandung karakter khusus</li>
                </ul>
            </div>

            <Button
                type="submit"
                variant="primary"
                class="w-full"
                :loading="form.processing"
            >
                Simpan & Lanjutkan
            </Button>
        </form>
    </div>
</template>
