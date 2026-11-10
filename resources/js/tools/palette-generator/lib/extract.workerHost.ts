/**
 * Cria o worker da extracao, ou `null` quando o navegador nao tem `Worker`
 * (jsdom nos testes, ambiente sem suporte). Quem recebe `null` roda a mesma
 * conta na thread principal.
 *
 * O nome do arquivo e resolvido pelo bundler (`new URL(..., import.meta.url)`),
 * entao o modulo do worker sai num chunk proprio, carregado sob demanda.
 */
export function spawnExtractionWorker(): Worker | null {
    if (typeof Worker === 'undefined') {
        return null;
    }

    try {
        return new Worker(new URL('./extract.worker.ts', import.meta.url), {
            type: 'module',
        });
    } catch {
        return null;
    }
}
