import { describe, expect, it } from 'vitest';
import { namedColorEntries, namedColors, nearestNamedColor } from '@/lib/color';

/**
 * Nomes CSS Color 4 (ADR-117). O nome e um rotulo de aproximacao; o codigo
 * exato sempre acompanha na tela.
 */

describe('namedColors', () => {
    it('cobre a lista do CSS Color 4 com rebeccapurple', () => {
        expect(Object.keys(namedColors).length).toBeGreaterThanOrEqual(148);
        expect(namedColors.rebeccapurple).toBe('663399');
        expect(namedColors.aliceblue).toBe('f0f8ff');
        expect(namedColors.black).toBe('000000');
    });

    it('expõe os pares em ordem alfabetica', () => {
        const names = namedColorEntries.map(([name]) => name);

        expect(names).toEqual([...names].sort());
        expect(names[0]).toBe('aliceblue');
    });

    it('nao inclui transparent no mapa (tratado pelo parser)', () => {
        expect(namedColors.transparent).toBeUndefined();
    });
});

describe('nearestNamedColor', () => {
    it('reconhece as cores exatas', () => {
        expect(nearestNamedColor({ r: 255, g: 0, b: 0, a: 1 })).toBe('red');
        expect(nearestNamedColor({ r: 0, g: 0, b: 255, a: 1 })).toBe('blue');
        expect(nearestNamedColor({ r: 255, g: 255, b: 255, a: 1 })).toBe(
            'white',
        );
    });

    it('aproxima sem exigir igualdade exata', () => {
        expect(nearestNamedColor({ r: 252, g: 4, b: 3, a: 1 })).toBe('red');
    });

    it('devolve transparent quando nao ha cor visivel', () => {
        expect(nearestNamedColor({ r: 255, g: 0, b: 0, a: 0 })).toBe(
            'transparent',
        );
    });
});
