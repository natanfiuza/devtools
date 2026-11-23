import { flushPromises, mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import { formatColor, parseColor } from '@/lib/color';
import {
    generateLayoutSystem,
    type LayoutPairCheck,
    type LayoutToken,
} from '@/tools/layout-harmonizer/engine';
import TokenCell from '@/tools/layout-harmonizer/components/TokenCell.vue';
import { formatRatio } from '@/tools/layout-harmonizer/lib/boardView';
import { exportMessages } from './support/langMessages';

/**
 * Uma linha do quadro: valores reais, selos e os dois botoes de copia. O
 * swatch e demonstracao (aria-hidden); todo texto fica na superficie da UI.
 */

function i18n() {
    const instance = createI18nInstance();

    applyTranslations(instance, {
        locale: 'pt_BR',
        translations: exportMessages().pt_BR,
    });

    return instance;
}

function token(): LayoutToken {
    const parsed = parseColor('#3b82f6');

    if (!parsed.ok) {
        throw new Error('cor de teste invalida');
    }

    return generateLayoutSystem({
        seed: parsed.color.rgba,
        strategy: 'analogous',
    }).themes.light.tokens['on-primary'];
}

function check(
    background: LayoutPairCheck['background'],
    ratio: number,
): LayoutPairCheck {
    return {
        foreground: 'on-primary',
        background,
        verdict: {
            ratio,
            aaNormal: ratio >= 4.5,
            aaaNormal: ratio >= 7,
            aaLarge: ratio >= 3,
            aaaLarge: ratio >= 4.5,
        },
    };
}

function mountCell(
    props: {
        checks?: LayoutPairCheck[];
        token?: LayoutToken;
        copy?: (value: string, success?: string) => Promise<boolean>;
    } = {},
) {
    return mount(TokenCell, {
        props: {
            token: props.token ?? token(),
            purpose: 'Finalidade do token',
            checks: props.checks ?? [],
            copy: props.copy ?? vi.fn(async () => true),
        },
        global: { plugins: [i18n()] },
    });
}

describe('conteudo da celula', () => {
    it('mostra a chave crua, a finalidade, o HEX e o OKLCH', () => {
        const value = token();
        const wrapper = mountCell({ token: value });

        expect(wrapper.text()).toContain('on-primary');
        expect(wrapper.text()).toContain('Finalidade do token');
        expect(wrapper.get('[data-test="token-hex"]').text()).toBe(value.hex);
        expect(wrapper.get('[data-test="token-oklch"]').text()).toBe(
            formatColor(value.rgba, 'oklch'),
        );
    });

    it('mostra a proveniencia traduzida', () => {
        const wrapper = mountCell();

        expect(wrapper.text()).toContain('Por regra');
    });

    it('marca croma reduzido com a explicacao no titulo', () => {
        const wrapper = mountCell({
            token: { ...token(), clipped: true, source: 'feedback' },
        });
        const badge = wrapper
            .findAll('span')
            .find((span) => span.text() === 'Croma reduzido');

        expect(wrapper.text()).toContain('Feedback');
        expect(badge?.attributes('title')).toContain('sRGB');
        // A explicacao tambem existe como texto acessivel, sem depender do
        // titulo (hover).
        expect(wrapper.text()).toContain('sRGB');
    });

    it('mantem o swatch fora da arvore de acessibilidade e sem texto', () => {
        const swatch = mountCell().get('[data-test="token-swatch"]');

        expect(swatch.attributes('aria-hidden')).toBe('true');
        expect(swatch.text()).toBe('');
    });
});

describe('selos de contraste', () => {
    it('mostra um selo AA aprovado com a razao no idioma', () => {
        const wrapper = mountCell({ checks: [check('primary', 4.826)] });
        const badge = wrapper.get('[data-test="token-cell"] ul li');

        expect(badge.text()).toContain('AA');
        expect(badge.text()).toContain(formatRatio(4.826, 'pt_BR'));
        expect(badge.attributes('title')).toBe('Contra primary');
        // O fundo medido tambem vem em texto acessivel, nao so no titulo.
        expect(badge.text()).toContain('Contra primary');
    });

    it('mostra um selo de falha quando o par nao atinge AA', () => {
        const wrapper = mountCell({ checks: [check('primary-hover', 3.1)] });
        const badge = wrapper.get('[data-test="token-cell"] ul li');

        expect(badge.text()).toContain('Falha AA');
        expect(badge.text()).toContain('3,1');
    });

    it('nao cria lista de selos quando o token nao tem par medido', () => {
        const wrapper = mountCell();

        expect(wrapper.find('[data-test="token-cell"] ul').exists()).toBe(
            false,
        );
    });
});

describe('copiar valores', () => {
    it('copia o HEX com o recado do token e confirma no botao', async () => {
        const copy = vi.fn(async () => true);
        const value = token();
        const wrapper = mountCell({ token: value, copy });

        await wrapper.get('[data-test="copy-hex"]').trigger('click');
        await flushPromises();

        expect(copy).toHaveBeenCalledWith(
            value.hex,
            expect.stringContaining('on-primary'),
        );
        expect(
            wrapper
                .get('[data-test="copy-hex"]')
                .find('[data-test="copied-icon"]')
                .exists(),
        ).toBe(true);
    });

    it('copia o OKLCH em outro botao', async () => {
        const copy = vi.fn(async () => true);
        const value = token();
        const wrapper = mountCell({ token: value, copy });

        await wrapper.get('[data-test="copy-oklch"]').trigger('click');
        await flushPromises();

        expect(copy).toHaveBeenCalledWith(
            formatColor(value.rgba, 'oklch'),
            expect.stringContaining('on-primary'),
        );
    });

    it('so confirma o botao quando a copia deu certo', async () => {
        const copy = vi.fn(async () => false);
        const wrapper = mountCell({ copy });

        await wrapper.get('[data-test="copy-hex"]').trigger('click');
        await flushPromises();

        expect(
            wrapper
                .get('[data-test="copy-hex"]')
                .find('[data-test="copied-icon"]')
                .exists(),
        ).toBe(false);
    });
});
