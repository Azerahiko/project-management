<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import { store as loginStore } from '@/routes/login';
import AuthLayout from '@/layouts/AuthLayout.vue';
import Button from '@/components/ui/Button.vue';
import Input from '@/components/ui/Input.vue';

defineOptions({
    layout: AuthLayout,
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

function handleSubmit(): void {
    form.post(loginStore.url());
}

const page = usePage();
const errors = computed(() => page.props.errors);

const showPassword = ref(false);
</script>

<template>
    <div class="w-full">
        <form @submit.prevent="handleSubmit" class="space-y-4">
            <Input
                v-model="form.email"
                type="email"
                label="Email"
                placeholder="Masukkan email"
                autocomplete="email"
                required
                :error="errors.email"
                @keydown.enter="handleSubmit"
            />

            <div class="relative">
                <Input
                    v-model="form.password"
                    :type="showPassword ? 'text' : 'password'"
                    label="Kata Sandi"
                    placeholder="Masukkan kata sandi"
                    autocomplete="current-password"
                    required
                    :error="errors.password"
                    @keydown.enter="handleSubmit"
                />
                <button
                    type="button"
                    class="absolute top-[35px] right-3 text-surface-400 hover:text-surface-600 dark:hover:text-surface-300"
                    @click="showPassword = !showPassword"
                    aria-label="Tampilkan/Sembunyikan kata sandi"
                >
                    <svg
                        v-if="showPassword"
                        class="h-5 w-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88a3 3 0 114.242-4.242M12 18a6 6 0 01-4.53-5.332m.923-3.182a3 3 0 10-6.066 0"
                        />
                    </svg>
                    <svg
                        v-else
                        class="h-5 w-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                        />
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                        />
                    </svg>
                </button>
            </div>

            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2">
                    <input
                        type="checkbox"
                        v-model="form.remember"
                        class="h-4 w-4 rounded border-surface-300 text-primary-600 focus:ring-primary-500 dark:border-surface-600"
                    />
                    <span class="text-sm text-surface-600 dark:text-surface-400"
                        >Ingat saya</span
                    >
                </label>
            </div>

            <div
                class="mt-2"
                v-if="errors.form || errors.email || errors.password"
            >
                <div
                    class="rounded-lg bg-red-50 p-3 text-red-700 dark:bg-red-900/30 dark:text-red-300"
                >
                    <p v-if="errors.form">{{ errors.form[0] }}</p>
                    <p v-else-if="errors.email">{{ errors.email[0] }}</p>
                    <p v-else>{{ errors.password[0] }}</p>
                </div>
            </div>

            <Button
                type="submit"
                class="mt-4 w-full"
                :loading="form.processing"
            >
                Masuk
            </Button>
        </form>
    </div>
</template>
