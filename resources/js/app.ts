import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h, type DefineComponent } from 'vue';

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
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });
        app.use(plugin);
        app.mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
