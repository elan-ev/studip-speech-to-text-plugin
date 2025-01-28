import '../css/main.css';
import { NamedRoutes } from '@elan-ev/studip-named-routes';
import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h } from 'vue';
import { createGettext } from 'vue3-gettext';

const appName = 'Speech to text';

const translations = {
    en: {},
};

// load courseware's CSS
STUDIP.loadChunk("courseware", { silent: true }).catch(() => {});

createInertiaApp({
    progress: {
        color: '#4B5563',
    },
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
        return pages[`./Pages/${name}.vue`];
    },
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });

        app.use(createGettext({ translations, silent: true }))
            .use(plugin)
            .use(NamedRoutes, window.NamedRoutes);

        return app.mount(el);
    },
});
