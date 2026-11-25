/**
 * Onde o worker do diff nasce.
 *
 * Fica num modulo separado porque e a **unica** linha da tool que depende de
 * um recurso de navegador; a tela pergunta por ela atraves de uma funcao
 * injetavel (como no conversor e no JSON Viewer).
 */

/**
 * O worker, ou `null` quando o ambiente nao tem como criar um.
 *
 * O `new Worker(new URL(...))` e a forma que o Vite reconhece para montar o
 * chunk do worker com a URL certa. A checagem de `Worker` vem **antes** dele
 * de proposito: no SSR e no jsdom nao existe `Worker`, e a expressao da URL
 * nao chega a ser avaliada.
 */
export function createDiffWorker(): Worker | null {
    if (typeof Worker === 'undefined') {
        return null;
    }

    try {
        return new Worker(new URL('./diff.worker.ts', import.meta.url), {
            type: 'module',
        });
    } catch {
        // CSP que bloqueia worker, navegador antigo: a tool continua na thread
        // principal, que da o mesmo resultado.
        return null;
    }
}
