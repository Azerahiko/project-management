import type { Directive } from 'vue';
import type { Auth } from '@/types/auth';
import type { User } from '@/types/auth';
import type { Router } from '@inertiajs/vue3';
import type { Page, SharedPageProps, createHeadManager } from '@inertiajs/core';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            app: {
                name: string;
                shortName: string;
            };
            auth: Auth;
            csrf_token: string;
            flash: {
                success: () => string | null;
                error: () => string | null;
            };
            errors: Record<string, string[]>;
            ziggy: {
                namedRoutes: Record<string, string>;
                namedRoutesWithParams: Record<string, string>;
            };
        };
    }
}

declare module 'vue' {
    interface GlobalDirectives {
        vFocus: Directive<HTMLElement, boolean | undefined>;
    }

    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page<
            SharedPageProps & {
                name: string;
                app: { name: string; shortName: string };
                auth: Auth;
                csrf_token: string;
                flash: {
                    success: () => string | null;
                    error: () => string | null;
                };
                errors: Record<string, string[]>;
            }
        >;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}
