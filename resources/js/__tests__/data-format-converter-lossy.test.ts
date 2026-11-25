import { parse as parseToml } from 'smol-toml';
import { describe, expect, it } from 'vitest';
import { convert } from '@/tools/data-format-converter/lib/convert';
import type {
    ConvertSuccess,
    DataFormat,
} from '@/tools/data-format-converter/types';

/**
 * O que a conversao perde, e o aviso de cada perda.
 *
 * A decisao da F1 (ADR-180) e converter **sempre** e dizer o que ficou de fora,
 * em vez de recusar a conversao. Cada teste aqui e um caso em que os dois
 * formatos nao tem o mesmo vocabulario: o `null` que o TOML nao tem, o
 * comentario que o JSON nao tem, a data que so o TOML tem, o `NaN` que o JSON
 * nao escreve.
 *
 * O aviso agrupa por codigo e conta: "3 valores nulos omitidos" e a frase que a
 * pessoa le, e nao tres linhas iguais.
 */

function ok(text: string, from: DataFormat, to: DataFormat): ConvertSuccess {
    const result = convert({ text, from, to });

    expect(result.ok, JSON.stringify(result)).toBe(true);

    return result as ConvertSuccess;
}

function lossOf(resultado: ConvertSuccess, code: string): number {
    return resultado.losses.find((loss) => loss.code === code)?.count ?? 0;
}

describe('perdas da conversao', () => {
    it('omite o null no TOML e conta cada um', () => {
        const resultado = ok('{"a":null,"b":null,"c":1}', 'json', 'toml');

        expect(parseToml(resultado.text)).toEqual({ c: 1 });
        expect(lossOf(resultado, 'null-dropped')).toBe(2);
    });

    it('omite o null dentro de array no TOML', () => {
        const resultado = ok('{"a":[1,null,2]}', 'json', 'toml');

        expect(parseToml(resultado.text)).toEqual({ a: [1, 2] });
        expect(lossOf(resultado, 'null-dropped')).toBe(1);
    });

    it('avisa do comentario que a origem realmente tinha, com a contagem', () => {
        const resultado = ok('# nota\na = 1 # fim\n', 'toml', 'json');

        expect(lossOf(resultado, 'comments-dropped')).toBe(2);
    });

    it('nao avisa de comentario quando a origem nao tem nenhum', () => {
        // O aviso e do que a conversao perdeu, e nao do formato: um XML sem
        // comentario nao ganha o aviso so por ser XML.
        expect(ok('a = 1\n', 'toml', 'json').losses).toEqual([]);
        expect(ok('<a>1</a>', 'xml', 'json').losses).toEqual([]);
        expect(ok('a: 1\n', 'yaml', 'json').losses).toEqual([]);
        expect(ok('{"a":1}', 'json', 'yaml').losses).toEqual([]);
    });

    it('nao confunde o # dentro de string ou de bloco com comentario', () => {
        // O `#` so abre comentario fora de string. YAML e TOML tem formas que
        // deixam o `#` virar conteudo, e nenhuma delas pode gerar aviso.
        expect(
            ok('url: "http://x#y"\nbloco: |\n  # texto\n', 'yaml', 'json')
                .losses,
        ).toEqual([]);
        expect(
            ok(
                "url = \"http://x#y\"\nnota = '''\n# texto\n'''\n",
                'toml',
                'json',
            ).losses,
        ).toEqual([]);
    });

    it('escreve a data do TOML como texto no JSON', () => {
        const resultado = ok('when = 2020-01-01T00:00:00Z\n', 'toml', 'json');

        expect(JSON.parse(resultado.text)).toEqual({
            when: '2020-01-01T00:00:00.000Z',
        });
        expect(lossOf(resultado, 'date-as-text')).toBe(1);
    });

    it('escreve o binario do YAML como base64 no JSON', () => {
        const resultado = ok('data: !!binary |\n  aGVsbG8=\n', 'yaml', 'json');

        expect(JSON.parse(resultado.text)).toEqual({ data: 'aGVsbG8=' });
        expect(lossOf(resultado, 'binary-as-base64')).toBe(1);
    });

    it('escreve NaN e Infinity como null no JSON', () => {
        const resultado = ok('a: .nan\nb: .inf\nc: -.inf\n', 'yaml', 'json');

        expect(JSON.parse(resultado.text)).toEqual({
            a: null,
            b: null,
            c: null,
        });
        expect(lossOf(resultado, 'non-finite-as-null')).toBe(3);
    });

    it('escreve NaN e Infinity como texto no XML', () => {
        const resultado = ok('a: .nan\nb: .inf\n', 'yaml', 'xml');

        expect(resultado.text).toContain('<a>NaN</a>');
        expect(resultado.text).toContain('<b>Infinity</b>');
        expect(lossOf(resultado, 'non-finite-as-text')).toBe(2);
    });

    it('escreve o null do JSON como elemento vazio no XML', () => {
        const resultado = ok('{"a":null}', 'json', 'xml');

        expect(resultado.text).toContain('<a/>');
        expect(lossOf(resultado, 'null-as-empty-element')).toBe(1);
    });

    it('deixa o YAML escrever NaN e Infinity sem perda', () => {
        // O YAML 1.2 tem `.nan` e `.inf`: o valor atravessa a arvore e chega ao
        // destino - nada a avisar.
        const resultado = ok('a: .nan\nb: .inf\n', 'yaml', 'yaml');

        expect(resultado.text).toContain('.nan');
        expect(resultado.text).toContain('.inf');
        // Nada se perdeu: nem valor, nem comentario.
        expect(resultado.losses).toEqual([]);
    });
});
