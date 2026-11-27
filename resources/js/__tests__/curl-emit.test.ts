import { describe, expect, it } from 'vitest';
import { emitAll, emitFor } from '@/tools/curl-to-code/lib/emit';
import { emitAxios } from '@/tools/curl-to-code/lib/emit/axios';
import { emitFetch } from '@/tools/curl-to-code/lib/emit/fetch';
import { emitLaravel } from '@/tools/curl-to-code/lib/emit/laravel';
import { jsString, phpString } from '@/tools/curl-to-code/lib/emit/strings';
import type { CurlRequest } from '@/tools/curl-to-code/types';

/**
 * Os tres emissores.
 *
 * A comparacao e string com string: as abas da tela mostram exatamente o que
 * esta aqui, entao qualquer mudanca de formato aparece no teste antes de
 * aparecer para o usuario. Os literais usam `String.raw` para a barra escrita
 * no codigo gerado nao ser interpretada pelo proprio teste.
 */

const POST_USERS: CurlRequest = {
    method: 'POST',
    url: 'https://api.example.com/users',
    headers: [{ name: 'Content-Type', value: 'application/json' }],
    body: '{"name":"Ana"}',
    basicAuth: null,
};

const GET_SIMPLE: CurlRequest = {
    method: 'GET',
    url: 'https://x.test',
    headers: [],
    body: null,
    basicAuth: null,
};

const GET_BASIC: CurlRequest = {
    method: 'GET',
    url: 'https://x.test',
    headers: [],
    body: null,
    basicAuth: { username: 'ana', password: 'segredo' },
};

describe('emitLaravel', () => {
    it('encadeia cabecalhos, corpo e verbo', () => {
        expect(emitLaravel(POST_USERS)).toBe(
            String.raw`Http::withHeaders([
    'Content-Type' => 'application/json',
])
    ->withBody('{"name":"Ana"}', 'application/json')
    ->post('https://api.example.com/users');`,
        );
    });

    it('escreve a chamada simples numa linha', () => {
        expect(emitLaravel(GET_SIMPLE)).toBe("Http::get('https://x.test');");
    });

    it('usa withBasicAuth para o -u', () => {
        expect(emitLaravel(GET_BASIC)).toBe(
            "Http::withBasicAuth('ana', 'segredo')->get('https://x.test');",
        );
    });

    it('encadeia cabecalho, credencial, corpo e verbo', () => {
        const request: CurlRequest = {
            method: 'POST',
            url: 'https://x.test',
            headers: [{ name: 'X-Trace', value: 'abc' }],
            body: 'a=1',
            basicAuth: { username: 'ana', password: 'segredo' },
        };

        expect(emitLaravel(request)).toBe(
            String.raw`Http::withHeaders([
    'X-Trace' => 'abc',
])
    ->withBasicAuth('ana', 'segredo')
    ->withBody('a=1', 'text/plain')
    ->post('https://x.test');`,
        );
    });

    it('usa o verbo de fachada quando existe', () => {
        const head: CurlRequest = { ...GET_SIMPLE, method: 'HEAD' };
        const patch: CurlRequest = {
            ...GET_SIMPLE,
            method: 'PATCH',
            headers: [{ name: 'Accept', value: 'text/plain' }],
        };

        expect(emitLaravel(head)).toBe("Http::head('https://x.test');");
        expect(emitLaravel(patch)).toBe(
            String.raw`Http::withHeaders([
    'Accept' => 'text/plain',
])->patch('https://x.test');`,
        );
    });

    it('manda verbos fora da fachada por send', () => {
        const options: CurlRequest = { ...GET_SIMPLE, method: 'OPTIONS' };

        expect(emitLaravel(options)).toBe(
            "Http::send('OPTIONS', 'https://x.test');",
        );
    });
});

describe('emitFetch', () => {
    it('escreve metodo, cabecalhos e corpo', () => {
        expect(emitFetch(POST_USERS)).toBe(
            String.raw`const response = await fetch('https://api.example.com/users', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
    },
    body: '{"name":"Ana"}',
});`,
        );
    });

    it('omite cabecalhos e corpo quando nao ha', () => {
        expect(emitFetch(GET_SIMPLE)).toBe(
            String.raw`const response = await fetch('https://x.test', {
    method: 'GET',
});`,
        );
    });

    it('monta o Authorization do -u com btoa', () => {
        expect(emitFetch(GET_BASIC)).toBe(
            String.raw`const response = await fetch('https://x.test', {
    method: 'GET',
    headers: {
        'Authorization': 'Basic ' + btoa('ana:segredo'),
    },
});`,
        );
    });

    it('nao substitui o Authorization que o comando ja trazia', () => {
        const request: CurlRequest = {
            ...GET_BASIC,
            headers: [{ name: 'Authorization', value: 'Bearer x' }],
        };

        expect(emitFetch(request)).toBe(
            String.raw`const response = await fetch('https://x.test', {
    method: 'GET',
    headers: {
        'Authorization': 'Bearer x',
    },
});`,
        );
    });
});

describe('emitAxios', () => {
    it('usa data para o corpo', () => {
        expect(emitAxios(POST_USERS)).toBe(
            String.raw`const response = await axios.request({
    url: 'https://api.example.com/users',
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
    },
    data: '{"name":"Ana"}',
});`,
        );
    });

    it('escreve a chamada simples', () => {
        expect(emitAxios(GET_SIMPLE)).toBe(
            String.raw`const response = await axios.request({
    url: 'https://x.test',
    method: 'GET',
});`,
        );
    });

    it('usa o objeto auth para o -u', () => {
        expect(emitAxios(GET_BASIC)).toBe(
            String.raw`const response = await axios.request({
    url: 'https://x.test',
    method: 'GET',
    auth: {
        username: 'ana',
        password: 'segredo',
    },
});`,
        );
    });
});

describe('emitAll e emitFor', () => {
    it('devolve as tres saidas na ordem das abas', () => {
        const all = emitAll(GET_SIMPLE);

        expect(Object.keys(all)).toEqual(['laravel', 'fetch', 'axios']);
        expect(emitFor('laravel', GET_SIMPLE)).toBe(all.laravel);
        expect(emitFor('fetch', GET_SIMPLE)).toBe(all.fetch);
        expect(emitFor('axios', GET_SIMPLE)).toBe(all.axios);
    });
});

describe('literais', () => {
    it('escapa aspas, barra e quebras no JavaScript', () => {
        expect(jsString("it's \\ ok")).toBe(String.raw`'it\'s \\ ok'`);
        expect(jsString('a\nb')).toBe(String.raw`'a\nb'`);
        expect(jsString('a\rb')).toBe(String.raw`'a\rb'`);
    });

    it('escapa so aspas e barra no PHP e mantem a quebra', () => {
        expect(phpString("it's \\ ok")).toBe(String.raw`'it\'s \\ ok'`);
        expect(phpString('a\nb')).toBe("'a\nb'");
    });
});
