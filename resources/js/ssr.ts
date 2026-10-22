import { createInertiaApp } from '@inertiajs/vue3';
import { createSSRApp, h, type DefineComponent } from 'vue';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

const pages = import.meta.glob<{ default: DefineComponent }>(
    './pages/**/*.vue',
);

function resolvePageComponent(name: string) {
    const page = pages[`./pages/${name}.vue`];

    if (!page) {
        throw new Error(`Page not found: ${name}`);
    }

    return page().then((module) => module.default);
}

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: resolvePageComponent,
    setup({ App, props, plugin }) {
        return createSSRApp({ render: () => h(App, props) }).use(plugin);
    },
    progress: {
        color: '#4B5563',
    },
});
