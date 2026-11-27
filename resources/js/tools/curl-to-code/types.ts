/**
 * A requisicao que um comando curl guarda.
 *
 * O parser reduz a linha de comando a este modelo - metodo, URL, cabecalhos,
 * corpo e credencial - e os tres emissores escrevem a partir dele. Tudo que
 * nao cabe no modelo e listado em `unsupported`, nunca descartado em silencio:
 * quem cola `-k` ou `-b` na tela ve o que ficou de fora.
 */

export interface CurlHeader {
    name: string;
    value: string;
}

export interface CurlCredentials {
    username: string;
    password: string;
}

export interface CurlRequest {
    method: string;
    url: string;
    headers: CurlHeader[];
    /** O corpo ja com os `-d` juntados por `&`; `null` quando nao ha. */
    body: string | null;
    basicAuth: CurlCredentials | null;
}

export type CurlWarningCode =
    | 'credential_in_output'
    | 'extra_url'
    | 'header_without_colon'
    | 'data_from_file';

export interface CurlWarning {
    code: CurlWarningCode;
    token: string;
}

export type CurlErrorCode =
    'empty' | 'not_curl' | 'shell' | 'missing_value' | 'missing_url';

export interface CurlError {
    code: CurlErrorCode;
    /** A opcao que falhou, quando ha uma (`-X`, `--url`...). */
    option: string;
    /** Detalhe sem traducao: o codigo do problema do shell. */
    detail: string;
}

export type CurlParseResult =
    | {
          ok: true;
          request: CurlRequest;
          unsupported: string[];
          warnings: CurlWarning[];
      }
    | { ok: false; error: CurlError };

export type CurlTarget = 'laravel' | 'fetch' | 'axios';

export const CURL_TARGETS: readonly CurlTarget[] = [
    'laravel',
    'fetch',
    'axios',
];
