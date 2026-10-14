import AppLayout from '@/layouts/AppLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

export const appName = import.meta.env.VITE_APP_NAME || 'devtools';

/** Titulo da aba: "Pagina - devtools". */
export function appTitle(title: string): string {
    return title ? `${title} - ${appName}` : appName;
}

/**
 * Layout padrao por pagina. Compartilhado entre o cliente (app.ts) e o
 * servidor (ssr.ts) para que a hidratacao veja exatamente a mesma arvore.
 *
 * As telas publicas ficam em PublicLayout; as de auth e settings mantem os
 * layouts do starter kit (o PublicLayout e o do site, nao o do painel).
 */
export function resolveLayout(name: string): unknown {
    switch (true) {
        case name === 'Home':
        case name === 'Styleguide':
            return PublicLayout;
        case name.startsWith('auth/'):
            return AuthLayout;
        case name.startsWith('settings/'):
            return [AppLayout, SettingsLayout];
        default:
            return AppLayout;
    }
}
