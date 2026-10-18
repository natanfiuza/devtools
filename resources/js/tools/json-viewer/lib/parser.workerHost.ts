/**
 * Onde o worker nasce.
 *
 * Fica num modulo separado porque e a **unica** linha do viewer que depende de
 * um recurso de navegador, e o cliente (testavel em jsdom) pergunta por ela
 * atraves de uma funcao injetavel.
 */

/**
 * O worker do parser, ou `null` quando o ambiente nao tem como criar um.
 *
 * O `new Worker(new URL(...))` e a forma que o Vite reconhece para montar o
 * chunk do worker com a URL certa. A checagem de `Worker` vem **antes** dele de
 * proposito: no SSR e no jsdom nao existe `Worker`, e a expressao da URL nao
 * chega a ser avaliada.
 */
export function createParserWorker(): Worker | null {
    if (typeof Worker === 'undefined') {
        return null;
    }

    try {
        return new Worker(new URL('./parser.worker.ts', import.meta.url), {
            type: 'module',
        });
    } catch {
        // CSP que bloqueia worker, navegador antigo: a tool continua na thread
        // principal, que da o mesmo resultado.
        return null;
    }
}
