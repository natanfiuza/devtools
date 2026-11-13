/**
 * Cliente JSON da biblioteca de cores (F5, ADR-127).
 *
 * O anonimo nunca chama estas rotas: sem conta, historico e favoritos ficam
 * no `localStorage`. O `credentials: 'same-origin'` mantem a sessao e o
 * `X-XSRF-TOKEN` vem do cookie que o Laravel ja grava.
 */

const BASE = '/api/tools/colors';

export type ColorsApiMethod = 'GET' | 'POST' | 'DELETE';

function csrfToken(): string {
    if (typeof document === 'undefined') {
        return '';
    }

    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);

    return match && match[1] ? decodeURIComponent(match[1]) : '';
}

export async function colorsApi<T>(
    path: string,
    options: { method?: ColorsApiMethod; body?: unknown } = {},
): Promise<T> {
    const method = options.method ?? 'GET';
    const headers = new Headers({
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    });

    if (method !== 'GET') {
        headers.set('X-XSRF-TOKEN', csrfToken());
    }

    if (options.body !== undefined) {
        headers.set('Content-Type', 'application/json');
    }

    const response = await fetch(`${BASE}${path}`, {
        method,
        headers,
        credentials: 'same-origin',
        body:
            options.body === undefined
                ? undefined
                : JSON.stringify(options.body),
    });

    if (!response.ok) {
        throw new Error(`colors_api_${response.status}`);
    }

    return (await response.json()) as T;
}
