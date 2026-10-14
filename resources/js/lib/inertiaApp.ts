import AccountLayout from '@/layouts/account/AccountLayout.vue';
import AuthLayout from '@/layouts/AuthLayout.vue';
import PublicLayout from '@/layouts/PublicLayout.vue';
import AdminLayout from '@/layouts/admin/AdminLayout.vue';

export const appName = import.meta.env.VITE_APP_NAME || 'devtools';

/** Titulo da aba: "Pagina - devtools". */
export function appTitle(title: string): string {
    return title ? `${title} - ${appName}` : appName;
}

/**
 * Layout padrao por pagina. Compartilhado entre o cliente (app.ts) e o
 * servidor (ssr.ts) para que a hidratacao veja exatamente a mesma arvore.
 *
 * As telas publicas ficam em PublicLayout, o login em AuthLayout e o painel
 * administrativo no layout proprio dele. Tudo o mais e a area da conta - uma
 * navegacao so para visao geral, historico, faturas e settings.
 */
export function resolveLayout(name: string): unknown {
    switch (true) {
        case name === 'Home':
        case name === 'Styleguide':
        case name === 'Privacy':
        case name === 'Terms':
            return PublicLayout;
        case name.startsWith('newsletter/'):
            return PublicLayout;
        // As ferramentas do site (F2) sao paginas publicas e indexaveis: mesma
        // casca da home, sem sidebar nem barra de conta.
        case name.startsWith('tools/'):
            return PublicLayout;
        case name.startsWith('auth/'):
            return AuthLayout;
        // O painel tem layout proprio: nao herda o header publico nem a
        // sidebar da area autenticada (DESIGN: Painel administrativo).
        case name.startsWith('admin/'):
            return AdminLayout;
        default:
            return AccountLayout;
    }
}
