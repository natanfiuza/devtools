import { describe, expect, it } from 'vitest';
import { parseColor, type Rgba } from '@/lib/color';
import {
    generateLayoutSystem,
    type LayoutSystemResult,
} from '@/tools/layout-harmonizer/engine';
import {
    acceptSuggestion,
    emptyEditState,
    setOverride,
    toggleLock,
    type LayoutEditState,
} from '@/tools/layout-harmonizer/lib/editState';
import {
    LAYOUT_SNAPSHOT_VERSION,
    buildSnapshot,
} from '@/tools/layout-harmonizer/lib/snapshot';
import { LAYOUT_SUGGESTIONS_VERSION } from '@/tools/layout-harmonizer/lib/suggestions';

/**
 * Snapshot (F3): congela o estado efetivo com versoes, overrides, bloqueios e
 * aceites, sem regenerar nada e sem compartilhar referencias com o quadro.
 */

function colorOf(input: string): Rgba {
    const parsed = parseColor(input);

    if (!parsed.ok) {
        throw new Error(`cor de teste invalida: ${input}`);
    }

    return parsed.color.rgba;
}

const BLUE = colorOf('#3b82f6');
const RED = colorOf('#ff0000');
const GREEN = colorOf('#00ff00');

function generate(): LayoutSystemResult {
    return generateLayoutSystem({ seed: BLUE, strategy: 'analogous' });
}

function stateWithEdits(): LayoutEditState {
    let state = setOverride(emptyEditState(), 'light', 'primary', RED);

    state = acceptSuggestion(
        state,
        'dark',
        'on-primary',
        colorOf('#121212'),
        GREEN,
        'aa',
    );
    state = toggleLock(state, 'dark', 'background', GREEN);

    return state;
}

describe('versoes', () => {
    it('registra as versoes do snapshot, do motor e das sugestoes', () => {
        const result = generate();
        const snapshot = buildSnapshot(result, emptyEditState(), 'aaa');

        expect(snapshot.snapshotVersion).toBe(LAYOUT_SNAPSHOT_VERSION);
        expect(snapshot.schemaVersion).toBe(result.schemaVersion);
        expect(snapshot.algorithmVersion).toBe(result.algorithmVersion);
        expect(snapshot.suggestionsVersion).toBe(LAYOUT_SUGGESTIONS_VERSION);
        expect(snapshot.target).toBe('aaa');
        expect(snapshot.strategy).toBe('analogous');
        expect(snapshot.secondarySource).toBe('auto');
    });
});

describe('estado efetivo', () => {
    it('congela tokens e checks dos dois temas', () => {
        const result = generate();
        const snapshot = buildSnapshot(result, emptyEditState(), 'aa');
        const light = snapshot.themes.find((theme) => theme.theme === 'light');

        expect(snapshot.themes).toHaveLength(2);
        expect(light).toBeDefined();
        expect(light!.tokens).toHaveLength(33);
        expect(light!.tokens.find((token) => token.id === 'primary')!.hex).toBe(
            result.themes.light.tokens.primary.hex,
        );
        expect(light!.checks).toHaveLength(result.themes.light.checks.length);
    });

    it('copia a seed original, a efetiva e o fundo', () => {
        const result = generateLayoutSystem({
            seed: colorOf('rgba(59, 130, 246, 0.6)'),
            strategy: 'analogous',
            background: { r: 255, g: 255, b: 255 },
        });
        const snapshot = buildSnapshot(result, emptyEditState(), 'aa');

        expect(snapshot.seed.original.a).toBeCloseTo(0.6, 5);
        expect(snapshot.seed.effective.a).toBe(1);
        expect(snapshot.seed.background).toEqual({ r: 255, g: 255, b: 255 });
    });

    it('nao compartilha referencias com o resultado', () => {
        const result = generate();
        const snapshot = buildSnapshot(result, emptyEditState(), 'aa');
        const token = snapshot.themes[0]!.tokens.find(
            (item) => item.id === 'primary',
        )!;
        const before = result.themes.light.tokens.primary.rgba;

        token.rgba.r = 1;
        snapshot.themes[0]!.checks[0]!.verdict.ratio = 0;

        expect(result.themes.light.tokens.primary.rgba).toEqual(before);
        expect(result.themes.light.checks[0]!.verdict.ratio).not.toBe(0);
    });
});

describe('edicoes congeladas', () => {
    it('lista overrides com origem, rgba e hex', () => {
        const snapshot = buildSnapshot(generate(), stateWithEdits(), 'aa');

        expect(snapshot.overrides).toContainEqual({
            theme: 'light',
            token: 'primary',
            origin: 'edit',
            rgba: { r: 255, g: 0, b: 0, a: 1 },
            hex: '#ff0000',
        });
        expect(snapshot.overrides).toContainEqual({
            theme: 'dark',
            token: 'on-primary',
            origin: 'accept',
            rgba: { r: 0, g: 255, b: 0, a: 1 },
            hex: '#00ff00',
        });
        expect(snapshot.overrides).toContainEqual({
            theme: 'dark',
            token: 'background',
            origin: 'lock',
            rgba: { r: 0, g: 255, b: 0, a: 1 },
            hex: '#00ff00',
        });
    });

    it('lista bloqueios e aceites', () => {
        const snapshot = buildSnapshot(generate(), stateWithEdits(), 'aa');

        expect(snapshot.locks).toEqual([
            { theme: 'dark', token: 'background' },
        ]);
        expect(snapshot.acceptedAdjustments).toHaveLength(1);
        expect(snapshot.acceptedAdjustments[0]).toMatchObject({
            theme: 'dark',
            token: 'on-primary',
            target: 'aa',
            from: { r: 18, g: 18, b: 18 },
            to: { r: 0, g: 255, b: 0 },
        });
    });

    it('fica parado quando o estado muda depois', () => {
        const state = stateWithEdits();
        const snapshot = buildSnapshot(generate(), state, 'aa');
        const after = setOverride(state, 'light', 'primary', GREEN);

        expect(after.overrides.light?.primary?.rgb).toEqual({
            r: 0,
            g: 255,
            b: 0,
        });
        expect(snapshot.overrides).toContainEqual({
            theme: 'light',
            token: 'primary',
            origin: 'edit',
            rgba: { r: 255, g: 0, b: 0, a: 1 },
            hex: '#ff0000',
        });
    });
});

describe('diagnosticos', () => {
    it('lista apenas os checks que falham AA', () => {
        const result = generate();
        const snapshot = buildSnapshot(result, emptyEditState(), 'aa');
        const failing = [
            ...result.themes.light.checks,
            ...result.themes.dark.checks,
        ].filter((check) => !check.verdict.aaNormal);

        expect(snapshot.diagnostics).toHaveLength(failing.length);
        expect(snapshot.diagnostics.every((item) => item.aa === false)).toBe(
            true,
        );
    });
});
