import { computed, ref, type Ref } from 'vue';

export type Theme = 'light' | 'dark' | 'system';

const THEME_KEY = 'project-theme';

function applyTheme(theme: Theme): void {
    const root = document.documentElement;
    if (theme === 'dark') {
        root.setAttribute('data-theme', 'dark');
    } else if (theme === 'light') {
        root.removeAttribute('data-theme');
    } else {
        // system: use prefers-color-scheme
        const prefersDark = window.matchMedia(
            '(prefers-color-scheme: dark)',
        ).matches;
        if (prefersDark) {
            root.setAttribute('data-theme', 'dark');
        } else {
            root.removeAttribute('data-theme');
        }
    }
}

function persistTheme(theme: Theme): void {
    if (typeof window === 'undefined' || !window.localStorage) {
        return;
    }
    try {
        localStorage.setItem(THEME_KEY, theme);
    } catch {
        // localStorage unavailable
    }
}

function readStoredTheme(): Theme {
    if (typeof window === 'undefined' || !window.localStorage) {
        return 'system';
    }
    try {
        const stored = localStorage.getItem(THEME_KEY);
        if (stored === 'light' || stored === 'dark' || stored === 'system') {
            return stored;
        }
    } catch {
        // localStorage unavailable
    }
    return 'system';
}

function getSystemTheme(): 'light' | 'dark' {
    return window.matchMedia('(prefers-color-scheme: dark)').matches
        ? 'dark'
        : 'light';
}

const currentTheme = ref<Theme>('system');
const isInitialized = ref(false);

export function useTheme() {
    function setTheme(theme: Theme): void {
        currentTheme.value = theme;
        applyTheme(theme);
        persistTheme(theme);
    }

    const nextTheme = computed<Theme>(() => {
        const isDark =
            currentTheme.value === 'dark' ||
            (currentTheme.value === 'system' && getSystemTheme() === 'dark');

        return isDark ? 'light' : 'dark';
    });

    function toggleTheme(): void {
        setTheme(nextTheme.value);
    }

    function initTheme(): void {
        if (isInitialized.value) return;

        const stored = readStoredTheme();
        currentTheme.value = stored;
        applyTheme(stored);

        isInitialized.value = true;
    }

    const resolvedTheme = computed(() => {
        if (currentTheme.value === 'system') {
            return getSystemTheme();
        }
        return currentTheme.value;
    });

    // Initialize immediately if not already done
    if (!isInitialized.value && typeof window !== 'undefined') {
        initTheme();
    }

    return {
        currentTheme: currentTheme as Ref<Theme>,
        resolvedTheme,
        nextTheme,
        isInitialized: isInitialized as Ref<boolean>,
        setTheme,
        toggleTheme,
        initTheme,
    };
}

// Auto-initialize on import (for SSR safety, only runs in browser)
if (typeof window !== 'undefined') {
    // Apply immediately to prevent flash
    currentTheme.value = readStoredTheme();
    applyTheme(currentTheme.value);

    // Keep the system theme in sync when the OS preference changes.
    window
        .matchMedia('(prefers-color-scheme: dark)')
        .addEventListener('change', () => {
            if (currentTheme.value === 'system') {
                applyTheme('system');
            }
        });
}
