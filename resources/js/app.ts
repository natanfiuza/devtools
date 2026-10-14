import { createInertiaApp, router } from '@inertiajs/vue3';
import { initializeTheme } from '@/composables/useAppearance';
import { registerDirectives } from '@/lib/directives';
import { appTitle, resolveLayout } from '@/lib/inertiaApp';
import { initializeFlashToast } from '@/lib/flashToast';
import { i18n, syncI18n } from '@/lib/i18n';

void createInertiaApp({
    title: appTitle,
    layout: resolveLayout,
    withApp: (app, { page }) => {
        app.use(i18n);
        registerDirectives(app);

        const sync = (props: typeof page.props): void =>
            syncI18n(props, document.documentElement);

        // O idioma vive nas props compartilhadas: sincroniza na primeira
        // renderizacao e a cada navegacao (inclusive voltar/avancar).
        sync(page.props);

        router.on('navigate', (event) => {
            sync(event.detail.page.props);
        });
    },
    progress: {
        color: '#66ced6',
    },
});

// This will set light / dark mode on page load...
initializeTheme();

// This will listen for flash toast data from the server...
initializeFlashToast();
