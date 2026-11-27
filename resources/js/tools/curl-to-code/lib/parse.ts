/**
 * O comando curl, reduzido ao modelo da requisicao.
 *
 * O nucleo e o que aparece em quase toda linha colada: `-X`, `-H`, `-d`, `-G`,
 * `--url`, `-u` e `--json`. O resto nao e adivinhado: cada opcao de fora entra
 * em `unsupported`, para a tela avisar em vez de gerar codigo que finge nao ter
 * visto `-k` ou `-b`.
 *
 * A precedencia do metodo e a do proprio curl: `-X` manda; depois `-G` (GET);
 * depois o corpo (POST); sem nada disso, GET. `-G` move os `-d` para a query,
 * que e o unico jeito de mandar dados num GET sem corpo.
 *
 * `--json` e um atalho do curl: corpo mais `Content-Type` e `Accept` de JSON,
 * adicionados so quando o comando nao trouxe os dois.
 */

import {
    type CurlCredentials,
    type CurlError,
    type CurlErrorCode,
    type CurlHeader,
    type CurlParseResult,
    type CurlRequest,
    type CurlWarning,
} from '../types';
import { tokenizeShell } from './tokenize';

/** Opcoes curtas do nucleo que recebem valor. */
const SHORT_WITH_VALUE = new Set(['X', 'H', 'd', 'u']);

function isCurlCommand(token: string): boolean {
    const base = token.split(/[\\/]/).pop()?.toLowerCase() ?? '';

    return base === 'curl' || base === 'curl.exe';
}

function errorOf(code: CurlErrorCode, option: string, detail = ''): CurlError {
    return { code, option, detail };
}

/** Le um comando curl e devolve o modelo, os avisos e o que ficou de fora. */
export function parseCurl(source: string): CurlParseResult {
    if (source.trim() === '') {
        return { ok: false, error: errorOf('empty', '') };
    }

    const { tokens, issues } = tokenizeShell(source);

    if (issues.length > 0) {
        return {
            ok: false,
            error: errorOf('shell', '', issues[0]?.code ?? ''),
        };
    }

    const command = tokens[0] ?? '';

    if (!isCurlCommand(command)) {
        return { ok: false, error: errorOf('not_curl', command) };
    }

    let explicitMethod: string | null = null;
    let useGet = false;
    let json = false;
    let basicAuth: CurlCredentials | null = null;

    const headers: CurlHeader[] = [];
    const data: string[] = [];
    // A URL e coletada como os `-d`: a atribuicao fica fora das closures, para
    // o TypeScript nao tratar o valor como constante nula depois do laco.
    const urls: string[] = [];
    const unsupported: string[] = [];
    const warnings: CurlWarning[] = [];

    let failure: CurlError | null = null;

    const fail = (code: CurlErrorCode, option: string): number => {
        failure = errorOf(code, option);

        return tokens.length;
    };

    const addHeader = (raw: string): void => {
        const colon = raw.indexOf(':');

        if (colon <= 0) {
            warnings.push({ code: 'header_without_colon', token: raw });

            return;
        }

        headers.push({
            name: raw.slice(0, colon).trim(),
            value: raw.slice(colon + 1).trim(),
        });
    };

    const addData = (raw: string): void => {
        // `@arquivo` e `<arquivo` mandam o curl ler o disco; a tool nao le
        // arquivos, entao o valor fica de fora e a tela avisa.
        if (raw.startsWith('@') || raw.startsWith('<')) {
            warnings.push({ code: 'data_from_file', token: raw });
            unsupported.push(raw);

            return;
        }

        data.push(raw);
    };

    const addBasicAuth = (raw: string): void => {
        const colon = raw.indexOf(':');

        basicAuth =
            colon === -1
                ? { username: raw, password: '' }
                : {
                      username: raw.slice(0, colon),
                      password: raw.slice(colon + 1),
                  };

        // A senha nao se repete no aviso: ele nomeia o usuario, e o codigo
        // gerado e que carrega a credencial (com o aviso na tela).
        warnings.push({
            code: 'credential_in_output',
            token: basicAuth.username,
        });
    };

    const addUrl = (token: string): void => {
        if (urls.length === 0) {
            urls.push(token);

            return;
        }

        if (urls[0] !== token) {
            warnings.push({ code: 'extra_url', token });
        }
    };

    const takeValue = (
        attached: string | undefined,
        start: number,
    ): string | undefined => attached ?? tokens[start + 1];

    const readLong = (token: string, start: number): number => {
        const equals = token.indexOf('=');
        const name = (
            equals === -1 ? token : token.slice(0, equals)
        ).toLowerCase();
        const attached = equals === -1 ? undefined : token.slice(equals + 1);
        const consumed = attached === undefined ? 2 : 1;

        if (name === '--get') {
            useGet = true;

            return start + 1;
        }

        const value = takeValue(attached, start);

        if (
            name === '--request' ||
            name === '--header' ||
            name === '--data' ||
            name === '--data-raw' ||
            name === '--data-binary' ||
            name === '--url' ||
            name === '--user' ||
            name === '--json'
        ) {
            if (value === undefined || value === '') {
                return fail('missing_value', name);
            }

            if (name === '--request') {
                explicitMethod = value;
            } else if (name === '--header') {
                addHeader(value);
            } else if (name === '--url') {
                addUrl(value);
            } else if (name === '--user') {
                addBasicAuth(value);
            } else {
                if (name === '--json') {
                    json = true;
                }

                addData(value);
            }

            return start + consumed;
        }

        // Fora do nucleo: a opcao inteira vai para a lista de nao suportadas.
        // Opcoes longas com valor colado ao `=` chegam completas; as que
        // separam com espaco aparecem so pelo nome (o valor seguinte segue o
        // fluxo normal e vira URL extra, se nao for uma).
        unsupported.push(token);

        return start + 1;
    };

    const readShort = (token: string, start: number): number => {
        const head = token[1] ?? '';

        if (token.length === 2) {
            if (head === 'G') {
                useGet = true;

                return start + 1;
            }

            if (SHORT_WITH_VALUE.has(head)) {
                const value = tokens[start + 1];

                if (value === undefined || value === '') {
                    return fail('missing_value', token);
                }

                applyShort(head, value);

                return start + 2;
            }

            unsupported.push(token);

            return start + 1;
        }

        if (SHORT_WITH_VALUE.has(head)) {
            const value = token.slice(2);

            if (value === '') {
                return fail('missing_value', `-${head}`);
            }

            applyShort(head, value);

            return start + 1;
        }

        // Cluster de flags booleanas (`-sS`): cada letra vira uma entrada, para
        // a lista dizer exatamente o que foi ignorado.
        for (const flag of token.slice(1)) {
            if (flag === 'G') {
                useGet = true;
            } else {
                unsupported.push(`-${flag}`);
            }
        }

        return start + 1;
    };

    const applyShort = (flag: string, value: string): void => {
        if (flag === 'X') {
            explicitMethod = value;
        } else if (flag === 'H') {
            addHeader(value);
        } else if (flag === 'd') {
            addData(value);
        } else if (flag === 'u') {
            addBasicAuth(value);
        }
    };

    let index = 1;

    while (index < tokens.length && failure === null) {
        const token = tokens[index] ?? '';

        if (token.startsWith('--')) {
            index = readLong(token, index);
        } else if (token.startsWith('-') && token !== '-') {
            index = readShort(token, index);
        } else {
            addUrl(token);
            index += 1;
        }
    }

    if (failure !== null) {
        return { ok: false, error: failure };
    }

    let url: string | null = urls[0] ?? null;

    if (useGet && data.length > 0 && url !== null) {
        const query = data.join('&');

        url = `${url}${url.includes('?') ? '&' : '?'}${query}`;
    }

    const body = useGet || data.length === 0 ? null : data.join('&');

    if (json) {
        const has = (name: string): boolean =>
            headers.some((header) => header.name.toLowerCase() === name);

        if (!has('content-type')) {
            headers.push({
                name: 'Content-Type',
                value: 'application/json',
            });
        }

        if (!has('accept')) {
            headers.push({ name: 'Accept', value: 'application/json' });
        }
    }

    if (url === null || url === '') {
        return { ok: false, error: errorOf('missing_url', '') };
    }

    const method = (
        explicitMethod ?? (useGet ? 'GET' : body === null ? 'GET' : 'POST')
    ).toUpperCase();

    const request: CurlRequest = {
        method,
        url,
        headers,
        body,
        basicAuth,
    };

    return { ok: true, request, unsupported, warnings };
}
