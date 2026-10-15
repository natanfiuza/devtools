import { describe, expect, it } from 'vitest';
import { lineColumnAt, lineColumnFromMessage, positionAt, positionFromMessage } from '@/lib/position';

/**
 * Linha e coluna: a conta (posicao <-> linha/coluna) e a leitura das mensagens
 * dos motores.
 *
 * O que da para fixar aqui e o **formato** das mensagens conhecidas - V8 escreve
 * `at position N (line L column C)`, o SpiderMonkey escreve `line L column C` e
 * o `sql-formatter` escreve `at line L column C`. O que nao da e fixar a
 * mensagem crua: ela e do motor que estiver rodando.
 */

describe('positionFromMessage', () => {
    it('le a posicao no formato do V8', () => {
        expect(positionFromMessage('Unexpected number in JSON at position 7 (line 1 column 8)')).toBe(
            7,
        );
        expect(positionFromMessage('Expected property name at position 1')).toBe(1);
    });

    it('nao inventa posicao quando nao ha', () => {
        expect(positionFromMessage('Bad JSON at position 999')).toBe(999);
        expect(positionFromMessage('Unexpected end of JSON input')).toBeNull();
    });
});

describe('lineColumnFromMessage', () => {
    it('le linha e coluna no formato do SpiderMonkey', () => {
        expect(lineColumnFromMessage('JSON.parse: unexpected character at line 3 column 5')).toEqual(
            { line: 3, column: 5 },
        );
        expect(lineColumnFromMessage('JSON.parse: unexpected character at line 3')).toEqual({
            line: 3,
            column: null,
        });
        expect(lineColumnFromMessage('Unexpected end of JSON input')).toEqual({
            line: null,
            column: null,
        });
    });

    it('le linha e coluna no formato da biblioteca de SQL', () => {
        // O `sql-formatter` escreve a posicao no meio da frase, e a mensagem
        // pode continuar por dezenas de linhas de gramatica depois dela.
        expect(
            lineColumnFromMessage(
                `Parse error: Unexpected "'abc" at line 1 column 8.\nSQL dialect: mysql.`,
            ),
        ).toEqual({ line: 1, column: 8 });
        expect(
            lineColumnFromMessage('Parse error at token: «EOF» at line 3 column 1\nUnexpected EOF'),
        ).toEqual({ line: 3, column: 1 });
    });
});

describe('lineColumnAt', () => {
    const text = '{\n  "a": 1\n}';

    it('aponta o inicio do texto', () => {
        expect(lineColumnAt(text, 0)).toEqual({ line: 1, column: 1 });
    });

    it('conta a linha pela quebra anterior', () => {
        // Posicao 3 e o primeiro espaco da segunda linha.
        expect(lineColumnAt(text, 3)).toEqual({ line: 2, column: 2 });
    });

    it('conta a coluna em caracteres, e nao em code units', () => {
        // O emoji ocupa dois code units; a coluna nao pode pular dois numeros.
        expect(lineColumnAt('{"a":"😀" x}', 10)).toEqual({ line: 1, column: 10 });
    });

    it('prende a posicao ao texto', () => {
        expect(lineColumnAt(text, -5)).toEqual({ line: 1, column: 1 });
        expect(lineColumnAt(text, 999)).toEqual({ line: 3, column: 2 });
    });
});

describe('positionAt', () => {
    const text = '{\n  "a": 1\n}';

    it('volta a posicao da linha e da coluna', () => {
        expect(positionAt(text, 1, 1)).toBe(0);
        expect(positionAt(text, 2, 2)).toBe(3);
        expect(positionAt(text, 3, 2)).toBe(text.length);
    });

    it('e a volta de `lineColumnAt`', () => {
        for (let position = 0; position <= text.length; position += 1) {
            const { line, column } = lineColumnAt(text, position);

            expect(positionAt(text, line, column), `posicao ${position}`).toBe(position);
        }
    });

    it('conta a coluna em caracteres, e nao em code units', () => {
        // A setima coluna de `{"a":"😀" x}` e o `x`: o emoji ocupa dois code
        // units, e a marca do editor cai um caractere depois se a conta errar.
        expect(positionAt('{"a":"😀" x}', 1, 10)).toBe(10);
    });

    it('prende linha e coluna ao texto', () => {
        expect(positionAt(text, 1, 0)).toBe(0);
        // Linha que nao existe cai na ultima, no comeco dela.
        expect(positionAt(text, 99, 1)).toBe(text.lastIndexOf('\n') + 1);
        // Coluna que nao existe cai no fim da linha.
        expect(positionAt(text, 1, 999)).toBe(1);
    });
});
