import { describe, expect, it } from 'vitest';
import { formatHex, parseColor, type Rgba } from '@/lib/color';
import {
    LAYOUT_ALGORITHM_VERSION,
    LAYOUT_SCHEMA_VERSION,
    generateLayoutSystem,
    type LayoutStrategy,
} from '@/tools/layout-harmonizer/engine';
import {
    emptyEditState,
    setOverride,
    toggleLock,
} from '@/tools/layout-harmonizer/lib/editState';
import { buildSnapshot } from '@/tools/layout-harmonizer/lib/snapshot';
import {
    LAYOUT_STATE_VERSION,
    buildLayoutState,
    hydrateState,
    hydrationMode,
    isLayoutSystemState,
    materializeResult,
    type LayoutStateInputsV1,
    type LayoutSystemStateV1,
} from '@/tools/layout-harmonizer/lib/state';

/**
 * Envelope persistido da F4 (ADR-168): round-trip sem regenerar, textos crus
 * preservados e normalizacao de hidratacao que nunca troca o quadro gravado.
 */

function color(input: string): Rgba {
    const parsed = parseColor(input);

    if (!parsed.ok) {
        throw new Error(`cor de teste invalida: ${input}`);
    }

    return parsed.color.rgba;
}

function inputs(
    overrides: Partial<LayoutStateInputsV1> = {},
): LayoutStateInputsV1 {
    return {
        seedText: '#3b82f6',
        backgroundText: '',
        secondaryText: '',
        strategy: 'analogous',
        ...overrides,
    };
}

function snapshotOf(
    seed: Rgba,
    strategy: LayoutStrategy = 'analogous',
    background: { r: number; g: number; b: number } | null = null,
    secondary: Rgba | null = null,
    target: 'aa' | 'aaa' = 'aa',
) {
    return buildSnapshot(
        generateLayoutSystem({
            seed,
            strategy,
            background,
            secondary,
            overrides: undefined,
        }),
        emptyEditState(),
        target,
    );
}

function stateOf(
    overrides: Partial<LayoutStateInputsV1> = {},
    secondary: Rgba | null = null,
    snapshot = snapshotOf(color('#3b82f6')),
): LayoutSystemStateV1 {
    return buildLayoutState(inputs(overrides), secondary, snapshot);
}

describe('envelope do estado', () => {
    it('monta e hidrata o mesmo quadro, com entradas cruas intactas', () => {
        const snapshot = snapshotOf(color('#3b82f6'));
        const state = stateOf({}, null, snapshot);
        const hydrated = hydrateState(state);

        expect(state.stateVersion).toBe(LAYOUT_STATE_VERSION);
        expect(hydrated.inputs.seedText).toBe('#3b82f6');
        expect(hydrated.inputs.strategy).toBe('analogous');
        expect(hydrated.secondary).toBeNull();
        expect(hydrated.target).toBe('aa');
        expect(hydrated.mode).toBe('live');
        expect(hydrated.snapshot).toBe(snapshot);
    });

    it('sobrevive ao round-trip JSON do storage', () => {
        const state = stateOf();
        const parsed: unknown = JSON.parse(JSON.stringify(state));

        expect(isLayoutSystemState(parsed)).toBe(true);

        const hydrated = hydrateState(parsed as LayoutSystemStateV1);

        expect(hydrated.snapshot.seed.effective).toEqual(
            state.snapshot.seed.effective,
        );
        expect(hydrated.inputs.seedText).toBe('#3b82f6');
    });

    it('guarda alpha e sintaxe alternativa sem perder a composicao', () => {
        const seed = color('rgba(59, 130, 246, 0.6)');
        const snapshot = snapshotOf(seed, 'analogous', {
            r: 255,
            g: 255,
            b: 255,
        });
        const state = stateOf(
            {
                seedText: 'rgba(59, 130, 246, 0.6)',
                backgroundText: '#fff',
            },
            null,
            snapshot,
        );
        const hydrated = hydrateState(state);

        expect(hydrated.inputs.seedText).toBe('rgba(59, 130, 246, 0.6)');
        expect(hydrated.inputs.backgroundText).toBe('#fff');
        expect(hydrated.snapshot.seed.background).toEqual({
            r: 255,
            g: 255,
            b: 255,
        });
    });

    it('normaliza texto que nao reproduz mais a seed gravada', () => {
        const snapshot = snapshotOf(color('#3b82f6'));
        const hydrated = hydrateState(
            stateOf({ seedText: '#000000' }, null, snapshot),
        );

        expect(hydrated.inputs.seedText).toBe(
            formatHex(snapshot.seed.effective),
        );
    });

    it('preserva a secundaria manual efetiva e normaliza seu texto', () => {
        const manual = { r: 0, g: 255, b: 0, a: 1 };
        const snapshot = snapshotOf(
            color('#3b82f6'),
            'analogous',
            null,
            manual,
        );

        expect(snapshot.secondarySource).toBe('manual');

        const hydrated = hydrateState(
            stateOf({ secondaryText: 'verde-nao-existe' }, manual, snapshot),
        );

        expect(hydrated.secondary).toEqual(manual);
        expect(hydrated.inputs.secondaryText).toBe(
            formatHex({ ...manual, a: 1 }),
        );
    });

    it('limpa o texto da secundaria quando o quadro e automatico', () => {
        const hydrated = hydrateState(
            stateOf(
                { secondaryText: '#00ff00' },
                null,
                snapshotOf(color('#3b82f6')),
            ),
        );

        expect(hydrated.inputs.secondaryText).toBe('');
        expect(hydrated.secondary).toBeNull();
    });

    it('preserva a secundaria com alpha e o fundo de composicao', () => {
        const translucent = color('#00ff00');
        translucent.a = 0.5;

        const white = { r: 255, g: 255, b: 255 };
        const result = generateLayoutSystem({
            seed: color('#3b82f6'),
            strategy: 'analogous',
            secondary: translucent,
            secondaryBackground: white,
        });
        const state = buildLayoutState(
            inputs({
                secondaryText: 'rgba(0, 255, 0, 0.5)',
                secondaryBackgroundText: '#fff',
            }),
            translucent,
            buildSnapshot(result, emptyEditState(), 'aa'),
            white,
        );
        const parsed: unknown = JSON.parse(JSON.stringify(state));

        expect(isLayoutSystemState(parsed)).toBe(true);

        const hydrated = hydrateState(parsed as LayoutSystemStateV1);

        expect(hydrated.secondary).toEqual(translucent);
        expect(hydrated.secondaryBackground).toEqual(white);
        expect(hydrated.inputs.secondaryBackgroundText).toBe('#fff');
        expect(hydrated.inputs.secondaryText).toBe('rgba(0, 255, 0, 0.5)');
    });

    it('aceita linhas antigas sem os campos da emenda', () => {
        const state = stateOf();
        const old = JSON.parse(JSON.stringify(state)) as {
            inputs: Record<string, unknown>;
            secondary?: unknown;
        };

        delete old.inputs.secondaryBackgroundText;

        expect(isLayoutSystemState(old)).toBe(true);

        const hydrated = hydrateState(old as unknown as LayoutSystemStateV1);

        expect(hydrated.inputs.secondaryBackgroundText).toBe('');
        expect(hydrated.secondaryBackground).toBeNull();
    });

    it('normaliza a secundaria RGB sem canal alpha de linhas antigas', () => {
        const manual = { r: 0, g: 255, b: 0, a: 1 };
        const state = stateOf(
            { secondaryText: '#00ff00' },
            manual,
            snapshotOf(color('#3b82f6'), 'analogous', null, manual),
        );
        const old = JSON.parse(JSON.stringify(state)) as {
            secondary: Record<string, unknown>;
        };

        delete old.secondary.a;

        expect(isLayoutSystemState(old)).toBe(true);
        expect(
            hydrateState(old as unknown as LayoutSystemStateV1).secondary,
        ).toEqual(manual);
    });
});

describe('edicoes no snapshot', () => {
    it('reconstroi overrides, bloqueios e aceites sem chamar o motor', () => {
        const result = generateLayoutSystem({
            seed: color('#3b82f6'),
            strategy: 'analogous',
        });
        const edited = toggleLock(
            setOverride(emptyEditState(), 'light', 'primary', color('#ff0000')),
            'dark',
            'secondary',
            result.themes.dark.tokens.secondary.rgba,
        );
        const snapshot = buildSnapshot(result, edited, 'aaa');
        const hydrated = hydrateState(
            buildLayoutState(inputs(), null, snapshot),
        );

        expect(hydrated.target).toBe('aaa');
        expect(hydrated.editState.overrides.light?.primary).toEqual({
            rgb: { r: 255, g: 0, b: 0 },
            origin: 'edit',
        });
        expect(hydrated.editState.locks.dark?.secondary).toBe(true);
        expect(hydrated.editState.accepted).toEqual([]);
    });

    it('materializa o resultado live com as versoes atuais', () => {
        const snapshot = snapshotOf(color('#3b82f6'));
        const result = materializeResult(snapshot);

        expect(result.schemaVersion).toBe(LAYOUT_SCHEMA_VERSION);
        expect(result.algorithmVersion).toBe(LAYOUT_ALGORITHM_VERSION);
        expect(result.themes.light.tokens.primary.hex).toBe(
            snapshot.themes[0]!.tokens.find((token) => token.id === 'primary')!
                .hex,
        );
        expect(result.themes.dark.checks).toHaveLength(
            snapshot.themes[1]!.checks.length,
        );
    });
});

describe('modo de hidratacao', () => {
    it('live quando todas as versoes sao atuais', () => {
        expect(hydrationMode(stateOf())).toBe('live');
    });

    it('compatible quando algoritmo ou sugestoes sao anteriores', () => {
        const state = stateOf();
        const snapshot = {
            ...state.snapshot,
            algorithmVersion: LAYOUT_ALGORITHM_VERSION - 1,
        };

        expect(hydrationMode({ ...state, snapshot })).toBe('compatible');
    });

    it('readonly para versao mais nova ou desconhecida', () => {
        const state = stateOf();

        expect(
            hydrationMode({
                ...state,
                snapshot: {
                    ...state.snapshot,
                    algorithmVersion: LAYOUT_ALGORITHM_VERSION + 1,
                },
            }),
        ).toBe('readonly');

        expect(
            hydrationMode({
                ...state,
                snapshot: { ...state.snapshot, snapshotVersion: 2 },
            } as unknown as LayoutSystemStateV1),
        ).toBe('readonly');

        expect(
            hydrationMode({
                ...state,
                stateVersion: 2,
            } as unknown as LayoutSystemStateV1),
        ).toBe('readonly');
    });
});

describe('shape invalido', () => {
    it('recusa envelopes que nao sao o estado v1', () => {
        expect(isLayoutSystemState(null)).toBe(false);
        expect(isLayoutSystemState({})).toBe(false);
        expect(isLayoutSystemState({ ...stateOf(), stateVersion: 2 })).toBe(
            false,
        );

        const state = stateOf();
        const withoutTheme = {
            ...state,
            snapshot: {
                ...state.snapshot,
                themes: state.snapshot.themes.slice(0, 1),
            },
        };

        expect(isLayoutSystemState(withoutTheme)).toBe(false);
    });

    it('recusa tokens faltando no tema', () => {
        const state = stateOf();
        const broken = {
            ...state,
            snapshot: {
                ...state.snapshot,
                themes: state.snapshot.themes.map((theme, index) =>
                    index === 0
                        ? { ...theme, tokens: theme.tokens.slice(0, -1) }
                        : theme,
                ),
            },
        };

        expect(isLayoutSystemState(broken)).toBe(false);
    });
});
