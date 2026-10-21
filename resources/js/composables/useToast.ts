import { ref, type Ref } from 'vue';

export type ToastType = 'success' | 'error' | 'warning' | 'info';

export interface Toast {
    id: number;
    type: ToastType;
    title: string;
    message?: string;
    duration?: number;
}

const toasts = ref<Toast[]>([]);
let idCounter = 0;

function addToast(toast: Omit<Toast, 'id'>): number {
    const id = ++idCounter;
    toasts.value.push({ ...toast, id, duration: toast.duration ?? 4000 });
    return id;
}

function removeToast(id: number): void {
    const idx = toasts.value.findIndex((t) => t.id === id);
    if (idx !== -1) toasts.value.splice(idx, 1);
}

export function useToast() {
    function success(title: string, message?: string, duration?: number) {
        return addToast({ type: 'success', title, message, duration });
    }

    function error(title: string, message?: string, duration?: number) {
        return addToast({ type: 'error', title, message, duration });
    }

    function warning(title: string, message?: string, duration?: number) {
        return addToast({ type: 'warning', title, message, duration });
    }

    function info(title: string, message?: string, duration?: number) {
        return addToast({ type: 'info', title, message, duration });
    }

    function dismiss(id: number) {
        removeToast(id);
    }

    return {
        toasts: toasts as Ref<Toast[]>,
        success,
        error,
        warning,
        info,
        dismiss,
    };
}
