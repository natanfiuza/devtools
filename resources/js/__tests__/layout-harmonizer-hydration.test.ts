import { nextTick } from 'vue';
import { describe, expect, it } from 'vitest';
import { parseColor, type Rgba } from '@/lib/color';
import { generateLayoutSystem } from '@/tools/layout-harmonizer/engine';
import { useLayoutSystem } from '@/tools/layout-harmonizer/composables/useLayoutSystem';
import {
    effectiveOverrides,
    emptyEditState,
    setOverride,
    toggleLock,
} from '@/tools/layout-harmonizer/lib/editState';
import { buildSnapshot } from '@/tools/layout-harmonizer/lib/snapshot';
import {
    buildLayoutState,
    type LayoutStateInputsV1,
} from '@/tools/layout-harmonizer/lib/state';

/**
 * Hidratacao da F4 (ADR-168): reabrir um sistema salvo nao regenera o quadro
 * e consumir a primeira execucao do watch nao pode criar um laco.
 */

async function flush(): Promise<void> {
    await nextTick();
}

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

function savedState(
    edit = emptyEditState(),
    overrides: Partial<LayoutStateInputsV1> = {},
) {
    const result = generateLayoutSystem({
        seed: color('#3b82f6'),
        strategy: 'analogous',
        overrides: effectiveOverrides(edit),
    });

    return buildLayoutState(
        inputs(overrides),
        null,
        buildSnapshot(result, edit, 'aa'),
    );
}

describe('reabrir um sistema salvo', () => {
    it('mostra o snapshot gravado sem chamar o motor', () => {
        const state = savedState();
        const snapshot = structuredClone(state.snapshot);
        const primary = snapshot.themes[0]!.tokens.find(
            (token) => token.id === 'primary',
        )!;

        // Um valor que o motor nunca produziria: se a montagem regenerasse,
        // ele voltaria para o valor derivado.
        primary.hex = '#123456';
        primary.rgba = { r: 18, g: 52, b: 86, a: 1 };

        const controller = useLayoutSystem({
            initial: {
                state: {
                    ...state,
                    snapshot,
                },
                mode: 'live',
            },
        });

        expect(controller.result.value).not.toBeNull();
        expect(controller.snapshot.value).not.toBeNull();
        expect(controller.result.value!.themes.light.tokens.primary.hex).toBe(
            '#123456',
        );
        expect(controller.picker.text.value).toBe('#3b82f6');
        expect(controller.notice.value).toBeNull();
        expect(controller.editable.value).toBe(true);
    });

    it('preserva bloqueios e aceites gravados', () => {
        const base = generateLayoutSystem({
            seed: color('#3b82f6'),
            strategy: 'analogous',
        });
        const edit = toggleLock(
            setOverride(emptyEditState(), 'light', 'primary', color('#ff0000')),
            'dark',
            'background',
            base.themes.dark.tokens.background.rgba,
        );
        const result = generateLayoutSystem({
            seed: color('#3b82f6'),
            strategy: 'analogous',
            overrides: effectiveOverrides(edit),
        });
        const controller = useLayoutSystem({
            initial: {
                state: buildLayoutState(
                    inputs(),
                    null,
                    buildSnapshot(result, edit, 'aaa'),
                ),
                mode: 'live',
            },
        });

        expect(controller.target.value).toBe('aaa');
        expect(controller.result.value!.themes.light.tokens.primary.hex).toBe(
            '#ff0000',
        );
        expect(controller.isTokenLocked('dark', 'background')).toBe(true);
        expect(controller.editState.value.accepted).toEqual([]);
    });

    it('reabre com a secundaria manual efetiva', () => {
        const manual = { r: 0, g: 255, b: 0 };
        const result = generateLayoutSystem({
            seed: color('#3b82f6'),
            strategy: 'analogous',
            secondary: manual,
        });
        const controller = useLayoutSystem({
            initial: {
                state: buildLayoutState(
                    inputs({ secondaryText: '#00ff00' }),
                    manual,
                    buildSnapshot(result, emptyEditState(), 'aa'),
                ),
                mode: 'live',
            },
        });

        expect(controller.secondaryColor.value).toEqual(manual);
        expect(controller.secondarySource.value).toBe('manual');
        expect(controller.secondaryText.value).toBe('#00ff00');
    });

    it('volta a regenerar so quando a entrada muda', async () => {
        const controller = useLayoutSystem({
            initial: { state: savedState(), mode: 'live' },
        });
        const hydrated = controller.result.value;

        await flush();

        expect(controller.result.value).toBe(hydrated);

        controller.picker.setFromText('#22c55e');
        await flush();

        expect(controller.result.value).not.toBe(hydrated);
        expect(
            controller.result.value!.themes.light.tokens.primary.hex,
        ).not.toBe(hydrated!.themes.light.tokens.primary.hex);
        expect(controller.notice.value).toBeNull();
    });

    it('descarta manual sem bloqueio ao trocar a seed hidratada', async () => {
        const controller = useLayoutSystem({
            initial: {
                state: savedState(
                    setOverride(
                        emptyEditState(),
                        'light',
                        'secondary',
                        color('#ff0000'),
                    ),
                ),
                mode: 'live',
            },
        });

        expect(
            controller.editState.value.overrides.light?.secondary,
        ).toBeDefined();

        controller.picker.setFromText('#22c55e');
        await flush();

        expect(controller.editNotice.value).toBe('discarded');
        expect(
            controller.editState.value.overrides.light?.secondary,
        ).toBeUndefined();
    });
});

describe('seed vinda do CTA da paleta', () => {
    it('preenche a seed e gera uma unica vez', async () => {
        const controller = useLayoutSystem({ seedText: '#3b82f6' });

        expect(controller.picker.text.value).toBe('#3b82f6');
        // O watch e imediato: o CTA da paleta gera na propria montagem.
        expect(controller.result.value).not.toBeNull();

        await flush();

        expect(controller.result.value).not.toBeNull();
        expect(controller.notice.value).toBeNull();
    });

    it('seed invalida vira aviso e mantem o quadro vazio', async () => {
        const controller = useLayoutSystem({ seedText: 'nao-e-cor' });

        await flush();

        expect(controller.result.value).toBeNull();
        expect(controller.notice.value).toBe('invalid-seed');
    });
});
