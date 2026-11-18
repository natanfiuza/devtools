import { nextTick } from 'vue';
import { describe, expect, it, vi } from 'vitest';
import { contrastRatio, formatHex, parseColor, type Rgba } from '@/lib/color';
import { useLayoutSystem } from '@/tools/layout-harmonizer/composables/useLayoutSystem';
import type { LayoutStrategy } from '@/tools/layout-harmonizer/engine';

/**
 * Estado da F2 (ADR-149): abre vazio, gera so com seed valida e, quando a
 * entrada fica invalida, mantem o ultimo quadro em vez de apaga-lo.
 */

async function flush(): Promise<void> {
    await nextTick();
}

function colorOf(input: string): Rgba {
    const parsed = parseColor(input);

    if (!parsed.ok) {
        throw new Error(`cor de teste invalida: ${input}`);
    }

    return parsed.color.rgba;
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

describe('edicao manual (F3)', () => {
    async function withSeed(seed: string) {
        const state = useLayoutSystem();

        state.picker.setFromText(seed);
        await flush();

        return state;
    }

    it('aplica a cor em um tema sem tocar o outro e remede o contraste', async () => {
        const state = await withSeed('#3b82f6');
        const darkBefore = state.result.value!.themes.dark.tokens.primary.hex;

        state.applyColor('light', 'primary', colorOf('#ff0000'));

        expect(state.result.value!.themes.light.tokens.primary.hex).toBe(
            '#ff0000',
        );
        expect(state.result.value!.themes.light.tokens.primary.source).toBe(
            'manual',
        );
        expect(state.result.value!.themes.dark.tokens.primary.hex).toBe(
            darkBefore,
        );
        const board = state.result.value!.themes.light;
        const check = board.checks.find(
            (item) => item.foreground === 'on-primary',
        )!;

        expect(check.verdict.ratio).toBeCloseTo(
            contrastRatio(
                board.tokens['on-primary'].rgba,
                board.tokens[check.background].rgba,
            ),
            10,
        );
    });

    it('bloqueio sobrevive a troca de seed e manual sem bloqueio cai com aviso', async () => {
        const state = await withSeed('#3b82f6');
        const locked = state.result.value!.themes.light.tokens.primary.hex;

        state.toggleTokenLock('light', 'primary');
        state.applyColor('light', 'secondary', colorOf('#ff0000'));

        expect(state.editNotice.value).toBeNull();

        state.picker.setFromText('#22c55e');
        await flush();

        expect(state.result.value!.themes.light.tokens.primary.hex).toBe(
            locked,
        );
        expect(state.result.value!.themes.light.tokens.secondary.source).toBe(
            'rule',
        );
        expect(state.editNotice.value).toBe('discarded');
        expect(state.isStale.value).toBe(false);
        expect(state.notice.value).toBeNull();
        expect(
            state.editState.value.overrides.light?.secondary,
        ).toBeUndefined();
    });

    it('limpar a seed zera quadro, edicoes e avisos', async () => {
        const state = await withSeed('#3b82f6');

        state.toggleTokenLock('light', 'primary');
        state.picker.setFromText('');
        await flush();

        expect(state.result.value).toBeNull();
        expect(state.isTokenLocked('light', 'primary')).toBe(false);
        expect(state.editNotice.value).toBeNull();
        expect(state.snapshot.value).toBeNull();
    });

    it('trocar a meta nao regenera o quadro', async () => {
        const state = await withSeed('#777777');
        const before = state.result.value;

        state.target.value = 'aaa';
        await flush();

        expect(state.result.value).toBe(before);
        expect(
            state.suggestions.value.every((item) => item.target === 'aaa'),
        ).toBe(true);
    });

    it('entrada invalida desabilita a edicao', async () => {
        const state = await withSeed('#3b82f6');

        expect(state.editable.value).toBe(true);

        state.picker.setFromText('nao-e-cor');
        await flush();

        expect(state.editable.value).toBe(false);
    });
});

describe('sugestoes e aceites (F3)', () => {
    it('aceitar em um tema nao altera o outro', async () => {
        const state = useLayoutSystem();

        state.picker.setFromText('#777777');
        await flush();

        const acceptance = state.suggestions.value.find(
            (item) => item.proposal !== null && item.theme === 'dark',
        );

        expect(acceptance).toBeDefined();

        const lightBefore =
            state.result.value!.themes.light.tokens[acceptance!.token].hex;
        const proposal = formatHex({ ...acceptance!.proposal!, a: 1 });

        state.acceptAdjustment(acceptance!.theme, acceptance!.token);

        expect(
            state.result.value!.themes.dark.tokens[acceptance!.token].hex,
        ).toBe(proposal);
        expect(
            state.result.value!.themes.light.tokens[acceptance!.token].hex,
        ).toBe(lightBefore);
        expect(
            state.editState.value.overrides.dark?.[acceptance!.token]?.origin,
        ).toBe('accept');
        expect(state.editNotice.value).toBeNull();
        expect(
            state.suggestions.value.some(
                (item) =>
                    item.theme === acceptance!.theme &&
                    item.token === acceptance!.token,
            ),
        ).toBe(false);
    });

    it('aceitar nos dois temas aplica cada proposta e ignora o inviavel', async () => {
        const state = useLayoutSystem();

        state.picker.setFromText('#3b82f6');
        await flush();
        state.target.value = 'aaa';
        await flush();

        // AAA: apenas o token de fundo unico tem proposta (no tema claro).
        const acceptance = state.suggestions.value.find(
            (item) => item.proposal !== null,
        );

        expect(acceptance).toBeDefined();

        const otherTheme = acceptance!.theme === 'light' ? 'dark' : 'light';

        state.acceptAdjustmentInBothThemes(acceptance!.token);

        expect(
            state.editState.value.overrides[acceptance!.theme]?.[
                acceptance!.token
            ]?.origin,
        ).toBe('accept');
        expect(
            state.editState.value.overrides[otherTheme]?.[acceptance!.token],
        ).toBeUndefined();
        expect(
            state.result.value!.themes[acceptance!.theme].tokens[
                acceptance!.token
            ].hex,
        ).toBe(formatHex({ ...acceptance!.proposal!, a: 1 }));
    });

    it('ignora aceite sem sugestao pendente', async () => {
        const state = useLayoutSystem();

        state.picker.setFromText('#3b82f6');
        await flush();

        const before = state.result.value;

        state.acceptAdjustment('light', 'primary');
        state.acceptAdjustmentInBothThemes('background');

        expect(state.result.value).toBe(before);
        expect(state.editState.value.accepted).toHaveLength(0);
    });

    it('separa o aviso de descarte do estado obsoleto', async () => {
        const state = useLayoutSystem();

        state.picker.setFromText('#3b82f6');
        await flush();
        state.applyColor('light', 'primary', colorOf('#ff0000'));
        state.picker.setFromText('#22c55e');
        await flush();

        expect(state.editNotice.value).toBe('discarded');
        expect(state.isStale.value).toBe(false);
    });
});

describe('restaurar (F3)', () => {
    it('por token remove apenas aquele token', async () => {
        const state = useLayoutSystem();

        state.picker.setFromText('#3b82f6');
        await flush();
        state.applyColor('light', 'primary', colorOf('#ff0000'));
        state.applyColor('light', 'secondary', colorOf('#00ff00'));

        state.restoreTokenEdit('light', 'primary');

        expect(state.result.value!.themes.light.tokens.primary.source).toBe(
            'seed',
        );
        expect(state.result.value!.themes.light.tokens.secondary.hex).toBe(
            '#00ff00',
        );
    });

    it('por tema limpa so o tema escolhido', async () => {
        const state = useLayoutSystem();

        state.picker.setFromText('#3b82f6');
        await flush();
        state.applyColor('light', 'primary', colorOf('#ff0000'));
        state.applyColor('dark', 'primary', colorOf('#00ff00'));

        state.restoreThemeEdits('light');

        expect(state.editState.value.overrides.light).toBeUndefined();
        expect(state.editState.value.overrides.dark?.primary?.origin).toBe(
            'edit',
        );
    });

    it('no sistema limpa os dois temas', async () => {
        const state = useLayoutSystem();

        state.picker.setFromText('#3b82f6');
        await flush();
        state.applyColor('light', 'primary', colorOf('#ff0000'));
        state.applyColor('dark', 'primary', colorOf('#00ff00'));

        state.restoreAllEdits();

        expect(state.editState.value.overrides).toEqual({});
        expect(state.editState.value.locks).toEqual({});
        expect(state.editState.value.accepted).toEqual([]);
    });
});

describe('secundaria manual (F3)', () => {
    it('persiste enquanto a seed muda ate o reset explicito', async () => {
        const state = useLayoutSystem();

        state.picker.setFromText('#3b82f6');
        await flush();
        state.secondaryText.value = '#00ff00';
        await flush();

        expect(state.secondarySource.value).toBe('manual');
        expect(state.result.value!.themes.light.tokens.secondary.hex).toBe(
            '#00ff00',
        );

        state.picker.setFromText('#ef4444');
        await flush();

        expect(state.secondarySource.value).toBe('manual');
        expect(state.result.value!.themes.light.tokens.secondary.hex).toBe(
            '#00ff00',
        );
        expect(state.editNotice.value).toBeNull();

        state.resetSecondary();
        await flush();

        expect(state.secondarySource.value).toBe('auto');
        expect(state.result.value!.themes.light.tokens.secondary.hex).not.toBe(
            '#00ff00',
        );
    });

    it('texto invalido preserva o ultimo quadro e pede correcao', async () => {
        const state = useLayoutSystem();

        state.picker.setFromText('#3b82f6');
        await flush();

        const before = state.result.value;

        state.secondaryText.value = 'nao-e-cor';
        await flush();

        expect(state.result.value).toBe(before);
        expect(state.secondaryInvalid.value).toBe(true);
        expect(state.notice.value).toBeNull();
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
