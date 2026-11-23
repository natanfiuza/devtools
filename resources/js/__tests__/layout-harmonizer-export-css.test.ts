import { describe, expect, it } from 'vitest';
import { parseColor } from '@/lib/color';
import { generateLayoutSystem } from '@/tools/layout-harmonizer/engine';
import { buildLayoutCss } from '@/tools/layout-harmonizer/export/css';
import { emptyEditState } from '@/tools/layout-harmonizer/lib/editState';
import { buildSnapshot } from '@/tools/layout-harmonizer/lib/snapshot';
import {
    buildLayoutState,
    type LayoutStateInputsV1,
    type LayoutSystemStateV1,
} from '@/tools/layout-harmonizer/lib/state';
import type { LayoutExportInput } from '@/tools/layout-harmonizer/export/types';
import { exportMessages } from './support/langMessages';

/**
 * CSS exportado (F5, ADR-155/ADR-176).
 *
 * O arquivo sai do snapshot exibido: HEX/rgba no bloco base, OKLCH dentro do
 * `@supports`, seletores do escopo escolhido e avisos em comentario.
 */

const LOCALE = 'en';

function color(input: string) {
    const parsed = parseColor(input);

    if (!parsed.ok) {
        throw new Error(`cor de teste invalida: ${input}`);
    }

    return parsed.color.rgba;
}

const INPUTS: LayoutStateInputsV1 = {
    seedText: '#3b82f6',
    backgroundText: '',
    secondaryText: '',
    strategy: 'analogous',
};

const SNAPSHOT = buildSnapshot(
    generateLayoutSystem({ seed: color('#3b82f6'), strategy: 'analogous' }),
    emptyEditState(),
    'aa',
);

function stateOf(snapshot = SNAPSHOT): LayoutSystemStateV1 {
    return buildLayoutState(INPUTS, null, snapshot);
}

function messagesOf(locale = LOCALE): Record<string, unknown> {
    const tools = exportMessages()[locale]?.tools ?? {};
    const block = tools['layout-harmonizer'];

    expect(block).toBeDefined();

    return block as Record<string, unknown>;
}

function inputOf(
    overrides: Partial<LayoutExportInput> = {},
): LayoutExportInput {
    return {
        state: stateOf(),
        name: 'Sistema Oceano',
        language: 'en',
        messages: messagesOf(),
        origin: null,
        ...overrides,
    };
}

function trim(value: number, decimals: number): string {
    const text = value.toFixed(decimals);

    return text.includes('.')
        ? text.replace(/0+$/, '').replace(/\.$/, '')
        : text;
}

function oklchOf(token: { l: number; c: number; h: number }): string {
    return `oklch(${trim(token.l * 100, 2)}% ${trim(token.c, 4)} ${trim(token.h, 2)})`;
}

describe('export/css', () => {
    it('gera os blocos do escopo base, do tema escuro e do @supports nesta ordem', () => {
        const css = buildLayoutCss(inputOf(), {
            prefix: 'lh',
            scope: ':root',
        }).contents;

        const light = css.indexOf(':root, :root[data-theme="light"] {');
        const dark = css.indexOf(':root[data-theme="dark"] {');
        const supports = css.indexOf('@supports (color: oklch(0 0 0)) {');

        expect(light).toBeGreaterThan(-1);
        expect(dark).toBeGreaterThan(light);
        expect(supports).toBeGreaterThan(dark);

        const darkSupports = css.indexOf(
            ':root[data-theme="dark"] {',
            supports,
        );
        expect(darkSupports).toBeGreaterThan(supports);
    });

    it('declara os 33 tokens nos dois temas, em fallback e em OKLCH', () => {
        const css = buildLayoutCss(inputOf(), {
            prefix: 'lh',
            scope: ':root',
        }).contents;

        expect(css.match(/--lh-[a-z-]+:/g)).toHaveLength(33 * 4);
    });

    it('usa o HEX e o OKLCH do snapshot exibido', () => {
        const state = stateOf();
        const light = state.snapshot.themes.find(
            (theme) => theme.theme === 'light',
        );
        const primary = light?.tokens.find((token) => token.id === 'primary');

        expect(primary).toBeDefined();

        const css = buildLayoutCss(inputOf({ state }), {
            prefix: 'lh',
            scope: ':root',
        }).contents;

        expect(css).toContain(`--lh-primary: ${primary?.hex};`);
        expect(css).toContain(
            `--lh-primary: ${oklchOf(primary?.oklch ?? { l: 0, c: 0, h: 0 })};`,
        );
    });

    it('aplica prefixo e escopo de classe', () => {
        const css = buildLayoutCss(inputOf(), {
            prefix: 'brand',
            scope: '.app',
        }).contents;

        expect(css).toContain('.app, .app[data-theme="light"] {');
        expect(css).toContain('.app[data-theme="dark"] {');
        expect(css).toContain('--brand-primary:');
        expect(css).not.toContain('--lh-');
    });

    it('preserva alpha em rgba e em oklch', () => {
        const snapshot = structuredClone(SNAPSHOT);
        const light = snapshot.themes.find((theme) => theme.theme === 'light');
        const primary = light?.tokens.find((token) => token.id === 'primary');

        expect(primary).toBeDefined();

        if (!primary) {
            return;
        }

        primary.rgba.a = 0.5;

        const css = buildLayoutCss(inputOf({ state: stateOf(snapshot) }), {
            prefix: 'lh',
            scope: ':root',
        }).contents;

        expect(css).toContain(`--lh-primary: rgba(59, 130, 246, 0.5);`);
        expect(css).toContain(
            `--lh-primary: ${oklchOf(primary.oklch).replace(/\)$/, ' / 0.5)')};`,
        );
    });

    it('carrega as versoes do estado no cabecalho', () => {
        const css = buildLayoutCss(inputOf(), {
            prefix: 'lh',
            scope: ':root',
        }).contents;

        expect(css).toContain('Layout Harmonizer — Sistema Oceano');
        expect(css).toContain('State 1');
        expect(css).toContain('Schema 1');
        expect(css).toContain('Algorithm 2');
        expect(css).toContain('Suggestions 1');
        expect(css).toContain('Snapshot 1');
        expect(css).toContain('Default theme: light.');
    });

    it('sanitiza o nome no comentario e nunca fecha o comentario antes da hora', () => {
        const css = buildLayoutCss(inputOf({ name: 'Fim */ do comentario' }), {
            prefix: 'lh',
            scope: ':root',
        }).contents;

        const headerEnd = css.indexOf('*/');

        expect(headerEnd).toBeGreaterThan(-1);
        expect(css.slice(0, headerEnd)).toContain('Fim * / do comentario');
        expect(css.split('/*').length - 1).toBe(css.split('*/').length - 1);
    });

    it('lista as falhas de contraste do alvo AA em comentario', () => {
        const css = buildLayoutCss(inputOf(), {
            prefix: 'lh',
            scope: ':root',
        }).contents;

        expect(css).toContain('Warnings');
        expect(css).toMatch(/on-primary over primary-hover/);
        expect(css).toMatch(/\(target AA\)/);
    });

    it('mede o alvo AAA pelos checks do snapshot, nao pelos diagnostics de AA', () => {
        const snapshot = buildSnapshot(
            generateLayoutSystem({
                seed: color('#3b82f6'),
                strategy: 'analogous',
            }),
            emptyEditState(),
            'aaa',
        );

        const css = buildLayoutCss(inputOf({ state: stateOf(snapshot) }), {
            prefix: 'lh',
            scope: ':root',
        }).contents;

        const aaSnapshot = SNAPSHOT;

        expect(snapshot.diagnostics).toEqual(aaSnapshot.diagnostics);
        expect(css).toContain('(target AAA)');
    });

    it('e deterministico: a mesma entrada gera os mesmos bytes', () => {
        const first = buildLayoutCss(inputOf(), {
            prefix: 'lh',
            scope: ':root',
        });
        const second = buildLayoutCss(inputOf(), {
            prefix: 'lh',
            scope: ':root',
        });

        expect(first.contents).toBe(second.contents);
        expect(first.filename).toBe('colors.css');
        expect(first.mime).toBe('text/css;charset=utf-8');
    });
});
