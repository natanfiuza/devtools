/**
 * O `fetch` do navegador.
 *
 * A credencial do `-u` entra como cabecalho `Authorization`, montado com
 * `btoa` na hora - e o mesmo que o curl faz no fio, e o aviso da tela ja
 * lembrou que ela fica escrita no codigo. Se o comando ja trazia um
 * `Authorization`, ele manda: o `-u` nao o substitui.
 */

import type { CurlRequest } from '../../types';
import { jsString } from './strings';

export function emitFetch(request: CurlRequest): string {
    const fields: string[] = [`    method: ${jsString(request.method)},`];

    const hasAuthorization = request.headers.some(
        (header) => header.name.toLowerCase() === 'authorization',
    );

    const headerLines = request.headers.map(
        (header) =>
            `        ${jsString(header.name)}: ${jsString(header.value)},`,
    );

    if (request.basicAuth !== null && !hasAuthorization) {
        const pair = `${request.basicAuth.username}:${request.basicAuth.password}`;

        headerLines.push(
            `        'Authorization': 'Basic ' + btoa(${jsString(pair)}),`,
        );
    }

    if (headerLines.length > 0) {
        fields.push(`    headers: {\n${headerLines.join('\n')}\n    },`);
    }

    if (request.body !== null) {
        fields.push(`    body: ${jsString(request.body)},`);
    }

    return `const response = await fetch(${jsString(
        request.url,
    )}, {\n${fields.join('\n')}\n});`;
}
