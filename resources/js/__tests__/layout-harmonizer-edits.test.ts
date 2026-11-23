import { describe, expect, it } from 'vitest';
import { parseColor, type Rgba } from '@/lib/color';
import {
    acceptSuggestion,
    countEdits,
    dropUnlocked,
    effectiveOverrides,
    emptyEditState,
    hasAnyEdits,
    hasUnlockedOverrides,
    isLocked,
    restoreAll,
    restoreTheme,
    restoreToken,
    setOverride,
    signatureOf,
    toggleLock,
} from '@/tools/layout-harmonizer/lib/editState';

/**
 * Estado de edicao da F3: bloqueios por token e tema, origem do override,
 * descarte apenas do que nao esta bloqueado e restauracao por escopo.
 */

function colorOf(input: string): Rgba {
    const parsed = parseColor(input);

    if (!parsed.ok) {
        throw new Error(`cor de teste invalida: ${input}`);
    }

    return parsed.color.rgba;
}

const RED = colorOf('#ff0000');
const GREEN = colorOf('#00ff00');

describe('assinatura das entradas', () => {
    it('muda com estrategia e com secundaria manual', () => {
        const base = signatureOf(RED, 'analogous', null);

        expect(signatureOf(RED, 'analogous', null)).toBe(base);
        expect(signatureOf(RED, 'triadic', null)).not.toBe(base);
        expect(signatureOf(RED, 'analogous', GREEN)).not.toBe(base);
    });

    it('ignora o alpha ja composto na seed efetiva', () => {
        expect(signatureOf({ ...RED, a: 1 }, 'analogous', null)).toBe(
            signatureOf(RED, 'analogous', null),
        );
    });

    it('inclui o fundo de composicao da secundaria com alpha', () => {
        const translucent = { ...GREEN, a: 0.5 };
        const white = { r: 255, g: 255, b: 255 };
        const black = { r: 0, g: 0, b: 0 };
        const base = signatureOf(RED, 'analogous', translucent, white);

        expect(signatureOf(RED, 'analogous', translucent, white)).toBe(base);
        expect(signatureOf(RED, 'analogous', translucent, black)).not.toBe(
            base,
        );
        expect(signatureOf(RED, 'analogous', translucent, null)).not.toBe(base);
    });
});

describe('bloqueio', () => {
    it('ligar captura o valor efetivo como override de origem lock', () => {
        const state = toggleLock(emptyEditState(), 'light', 'primary', RED);

        expect(isLocked(state, 'light', 'primary')).toBe(true);
        expect(state.overrides.light?.primary).toEqual({
            rgb: { r: 255, g: 0, b: 0 },
            origin: 'lock',
        });
        expect(countEdits(state)).toBe(1);
    });

    it('desligar remove apenas o override de bloqueio', () => {
        let state = toggleLock(emptyEditState(), 'light', 'primary', RED);

        state = toggleLock(state, 'light', 'primary', RED);

        expect(isLocked(state, 'light', 'primary')).toBe(false);
        expect(state.overrides.light?.primary).toBeUndefined();
    });

    it('desligar preserva um valor manual ou aceito', () => {
        let state = setOverride(emptyEditState(), 'light', 'primary', GREEN);

        state = toggleLock(state, 'light', 'primary', GREEN);
        state = toggleLock(state, 'light', 'primary', GREEN);

        expect(isLocked(state, 'light', 'primary')).toBe(false);
        expect(state.overrides.light?.primary?.origin).toBe('edit');
        expect(state.overrides.light?.primary?.rgb).toEqual({
            r: 0,
            g: 255,
            b: 0,
        });
    });

    it('e independente por tema', () => {
        const state = toggleLock(emptyEditState(), 'light', 'primary', RED);

        expect(isLocked(state, 'light', 'primary')).toBe(true);
        expect(isLocked(state, 'dark', 'primary')).toBe(false);
        expect(state.overrides.dark).toBeUndefined();
    });
});

describe('aceite de sugestao', () => {
    it('mantem o bloqueio do token aceito e dos demais', () => {
        let state = toggleLock(emptyEditState(), 'light', 'primary', RED);

        state = toggleLock(state, 'light', 'on-primary', GREEN);
        state = toggleLock(state, 'dark', 'background', GREEN);
        state = acceptSuggestion(
            state,
            'light',
            'on-primary',
            GREEN,
            RED,
            'aaa',
        );

        expect(isLocked(state, 'light', 'on-primary')).toBe(true);
        expect(isLocked(state, 'light', 'primary')).toBe(true);
        expect(isLocked(state, 'dark', 'background')).toBe(true);
        expect(state.overrides.light?.['on-primary']).toEqual({
            rgb: { r: 255, g: 0, b: 0 },
            origin: 'accept',
        });
        expect(state.accepted).toHaveLength(1);
        expect(state.accepted[0]).toMatchObject({
            theme: 'light',
            token: 'on-primary',
            target: 'aaa',
        });
    });

    it('preserva os valores bloqueados ao montar os overrides efetivos', () => {
        let state = toggleLock(emptyEditState(), 'light', 'primary', RED);

        state = acceptSuggestion(
            state,
            'light',
            'on-primary',
            GREEN,
            RED,
            'aa',
        );

        expect(effectiveOverrides(state)?.light?.primary).toEqual({
            r: 255,
            g: 0,
            b: 0,
        });
        expect(effectiveOverrides(state)?.light?.['on-primary']).toEqual({
            r: 255,
            g: 0,
            b: 0,
        });
    });
});

describe('descarte na regeneracao', () => {
    it('mantem bloqueados e remove manuais e aceitos sem bloqueio', () => {
        let state = setOverride(emptyEditState(), 'light', 'primary', RED);

        state = acceptSuggestion(
            state,
            'light',
            'on-primary',
            GREEN,
            RED,
            'aa',
        );
        state = toggleLock(state, 'dark', 'background', GREEN);

        expect(hasUnlockedOverrides(state)).toBe(true);

        const dropped = dropUnlocked(state);

        expect(isLocked(dropped, 'dark', 'background')).toBe(true);
        expect(dropped.overrides.dark?.background?.origin).toBe('lock');
        expect(dropped.overrides.light).toBeUndefined();
        expect(dropped.accepted).toHaveLength(0);
        expect(hasUnlockedOverrides(dropped)).toBe(false);
    });

    it('sem edicoes devolve todos os mapas vazios', () => {
        expect(hasAnyEdits(emptyEditState())).toBe(false);
        expect(dropUnlocked(emptyEditState())).toEqual(emptyEditState());
    });
});

describe('restaurar', () => {
    it('por token remove override, bloqueio e aceite daquele token', () => {
        let state = toggleLock(emptyEditState(), 'light', 'primary', RED);

        state = acceptSuggestion(
            state,
            'light',
            'on-primary',
            GREEN,
            RED,
            'aa',
        );

        const restored = restoreToken(state, 'light', 'on-primary');

        expect(restored.overrides.light?.['on-primary']).toBeUndefined();
        expect(restored.overrides.light?.primary).toBeDefined();
        expect(restored.accepted).toHaveLength(0);
    });

    it('por tema limpa um tema e preserva o outro', () => {
        let state = toggleLock(emptyEditState(), 'light', 'primary', RED);

        state = toggleLock(state, 'dark', 'primary', GREEN);

        const restored = restoreTheme(state, 'light');

        expect(restored.overrides.light).toBeUndefined();
        expect(restored.locks.light).toBeUndefined();
        expect(isLocked(restored, 'dark', 'primary')).toBe(true);
    });

    it('no sistema limpa os dois temas', () => {
        let state = toggleLock(emptyEditState(), 'light', 'primary', RED);

        state = toggleLock(state, 'dark', 'primary', GREEN);

        expect(restoreAll(state)).toEqual(emptyEditState());
    });
});

describe('mapa para o motor', () => {
    it('copia os overrides por tema sem metadados', () => {
        let state = setOverride(emptyEditState(), 'light', 'primary', RED);

        state = toggleLock(state, 'dark', 'secondary', GREEN);

        expect(effectiveOverrides(state)).toEqual({
            light: { primary: { r: 255, g: 0, b: 0 } },
            dark: { secondary: { r: 0, g: 255, b: 0 } },
        });
        expect(effectiveOverrides(emptyEditState())).toBeUndefined();
    });
});
