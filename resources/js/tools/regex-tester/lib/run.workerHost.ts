/**
 * Onde o worker da busca nasce.
 *
 * Fica num modulo separado porque e a **unica** linha da tool que depende de um
 * recurso de navegador, e quem a chama (`runner.ts`) a recebe por parametro -
 * assim o teste pode fingir um worker sem tocar em `new Worker`.
 */

/**
 * O worker, ou `null` quando o ambiente nao tem como criar um.
 *
 * O `new Worker(new URL(...))` e a forma que o Vite reconhece para montar o
 * chunk do worker com a URL certa. A checagem de `Worker` vem **antes** dele de
 * proposito: no SSR e no jsdom nao existe `Worker`, e a expressao da URL nao
 * chega a ser avaliada.
 */
export function createRunWorker(): Worker | null {
    if (typeof Worker === 'undefined') {
        return null;
    }

    try {
        return new Worker(new URL('./run.worker.ts', import.meta.url), { type: 'module' });
    } catch {
        // CSP que bloqueia worker, navegador antigo: a busca continua na thread
        // principal, que da o mesmo resultado.
        return null;
    }
}
