import { describe, expect, it } from 'vitest';
import {
    analyzeJson,
    byteLength,
    hasBom,
    isValidJson,
    lineColumnAt,
    lineColumnFromMessage,
    positionFromMessage,
} from '@/tools/json-viewer/lib/parse';

/**
 * Validacao com linha e coluna.
 *
 * O que da para fixar aqui e a **conta** (posicao -> linha/coluna) e a leitura
 * das mensagens conhecidas dos motores. O que nao da e fixar a mensagem crua:
 * ela e do motor que estiver rodando, e o codigo so promete tratar os formatos
 * que existem hoje (V8 escreve `at position N (line L column C)`, o SpiderMonkey
 * escreve `line L column C`).
 */

/** O BOM e invisivel: escrito por codigo para nao sumir numa edicao. */
const BOM = String.fromCharCode(0xfeff);

describe('analyzeJson', () => {
    it('devolve o valor e o tamanho em bytes quando o texto e valido', () => {
        const result = analyzeJson('{"a":[1,true,null]}');

        expect(result.ok).toBe(true);

        if (result.ok) {
            expect(result.value).toEqual({ a: [1, true, null] });
            // 19 caracteres ASCII: 19 bytes.
            expect(result.bytes).toBe(19);
        }
    });

    it('aceita o BOM no inicio', () => {
        const result = analyzeJson(`${BOM}{"a":1}`);

        expect(result.ok).toBe(true);

        if (result.ok) {
            expect(result.value).toEqual({ a: 1 });
            // O BOM conta no tamanho: sao 3 bytes dele mais os 7 do texto.
            expect(result.bytes).toBe(10);
        }
    });

    it('devolve linha e coluna 1-based do erro', () => {
        const result = analyzeJson('{\n  "a": 1,\n}');

        expect(result.ok).toBe(false);

        if (!result.ok) {
            expect(result.position).not.toBeNull();
            // O `}` sobrando esta na terceira linha, primeira coluna.
            expect(result.line).toBe(3);
            expect(result.column).toBe(1);
            expect(result.message).not.toBe('');
        }
    });

    it('desloca a coluna quando o texto tem BOM', () => {
        const result = analyzeJson(`${BOM}{"a" 1}`);

        expect(result.ok).toBe(false);

        if (!result.ok && result.column !== null) {
            // Sem o ajuste do BOM a coluna sairia um caractere antes: o `1` e o
            // setimo caractere do texto colado.
            expect(result.position).toBe(5);
            expect(result.line).toBe(1);
            expect(result.column).toBe(7);
        }
    });

    it('nao inventa posicao quando o motor nao informa', () => {
        // Fim de entrada inesperado: o V8 nao diz onde.
        const result = analyzeJson('[1,2,');

        expect(result.ok).toBe(false);

        if (!result.ok) {
            expect(result.message).not.toBe('');
            expect(result.position).toBeNull();
            expect(result.line).toBeNull();
            expect(result.column).toBeNull();
        }
    });

    it('recusa posicao fora do texto', () => {
        // Mensagem de outro motor, com posicao que nao existe neste texto.
        expect(positionFromMessage('Bad JSON at position 999')).toBe(999);
        expect(positionFromMessage('Unexpected end of JSON input')).toBeNull();
    });
});

describe('mensagens do motor', () => {
    it('le a posicao no formato do V8', () => {
        expect(positionFromMessage('Unexpected number in JSON at position 7 (line 1 column 8)')).toBe(
            7,
        );
        expect(positionFromMessage('Expected property name at position 1')).toBe(1);
    });

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

describe('utilitarios', () => {
    it('mede bytes em UTF-8', () => {
        expect(byteLength('ab')).toBe(2);
        expect(byteLength('ção')).toBe(5);
        expect(byteLength('😀')).toBe(4);
    });

    it('reconhece o BOM so no inicio', () => {
        expect(hasBom(`${BOM}{}`)).toBe(true);
        expect(hasBom('{}')).toBe(false);
        expect(hasBom('')).toBe(false);
        expect(hasBom(`{${BOM}}`)).toBe(false);
    });

    it('responde validade sem devolver o valor', () => {
        expect(isValidJson('[]')).toBe(true);
        expect(isValidJson('[1,]')).toBe(false);
    });
});
