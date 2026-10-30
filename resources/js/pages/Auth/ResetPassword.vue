<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useForm, Link, Head } from '@inertiajs/vue3';
import passwordRoutes from '@/routes/password';
import { login } from '@/routes';
import AuthLayout from '@/layouts/AuthLayout.vue';
import gsap from 'gsap';
import { prefersReducedMotion } from '@/lib/motion';

defineOptions({ layout: AuthLayout });

const props = defineProps<{
    email: string;
    token: string;
}>();

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const showPassword = ref(false);
const cardRef = ref<HTMLElement | null>(null);
const logoRef = ref<HTMLElement | null>(null);
const titleRef = ref<HTMLElement | null>(null);
const formFieldsRef = ref<HTMLElement | null>(null);
const submitBtnRef = ref<HTMLElement | null>(null);

let entranceTimeline: gsap.core.Timeline | null = null;

function handleSubmit(): void {
    form.post(passwordRoutes.update.url(), {
        onFinish: () => {
            form.reset('password', 'password_confirmation');
        },
    });
}

const errorMessage = computed(() => {
    const errs = form.errors as Record<string, string | undefined>;
    return errs.form ?? errs.email ?? errs.password ?? errs.token ?? null;
});

onMounted(() => {
    if (prefersReducedMotion()) return;

    const tl = gsap.timeline({ defaults: { ease: 'power2.out' } });
    entranceTimeline = tl;

    if (cardRef.value) {
        tl.from(cardRef.value, {
            y: 12,
            opacity: 0,
            duration: 0.35,
        });
    }

    if (logoRef.value) {
        tl.from(
            logoRef.value,
            {
                y: -6,
                opacity: 0,
                duration: 0.3,
            },
            '-=0.45',
        );
    }

    const elementsToStagger = [
        titleRef.value,
        formFieldsRef.value,
        submitBtnRef.value,
    ].filter(Boolean);

    if (elementsToStagger.length > 0) {
        tl.from(
            elementsToStagger,
            {
                y: 6,
                opacity: 0,
                stagger: 0.05,
                duration: 0.3,
            },
            '-=0.3',
        );
    }
});

onUnmounted(() => {
    entranceTimeline?.kill();
});
</script>

<template>
    <Head title="Reset Password - Dijital Code" />

    <div class="relative w-full max-w-[420px]">
        <!-- Subtle Ambient Brand Aura Glow behind card -->
        <div
            class="pointer-events-none absolute -inset-2 -z-10 rounded-3xl border border-primary-300/40 bg-primary-500/10 blur-lg"
            aria-hidden="true"
        />

        <div
            ref="cardRef"
            class="relative w-full rounded-2xl border border-surface-200 bg-white p-8 shadow-lg sm:p-10"
        >
            <!-- Logo -->
            <div ref="logoRef" class="flex flex-col items-center">
                <img
                    src="/images/logo.png"
                    alt="Dijital Code"
                    class="h-9 w-auto max-w-[260px] object-contain drop-shadow-xs sm:h-10"
                />
            </div>

            <!-- Title & Subtitle Header -->
            <div ref="titleRef" class="mt-5 text-center">
                <h1
                    class="font-sans text-[23px] font-bold tracking-tight text-surface-950 sm:text-[25px]"
                >
                    Reset password
                </h1>
                <p class="mt-1 text-xs font-normal text-surface-700">
                    Enter your new password below.
                </p>

                <!-- Brand Color Decorative Bar -->
                <div class="mt-3 flex items-center justify-center gap-1">
                    <span class="h-1 w-7 rounded-full bg-[#008081]" />
                    <span class="h-1 w-2.5 rounded-full bg-[#F2C32D]" />
                </div>
            </div>

            <div
                v-if="errorMessage"
                class="mt-5 rounded-xl border border-accent-200 bg-accent-50 px-4 py-2.5 text-center text-xs font-semibold text-accent-700 shadow-xs"
                role="alert"
            >
                {{ errorMessage }}
            </div>

            <form
                :class="errorMessage ? 'mt-4' : 'mt-6'"
                @submit.prevent="handleSubmit"
            >
                <div ref="formFieldsRef" class="space-y-4">
                    <div>
                        <label
                            for="reset-email"
                            class="mb-1.5 block text-xs font-semibold text-surface-800"
                        >
                            Email address
                        </label>
                        <div
                            class="flex h-12 items-center rounded-xl border border-surface-200 bg-surface-50 px-3.5 opacity-80 shadow-2xs"
                        >
                            <svg
                                class="h-5 w-5 shrink-0 text-surface-600"
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
                                id="reset-email"
                                v-model="form.email"
                                type="email"
                                required
                                autocomplete="email"
                                readonly
                                class="min-w-0 flex-1 bg-transparent px-3 text-[14px] font-normal text-surface-950 outline-none"
                            />
                        </div>
                    </div>

                    <div>
                        <label
                            for="reset-password"
                            class="mb-1.5 block text-xs font-semibold text-surface-800"
                        >
                            New password
                        </label>
                        <div
                            class="group flex h-12 items-center rounded-xl border border-surface-200 bg-surface-50 px-3.5 shadow-2xs transition-all duration-200 focus-within:border-[#008081] focus-within:bg-white focus-within:ring-4 focus-within:ring-[#008081]/12"
                            :class="{
                                'border-accent-400 bg-accent-50/30 ring-2 ring-accent-400/20':
                                    form.errors.password,
                            }"
                        >
                            <svg
                                class="h-5 w-5 shrink-0 text-surface-600 transition-colors group-focus-within:text-[#008081]"
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
                                id="reset-password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                placeholder="Enter new password"
                                required
                                autocomplete="new-password"
                                class="min-w-0 flex-1 bg-transparent px-3 text-[14px] font-normal text-surface-950 outline-none placeholder:text-surface-600"
                            />
                            <button
                                type="button"
                                class="rounded-md p-1 text-surface-600 transition-colors hover:text-[#008081]"
                                :aria-label="
                                    showPassword
                                        ? 'Hide password'
                                        : 'Show password'
                                "
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

                    <div>
                        <label
                            for="reset-password-confirmation"
                            class="mb-1.5 block text-xs font-semibold text-surface-800"
                        >
                            Confirm password
                        </label>
                        <div
                            class="group flex h-12 items-center rounded-xl border border-surface-200 bg-surface-50 px-3.5 shadow-2xs transition-all duration-200 focus-within:border-[#008081] focus-within:bg-white focus-within:ring-4 focus-within:ring-[#008081]/12"
                        >
                            <svg
                                class="h-5 w-5 shrink-0 text-surface-600 transition-colors group-focus-within:text-[#008081]"
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
                                id="reset-password-confirmation"
                                v-model="form.password_confirmation"
                                :type="showPassword ? 'text' : 'password'"
                                placeholder="Confirm new password"
                                required
                                autocomplete="new-password"
                                class="min-w-0 flex-1 bg-transparent px-3 text-[14px] font-normal text-surface-950 outline-none placeholder:text-surface-600"
                            />
                        </div>
                    </div>
                </div>

                <div ref="submitBtnRef" class="mt-6">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="group relative flex h-12 w-full cursor-pointer items-center justify-center overflow-hidden rounded-xl bg-primary-700 text-[15px] font-semibold text-white transition-colors hover:bg-primary-800 disabled:cursor-not-allowed disabled:opacity-60"
                    >
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
                        <span v-else class="tracking-wide">
                            Reset password
                        </span>
                    </button>
                </div>
            </form>

            <div class="mt-6 text-center">
                <Link
                    :href="login.url()"
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-surface-700 transition-colors hover:text-[#008081]"
                >
                    <svg
                        class="h-3.5 w-3.5"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    >
                        <path d="m15 18-6-6 6-6" />
                    </svg>
                    <span>Back to log in</span>
                </Link>
            </div>
        </div>
    </div>
</template>
