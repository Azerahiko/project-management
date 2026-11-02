import { ref, type Ref } from 'vue';

export type ToastType = 'success' | 'error' | 'warning' | 'info';

export interface ToastAction {
    label: string;
    onClick: () => void;
}

export interface Toast {
    id: number;
    type: ToastType;
    title: string;
    message?: string;
    duration?: number;
    action?: ToastAction;
}

const toasts = ref<Toast[]>([]);
const timers = new Map<number, ReturnType<typeof setTimeout>>();
const startedAt = new Map<number, number>();
const remaining = new Map<number, number>();
let idCounter = 0;

function startTimer(id: number, duration: number): void {
    startedAt.set(id, Date.now());
    remaining.set(id, duration);
    timers.set(
        id,
        setTimeout(() => removeToast(id), duration),
    );
}

function addToast(toast: Omit<Toast, 'id'>): number {
    const id = ++idCounter;
    const duration = toast.duration ?? 4200;

    toasts.value.push({ ...toast, id, duration });

    if (duration > 0) {
        startTimer(id, duration);
    }

    return id;
}

function removeToast(id: number): void {
    const timer = timers.get(id);
    if (timer !== undefined) {
        clearTimeout(timer);
    }

    timers.delete(id);
    startedAt.delete(id);
    remaining.delete(id);

    const idx = toasts.value.findIndex((t) => t.id === id);
    if (idx !== -1) toasts.value.splice(idx, 1);
}

function pauseToast(id: number): void {
    const timer = timers.get(id);
    if (timer === undefined) {
        return;
    }

    clearTimeout(timer);
    timers.delete(id);

    const started = startedAt.get(id) ?? Date.now();
    const left = Math.max(0, (remaining.get(id) ?? 0) - (Date.now() - started));

    remaining.set(id, left);
    startedAt.delete(id);
}

function resumeToast(id: number): void {
    const toast = toasts.value.find((item) => item.id === id);

    if (!toast || !toast.duration || toast.duration <= 0) {
        return;
    }

    const left = remaining.get(id) ?? toast.duration;

    if (left <= 0) {
        removeToast(id);
        return;
    }

    startTimer(id, left);
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

    function pause(id: number) {
        pauseToast(id);
    }

    function resume(id: number) {
        resumeToast(id);
    }

    return {
        toasts: toasts as Ref<Toast[]>,
        success,
        error,
        warning,
        info,
        dismiss,
        pause,
        resume,
    };
}
