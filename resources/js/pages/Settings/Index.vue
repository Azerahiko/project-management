<script setup lang="ts">
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { getIcon } from '@/components/sidebar/icons';
import { useTheme } from '@/composables/useTheme';
import Button from '@/components/ui/Button.vue';
import { useToast } from '@/composables/useToast';

defineOptions({
    layout: AppLayout,
});

const page = usePage();
const user = page.props.auth.user;
const { currentTheme, setTheme } = useTheme();
const { success } = useToast();

const themeOptions = [
    {
        value: 'light',
        label: 'Terang',
        icon: 'Sun',
        description: 'Selalu gunakan tema terang',
    },
    {
        value: 'dark',
        label: 'Gelap',
        icon: 'Moon',
        description: 'Selalu gunakan tema gelap',
    },
] as const;

const currentOption = computed(() =>
    themeOptions.find((o) => o.value === currentTheme.value),
);

function selectTheme(theme: typeof currentTheme.value): void {
    setTheme(theme);
    success(
        'Tema diperbarui',
        `Tema diubah ke ${themeOptions.find((o) => o.value === theme)?.label}`,
    );
}
</script>

<template>
    <div class="mx-auto max-w-3xl space-y-6">
        <div>
            <h1
                class="text-2xl font-bold text-surface-900 dark:text-surface-100"
            >
                Pengaturan
            </h1>
            <p class="mt-1 text-surface-600 dark:text-surface-400">
                Kelola preferensi aplikasi Anda
            </p>
        </div>

        <!-- Appearance Section -->
        <div class="card space-y-6">
            <div class="flex items-center gap-3">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-100 dark:bg-primary-900/30"
                >
                    <component
                        :is="getIcon('Palette')"
                        class="h-5 w-5 text-primary-600 dark:text-primary-400"
                    />
                </div>
                <div>
                    <h2
                        class="text-lg font-semibold text-surface-900 dark:text-surface-100"
                    >
                        Tampilan
                    </h2>
                    <p class="text-sm text-surface-500 dark:text-surface-400">
                        Atur preferensi tema aplikasi
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div
                    v-for="option in themeOptions"
                    :key="option.value"
                    class="relative rounded-xl border p-4 transition-all duration-200"
                    :class="
                        currentTheme === option.value
                            ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20'
                            : 'border-surface-200 hover:border-surface-300 dark:border-surface-700 dark:hover:border-surface-600'
                    "
                    @click="selectTheme(option.value)"
                >
                    <div
                        class="absolute top-3 right-3"
                        v-if="currentTheme === option.value"
                    >
                        <component
                            :is="getIcon('Check')"
                            class="h-5 w-5 text-primary-600 dark:text-primary-400"
                        />
                    </div>
                    <div
                        class="mb-3 flex h-12 w-12 items-center justify-center rounded-lg"
                        :class="
                            currentTheme === option.value
                                ? 'bg-primary-100 dark:bg-primary-900/30'
                                : 'bg-surface-100 dark:bg-surface-800'
                        "
                    >
                        <component
                            :is="getIcon(option.icon)"
                            class="h-6 w-6"
                            :class="
                                currentTheme === option.value
                                    ? 'text-primary-600 dark:text-primary-400'
                                    : 'text-surface-400 dark:text-surface-500'
                            "
                        />
                    </div>
                    <h3
                        class="font-medium text-surface-900 dark:text-surface-100"
                    >
                        {{ option.label }}
                    </h3>
                    <p
                        class="mt-1 text-sm text-surface-500 dark:text-surface-400"
                    >
                        {{ option.description }}
                    </p>
                    <div
                        class="relative mt-3 h-6 w-full overflow-hidden rounded-full bg-surface-200 dark:bg-surface-700"
                    >
                        <div
                            class="absolute inset-y-0 left-0 h-full rounded-full transition-all duration-300"
                            :class="
                                option.value === 'light'
                                    ? 'w-full bg-yellow-400'
                                    : 'w-full bg-slate-800'
                            "
                        />
                    </div>
                </div>
            </div>

            <div
                class="border-t border-white/20 pt-4 dark:border-surface-700/30"
            >
                <p class="text-sm text-surface-500 dark:text-surface-400">
                    Tema saat ini:
                    <strong class="capitalize">{{
                        currentOption?.label
                    }}</strong>
                </p>
            </div>
        </div>

        <!-- Profile Section -->
        <div class="card space-y-6">
            <div class="flex items-center gap-3">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900/30"
                >
                    <component
                        :is="getIcon('User')"
                        class="h-5 w-5 text-green-600 dark:text-green-400"
                    />
                </div>
                <div>
                    <h2
                        class="text-lg font-semibold text-surface-900 dark:text-surface-100"
                    >
                        Profil
                    </h2>
                    <p class="text-sm text-surface-500 dark:text-surface-400">
                        Informasi akun Anda
                    </p>
                </div>
            </div>

            <div class="space-y-4">
                <div
                    class="flex items-center gap-4 rounded-lg bg-surface-50 p-4 dark:bg-surface-800/50"
                >
                    <div
                        class="flex h-14 w-14 items-center justify-center rounded-full bg-primary-100 text-xl font-semibold text-primary-700 dark:bg-primary-900/30 dark:text-primary-300"
                    >
                        {{ user?.name?.charAt(0).toUpperCase() }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <p
                            class="truncate font-medium text-surface-900 dark:text-surface-100"
                        >
                            {{ user?.name }}
                        </p>
                        <p
                            class="truncate text-sm text-surface-500 dark:text-surface-400"
                        >
                            {{ user?.email }}
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                    <div
                        class="rounded-lg bg-surface-50 p-3 dark:bg-surface-800/50"
                    >
                        <p class="text-surface-500 dark:text-surface-400">
                            Peran
                        </p>
                        <p
                            class="font-medium text-surface-900 capitalize dark:text-surface-100"
                        >
                            {{ user?.roles?.join(', ') || '—' }}
                        </p>
                    </div>
                    <div
                        class="rounded-lg bg-surface-50 p-3 dark:bg-surface-800/50"
                    >
                        <p class="text-surface-500 dark:text-surface-400">
                            Status
                        </p>
                        <p
                            class="font-medium text-surface-900 dark:text-surface-100"
                        >
                            {{ user?.is_active ? 'Aktif' : 'Nonaktif' }}
                        </p>
                    </div>
                </div>
            </div>

            <div
                class="border-t border-white/20 pt-4 dark:border-surface-700/30"
            >
                <p class="text-sm text-surface-500 dark:text-surface-400">
                    Untuk mengubah nama, email, atau kata sandi, gunakan fitur
                    profil yang tersedia di menu dropdown pengguna.
                </p>
            </div>
        </div>

        <!-- Account Section -->
        <div class="card space-y-6">
            <div class="flex items-center gap-3">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-lg bg-purple-100 dark:bg-purple-900/30"
                >
                    <component
                        :is="getIcon('Shield')"
                        class="h-5 w-5 text-purple-600 dark:text-purple-400"
                    />
                </div>
                <div>
                    <h2
                        class="text-lg font-semibold text-surface-900 dark:text-surface-100"
                    >
                        Akun
                    </h2>
                    <p class="text-sm text-surface-500 dark:text-surface-400">
                        Pengaturan keamanan dan akses
                    </p>
                </div>
            </div>

            <div class="space-y-3">
                <div
                    class="flex items-center justify-between rounded-lg bg-surface-50 p-4 dark:bg-surface-800/50"
                >
                    <div>
                        <p
                            class="font-medium text-surface-900 dark:text-surface-100"
                        >
                            Verifikasi Email
                        </p>
                        <p
                            class="text-sm text-surface-500 dark:text-surface-400"
                        >
                            {{
                                user?.email_verified_at
                                    ? 'Terverifikasi'
                                    : 'Belum terverifikasi'
                            }}
                        </p>
                    </div>
                    <Button
                        variant="ghost"
                        size="sm"
                        :disabled="!!user?.email_verified_at"
                    >
                        Kirim Ulang
                    </Button>
                </div>

                <div
                    class="flex items-center justify-between rounded-lg bg-surface-50 p-4 dark:bg-surface-800/50"
                >
                    <div>
                        <p
                            class="font-medium text-surface-900 dark:text-surface-100"
                        >
                            Kata Sandi
                        </p>
                        <p
                            class="text-sm text-surface-500 dark:text-surface-400"
                        >
                            Terakhir diubah: belum tersedia
                        </p>
                    </div>
                    <Button variant="ghost" size="sm" :disabled="true">
                        Ubah Kata Sandi
                    </Button>
                </div>

                <div
                    class="flex items-center justify-between rounded-lg bg-surface-50 p-4 dark:bg-surface-800/50"
                >
                    <div>
                        <p
                            class="font-medium text-surface-900 dark:text-surface-100"
                        >
                            Autentikasi Dua Faktor (2FA)
                        </p>
                        <p
                            class="text-sm text-surface-500 dark:text-surface-400"
                        >
                            {{
                                user?.two_factor_enabled
                                    ? 'Diaktifkan'
                                    : 'Nonaktif'
                            }}
                        </p>
                    </div>
                    <Button variant="ghost" size="sm" :disabled="true">
                        Kelola
                    </Button>
                </div>

                <div
                    class="flex items-center justify-between rounded-lg bg-surface-50 p-4 dark:bg-surface-800/50"
                >
                    <div>
                        <p
                            class="font-medium text-surface-900 dark:text-surface-100"
                        >
                            Passkeys
                        </p>
                        <p
                            class="text-sm text-surface-500 dark:text-surface-400"
                        >
                            {{
                                (user?.passkeys_count ?? 0) > 0
                                    ? (user?.passkeys_count ?? 0) + ' terdaftar'
                                    : 'Belum ada'
                            }}
                        </p>
                    </div>
                    <Button variant="ghost" size="sm" :disabled="true">
                        Kelola
                    </Button>
                </div>
            </div>

            <div
                class="border-t border-white/20 pt-4 dark:border-surface-700/30"
            >
                <p class="text-sm text-surface-500 dark:text-surface-400">
                    Fitur keamanan tambahan (ubah kata sandi, 2FA, passkeys)
                    akan segera tersedia.
                </p>
            </div>
        </div>
    </div>
</template>
