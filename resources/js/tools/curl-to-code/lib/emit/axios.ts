/**
 * O `axios.request`.
 *
 * O corpo sai como `data` (e nao `body`, que e do fetch), e a credencial do
 * `-u` vira o objeto `auth`, para o proprio axios montar o `Authorization` -
 * nada de `btoa` aqui.
 */

import type { CurlRequest } from '../../types';
import { jsString } from './strings';

export function emitAxios(request: CurlRequest): string {
    const fields: string[] = [
        `    url: ${jsString(request.url)},`,
        `    method: ${jsString(request.method)},`,
    ];

    if (request.headers.length > 0) {
        const headerLines = request.headers.map(
            (header) =>
                `        ${jsString(header.name)}: ${jsString(header.value)},`,
        );

        fields.push(`    headers: {\n${headerLines.join('\n')}\n    },`);
    }

    if (request.body !== null) {
        fields.push(`    data: ${jsString(request.body)},`);
    }

    if (request.basicAuth !== null) {
        fields.push(
            `    auth: {\n        username: ${jsString(
                request.basicAuth.username,
            )},\n        password: ${jsString(request.basicAuth.password)},\n    },`,
        );
    }

    return `const response = await axios.request({\n${fields.join('\n')}\n});`;
}
