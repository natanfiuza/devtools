import { describe, expect, it } from 'vitest';
import {
    flagsOf,
    highlight,
    REGEX_FLAGS,
    search,
    type RegexRequest,
    type RegexResult,
} from '@/tools/regex-tester/lib/run';

/**
 * O motor do Regex Tester: a busca, os grupos, o teto e a substituicao.
 *
 * O que este arquivo cobre e o que a tela nao mostra: o casamento **vazio** (o
 * que faria `a*` girar para sempre sobre `"bbb"`), o passo de code point com a
 * flag `u`, o corte no teto, e a substituicao que vale para o texto inteiro
 * mesmo quando a lista foi cortada.
 */

/** Um pedido com o minimo; cada teste diz so o que e dele. */
function pedido(overrides: Partial<RegexRequest>): RegexRequest {
    return {
        pattern: '',
        flags: 'g',
        text: '',
        replacement: null,
        maxMatches: 100,
        ...overrides,
    };
}

function ok(result: RegexResult): Extract<RegexResult, { ok: true }> {
    if (!result.ok) {
        throw new Error(
            `esperava um resultado valido, veio ${JSON.stringify(result)}`,
        );
    }

    return result;
}

describe('flagsOf', () => {
    it('liga o g sozinho quando nao ha alternador', () => {
        expect(flagsOf({})).toBe('g');
    });

    it('segue a ordem da tela, e nao a ordem do objeto', () => {
        expect(flagsOf({ u: true, i: true })).toBe('giu');
        expect(flagsOf({ s: true, m: true, u: true, i: true })).toBe('gimsu');
    });

    it('deixa de fora o que esta desligado', () => {
        expect(flagsOf({ i: true, m: false, s: false, u: true })).toBe('giu');
    });

    it('oferece as quatro na ordem', () => {
        expect(REGEX_FLAGS).toEqual(['i', 'm', 's', 'u']);
    });
});

describe('a busca', () => {
    it('acha todos os casamentos, com a posicao', () => {
        const result = ok(search(pedido({ pattern: 'a', text: 'banana' })));

        expect(
            result.matches.map((match) => [match.index, match.text]),
        ).toEqual([
            [1, 'a'],
            [3, 'a'],
            [5, 'a'],
        ]);
        expect(result.truncated).toBe(false);
    });

    it('devolve os grupos numerados, com o que nao participou em null', () => {
        const result = ok(search(pedido({ pattern: '(a)|(b)', text: 'ab' })));

        expect(result.matches.map((match) => match.groups)).toEqual([
            ['a', null],
            [null, 'b'],
        ]);
        expect(result.matches.every((match) => match.named.length === 0)).toBe(
            true,
        );
    });

    it('devolve os grupos nomeados', () => {
        const result = ok(
            search(
                pedido({
                    pattern: '(?<ano>\\d{4})-(?<parcial>\\d{2})?',
                    text: '2026-',
                }),
            ),
        );

        expect(result.matches[0]?.named).toEqual([
            { name: 'ano', text: '2026' },
            { name: 'parcial', text: null },
        ]);
    });

    it('nao trava no casamento vazio', () => {
        // Sem o avanco na mao, o `exec` devolveria o mesmo vazio na posicao 0
        // para sempre: o teste terminaria por estouro, e a aba travaria.
        const result = ok(search(pedido({ pattern: 'a*', text: 'bbb' })));

        expect(result.matches.map((match) => match.index)).toEqual([
            0, 1, 2, 3,
        ]);
        expect(result.matches.map((match) => match.text)).toEqual([
            '',
            '',
            '',
            '',
        ]);
    });

    it('acaba quando o vazio passa do fim do texto', () => {
        const result = ok(search(pedido({ pattern: 'a*', text: '' })));

        expect(result.matches.map((match) => match.index)).toEqual([0]);
    });

    it('anda de code point em code point com a flag u', () => {
        // O emoji tem dois code units: sem o cuidado, o avanco cairia no meio
        // do par - posicao em que o motor, em modo unicode, nao casa.
        const unicode = ok(
            search(pedido({ pattern: '', flags: 'gu', text: '😀😀' })),
        );
        const codeUnit = ok(
            search(pedido({ pattern: '', flags: 'g', text: '😀😀' })),
        );

        expect(unicode.matches.map((match) => match.index)).toEqual([0, 2, 4]);
        expect(codeUnit.matches.map((match) => match.index)).toEqual([
            0, 1, 2, 3, 4,
        ]);
    });

    it('entrega as flags ao motor', () => {
        const semFlag = ok(search(pedido({ pattern: 'A', text: 'a' })));
        const comFlag = ok(
            search(pedido({ pattern: 'A', flags: 'gi', text: 'a' })),
        );
        const linha = ok(
            search(pedido({ pattern: '^b', flags: 'gm', text: 'a\nb' })),
        );
        const ponto = ok(
            search(pedido({ pattern: 'a.b', flags: 'gs', text: 'a\nb' })),
        );

        expect(semFlag.matches).toEqual([]);
        expect(comFlag.matches).toHaveLength(1);
        expect(linha.matches.map((match) => match.index)).toEqual([2]);
        expect(ponto.matches).toHaveLength(1);
    });

    it('corta no teto e avisa', () => {
        const cortado = ok(
            search(pedido({ pattern: 'a', text: 'banana', maxMatches: 2 })),
        );
        const inteiro = ok(
            search(pedido({ pattern: 'a', text: 'banana', maxMatches: 3 })),
        );

        expect(cortado.matches).toHaveLength(2);
        expect(cortado.truncated).toBe(true);
        expect(inteiro.matches).toHaveLength(3);
        expect(inteiro.truncated).toBe(false);
    });

    it('sem teto nao devolve casamento nenhum', () => {
        const result = ok(
            search(pedido({ pattern: 'a', text: 'banana', maxMatches: 0 })),
        );

        expect(result.matches).toEqual([]);
        expect(result.truncated).toBe(true);
    });

    it('devolve o padrao invalido como resultado, e nao como excecao', () => {
        const result = search(pedido({ pattern: 'a(', text: 'banana' }));

        expect(result.ok).toBe(false);

        if (result.ok) {
            return;
        }

        // O detalhe e a mensagem do motor, crua: e o unico texto que diz o que
        // esta errado na expressao.
        expect(result.detail).toContain('a(');
    });

    it('so devolve dados clonaveis por estrutura', () => {
        const valid = ok(
            search(pedido({ pattern: '(?<x>a)', text: 'banana' })),
        );
        const failure = search(pedido({ pattern: 'a(' }));

        // Isto atravessa o `postMessage`: um `Map` ou uma referencia circular
        // quebraria no navegador, e nao aqui.
        expect(structuredClone(valid)).toEqual(valid);
        expect(structuredClone(failure)).toEqual(failure);
    });
});

describe('a substituicao', () => {
    it('troca todos os casamentos', () => {
        const result = ok(
            search(pedido({ pattern: 'a', text: 'banana', replacement: 'X' })),
        );

        expect(result.replaced).toBe('bXnXnX');
    });

    it('aceita os grupos numerados no texto de troca', () => {
        const result = ok(
            search(
                pedido({
                    pattern: '(\\w+)@(\\w+)',
                    text: 'ana@dev',
                    replacement: '$2.$1',
                }),
            ),
        );

        expect(result.replaced).toBe('dev.ana');
    });

    it('aceita o grupo nomeado no texto de troca', () => {
        const result = ok(
            search(
                pedido({
                    pattern: '(?<quem>\\w+)@(?<onde>\\w+)',
                    text: 'ana@dev',
                    replacement: '$<onde>/$<quem>',
                }),
            ),
        );

        expect(result.replaced).toBe('dev/ana');
    });

    it('vale para o texto inteiro, e nao so para a lista cortada', () => {
        // A lista cortada e um recorte da tela; a substituicao e o resultado de
        // verdade, e corta-la seria mentir sobre o que o `replace` faz.
        const result = ok(
            search(
                pedido({
                    pattern: 'a',
                    text: 'banana',
                    replacement: 'X',
                    maxMatches: 2,
                }),
            ),
        );

        expect(result.matches).toHaveLength(2);
        expect(result.replaced).toBe('bXnXnX');
    });

    it('sem substituicao nao ha texto trocado', () => {
        const result = ok(search(pedido({ pattern: 'a', text: 'banana' })));

        expect(result.replaced).toBeNull();
    });
});

describe('a previa realcada', () => {
    it('junta os pedacos no texto original', () => {
        const matches = ok(
            search(pedido({ pattern: 'a', text: 'banana' })),
        ).matches;

        expect(highlight('banana', matches)).toEqual([
            { text: 'b', match: false },
            { text: 'a', match: true },
            { text: 'n', match: false },
            { text: 'a', match: true },
            { text: 'n', match: false },
            { text: 'a', match: true },
        ]);
    });

    it('nao pinta o casamento vazio', () => {
        const matches = ok(
            search(pedido({ pattern: 'a*', text: 'bbb' })),
        ).matches;

        expect(highlight('bbb', matches)).toEqual([
            { text: 'bbb', match: false },
        ]);
    });

    it('sem casamento o texto inteiro e um pedaco', () => {
        expect(highlight('abc', [])).toEqual([{ text: 'abc', match: false }]);
    });

    it('nao volta atras, e nao perde texto', () => {
        const fora = ok(
            search(pedido({ pattern: '[ab]', text: 'abc' })),
        ).matches;

        // A lista chega na ordem da busca; fora de ordem, o pedaco que ja
        // passou e ignorado em vez de reabrir o texto por tras.
        const parts = highlight('abc', [...fora].reverse());

        expect(parts).toEqual([
            { text: 'a', match: false },
            { text: 'b', match: true },
            { text: 'c', match: false },
        ]);
    });

    it('texto vazio nao rende pedaco nenhum', () => {
        expect(highlight('', [])).toEqual([]);
    });
});
