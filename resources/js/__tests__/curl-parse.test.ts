import { describe, expect, it } from 'vitest';
import { parseCurl } from '@/tools/curl-to-code/lib/parse';
import type { CurlParseResult } from '@/tools/curl-to-code/types';

/**
 * O parser do comando curl.
 *
 * O teste fixa o contrato que a tela consome: o modelo da requisicao, a lista
 * do que ficou de fora (nunca vazia por engano), os avisos e os erros. Cada
 * caso usa uma linha real de README, com aspas e continuacao.
 */

function ok(source: string) {
    const result = parseCurl(source);

    if (!result.ok) {
        throw new Error(`esperava sucesso, veio ${result.error.code}`);
    }

    return result;
}

function error(source: string): CurlParseResult & { ok: false } {
    const result = parseCurl(source);

    if (result.ok) {
        throw new Error('esperava erro, veio sucesso');
    }

    return result;
}

describe('parseCurl', () => {
    it('le um GET simples', () => {
        const result = ok('curl https://api.example.com/users');

        expect(result.request).toEqual({
            method: 'GET',
            url: 'https://api.example.com/users',
            headers: [],
            body: null,
            basicAuth: null,
        });
        expect(result.unsupported).toEqual([]);
        expect(result.warnings).toEqual([]);
    });

    it('le metodo, cabecalho e corpo', () => {
        const result = ok(
            'curl -X POST https://api.example.com/users -H \'Content-Type: application/json\' -d \'{"name":"Ana"}\'',
        );

        expect(result.request.method).toBe('POST');
        expect(result.request.headers).toEqual([
            { name: 'Content-Type', value: 'application/json' },
        ]);
        expect(result.request.body).toBe('{"name":"Ana"}');
    });

    it('aceita valor colado a opcao', () => {
        const result = ok(
            "curl -XPOST -H'Accept: text/plain' -d'a=1&b=2' https://x.test",
        );

        expect(result.request.method).toBe('POST');
        expect(result.request.headers).toEqual([
            { name: 'Accept', value: 'text/plain' },
        ]);
        expect(result.request.body).toBe('a=1&b=2');
    });

    it('junta os -d por &', () => {
        const result = ok('curl -d a=1 -d b=2 https://x.test');

        expect(result.request.body).toBe('a=1&b=2');
        expect(result.request.method).toBe('POST');
    });

    it('move os -d para a query com -G', () => {
        const result = ok(
            'curl -G -d q=hello -d page=2 https://x.test/search?x=1',
        );

        expect(result.request.method).toBe('GET');
        expect(result.request.url).toBe(
            'https://x.test/search?x=1&q=hello&page=2',
        );
        expect(result.request.body).toBeNull();
    });

    it('usa ? quando a URL ainda nao tinha query', () => {
        const result = ok('curl --get --data q=hello https://x.test/search');

        expect(result.request.url).toBe('https://x.test/search?q=hello');
    });

    it('le o --url', () => {
        const result = ok('curl --url https://x.test --request PUT');

        expect(result.request.url).toBe('https://x.test');
        expect(result.request.method).toBe('PUT');
    });

    it('le o --json e completa os cabecalhos que faltam', () => {
        const result = ok('curl --json \'{"a":1}\' https://x.test');

        expect(result.request.method).toBe('POST');
        expect(result.request.body).toBe('{"a":1}');
        expect(result.request.headers).toEqual([
            { name: 'Content-Type', value: 'application/json' },
            { name: 'Accept', value: 'application/json' },
        ]);
    });

    it('respeita o Content-Type que o comando ja trazia', () => {
        const result = ok(
            "curl --json '{}' -H 'Content-Type: text/plain' https://x.test",
        );

        expect(result.request.headers).toEqual([
            { name: 'Content-Type', value: 'text/plain' },
            { name: 'Accept', value: 'application/json' },
        ]);
    });

    it('le a credencial do -u sem repetir a senha no aviso', () => {
        const result = ok('curl -u ana:segredo https://x.test/private');

        expect(result.request.basicAuth).toEqual({
            username: 'ana',
            password: 'segredo',
        });
        expect(result.warnings).toEqual([
            { code: 'credential_in_output', token: 'ana' },
        ]);
    });

    it('aceita -u sem senha', () => {
        const result = ok('curl --user ana https://x.test/private');

        expect(result.request.basicAuth).toEqual({
            username: 'ana',
            password: '',
        });
    });

    it('lista as opcoes fora do nucleo', () => {
        const result = ok('curl -k -sS --compressed https://x.test');

        expect(result.unsupported).toEqual(['-k', '-s', '-S', '--compressed']);
        expect(result.request.method).toBe('GET');
    });

    it('recusa -d de arquivo e lista o valor', () => {
        const result = ok('curl -d @body.json https://x.test');

        expect(result.request.body).toBeNull();
        expect(result.request.method).toBe('GET');
        expect(result.unsupported).toEqual(['@body.json']);
        expect(result.warnings).toEqual([
            { code: 'data_from_file', token: '@body.json' },
        ]);
    });

    it('avisa a URL extra e usa a primeira', () => {
        const result = ok('curl https://a.test https://b.test');

        expect(result.request.url).toBe('https://a.test');
        expect(result.warnings).toEqual([
            { code: 'extra_url', token: 'https://b.test' },
        ]);
    });

    it('aceita o caminho completo do curl', () => {
        expect(ok('/usr/bin/curl https://x.test').request.url).toBe(
            'https://x.test',
        );
        expect(ok('curl.exe https://x.test').request.url).toBe(
            'https://x.test',
        );
    });

    it('avisa cabecalho sem dois-pontos e nao o usa', () => {
        const result = ok("curl -H 'X-Sem-Dois-Pontos' https://x.test");

        expect(result.request.headers).toEqual([]);
        expect(result.warnings).toEqual([
            { code: 'header_without_colon', token: 'X-Sem-Dois-Pontos' },
        ]);
    });

    it('recusa entrada vazia', () => {
        expect(error('   ').error).toEqual({
            code: 'empty',
            option: '',
            detail: '',
        });
    });

    it('recusa comando que nao e curl', () => {
        expect(error('wget https://x.test').error).toEqual({
            code: 'not_curl',
            option: 'wget',
            detail: '',
        });
    });

    it('recusa aspas sem par', () => {
        expect(error("curl 'https://x.test").error).toEqual({
            code: 'shell',
            option: '',
            detail: 'unterminated_single',
        });
    });

    it('recusa opcao sem valor', () => {
        expect(error('curl -X').error).toEqual({
            code: 'missing_value',
            option: '-X',
            detail: '',
        });
        expect(error('curl --url').error).toEqual({
            code: 'missing_value',
            option: '--url',
            detail: '',
        });
    });

    it('recusa comando sem URL', () => {
        expect(error("curl -H 'Accept: a'").error).toEqual({
            code: 'missing_url',
            option: '',
            detail: '',
        });
    });
});
