import { describe, expect, it } from 'vitest';
import { parseColor, rgbToOklch } from '@/lib/color';

/**
 * Parser do nucleo (ADR-117). Erros tem codigo proprio - o campo nunca fica
 * com um `NaN` que so aparece depois, na tela.
 */

function parsed(input: string) {
    const result = parseColor(input);

    if (!result.ok) {
        throw new Error(`esperava sucesso para ${input}, veio ${result.code}`);
    }

    return result.color;
}

describe('HEX', () => {
    it('expande 3, 4, 6 e 8 digitos', () => {
        expect(parsed('#abc').rgba).toEqual({ r: 170, g: 187, b: 204, a: 1 });
        expect(parsed('#abcd').rgba.a).toBeCloseTo(221 / 255, 12);
        expect(parsed('#ff0000').rgba).toEqual({ r: 255, g: 0, b: 0, a: 1 });
        expect(parsed('#ff000080').rgba.a).toBeCloseTo(128 / 255, 12);
    });

    it('aceita sem o cerquilha e em maiusculas', () => {
        expect(parsed('FF0000').rgba).toEqual({ r: 255, g: 0, b: 0, a: 1 });
        expect(parsed('#AbCdEf').rgba).toEqual({
            r: 171,
            g: 205,
            b: 239,
            a: 1,
        });
    });

    it('rejeita comprimento invalido', () => {
        expect(parseColor('#12')).toEqual({ ok: false, code: 'syntax' });
        expect(parseColor('#12345')).toEqual({ ok: false, code: 'syntax' });
    });
});

describe('rgb() e hsl()', () => {
    it('aceita virgula, espaco e alpha com barra', () => {
        expect(parsed('rgb(255, 0, 0)').rgba).toEqual({
            r: 255,
            g: 0,
            b: 0,
            a: 1,
        });
        expect(parsed('rgb(255 0 0 / 50%)').rgba.a).toBeCloseTo(0.5, 12);
        expect(parsed('rgba(255, 0, 0, 0.25)').rgba.a).toBe(0.25);
    });

    it('interpreta porcentagem de canal', () => {
        expect(parsed('rgb(100% 0% 0%)').rgba).toEqual({
            r: 255,
            g: 0,
            b: 0,
            a: 1,
        });
        expect(parsed('rgb(50% 0% 0%)').rgba.r).toBeCloseTo(127.5, 12);
    });

    it('converte HSL nos dois formatos', () => {
        expect(parsed('hsl(120, 100%, 50%)').rgba).toEqual({
            r: 0,
            g: 255,
            b: 0,
            a: 1,
        });
        expect(parsed('hsl(120 100% 50% / 0.25)').rgba.a).toBe(0.25);
        expect(parsed('hsla(120, 100%, 50%, 0.5)').rgba.a).toBe(0.5);
    });

    it('aceita angulo com unidade', () => {
        expect(parsed('hsl(120deg 100% 50%)').rgba).toEqual({
            r: 0,
            g: 255,
            b: 0,
            a: 1,
        });
        expect(parsed('hsl(0.5turn 100% 50%)').rgba).toEqual({
            r: 0,
            g: 255,
            b: 255,
            a: 1,
        });
    });

    it('rejeita canal fora do intervalo', () => {
        expect(parseColor('rgb(300 0 0)')).toEqual({
            ok: false,
            code: 'range',
        });
        expect(parseColor('hsl(0 120% 50%)')).toEqual({
            ok: false,
            code: 'range',
        });
        expect(parseColor('rgb(255 0 0 / 2)')).toEqual({
            ok: false,
            code: 'range',
        });
    });
});

describe('oklch() e oklab()', () => {
    it('converte OKLCH para sRGB proximo da referencia', () => {
        const color = parsed('oklch(62.8% 0.2577 29.23)');

        expect(color.rgba.r).toBeGreaterThan(250);
        expect(color.rgba.g).toBeLessThan(10);
        expect(color.rgba.b).toBeLessThan(10);
        expect(color.outOfGamut).toBe(false);
        expect(color.sourceValue).toBeNull();
    });

    it('reduz croma quando a cor nao cabe no sRGB e mantem L e H', () => {
        const source = 'oklch(70% 0.4 150)';
        const color = parsed(source);

        expect(color.outOfGamut).toBe(true);
        expect(color.sourceValue).toBe(source);
        expect(color.rgba.r).toBeGreaterThanOrEqual(0);
        expect(color.rgba.r).toBeLessThanOrEqual(255);

        const effective = rgbToOklch(color.rgba);

        expect(effective.l).toBeCloseTo(0.7, 2);
        expect(effective.h).toBeCloseTo(150, 0);
        expect(effective.c).toBeLessThan(0.4);
    });

    it('converte OKLab preservando o alpha', () => {
        const color = parsed('oklab(0.7 0.1 -0.1 / 40%)');

        expect(color.rgba.a).toBeCloseTo(0.4, 12);
        expect(color.syntax).toBe('oklab');
    });

    it('rejeita luminosidade fora de 0..1', () => {
        expect(parseColor('oklch(150% 0.1 20)')).toEqual({
            ok: false,
            code: 'range',
        });
    });
});

describe('nomes', () => {
    it('reconhece nomes CSS e transparent', () => {
        expect(parsed('rebeccapurple').rgba).toEqual({
            r: 102,
            g: 51,
            b: 153,
            a: 1,
        });
        expect(parsed('transparent').rgba.a).toBe(0);
        expect(parsed('RED').syntax).toBe('name');
    });

    it('distingue nome desconhecido de sintaxe quebrada', () => {
        expect(parseColor('notacolor')).toEqual({
            ok: false,
            code: 'unknown_name',
        });
        expect(parseColor('rgb(1, 2')).toEqual({ ok: false, code: 'syntax' });
    });
});

describe('erros', () => {
    it('distingue vazio de invalido', () => {
        expect(parseColor('   ')).toEqual({ ok: false, code: 'empty' });
        expect(parseColor('rgb(1, 2)')).toEqual({ ok: false, code: 'syntax' });
        expect(parseColor('foo(1 2 3)')).toEqual({ ok: false, code: 'syntax' });
    });

    it('aceita sintaxe moderna com barra quebrada como erro', () => {
        expect(parseColor('rgb(1 2 3 / )')).toEqual({
            ok: false,
            code: 'syntax',
        });
        expect(parseColor('rgb(1 2 3 / 0.5 / 0.5)')).toEqual({
            ok: false,
            code: 'syntax',
        });
    });
});
