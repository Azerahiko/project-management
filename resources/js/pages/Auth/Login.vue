<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm, Link, Head } from '@inertiajs/vue3';
import { store as loginStore } from '@/routes/login';
import password from '@/routes/password';
import AuthLayout from '@/layouts/AuthLayout.vue';

defineOptions({ layout: AuthLayout });

const form = useForm({
    email: '',
    password: '',
    remember: true,
});

const showPassword = ref(false);

function handleSubmit(): void {
    form.post(loginStore.url(), {
        onFinish: () => {
            form.password = '';
        },
    });
}

const errorMessage = computed(() => {
    const errs = form.errors as Record<string, string | undefined>;
    return errs.form ?? errs.email ?? errs.password ?? null;
});
</script>

<template>
    <Head title="Log In - Dijital Code" />

    <div class="card-float-wrapper relative w-full max-w-[420px]">
        <!-- Subtle Ambient Brand Aura Glow behind card -->
        <div
            class="aura-glow pointer-events-none absolute -inset-3 -z-10 rounded-[36px] bg-gradient-to-tr from-[#008289]/35 via-[#F6B828]/15 to-[#008289]/25 blur-2xl"
            aria-hidden="true"
        />

        <!-- Floating Card -->
        <div
            class="auth-card relative w-full rounded-[28px] bg-white p-8 shadow-[0_25px_60px_-12px_rgba(0,0,0,0.6),0_0_0_1px_rgba(255,255,255,0.9)_inset,0_2px_4px_rgba(0,0,0,0.04)] ring-1 ring-black/[0.05] sm:p-10"
        >
            <!-- Logo Container -->
            <div class="flex flex-col items-center">
                <img
                    src="/images/logo.png"
                    alt="Dijital Code"
                    class="h-9 w-auto max-w-[260px] object-contain drop-shadow-xs sm:h-10"
                />
            </div>

            <!-- Title & Subtitle Header -->
            <div class="mt-5 text-center">
                <h1
                    class="font-sans text-[23px] font-bold tracking-tight text-[#0F1E26] sm:text-[25px]"
                >
                    Log in to your account
                </h1>
                <p class="mt-1 text-xs font-medium text-[#64748B]">
                    Task Flow &amp; Project Management
                </p>

                <!-- Brand Color Decorative Accent -->
                <div class="mt-3 flex items-center justify-center gap-1.5">
                    <span class="h-1 w-8 rounded-full bg-[#008289]" />
                    <span class="h-1 w-2.5 rounded-full bg-[#F6B828]" />
                </div>
            </div>

            <!-- Error Notification Alert -->
            <div
                v-if="errorMessage"
                class="mt-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-center text-xs font-semibold text-rose-600 shadow-xs"
                role="alert"
            >
                {{ errorMessage }}
            </div>

            <!-- Form -->
            <form
                :class="errorMessage ? 'mt-4' : 'mt-6'"
                @submit.prevent="handleSubmit"
            >
                <div class="space-y-4">
                    <!-- Email Address Field -->
                    <div>
                        <label
                            for="login-email"
                            class="mb-1.5 block text-xs font-semibold text-[#334155]"
                        >
                            Email address
                        </label>
                        <div
                            class="group flex h-12 items-center rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-3.5 shadow-2xs transition-all duration-200 focus-within:border-[#008289] focus-within:bg-white focus-within:ring-4 focus-within:ring-[#008289]/12"
                            :class="{
                                'border-rose-400 bg-rose-50/30 ring-2 ring-rose-400/20':
                                    form.errors.email,
                            }"
                        >
                            <!-- Mail Icon -->
                            <svg
                                class="h-5 w-5 shrink-0 text-[#94A3B8] transition-colors group-focus-within:text-[#008289]"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <rect
                                    width="20"
                                    height="16"
                                    x="2"
                                    y="4"
                                    rx="2.5"
                                />
                                <path
                                    d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"
                                />
                            </svg>
                            <input
                                id="login-email"
                                v-model="form.email"
                                type="email"
                                placeholder="Enter your email"
                                required
                                autocomplete="email"
                                :aria-invalid="!!form.errors.email"
                                class="min-w-0 flex-1 bg-transparent px-3 text-[14px] font-normal text-[#0F1E26] outline-none placeholder:text-[#94A3B8]"
                            />
                        </div>
                    </div>

                    <!-- Password Field -->
                    <div>
                        <label
                            for="login-password"
                            class="mb-1.5 block text-xs font-semibold text-[#334155]"
                        >
                            Password
                        </label>
                        <div
                            class="group flex h-12 items-center rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-3.5 shadow-2xs transition-all duration-200 focus-within:border-[#008289] focus-within:bg-white focus-within:ring-4 focus-within:ring-[#008289]/12"
                            :class="{
                                'border-rose-400 bg-rose-50/30 ring-2 ring-rose-400/20':
                                    form.errors.password,
                            }"
                        >
                            <!-- Lock Icon -->
                            <svg
                                class="h-5 w-5 shrink-0 text-[#94A3B8] transition-colors group-focus-within:text-[#008289]"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="1.8"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <rect
                                    width="18"
                                    height="11"
                                    x="3"
                                    y="11"
                                    rx="2.5"
                                    ry="2.5"
                                />
                                <path d="M7 11V7a5 5 0 0 1 10 0v4" />
                            </svg>
                            <input
                                id="login-password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                placeholder="Enter your password"
                                required
                                autocomplete="current-password"
                                :aria-invalid="!!form.errors.password"
                                class="min-w-0 flex-1 bg-transparent px-3 text-[14px] font-normal text-[#0F1E26] outline-none placeholder:text-[#94A3B8]"
                            />
                            <!-- Password Eye Toggle -->
                            <button
                                type="button"
                                class="rounded-md p-1 text-[#94A3B8] transition-colors hover:text-[#008289]"
                                :aria-label="
                                    showPassword
                                        ? 'Hide password'
                                        : 'Show password'
                                "
                                :aria-pressed="showPassword"
                                @click="showPassword = !showPassword"
                            >
                                <svg
                                    v-if="!showPassword"
                                    class="h-5 w-5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M3 10c2 3 5.2 4.5 9 4.5S19 13 21 10"
                                    />
                                    <path
                                        d="m6 13.5-1.5 2M10 15.2l-.6 2.3M14 15.2l.6 2.3M18 13.5l1.5 2"
                                    />
                                </svg>
                                <svg
                                    v-else
                                    class="h-5 w-5"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="1.8"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="M2 12s3.5-6.5 10-6.5S22 12 22 12s-3.5 6.5-10 6.5S2 12 2 12Z"
                                    />
                                    <circle cx="12" cy="12" r="3" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Remember Me & Forgot Password -->
                    <div class="flex items-center justify-between pt-1">
                        <label
                            class="flex cursor-pointer items-center gap-2 select-none"
                        >
                            <input
                                v-model="form.remember"
                                type="checkbox"
                                class="h-4 w-4 rounded border-[#CBD5E1] text-[#008289] accent-[#008289] focus:ring-[#008289]/30"
                            />
                            <span class="text-xs font-medium text-[#475569]">
                                Remember me
                            </span>
                        </label>
                        <Link
                            :href="password.request.url()"
                            class="text-xs font-semibold text-[#008289] transition-colors hover:text-[#005B60] hover:underline"
                        >
                            Forgot your password?
                        </Link>
                    </div>
                </div>

                <!-- Submit Button with Gradient & Micro-interaction -->
                <div class="mt-7">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="group relative flex h-12 w-full cursor-pointer items-center justify-center overflow-hidden rounded-xl bg-gradient-to-r from-[#008289] via-[#009199] to-[#008289] bg-[length:200%_auto] text-[15px] font-bold text-white shadow-[0_8px_20px_-4px_rgba(0,130,137,0.45)] transition-all duration-300 hover:-translate-y-0.5 hover:bg-right hover:shadow-[0_12px_24px_-4px_rgba(0,130,137,0.55)] active:translate-y-0 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <!-- Shimmer light effect -->
                        <span
                            class="pointer-events-none absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-white/20 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full"
                        />

                        <!-- Loading spinner -->
                        <svg
                            v-if="form.processing"
                            class="h-5 w-5 animate-spin text-white"
                            fill="none"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <circle
                                class="opacity-25"
                                cx="12"
                                cy="12"
                                r="10"
                                stroke="currentColor"
                                stroke-width="4"
                            />
                            <path
                                class="opacity-75"
                                fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                            />
                        </svg>

                        <!-- Label with arrow -->
                        <span
                            v-else
                            class="flex items-center gap-2 tracking-wide"
                        >
                            <span>Log in</span>
                            <svg
                                class="h-4 w-4 transition-transform duration-200 group-hover:translate-x-1"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2.2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path d="M5 12h14" />
                                <path d="m12 5 7 7-7 7" />
                            </svg>
                        </span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<style scoped>
@keyframes entranceFadeUp {
    0% {
        opacity: 0;
        transform: translateY(24px) scale(0.98);
    }
    100% {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes gentleFloat {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-6px);
    }
}

@keyframes auraPulse {
    0%,
    100% {
        opacity: 0.65;
        transform: scale(0.98);
    }
    50% {
        opacity: 0.95;
        transform: scale(1.03);
    }
}

.card-float-wrapper {
    animation:
        entranceFadeUp 0.65s cubic-bezier(0.16, 1, 0.3, 1) forwards,
        gentleFloat 4s ease-in-out 0.7s infinite;
}

.aura-glow {
    animation: auraPulse 4s ease-in-out infinite;
}

@media (prefers-reduced-motion: reduce) {
    .card-float-wrapper,
    .aura-glow {
        animation: none !important;
    }
}
</style>
