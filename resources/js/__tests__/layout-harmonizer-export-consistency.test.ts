import { describe, expect, it } from 'vitest';
import { formatHex, parseColor } from '@/lib/color';
import { generateLayoutSystem } from '@/tools/layout-harmonizer/engine';
import { buildLayoutCss } from '@/tools/layout-harmonizer/export/css';
import { buildLayoutMarkdown } from '@/tools/layout-harmonizer/export/markdown';
import { buildLayoutZip } from '@/tools/layout-harmonizer/export/zip';
import type { LayoutExportInput } from '@/tools/layout-harmonizer/export/types';
import { unzipSync, strFromU8 } from 'fflate';
import { emptyEditState } from '@/tools/layout-harmonizer/lib/editState';
import { buildSnapshot } from '@/tools/layout-harmonizer/lib/snapshot';
import {
    buildLayoutState,
    type LayoutSystemStateV1,
} from '@/tools/layout-harmonizer/lib/state';
import { LAYOUT_MARKDOWN_FILENAME } from '@/tools/layout-harmonizer/export/filename';
import { layoutExportMessages } from './support/langMessages';

/**
 * Consistencia das exportacoes com o quadro (F5, ADR-175).
 *
 * Os tres arquivos saem do mesmo snapshot ja exibido: nada e regenerado no
 * caminho. Um valor sentinela trocado direto no snapshot aparece no Markdown e
 * no CSS, que e a prova de que os exportadores nao recalculam nada.
 */

function color(input: string) {
    const parsed = parseColor(input);

    if (!parsed.ok) {
        throw new Error(`cor de teste invalida: ${input}`);
    }

    return parsed.color.rgba;
}

function stateFixture(): LayoutSystemStateV1 {
    const snapshot = buildSnapshot(
        generateLayoutSystem({
            seed: color('#3b82f6'),
            strategy: 'analogous',
            background: null,
            secondary: null,
            overrides: undefined,
        }),
        emptyEditState(),
        'aa',
    );

    return buildLayoutState(
        {
            seedText: '#3b82f6',
            backgroundText: '',
            secondaryText: '',
            strategy: 'analogous',
        },
        null,
        snapshot,
    );
}

function inputOf(state: LayoutSystemStateV1): LayoutExportInput {
    return {
        state,
        name: 'Sistema Oceano',
        language: 'en',
        messages: layoutExportMessages()['en'] ?? {},
        origin: null,
    };
}

describe('exportacao x snapshot', () => {
    it('leva os mesmos HEX e OKLCH do snapshot para o Markdown e o CSS', () => {
        const state = stateFixture();
        const input = inputOf(state);
        const markdown = buildLayoutMarkdown(input).contents;
        const css = buildLayoutCss(input, {
            prefix: 'lh',
            scope: ':root',
        }).contents;

        for (const board of state.snapshot.themes) {
            for (const token of board.tokens) {
                expect(markdown).toContain(token.hex);
                expect(css).toContain(`--lh-${token.id}: ${token.hex};`);
            }
        }
    });

    it('nao regenera: um valor sentinela no snapshot chega aos arquivos', () => {
        const state = stateFixture();
        const light = state.snapshot.themes.find(
            (board) => board.theme === 'light',
        );
        const primary = light?.tokens.find((token) => token.id === 'primary');

        expect(primary).toBeDefined();

        const original = formatHex({ ...primary!.rgba, a: 1 });

        primary!.hex = '#123456';
        primary!.rgba = { r: 0x12, g: 0x34, b: 0x56, a: 1 };
        primary!.oklch = { l: 0.463, c: 0.1, h: 250 };

        const input = inputOf(state);
        const markdown = buildLayoutMarkdown(input).contents;
        const css = buildLayoutCss(input, {
            prefix: 'lh',
            scope: ':root',
        }).contents;

        expect(original).not.toBe('#123456');
        expect(markdown).toContain('#123456');
        expect(css).toContain('--lh-primary: #123456;');
        expect(css).toContain('oklch(46.3% 0.1 250)');
    });

    it('o ZIP carrega exatamente o Markdown e o CSS gerados', async () => {
        const input = inputOf(stateFixture());
        const markdown = buildLayoutMarkdown(input).contents;
        const css = buildLayoutCss(input, {
            prefix: 'lh',
            scope: ':root',
        }).contents;
        const zip = buildLayoutZip(markdown, css, input.name);
        const files = unzipSync(new Uint8Array(await zip.blob.arrayBuffer()));

        expect(strFromU8(files[LAYOUT_MARKDOWN_FILENAME]!)).toBe(markdown);
        expect(strFromU8(files['colors.css']!)).toBe(css);
    });
});
