import { ref, type Ref } from 'vue';

export type Theme = 'light' | 'dark';

const THEME_KEY = 'project-theme';

function applyTheme(theme: Theme): void {
    const root = document.documentElement;
    if (theme === 'dark') {
        root.setAttribute('data-theme', 'dark');
    } else {
        root.removeAttribute('data-theme');
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
        return 'light';
    }
    try {
        const stored = localStorage.getItem(THEME_KEY);
        if (stored === 'light' || stored === 'dark') {
            return stored;
        }
    } catch {
        // localStorage unavailable
    }
    // Legacy 'system' or invalid value: fall back to Light and persist the valid value.
    persistTheme('light');
    return 'light';
}

const currentTheme = ref<Theme>('light');
const isInitialized = ref(false);

export function useTheme() {
    function setTheme(theme: Theme): void {
        currentTheme.value = theme;
        applyTheme(theme);
        persistTheme(theme);
    }

    function toggleTheme(): void {
        setTheme(currentTheme.value === 'dark' ? 'light' : 'dark');
    }

    function initTheme(): void {
        if (isInitialized.value) return;

        const stored = readStoredTheme();
        currentTheme.value = stored;
        applyTheme(stored);

        isInitialized.value = true;
    }

    // Initialize immediately if not already done
    if (!isInitialized.value && typeof window !== 'undefined') {
        initTheme();
    }

    return {
        currentTheme: currentTheme as Ref<Theme>,
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
}
