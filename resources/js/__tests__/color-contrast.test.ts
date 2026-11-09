import { describe, expect, it } from 'vitest';
import {
    BLACK,
    WHITE,
    classifyContrast,
    compositeOver,
    contrastRatio,
    contrastReport,
    relativeLuminance,
} from '@/lib/color';

/**
 * Contraste WCAG (ADR-117): composicao do alpha antes da luminancia e
 * classificacao no valor cru da razao - arredondar so a exibicao.
 */

describe('compositeOver', () => {
    it('alpha 0 devolve o fundo', () => {
        expect(compositeOver({ r: 255, g: 0, b: 0, a: 0 }, WHITE)).toEqual({
            r: 255,
            g: 255,
            b: 255,
        });
    });

    it('alpha 1 devolve a cor', () => {
        expect(compositeOver({ r: 255, g: 0, b: 0, a: 1 }, WHITE)).toEqual({
            r: 255,
            g: 0,
            b: 0,
        });
    });

    it('alpha 0.5 mistura metade a metade', () => {
        expect(compositeOver({ r: 255, g: 0, b: 0, a: 0.5 }, WHITE)).toEqual({
            r: 255,
            g: 127.5,
            b: 127.5,
        });
        expect(compositeOver({ r: 255, g: 0, b: 0, a: 0.5 }, BLACK)).toEqual({
            r: 127.5,
            g: 0,
            b: 0,
        });
    });
});

describe('relativeLuminance e contrastRatio', () => {
    it('preto e branco sao os extremos', () => {
        expect(relativeLuminance(BLACK)).toBe(0);
        expect(relativeLuminance(WHITE)).toBeCloseTo(1, 10);
        expect(contrastRatio(WHITE, BLACK)).toBeCloseTo(21, 5);
    });

    it('a razao e simetrica', () => {
        const gray = { r: 118, g: 118, b: 118 };

        expect(contrastRatio(gray, WHITE)).toBeCloseTo(
            contrastRatio(WHITE, gray),
            12,
        );
    });

    it('reproduz o valor conhecido de #767676 sobre branco', () => {
        expect(contrastRatio({ r: 118, g: 118, b: 118 }, WHITE)).toBeCloseTo(
            4.54,
            1,
        );
    });
});

describe('classifyContrast', () => {
    it('nao arredonda a classificacao', () => {
        expect(classifyContrast(4.4999).aaNormal).toBe(false);
        expect(classifyContrast(4.5).aaNormal).toBe(true);
        expect(classifyContrast(6.9999).aaaNormal).toBe(false);
        expect(classifyContrast(7).aaaNormal).toBe(true);
    });

    it('aplica os limites de texto grande', () => {
        expect(classifyContrast(2.9999).aaLarge).toBe(false);
        expect(classifyContrast(3).aaLarge).toBe(true);
        expect(classifyContrast(4.5).aaaLarge).toBe(true);
        expect(classifyContrast(4.4999).aaaLarge).toBe(false);
    });

    it('devolve a razao crua junto dos selos', () => {
        expect(classifyContrast(21).ratio).toBe(21);
    });
});

describe('contrastReport', () => {
    it('compoe o alpha sobre o fundo antes de medir', () => {
        const report = contrastReport({ r: 0, g: 0, b: 0, a: 0 }, WHITE);

        expect(report.background).toEqual(WHITE);
        expect(report.white.ratio).toBeCloseTo(1, 10);
        expect(report.black.ratio).toBeCloseTo(21, 5);
    });

    it('relata as duas opcoes de texto para uma cor opaca', () => {
        const report = contrastReport({ r: 255, g: 255, b: 255, a: 1 }, WHITE);

        expect(report.black.ratio).toBeCloseTo(21, 5);
        expect(report.black.aaaNormal).toBe(true);
        expect(report.white.ratio).toBeCloseTo(1, 10);
    });
});
