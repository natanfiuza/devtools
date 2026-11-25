import { parse as parseToml } from 'smol-toml';
import { describe, expect, it } from 'vitest';
import { parse as parseYaml } from 'yaml';
import { convert } from '@/tools/data-format-converter/lib/convert';
import type {
    ConvertFailure,
    ConvertSuccess,
    DataFormat,
} from '@/tools/data-format-converter/types';

/**
 * O motor do conversor: a matriz dos quatro formatos, a normalizacao de
 * mesmo-formato e os erros que o destino nao aceita.
 *
 * A maior parte das assercoes le o **documento convertido de volta** com o
 * parser do formato de destino. Comparar texto exato so funciona para o JSON e
 * para o XML, que sao escritos aqui; o YAML e o TOML sao de biblioteca, e o
 * estilo que elas escolhem (aspas, indentacao) nao e contrato - o que e
 * contrato e o valor. O JSON e o XML tem a forma conferida nas letras, porque a
 * ordem das chaves e a indentacao fazem parte da decisao (ADR-180).
 */

function ok(text: string, from: DataFormat, to: DataFormat): ConvertSuccess {
    const result = convert({ text, from, to });

    expect(result.ok, JSON.stringify(result)).toBe(true);

    return result as ConvertSuccess;
}

function fail(text: string, from: DataFormat, to: DataFormat): ConvertFailure {
    const result = convert({ text, from, to });

    expect(result.ok, JSON.stringify(result)).toBe(false);

    return result as ConvertFailure;
}

function codes(result: ConvertSuccess): string[] {
    return result.losses.map((loss) => loss.code);
}

describe('conversor de formatos: a matriz', () => {
    it('normaliza JSON para JSON sem reordenar as chaves', () => {
        const resultado = ok('{"b":1,"a":[1,2],"c":[]}', 'json', 'json');

        expect(resultado.text).toBe(`{
  "b": 1,
  "a": [
    1,
    2
  ],
  "c": []
}`);
        expect(resultado.losses).toEqual([]);
    });

    it('entrega a chave que parece inteiro primeiro, como o JSON.parse', () => {
        // Limitacao do JavaScript, registrada no ACEITE: `JSON.parse` (e o
        // `yaml`) devolvem objeto, e a enumeracao da chave que parece inteiro
        // vem antes das outras. A arvore preserva a ordem que recebe - e esta.
        const resultado = ok('{"b":1,"2":2,"a":3}', 'json', 'json');

        expect(resultado.text).toBe(`{
  "2": 2,
  "b": 1,
  "a": 3
}`);
    });

    it('converte JSON para YAML mantendo os valores e a ordem', () => {
        const resultado = ok(
            '{"name":"devtools","count":2,"tags":["a","b"],"meta":{"ok":true}}',
            'json',
            'yaml',
        );

        expect(parseYaml(resultado.text)).toEqual({
            name: 'devtools',
            count: 2,
            tags: ['a', 'b'],
            meta: { ok: true },
        });
        // A ordem do documento e a ordem do YAML: `name` vem antes de `count`.
        expect(resultado.text.indexOf('name')).toBeLessThan(
            resultado.text.indexOf('count'),
        );
        expect(resultado.losses).toEqual([]);
    });

    it('converte JSON para TOML', () => {
        const resultado = ok(
            '{"title":"x","count":2,"tags":["a","b"],"owner":{"name":"Ada"}}',
            'json',
            'toml',
        );

        expect(parseToml(resultado.text)).toEqual({
            title: 'x',
            count: 2,
            tags: ['a', 'b'],
            owner: { name: 'Ada' },
        });
    });

    it('converte JSON para XML com a convencao da arvore', () => {
        const resultado = ok(
            '{"user":{"@id":"1","name":"Ada"}}',
            'json',
            'xml',
        );

        expect(resultado.text).toBe(`<?xml version="1.0" encoding="UTF-8"?>
<user id="1">
  <name>Ada</name>
</user>`);
    });

    it('converte YAML para JSON', () => {
        const resultado = ok(
            'server:\n  host: localhost\n  port: 8080\nenabled: true\n',
            'yaml',
            'json',
        );

        expect(JSON.parse(resultado.text)).toEqual({
            server: { host: 'localhost', port: 8080 },
            enabled: true,
        });
        // Sem comentario no texto, nao ha aviso: a perda contada e a do que a
        // origem tinha, e nao uma do formato.
        expect(codes(resultado)).toEqual([]);
    });

    it('converte TOML para JSON', () => {
        const resultado = ok(
            'title = "x"\n\n[owner]\nname = "Ada"\n',
            'toml',
            'json',
        );

        expect(JSON.parse(resultado.text)).toEqual({
            title: 'x',
            owner: { name: 'Ada' },
        });
    });

    it('converte XML para JSON', () => {
        const resultado = ok(
            '<config env="dev"><db host="localhost"/><db host="replica"/></config>',
            'xml',
            'json',
        );

        expect(JSON.parse(resultado.text)).toEqual({
            config: {
                '@env': 'dev',
                db: [{ '@host': 'localhost' }, { '@host': 'replica' }],
            },
        });
    });

    it('converte entre os tres formatos que nao sao JSON', () => {
        // Um YAML com aninhamento e lista, lido por cada destino: e a prova de
        // que a arvore e o unico caminho - nenhum par fala direto com o outro.
        const yaml = 'app:\n  name: devtools\n  ports:\n    - 80\n    - 443\n';

        expect(JSON.parse(ok(yaml, 'yaml', 'json').text)).toEqual({
            app: { name: 'devtools', ports: [80, 443] },
        });
        expect(parseToml(ok(yaml, 'yaml', 'toml').text)).toEqual({
            app: { name: 'devtools', ports: [80, 443] },
        });
        expect(ok(yaml, 'yaml', 'xml').text).toContain('<app>');
    });

    it('trata o mesmo formato como normalizacao, com o aviso do comentario', () => {
        const resultado = ok('# comentario\nb: 2\na: 1\n', 'yaml', 'yaml');

        expect(parseYaml(resultado.text)).toEqual({ b: 2, a: 1 });
        expect(codes(resultado)).toEqual(['comments-dropped']);
    });
});

describe('conversor de formatos: erros', () => {
    it('devolve linha e coluna do JSON invalido', () => {
        const resultado = fail('{\n  "a": 1\n  "b": 2\n}', 'json', 'yaml');

        expect(resultado.code).toBe('parse');
        expect(resultado.line).toBe(3);
        expect(resultado.column).toBeGreaterThanOrEqual(1);
        expect(resultado.detail).not.toBe('');
    });

    it('devolve linha e coluna do YAML invalido', () => {
        const resultado = fail('a: [1,\n', 'yaml', 'json');

        expect(resultado.code).toBe('parse');
        expect(resultado.line).toBeGreaterThanOrEqual(1);
        expect(resultado.column).toBeGreaterThanOrEqual(1);
    });

    it('devolve linha e coluna do TOML invalido', () => {
        const resultado = fail('a = \n', 'toml', 'json');

        expect(resultado.code).toBe('parse');
        expect(resultado.line).toBeGreaterThanOrEqual(1);
        expect(resultado.column).toBeGreaterThanOrEqual(1);
    });

    it('devolve erro sem posicao para o XML que nao fecha', () => {
        // A mensagem do `parsererror` varia de motor para motor; o que e
        // contrato e o codigo do erro.
        const resultado = fail('<a><b></a>', 'xml', 'json');

        expect(resultado.code).toBe('parse');
        expect(resultado.detail).not.toBe('');
    });

    it('recusa raiz que nao e tabela no TOML', () => {
        const resultado = fail('[1,2]', 'json', 'toml');

        expect(resultado.code).toBe('toml-root');
        expect(resultado.detail).toBeNull();
    });

    it('recusa array de tipos misturados no TOML, apontando o caminho', () => {
        const resultado = fail('{"a":{"b":[1,true]}}', 'json', 'toml');

        expect(resultado.code).toBe('toml-mixed-array');
        expect(resultado.detail).toBe('a.b');
    });

    it('recusa chave que nao e nome de elemento XML', () => {
        const resultado = fail('{"1st":1}', 'json', 'xml');

        expect(resultado.code).toBe('xml-name');
        expect(resultado.detail).toBe('1st');
    });

    it('recusa atributo XML que nao e escalar', () => {
        const resultado = fail('{"user":{"@id":{"x":1}}}', 'json', 'xml');

        expect(resultado.code).toBe('xml-attribute');
        expect(resultado.detail).toBe('@id');
    });
});
