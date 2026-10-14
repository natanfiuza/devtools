import type { App } from 'vue';

/**
 * Diretivas dos componentes do kit.
 *
 * Precisa ser registrada nos dois lados: no cliente (app.ts) e no SSR (ssr.ts).
 * O renderizador do servidor tambem resolve as diretivas do template - sem o
 * registro, a pagina quebra no SSR mesmo sem executar nenhum hook de DOM.
 */
export function registerDirectives(app: App): void {
    app.directive('focus', {
        mounted: (el: HTMLElement, binding: { value: unknown }) => {
            if (binding.value !== false) {
                el.focus();
            }
        },
    });
}
