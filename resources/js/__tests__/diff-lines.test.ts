import { describe, expect, it } from 'vitest';
import { diffLines, splitLines } from '@/tools/diff/lib/lines';

/**
 * O motor do diff por linhas.
 *
 * O que este arquivo cobra e o contrato de fora: hunk, linha absoluta de cada
 * lado, o par que vira "alterado", o corte de prefixo/sufixo e o orcamento. O
 * **minimo de edicoes** do Myers e conferido por forca bruta (o LCS calculado
 * aqui, por programacao dinamica) em dezenas de pares pequenos: um erro no
 * caminho aparece como uma edicao a mais que o necessario.
 */

/** O tamanho do maior trecho comum - o Myers tem que custar N+M-2·LCS. */
function lcs(a: readonly string[], b: readonly string[]): number {
    const width = b.length + 1;
    const table = new Int32Array((a.length + 1) * width);
    const at = (row: number, column: number): number =>
        table[row * width + column] ?? 0;

    for (let i = 1; i <= a.length; i += 1) {
        for (let j = 1; j <= b.length; j += 1) {
            table[i * width + j] =
                a[i - 1] === b[j - 1]
                    ? at(i - 1, j - 1) + 1
                    : Math.max(at(i - 1, j), at(i, j - 1));
        }
    }

    return at(a.length, b.length);
}

/** Gerador determinista: os casos aleatorios sao os mesmos em toda rodada. */
function random(seed: number): () => number {
    let state = seed;

    return () => {
        state = (state * 1103515245 + 12345) % 2147483648;

        return state / 2147483648;
    };
}

describe('splitLines', () => {
    it('corta nas quebras e descarta a linha vazia do fim', () => {
        expect(splitLines('')).toEqual([]);
        expect(splitLines('a')).toEqual(['a']);
        expect(splitLines('a\n')).toEqual(['a']);
        expect(splitLines('a\nb\n')).toEqual(['a', 'b']);
    });

    it('normaliza CRLF e CR antes de cortar', () => {
        expect(splitLines('a\r\nb\r\nc')).toEqual(['a', 'b', 'c']);
        expect(splitLines('a\rb')).toEqual(['a', 'b']);
    });

    it('mantem a linha vazia que o documento tem de verdade', () => {
        // So a ultima vazia (a do newline final) sai; a outra e uma linha.
        expect(splitLines('a\n\n')).toEqual(['a', '']);
    });
});

describe('diffLines', () => {
    it('nao marca nada em textos iguais', () => {
        const resultado = diffLines('a\nb\nc', 'a\nb\nc');

        expect(resultado).toEqual({
            hunks: [],
            removed: 0,
            added: 0,
            truncated: false,
        });
    });

    it('trata CRLF e LF como o mesmo texto', () => {
        const resultado = diffLines('a\r\nb\r\n', 'a\nb\n');

        expect(resultado.hunks).toEqual([]);
    });

    it('marca a linha alterada como um par dos dois lados', () => {
        const resultado = diffLines('a\nb\nc', 'a\nB\nc');

        expect(resultado.hunks).toHaveLength(1);
        expect(resultado.hunks[0]?.leftStart).toBe(1);
        expect(resultado.hunks[0]?.rightStart).toBe(1);
        expect(resultado.hunks[0]?.lines).toEqual([
            { type: 'removed', line: 1, text: 'b', paired: true },
            { type: 'added', line: 1, text: 'B', paired: true },
        ]);
        expect(resultado.removed).toBe(1);
        expect(resultado.added).toBe(1);
    });

    it('marca a insercao no meio com a linha absoluta certa', () => {
        const resultado = diffLines('a\nc', 'a\nb\nc');

        expect(resultado.hunks).toEqual([
            {
                leftStart: 1,
                rightStart: 1,
                lines: [{ type: 'added', line: 1, text: 'b', paired: false }],
            },
        ]);
        expect(resultado.added).toBe(1);
        expect(resultado.removed).toBe(0);
    });

    it('marca a remocao no fim', () => {
        const resultado = diffLines('a\nb\nc', 'a\nb');

        expect(resultado.hunks).toEqual([
            {
                leftStart: 2,
                rightStart: 2,
                lines: [{ type: 'removed', line: 2, text: 'c', paired: false }],
            },
        ]);
    });

    it('mantem as linhas absolutas depois de recortar prefixo e sufixo', () => {
        // "a" e "d/e" sao o prefixo e o sufixo comuns; o miolo comeca na
        // linha 1 dos dois lados, e o hunk tem que falar em linha 1.
        const resultado = diffLines('a\nb\nc\nd', 'a\nB\nc\nd');

        expect(resultado.hunks[0]?.leftStart).toBe(1);
        expect(resultado.hunks[0]?.lines[0]?.line).toBe(1);
    });

    it('cria um hunk para cada regiao separada por linha comum', () => {
        const resultado = diffLines('a\nb\nc\nd\ne', 'a\nX\nc\nY\ne');

        expect(resultado.hunks).toHaveLength(2);
        expect(resultado.hunks[0]?.leftStart).toBe(1);
        expect(resultado.hunks[1]?.leftStart).toBe(3);
        expect(resultado.removed).toBe(2);
        expect(resultado.added).toBe(2);
    });

    it('pareia so o que tem par: 2 remocoes e 3 adicoes', () => {
        const resultado = diffLines('a\nb\nc\nd', 'a\nX\nY\nZ\nd');
        const linhas = resultado.hunks[0]?.lines ?? [];
        const removidas = linhas.filter((line) => line.type === 'removed');
        const adicionadas = linhas.filter((line) => line.type === 'added');

        expect(removidas).toHaveLength(2);
        expect(adicionadas).toHaveLength(3);
        // Toda remocao tem par; so as duas primeiras adicoes tem.
        expect(removidas.every((line) => line.paired)).toBe(true);
        expect(adicionadas.filter((line) => line.paired)).toHaveLength(2);
        expect(adicionadas.filter((line) => !line.paired)).toHaveLength(1);
    });

    it('cobra do Myers o menor numero de edicoes em pares aleatorios', () => {
        const next = random(20261124);

        for (let caso = 0; caso < 60; caso += 1) {
            const a = Array.from(
                { length: Math.floor(next() * 7) },
                () => 'abcd'[Math.floor(next() * 4)] ?? 'a',
            );
            const b = Array.from(
                { length: Math.floor(next() * 7) },
                () => 'abcd'[Math.floor(next() * 4)] ?? 'a',
            );
            const resultado = diffLines(a.join('\n'), b.join('\n'));

            expect(
                resultado.removed + resultado.added,
                `${JSON.stringify(a)} x ${JSON.stringify(b)}`,
            ).toBe(a.length + b.length - 2 * lcs(a, b));
        }
    });

    it('reconstroi o modificado a partir dos hunks', () => {
        const a = ['um', 'dois', 'tres', 'quatro', 'cinco'];
        const b = ['um', 'two', 'tres', 'quatro', 'cinco', 'seis'];
        const resultado = diffLines(a.join('\n'), b.join('\n'));
        const linhas = [...b];

        // Desfaz cada mudanca do fim para o comeco: sai a linha removida,
        // entram as adicionadas do mesmo hunk no lugar do par.
        for (let index = resultado.hunks.length - 1; index >= 0; index -= 1) {
            const hunk = resultado.hunks[index];

            if (hunk === undefined) {
                continue;
            }

            const removidas = hunk.lines.filter(
                (line) => line.type === 'removed',
            );
            const adicionadas = hunk.lines.filter(
                (line) => line.type === 'added',
            );

            linhas.splice(hunk.rightStart, adicionadas.length);
            linhas.splice(
                hunk.rightStart,
                0,
                ...removidas.map((line) => line.text),
            );
        }

        expect(linhas).toEqual(a);
    });

    it('cai para o diff por posicao quando estoura o orcamento', () => {
        const esquerda = Array.from({ length: 1001 }, (_, i) => `esq-${i}`);
        const direita = Array.from({ length: 1000 }, (_, i) => `dir-${i}`);
        const resultado = diffLines(esquerda.join('\n'), direita.join('\n'));

        expect(resultado.truncated).toBe(true);
        // Todo par de linhas diferentes vira uma remocao e uma adicao; a
        // linha extra da esquerda so tem remocao.
        expect(resultado.removed).toBe(1001);
        expect(resultado.added).toBe(1000);
        expect(resultado.hunks).toHaveLength(1);
        expect(resultado.hunks[0]?.leftStart).toBe(0);
        expect(resultado.hunks[0]?.rightStart).toBe(0);
        expect(resultado.hunks[0]?.lines[0]?.paired).toBe(true);
    });
});
