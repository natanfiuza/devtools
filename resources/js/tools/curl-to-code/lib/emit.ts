/**
 * Os tres destinos, pela mesma requisicao.
 *
 * A tela escolhe a aba e copia o texto daquela; os emissores sao funcoes
 * puras do modelo, entao o teste compara string com string e a tela nao tem
 * logica de geracao.
 */

import type { CurlRequest, CurlTarget } from '../types';
import { emitAxios } from './emit/axios';
import { emitFetch } from './emit/fetch';
import { emitLaravel } from './emit/laravel';

export { emitAxios } from './emit/axios';
export { emitFetch } from './emit/fetch';
export { emitLaravel } from './emit/laravel';

export function emitFor(target: CurlTarget, request: CurlRequest): string {
    switch (target) {
        case 'laravel':
            return emitLaravel(request);
        case 'fetch':
            return emitFetch(request);
        case 'axios':
            return emitAxios(request);
    }
}

/** Os tres textos, na ordem das abas. */
export function emitAll(request: CurlRequest): Record<CurlTarget, string> {
    return {
        laravel: emitLaravel(request),
        fetch: emitFetch(request),
        axios: emitAxios(request),
    };
}
