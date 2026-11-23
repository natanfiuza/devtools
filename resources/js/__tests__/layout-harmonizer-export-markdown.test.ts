import { describe, expect, it } from 'vitest';
import { compositeOver, formatHex, parseColor } from '@/lib/color';
import { generateLayoutSystem } from '@/tools/layout-harmonizer/engine';
import { buildLayoutMarkdown } from '@/tools/layout-harmonizer/export/markdown';
import { lookupMessage } from '@/tools/layout-harmonizer/export/messages';
import type { LayoutExportInput } from '@/tools/layout-harmonizer/export/types';
import {
    emptyEditState,
    setOverride,
    toggleLock,
} from '@/tools/layout-harmonizer/lib/editState';
import { buildSnapshot } from '@/tools/layout-harmonizer/lib/snapshot';
import {
    buildLayoutState,
    type LayoutStateInputsV1,
    type LayoutSystemStateV1,
} from '@/tools/layout-harmonizer/lib/state';
import { exportMessages } from './support/langMessages';

/**
 * Markdown exportado (F5, ADR-148/ADR-177).
 *
 * O documento tem as secoes do contrato, os 33 tokens dos dois temas, os
 * 16 pares de contraste, avisos sempre presentes e origem so quando o
 * credito publico existe.
 */

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

const RESULT = generateLayoutSystem({
    seed: color('#3b82f6'),
    strategy: 'analogous',
});

const SNAPSHOT = buildSnapshot(RESULT, emptyEditState(), 'aa');

function stateOf(snapshot = SNAPSHOT): LayoutSystemStateV1 {
    return buildLayoutState(INPUTS, null, snapshot);
}

function messagesOf(locale = 'en'): Record<string, unknown> {
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

function markdown(overrides: Partial<LayoutExportInput> = {}): string {
    return buildLayoutMarkdown(inputOf(overrides)).contents;
}

describe('export/markdown', () => {
    it('segue a ordem do contrato', () => {
        const text = markdown();
        const headings = [
            '# Sistema Oceano',
            '## Metadata',
            '## Instructions for the AI',
            '## Themes',
            '## Tokens — Light theme',
            '## Tokens — Dark theme',
            '## Application rules',
            '## Contrast — Light theme',
            '## Contrast — Dark theme',
            '## Warnings',
            '## Generated',
        ];

        let previous = -1;

        for (const heading of headings) {
            const index = text.indexOf(heading);

            expect(index, heading).toBeGreaterThan(previous);
            previous = index;
        }
    });

    it('lista os 33 tokens em cada tema com finalidade, HEX e OKLCH', () => {
        const text = markdown();
        const rows = text.match(
            /^\| [a-z-]+ \| [^|\n]+ \| #[0-9a-f]{6} \| oklch\(/gm,
        );

        expect(rows).toHaveLength(66);
        expect(text).toContain('Main brand colour');
    });

    it('traz metadados, versoes e instrucoes para a IA', () => {
        const text = markdown();

        expect(text).toContain('Strategy: Analogous');
        expect(text).toContain('Contrast target: AA');
        expect(text).toContain('Original seed: #3b82f6');
        expect(text).toContain('Effective seed: #3b82f6');
        expect(text).toContain('Secondary: automatic');
        expect(text).toContain(
            'Versions: state 1, schema 1, algorithm 2, suggestions 1, snapshot 1',
        );
        expect(text).toContain('Do not invent colours');
        expect(text).toContain('must stay exactly as listed');
        expect(text).toContain('focus-ring');
        expect(text).toContain('data-theme="dark"');
    });

    it('lista a secundaria com alpha, efetiva e fundo de composicao', () => {
        const translucent = { ...color('#00ff00'), a: 0.5 };
        const white = { r: 255, g: 255, b: 255 };
        const result = generateLayoutSystem({
            seed: color('#3b82f6'),
            strategy: 'analogous',
            secondary: translucent,
            secondaryBackground: white,
        });
        const state = buildLayoutState(
            {
                ...INPUTS,
                secondaryText: 'rgba(0, 255, 0, 0.5)',
                secondaryBackgroundText: '#ffffff',
            },
            translucent,
            buildSnapshot(result, emptyEditState(), 'aa'),
            white,
        );
        const text = markdown({ state });

        expect(text).toContain('Secondary: manual');
        expect(text).toContain('Original secondary: rgba(0, 255, 0, 0.5)');
        expect(text).toContain(
            `Effective secondary: ${formatHex({ ...compositeOver(translucent, white), a: 1 })}`,
        );
        expect(text).toContain('Secondary composition background: #ffffff');
    });

    it('mostra a origem somente quando o credito e de paleta publica', () => {
        const publicOrigin = markdown({
            origin: { name: 'Paleta Onda', public: true },
        });
        const privateOrigin = markdown({
            origin: { name: 'Paleta Onda', public: false },
        });

        expect(publicOrigin).toContain('Origin: Paleta Onda (public palette).');
        expect(privateOrigin).not.toContain('Paleta Onda');
    });

    it('escapa o nome no titulo e nas celulas', () => {
        const text = markdown({
            name: 'Sistema | Onda\nNova *teste*',
        });

        expect(text).toContain('# Sistema \\| Onda Nova \\*teste\\*');
        expect(text).not.toContain('\nNova');
    });

    it('sempre tem a secao de avisos, com contraste e limitacoes', () => {
        const text = markdown();

        expect(text).toContain('## Warnings');
        expect(text).toMatch(/on-primary over primary-hover/);
        expect(text).toContain('(target AA)');
        expect(text).toContain(
            'Contrast targets are advisory: failures are reported and colours are never changed automatically.',
        );
    });

    it('lista bloqueios e valores manuais', () => {
        const locked = toggleLock(
            emptyEditState(),
            'light',
            'primary',
            color('#3b82f6'),
        );
        const edited = setOverride(locked, 'dark', 'surface', color('#111111'));
        const snapshot = buildSnapshot(RESULT, edited, 'aa');
        const text = markdown({ state: stateOf(snapshot) });

        expect(text).toContain('Light theme: primary is locked.');
        expect(text).toContain(
            'Dark theme: surface was set manually (#111111).',
        );
        expect(text).toContain('| Yes |');
    });

    it('usa o alvo AAA nas instrucoes e nos avisos', () => {
        const snapshot = buildSnapshot(RESULT, emptyEditState(), 'aaa');
        const text = markdown({ state: stateOf(snapshot) });

        expect(text).toContain('Contrast target: AAA');
        expect(text).toContain('above the AAA target');
        expect(text).toContain('(target AAA)');
    });

    it('sai nos cinco idiomas a partir do subtree do servidor', () => {
        const locales = ['en', 'pt_BR', 'pt_PT', 'fr', 'es'] as const;
        // Uma leitura so do PHP: cinco spawns passariam do timeout da suite.
        const all = exportMessages();

        for (const locale of locales) {
            const messages = (all[locale]?.tools?.['layout-harmonizer'] ??
                {}) as Record<string, unknown>;
            const text = buildLayoutMarkdown(
                inputOf({ language: locale, messages }),
            ).contents;

            expect(text, locale).toContain('# Sistema Oceano');
            expect(text, locale).toContain(
                lookupMessage(messages, 'export.md.purpose')!,
            );
            expect(text, locale).toContain(
                lookupMessage(messages, 'export.md.metadata_title')!,
            );
            expect(text, locale).toContain(
                lookupMessage(messages, 'export.md.generated_title')!,
            );
            // O idioma do documento e o escolhido, nao o ativo da UI.
            expect(text, locale).toContain(
                lookupMessage(messages, 'export.md.language')!.replace(
                    '{language}',
                    locale,
                ),
            );
        }
    });

    it('e deterministico e nomeia o arquivo pelo idioma', () => {
        const first = buildLayoutMarkdown(inputOf());
        const second = buildLayoutMarkdown(inputOf());

        expect(first.contents).toBe(second.contents);
        expect(first.filename).toBe('design-instructions.en.md');
        expect(first.mime).toBe('text/markdown;charset=utf-8');
    });
});
