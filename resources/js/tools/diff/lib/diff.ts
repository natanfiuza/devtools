import { jsonDiff } from './jsonDiff';
import { diffLines } from './lines';
import type { DiffRequest, DiffResult } from '../types';

/**
 * O motor do diff: a escolha do modo e a unica coisa que acontece aqui.
 *
 * Os dois modos comparam coisas diferentes e devolvem resultados diferentes; a
 * tela pede por um e pinta o que veio. Manter a escolha num lugar so e o que
 * deixa o worker e a thread principal chamando exatamente a mesma funcao.
 */
export function diffDocuments(request: DiffRequest): DiffResult {
    return request.mode === 'text'
        ? { mode: 'text', text: diffLines(request.left, request.right) }
        : { mode: 'json', json: jsonDiff(request.left, request.right) };
}
