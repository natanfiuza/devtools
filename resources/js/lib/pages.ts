import type { DefineComponent } from 'vue';

/**
 * Resolve uma pagina em resources/js/pages.
 *
 * No cliente o `resolve` e injetado pelo plugin @inertiajs/vite (mesma
 * convencao, mesmo glob). No SSR o bundle e montado por nos, entao o
 * resolvedor precisa ser explicito e tipado.
 *
 * O glob e relativo a este arquivo (resources/js/lib), dai o ../pages.
 */
const pages = import.meta.glob('../pages/**/*.vue');

export function resolvePage(name: string): Promise<DefineComponent> {
    const loader = pages[`../pages/${name}.vue`];

    if (loader === undefined) {
        throw new Error(`Pagina nao encontrada: ${name}`);
    }

    return loader().then(
        (module) => (module as { default: DefineComponent }).default,
    );
}
