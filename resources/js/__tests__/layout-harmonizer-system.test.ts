import { nextTick } from 'vue';
import { describe, expect, it, vi } from 'vitest';
import { useLayoutSystem } from '@/tools/layout-harmonizer/composables/useLayoutSystem';
import type { LayoutStrategy } from '@/tools/layout-harmonizer/engine';

/**
 * Estado da F2 (ADR-149): abre vazio, gera so com seed valida e, quando a
 * entrada fica invalida, mantem o ultimo quadro em vez de apaga-lo.
 */

async function flush(): Promise<void> {
    await nextTick();
}

describe('entrada da seed', () => {
    it('abre vazia, sem gerar nada', () => {
        const state = useLayoutSystem();

        expect(state.hasSeed.value).toBe(false);
        expect(state.result.value).toBeNull();
        expect(state.notice.value).toBeNull();
        expect(state.isStale.value).toBe(false);
        expect(state.needsBackground.value).toBe(false);
        expect(state.picker.text.value).toBe('');
    });

    it('gera os dois temas com uma seed valida', () => {
        const state = useLayoutSystem();

        state.picker.setFromText('#3b82f6');

        return flush().then(() => {
            expect(state.notice.value).toBeNull();
            expect(state.result.value).not.toBeNull();
            expect(state.result.value?.themes.light.tokens).toBeDefined();
            expect(state.result.value?.themes.dark.tokens).toBeDefined();
            expect(state.seed.value).toBe(state.result.value?.seed);
        });
    });

    it('mantem a mesma referencia do quadro quando o texto fica invalido', async () => {
        const state = useLayoutSystem();

        state.picker.setFromText('#3b82f6');
        await flush();

        const before = state.result.value;

        state.picker.setFromText('nao-e-uma-cor');
        await flush();

        expect(state.result.value).toBe(before);
        expect(state.notice.value).toBe('invalid-seed');
        expect(state.isStale.value).toBe(true);
    });

    it('limpa o quadro e o aviso quando a seed volta a ser vazia', async () => {
        const state = useLayoutSystem();

        state.picker.setFromText('#3b82f6');
        await flush();

        state.picker.setFromText('');
        await flush();

        expect(state.result.value).toBeNull();
        expect(state.notice.value).toBeNull();
        expect(state.hasSeed.value).toBe(false);
    });

    it('regenera ao trocar a estrategia', async () => {
        const state = useLayoutSystem();

        state.picker.setFromText('#3b82f6');
        await flush();

        const analogous = state.result.value;

        state.strategy.value = 'complementary';
        await flush();

        expect(state.result.value).not.toBe(analogous);
        expect(state.result.value?.strategy).toBe('complementary');
        expect(state.notice.value).toBeNull();
    });

    it('preserva o quadro e avisa quando a estrategia nao e reconhecida', async () => {
        const state = useLayoutSystem();

        state.picker.setFromText('#3b82f6');
        await flush();

        const before = state.result.value;

        state.strategy.value = 'bogus' as LayoutStrategy;
        await flush();

        expect(state.result.value).toBe(before);
        expect(state.notice.value).toBe('invalid-strategy');
        expect(state.isStale.value).toBe(true);
    });
});

describe('alpha e fundo de composicao', () => {
    it('exige fundo explicito quando a seed tem transparencia', async () => {
        const state = useLayoutSystem();

        state.picker.setFromText('rgba(59, 130, 246, 0.6)');
        await flush();

        expect(state.needsBackground.value).toBe(true);
        expect(state.result.value).toBeNull();
        expect(state.notice.value).toBe('seed-alpha-without-background');
        expect(state.backgroundInvalid.value).toBe(false);
    });

    it('gera ao informar um fundo valido e registra a composicao', async () => {
        const state = useLayoutSystem();

        state.picker.setFromText('rgba(59, 130, 246, 0.6)');
        await flush();

        state.backgroundText.value = '#fff';
        await flush();

        expect(state.notice.value).toBeNull();
        expect(state.result.value).not.toBeNull();
        expect(state.result.value?.seed.background).toEqual({
            r: 255,
            g: 255,
            b: 255,
        });
        expect(state.backgroundInvalid.value).toBe(false);
    });

    it('marca o fundo invalido e mantem o ultimo quadro', async () => {
        const state = useLayoutSystem();

        state.picker.setFromText('rgba(59, 130, 246, 0.6)');
        await flush();
        state.backgroundText.value = '#fff';
        await flush();

        const before = state.result.value;

        state.backgroundText.value = 'nao-e-cor';
        await flush();

        expect(state.result.value).toBe(before);
        expect(state.backgroundInvalid.value).toBe(true);
        expect(state.notice.value).toBe('seed-alpha-without-background');
        expect(state.isStale.value).toBe(true);
    });
});

describe('persistencia', () => {
    it('nao le nem grava nada no storage do navegador', async () => {
        const getItem = vi.spyOn(Storage.prototype, 'getItem');
        const setItem = vi.spyOn(Storage.prototype, 'setItem');
        const state = useLayoutSystem();

        state.picker.setFromText('#3b82f6');
        await flush();
        state.strategy.value = 'triadic';
        await flush();

        expect(getItem).not.toHaveBeenCalled();
        expect(setItem).not.toHaveBeenCalled();
    });
});
