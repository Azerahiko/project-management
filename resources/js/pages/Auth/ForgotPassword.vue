<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Link, useForm, usePage, Head } from '@inertiajs/vue3';
import password from '@/routes/password';
import { login } from '@/routes';
import AuthLayout from '@/layouts/AuthLayout.vue';
import gsap from 'gsap';
import { prefersReducedMotion } from '@/lib/motion';

defineOptions({ layout: AuthLayout });

const form = useForm({
    email: '',
});

const cardRef = ref<HTMLElement | null>(null);
const logoRef = ref<HTMLElement | null>(null);
const titleRef = ref<HTMLElement | null>(null);
const formFieldsRef = ref<HTMLElement | null>(null);
const submitBtnRef = ref<HTMLElement | null>(null);

let entranceTimeline: gsap.core.Timeline | null = null;

function handleSubmit(): void {
    form.post(password.email.url(), {
        onFinish: () => {
            form.email = '';
        },
    });
}

const page = usePage();
const errors = computed(() => page.props.errors ?? {});

const errorMessage = computed(() => {
    const errs = errors.value as Record<string, string | string[] | undefined>;
    if (errs.form) {
        return Array.isArray(errs.form) ? errs.form[0] : errs.form;
    }
    if (errs.email) {
        return Array.isArray(errs.email) ? errs.email[0] : errs.email;
    }
    if (form.errors.email) {
        return form.errors.email;
    }
    return null;
});

const successMessage = computed(() => {
    const flash = page.props.flash as Record<string, any> | undefined;
    const msg = flash?.status ?? flash?.success ?? flash?.message;
    return typeof msg === 'function' ? msg() : msg || null;
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
    <Head title="Forgot Password - Dijital Code" />

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
                    Forgot password?
                </h1>
                <p
                    class="mt-1 text-xs leading-relaxed font-normal text-surface-700"
                >
                    Enter your email address and we'll send you a password reset
                    link.
                </p>

                <!-- Brand Color Decorative Bar -->
                <div class="mt-3 flex items-center justify-center gap-1">
                    <span class="h-1 w-7 rounded-full bg-[#008081]" />
                    <span class="h-1 w-2.5 rounded-full bg-[#F2C32D]" />
                </div>
            </div>

            <!-- Success Message -->
            <div
                v-if="successMessage"
                class="mt-5 rounded-xl border border-primary-200 bg-primary-50 px-4 py-2.5 text-center text-xs font-semibold text-primary-700 shadow-xs"
                role="status"
            >
                {{ successMessage }}
            </div>

            <!-- Error Message -->
            <div
                v-if="errorMessage"
                class="mt-5 rounded-xl border border-accent-200 bg-accent-50 px-4 py-2.5 text-center text-xs font-semibold text-accent-700 shadow-xs"
                role="alert"
            >
                {{ errorMessage }}
            </div>

            <form
                :class="errorMessage || successMessage ? 'mt-4' : 'mt-6'"
                @submit.prevent="handleSubmit"
            >
                <div ref="formFieldsRef">
                    <label
                        for="forgot-email"
                        class="mb-1.5 block text-xs font-semibold text-surface-800"
                    >
                        Email address
                    </label>
                    <div
                        class="group flex h-12 items-center rounded-xl border border-surface-200 bg-surface-50 px-3.5 shadow-2xs transition-all duration-200 focus-within:border-[#008081] focus-within:bg-white focus-within:ring-4 focus-within:ring-[#008081]/12"
                        :class="{
                            'border-accent-400 bg-accent-50/30 ring-2 ring-accent-400/20':
                                form.errors.email,
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
                            <rect width="20" height="16" x="2" y="4" rx="2.5" />
                            <path
                                d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"
                            />
                        </svg>
                        <input
                            id="forgot-email"
                            v-model="form.email"
                            type="email"
                            placeholder="Enter your email"
                            required
                            autocomplete="email"
                            class="min-w-0 flex-1 bg-transparent px-3 text-[14px] font-normal text-surface-950 outline-none placeholder:text-surface-600"
                        />
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
                            Send reset link
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
