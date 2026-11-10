import { acceptsImageType } from './decodeImage';
import type { ImageErrorCode } from './imageErrors';
import type { ImageLimits } from './imageLimits';

/**
 * Importacao de imagem por URL (ADR-122).
 *
 * O navegador busca a imagem direto na origem, com `mode: 'cors'` e sem
 * credenciais. **Nunca** existe proxy: se a origem nao liberar CORS, a
 * mensagem explica e oferece o arquivo local. Nada de servico externo.
 *
 * Somente HTTPS, sem usuario/senha na URL. O teto de bytes e checado no
 * `Content-Length` (quando existe) e de novo durante a leitura, porque o
 * cabecalho pode faltar ou mentir.
 */

export interface ParsedImageUrl {
    ok: true;
    url: URL;
}

export type ParseImageUrlResult =
    | ParsedImageUrl
    | { ok: false; error: ImageErrorCode };

export type FetchImageOutcome =
    | { ok: true; blob: Blob; mime: string }
    | { ok: false; error: ImageErrorCode };

export function parseImageUrl(raw: string): ParseImageUrlResult {
    let url: URL;

    try {
        url = new URL(raw.trim());
    } catch {
        return { ok: false, error: 'invalid_url' };
    }

    if (url.protocol !== 'https:') {
        return { ok: false, error: 'not_https' };
    }

    // Usuario/senha na URL nao tem uso aqui e viram vetor de confusao.
    if (url.username !== '' || url.password !== '') {
        return { ok: false, error: 'invalid_url' };
    }

    return { ok: true, url };
}

function mimeFrom(response: Response): string {
    const header = response.headers.get('content-type') ?? '';

    return header.split(';')[0]?.trim().toLowerCase() ?? '';
}

async function readBlob(
    response: Response,
    mime: string,
    maxBytes: number,
): Promise<FetchImageOutcome> {
    if (response.body === null) {
        const buffer = await response.arrayBuffer();

        if (buffer.byteLength > maxBytes) {
            return { ok: false, error: 'too_large' };
        }

        return { ok: true, blob: new Blob([buffer], { type: mime }), mime };
    }

    const reader = response.body.getReader();
    const chunks: Uint8Array[] = [];
    let total = 0;

    for (;;) {
        const { done, value } = await reader.read();

        if (done) {
            break;
        }

        if (value === undefined) {
            continue;
        }

        total += value.byteLength;

        if (total > maxBytes) {
            // O teto vale mais que o resto do download: para de ler ja.
            await reader.cancel().catch(() => undefined);

            return { ok: false, error: 'too_large' };
        }

        chunks.push(value);
    }

    if (total === 0) {
        return { ok: false, error: 'not_image' };
    }

    // Fragments de rede sao sempre apoiados em `ArrayBuffer`; o tipo generico
    // do stream e mais largo que o `BlobPart` que o construtor aceita.
    return {
        ok: true,
        blob: new Blob(chunks as BlobPart[], { type: mime }),
        mime,
    };
}

export async function fetchImage(
    raw: string,
    limits: ImageLimits,
    signal?: AbortSignal,
): Promise<FetchImageOutcome> {
    const parsed = parseImageUrl(raw);

    if (!parsed.ok) {
        return parsed;
    }

    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), limits.fetchTimeoutMs);
    const forwardAbort = (): void => controller.abort();

    signal?.addEventListener('abort', forwardAbort);

    try {
        const response = await fetch(parsed.url.toString(), {
            mode: 'cors',
            credentials: 'omit',
            referrerPolicy: 'no-referrer',
            signal: controller.signal,
        });

        if (!response.ok) {
            return { ok: false, error: 'network' };
        }

        const mime = mimeFrom(response);

        if (!acceptsImageType(mime)) {
            return { ok: false, error: 'not_image' };
        }

        const declared = Number.parseInt(
            response.headers.get('content-length') ?? '',
            10,
        );

        if (Number.isFinite(declared) && declared > limits.maxBytes) {
            return { ok: false, error: 'too_large' };
        }

        return await readBlob(response, mime, limits.maxBytes);
    } catch {
        if (signal?.aborted === true) {
            return { ok: false, error: 'cancelled' };
        }

        // AbortError do nosso timer: o servidor nao respondeu a tempo.
        if (controller.signal.aborted) {
            return { ok: false, error: 'timeout' };
        }

        // `TypeError` e o que o navegador da quando o CORS nao libera; rede
        // caida tambem chega aqui. A tela trata os dois como a mesma coisa.
        return { ok: false, error: 'cors' };
    } finally {
        clearTimeout(timer);
        signal?.removeEventListener('abort', forwardAbort);
    }
}
