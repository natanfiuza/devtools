import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { renderToString } from '@vue/server-renderer';
import { createSSRApp, h } from 'vue';
import { registerDirectives } from '@/lib/directives';
import { createI18nInstance, syncI18n } from '@/lib/i18n';
import { appTitle, resolveLayout } from '@/lib/inertiaApp';
import { resolvePage } from '@/lib/pages';

/**
 * SSR: ligado em producao por INERTIA_SSR_ENABLED (config/inertia.php).
 * Cada requisicao cria a propria instancia do vue-i18n para que duas
 * requisicoes simultaneas em idiomas diferentes nao se misturem.
 */
void createServer((page) =>
    createInertiaApp({
        page,
        render: renderToString,
        resolve: resolvePage,
        title: appTitle,
        layout: resolveLayout,
        setup: ({ App, props }) => {
            const app = createSSRApp({ render: () => h(App, props) });
            const i18n = createI18nInstance();

            syncI18n(props.initialPage.props, null, i18n);
            app.use(i18n);
            registerDirectives(app);

            return app;
        },
    }),
);
