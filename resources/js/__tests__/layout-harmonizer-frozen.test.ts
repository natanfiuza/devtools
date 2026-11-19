import { nextTick } from 'vue';
import { describe, expect, it } from 'vitest';
import { contrastRatio, parseColor, type Rgba } from '@/lib/color';
import { generateLayoutSystem } from '@/tools/layout-harmonizer/engine';
import { useFrozenLayout } from '@/tools/layout-harmonizer/composables/useFrozenLayout';
import { emptyEditState } from '@/tools/layout-harmonizer/lib/editState';
import {
    applyFrozenColor,
    frozenResult,
    toggleFrozenLock,
} from '@/tools/layout-harmonizer/lib/frozen';
import { buildSnapshot } from '@/tools/layout-harmonizer/lib/snapshot';
import type { LayoutStateInputsV1 } from '@/tools/layout-harmonizer/lib/state';

/**
 * Modo congelado (ADR-169): versoes antigas sao editadas no lugar, sem gerar
 * tokens, sem restaurar e sem sugerir. Nada aqui chama o motor.
 */

function color(input: string): Rgba {
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

function snapshotOf() {
    return buildSnapshot(
        generateLayoutSystem({
            seed: color('#3b82f6'),
            strategy: 'analogous',
        }),
        emptyEditState(),
        'aa',
    );
}

describe('resultado congelado', () => {
    it('carrega as versoes gravadas, nao as do motor atual', () => {
        const snapshot = { ...snapshotOf(), algorithmVersion: 0 };
        const result = frozenResult(snapshot);

        expect(result.algorithmVersion).toBe(0);
        expect(result.schemaVersion).toBe(snapshot.schemaVersion);
        expect(result.themes.light.tokens).toHaveProperty('primary');
    });
});

describe('edicao de token congelado', () => {
    it('atualiza so o tema escolhido e remede os checks', () => {
        const snapshot = snapshotOf();
        const darkBefore = snapshot.themes[1]!.tokens.find(
            (token) => token.id === 'primary',
        )!.hex;

        const next = applyFrozenColor(
            snapshot,
            'light',
            'primary',
            color('#ff0000'),
        );
        const result = frozenResult(next);
        const board = result.themes.light;
        const check = board.checks.find(
            (item) => item.foreground === 'on-primary',
        )!;

        expect(board.tokens.primary.hex).toBe('#ff0000');
        expect(board.tokens.primary.source).toBe('manual');
        expect(result.themes.dark.tokens.primary.hex).toBe(darkBefore);
        expect(check.verdict.ratio).toBeCloseTo(
            contrastRatio(
                board.tokens['on-primary'].rgba,
                board.tokens[check.background].rgba,
            ),
            10,
        );
        expect(next.overrides).toEqual([
            {
                theme: 'light',
                token: 'primary',
                origin: 'edit',
                rgba: { r: 255, g: 0, b: 0, a: 1 },
                hex: '#ff0000',
            },
        ]);
        expect(next.algorithmVersion).toBe(snapshot.algorithmVersion);
        expect(next.suggestionsVersion).toBe(snapshot.suggestionsVersion);
    });

    it('nao mexe no snapshot de origem', () => {
        const snapshot = snapshotOf();
        const before = snapshot.themes[0]!.tokens.find(
            (token) => token.id === 'primary',
        )!.hex;

        applyFrozenColor(snapshot, 'light', 'primary', color('#ff0000'));

        expect(
            snapshot.themes[0]!.tokens.find((token) => token.id === 'primary')!
                .hex,
        ).toBe(before);
    });

    it('bloqueia e desbloqueia sem mudar o valor exibido', () => {
        const snapshot = snapshotOf();
        const locked = toggleFrozenLock(snapshot, 'dark', 'primary');

        expect(locked.locks).toContainEqual({
            theme: 'dark',
            token: 'primary',
        });
        expect(frozenResult(locked).themes.dark.tokens.primary.hex).toBe(
            frozenResult(snapshot).themes.dark.tokens.primary.hex,
        );

        const unlocked = toggleFrozenLock(locked, 'dark', 'primary');

        expect(unlocked.locks).toEqual([]);
    });
});

describe('controller congelado', () => {
    it('edita no lugar e desliga restaurar/sugerir', () => {
        const controller = useFrozenLayout({
            snapshot: snapshotOf(),
            inputs: INPUTS,
        });

        expect(controller.mode).toBe('compatible');
        expect(controller.canRestoreEdits).toBe(false);
        expect(controller.canSuggest).toBe(false);
        expect(controller.editable.value).toBe(true);
        expect(controller.suggestions.value).toEqual([]);

        controller.applyColor('light', 'primary', color('#ff0000'));

        expect(controller.result.value!.themes.light.tokens.primary.hex).toBe(
            '#ff0000',
        );
        expect(controller.editState.value.overrides.light?.primary).toEqual({
            rgb: { r: 255, g: 0, b: 0 },
            origin: 'edit',
        });

        // Restaurar e aceitar sao no-op no modo congelado.
        controller.restoreAllEdits();
        controller.acceptAdjustment('light', 'primary');

        expect(
            controller.editState.value.overrides.light?.primary,
        ).toBeDefined();
        expect(controller.editState.value.accepted).toEqual([]);
    });

    it('marca regeneracao pendente sem trocar o quadro', async () => {
        const controller = useFrozenLayout({
            snapshot: snapshotOf(),
            inputs: INPUTS,
        });
        const before = controller.result.value;

        expect(controller.pendingRegeneration.value).toBe(false);

        controller.strategy.value = 'triadic';
        await nextTick();

        expect(controller.pendingRegeneration.value).toBe(true);
        expect(controller.result.value).toBe(before);

        controller.secondaryText.value = '#00ff00';
        await nextTick();

        expect(controller.pendingRegeneration.value).toBe(true);
    });

    it('readonly nao aceita edicao de token', () => {
        const controller = useFrozenLayout(
            { snapshot: snapshotOf(), inputs: INPUTS },
            { mode: 'readonly' },
        );

        expect(controller.mode).toBe('readonly');
        expect(controller.editable.value).toBe(false);

        const before = controller.result.value!.themes.light.tokens.primary.hex;

        controller.applyColor('light', 'primary', color('#ff0000'));
        controller.toggleTokenLock('light', 'primary');

        expect(controller.result.value!.themes.light.tokens.primary.hex).toBe(
            before,
        );
        expect(controller.editState.value.overrides).toEqual({});
        expect(controller.editState.value.locks).toEqual({});
    });

    it('hidrata os textos sem marcar regeneracao pendente', () => {
        const controller = useFrozenLayout({
            snapshot: snapshotOf(),
            inputs: {
                ...INPUTS,
                seedText: 'rgba(59, 130, 246, 1)',
            },
        });

        expect(controller.picker.text.value).toBe('rgba(59, 130, 246, 1)');
        expect(controller.pendingRegeneration.value).toBe(false);
    });
});
