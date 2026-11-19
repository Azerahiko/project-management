import { defineConfig, lazyPlugins } from 'vite-plus';

export default defineConfig(({ isSsrBuild }) => {
    const ssrInput = isSsrBuild ? 'resources/js/ssr.ts' : undefined;

    return {
        plugins: lazyPlugins(async () => {
            const [
                { default: inertia },
                { wayfinder },
                { default: tailwindcss },
                { default: vue },
                { default: laravel },
                { bunny },
            ] = await Promise.all([
                import('@inertiajs/vite'),
                import('@laravel/vite-plugin-wayfinder'),
                import('@tailwindcss/vite'),
                import('@vitejs/plugin-vue'),
                import('laravel-vite-plugin'),
                import('laravel-vite-plugin/fonts'),
            ]);

            return [
                laravel({
                    input: ['resources/css/app.css', 'resources/js/app.ts'],
                    refresh: true,
                    fonts: [
                        bunny('Plus Jakarta Sans', {
                            weights: [400, 500, 600, 700],
                        }),
                        bunny('JetBrains Mono', {
                            weights: [400, 500, 600],
                        }),
                    ],
                    ssr: ssrInput,
                }),
                inertia({
                    ssr: ssrInput ? { entry: ssrInput } : false,
                }),
                tailwindcss(),
                vue({
                    template: {
                        transformAssetUrls: {
                            base: null,
                            includeAbsolute: false,
                        },
                    },
                }),
                wayfinder({
                    formVariants: true,
                }),
            ];
        }),

        server: {
            host: '127.0.0.1',
            port: 5173,
            strictPort: true,
            watch: {
                ignored: [
                    '**/.agents/**',
                    '**/.claude/**',
                    '**/.cursor/**',
                    '**/.junie/**',
                    '**/vendor/**',
                ],
            },
        },

        lint: {
            ignorePatterns: [
                'vendor/**',
                'node_modules/**',
                'public/**',
                'bootstrap/ssr/**',
                'tailwind.config.js',
                'resources/js/actions/**',
                'resources/js/components/ui/*',
                'resources/js/routes/**',
                'resources/js/wayfinder/**',
            ],
            options: {
                denyWarnings: true,
                typeAware: true,
            },
        },

        fmt: {
            printWidth: 80,
            tabWidth: 4,
            singleQuote: true,
            semi: true,
            singleAttributePerLine: false,
            htmlWhitespaceSensitivity: 'css',
            ignorePatterns: [
                '.github/**',
                'composer.json',
                'resources/js/components/ui/*',
                'resources/views/mail/*',
            ],
            sortTailwindcss: {
                functions: ['clsx', 'cn', 'cva'],
                stylesheet: 'resources/css/app.css',
            },
        },
    };
});
