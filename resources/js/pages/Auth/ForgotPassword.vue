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

let floatTween: gsap.core.Tween | null = null;

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

    const tl = gsap.timeline({ defaults: { ease: 'power3.out' } });

    if (cardRef.value) {
        tl.from(cardRef.value, {
            y: 35,
            opacity: 0,
            scale: 0.98,
            duration: 0.8,
        });
    }

    if (logoRef.value) {
        tl.from(
            logoRef.value,
            {
                y: -16,
                opacity: 0,
                duration: 0.6,
                ease: 'back.out(1.8)',
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
                y: 18,
                opacity: 0,
                stagger: 0.09,
                duration: 0.55,
            },
            '-=0.3',
        );
    }

    if (cardRef.value) {
        floatTween = gsap.to(cardRef.value, {
            y: -6,
            duration: 3.2,
            repeat: -1,
            yoyo: true,
            ease: 'sine.inOut',
            delay: 1,
        });
    }
});

onUnmounted(() => {
    floatTween?.kill();
});
</script>

<template>
    <Head title="Forgot Password - Dijital Code" />

    <div class="relative w-full max-w-[420px]">
        <!-- Subtle Ambient Brand Aura Glow behind card -->
        <div
            class="pointer-events-none absolute -inset-2.5 -z-10 rounded-[36px] bg-gradient-to-tr from-[#008289]/30 via-transparent to-[#F6B828]/25 opacity-75 blur-2xl"
            aria-hidden="true"
        />

        <div
            ref="cardRef"
            class="relative w-full rounded-[28px] bg-white/95 p-8 shadow-[0_24px_55px_-12px_rgba(0,0,0,0.55),0_0_0_1px_rgba(255,255,255,0.8)_inset,0_1px_3px_rgba(0,0,0,0.06)] ring-1 ring-black/[0.04] backdrop-blur-xl sm:p-10"
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
                    class="font-sans text-[23px] font-bold tracking-tight text-[#0F1E26] sm:text-[25px]"
                >
                    Forgot password?
                </h1>
                <p
                    class="mt-1 text-xs leading-relaxed font-normal text-[#64748B]"
                >
                    Enter your email address and we'll send you a password reset
                    link.
                </p>

                <!-- Brand Color Decorative Bar -->
                <div class="mt-3 flex items-center justify-center gap-1">
                    <span class="h-1 w-7 rounded-full bg-[#008289]" />
                    <span class="h-1 w-2.5 rounded-full bg-[#F6B828]" />
                </div>
            </div>

            <!-- Success Message -->
            <div
                v-if="successMessage"
                class="mt-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-center text-xs font-semibold text-emerald-700 shadow-xs"
                role="status"
            >
                {{ successMessage }}
            </div>

            <!-- Error Message -->
            <div
                v-if="errorMessage"
                class="mt-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-center text-xs font-semibold text-rose-600 shadow-xs"
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
                            class="min-w-0 flex-1 bg-transparent px-3 text-[14px] font-normal text-[#0F1E26] outline-none placeholder:text-[#94A3B8]"
                        />
                    </div>
                </div>

                <div ref="submitBtnRef" class="mt-6">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="group relative flex h-12 w-full cursor-pointer items-center justify-center overflow-hidden rounded-xl bg-gradient-to-r from-[#008289] via-[#009199] to-[#008289] bg-[length:200%_auto] text-[15px] font-bold text-white shadow-[0_8px_20px_-4px_rgba(0,130,137,0.45)] transition-all duration-300 hover:-translate-y-0.5 hover:bg-right hover:shadow-[0_12px_24px_-4px_rgba(0,130,137,0.55)] active:translate-y-0 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <span
                            class="pointer-events-none absolute inset-0 -translate-x-full bg-gradient-to-r from-transparent via-white/20 to-transparent transition-transform duration-700 ease-out group-hover:translate-x-full"
                        />

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
                    class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#64748B] transition-colors hover:text-[#008289]"
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
