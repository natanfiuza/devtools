import type { Component } from 'vue';

/**
 * Componente de cada tool.
 *
 * O contrato do registro e: uma tool que funciona tem `manifest.json` e um
 * `Tool.vue` no proprio diretorio. A pagina da tool (`pages/tools/Show.vue`) e
 * generica - ela resolve o componente pelo slug e o encaixa no shell, entao a
 * F3 acrescenta ferramentas sem tocar em pagina nenhuma.
 *
 * O glob e **lazy** de proposito: `eager` colocaria CodeMirror e o worker no
 * chunk da home, que nao usa nada disso (ADR-018).
 */
const modules = import.meta.glob('./*/Tool.vue') as Record<
    string,
    () => Promise<unknown>
>;

/**
 * Carregador do componente da tool, ou `null` se ela nao tem um.
 *
 * `null` nao deveria acontecer - o servidor so renderiza tool `available` -,
 * mas o registro e do cliente: um diretorio sem `Tool.vue` daria erro de
 * runtime em vez de uma tela dizendo que a ferramenta nao esta disponivel.
 */
export function toolComponentLoader(
    slug: string,
): (() => Promise<Component>) | null {
    const loader = modules[`./${slug}/Tool.vue`];

    if (loader === undefined) {
        return null;
    }

    return () =>
        loader().then(
            (module) => (module as { default: Component }).default,
        );
}
