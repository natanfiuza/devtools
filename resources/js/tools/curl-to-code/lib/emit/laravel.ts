/**
 * O cliente HTTP do Laravel.
 *
 * `withHeaders` recebe o array na ordem do comando; `withBasicAuth` aparece
 * quando o `-u` veio; `withBody` leva o corpo **e** o tipo de conteudo, que o
 * Laravel exige como segundo argumento. Verbos que o cliente tem de fachada
 * viram `->post(...)`; qualquer outro sai por `->send(...)`, que aceita o
 * verbo como string.
 */

import type { CurlRequest } from '../../types';
import { phpString } from './strings';

const FACADE_VERBS = new Set(['get', 'post', 'put', 'patch', 'delete', 'head']);

function contentTypeOf(request: CurlRequest): string {
    const header = request.headers.find(
        (item) => item.name.toLowerCase() === 'content-type',
    );

    return header?.value ?? 'text/plain';
}

function verbCall(request: CurlRequest): string {
    const verb = request.method.toLowerCase();

    return FACADE_VERBS.has(verb)
        ? `${verb}(${phpString(request.url)})`
        : `send(${phpString(request.method)}, ${phpString(request.url)})`;
}

export function emitLaravel(request: CurlRequest): string {
    const head =
        request.headers.length === 0
            ? 'Http'
            : `Http::withHeaders([\n${request.headers
                  .map(
                      (header) =>
                          `    ${phpString(header.name)} => ${phpString(
                              header.value,
                          )},`,
                  )
                  .join('\n')}\n])`;

    const steps: string[] = [];

    if (request.basicAuth !== null) {
        steps.push(
            `withBasicAuth(${phpString(
                request.basicAuth.username,
            )}, ${phpString(request.basicAuth.password)})`,
        );
    }

    if (request.body !== null) {
        steps.push(
            `withBody(${phpString(request.body)}, ${phpString(
                contentTypeOf(request),
            )})`,
        );
    }

    const verb = verbCall(request);

    if (steps.length === 0) {
        return request.headers.length === 0
            ? `Http::${verb};`
            : `${head}->${verb};`;
    }

    if (request.headers.length === 0) {
        return `Http::${steps.join('->')}->${verb};`;
    }

    return `${head}\n    ->${steps.join('\n    ->')}\n    ->${verb};`;
}
