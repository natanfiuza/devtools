import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { formatHex, type Rgba } from '@/lib/color';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import SuggestionsPanel from '@/tools/layout-harmonizer/components/SuggestionsPanel.vue';
import type {
    LayoutTheme,
    LayoutTokenId,
} from '@/tools/layout-harmonizer/engine';
import { formatRatio } from '@/tools/layout-harmonizer/lib/boardView';
import type { LayoutEditTarget } from '@/tools/layout-harmonizer/lib/editState';
import type { LayoutSuggestion } from '@/tools/layout-harmonizer/lib/suggestions';
import { exportMessages } from './support/langMessages';

/**
 * Painel de sugestoes (F3): meta AA/AAA, proposta com razoes, aceite por tema
 * e nos dois temas, inviavel sem acao e nada aplicado automaticamente.
 */

function i18n() {
    const instance = createI18nInstance();

    applyTranslations(instance, {
        locale: 'pt_BR',
        translations: exportMessages().pt_BR,
    });

    return instance;
}

const RED: Rgba = { r: 255, g: 0, b: 0, a: 1 };

function suggestion(
    overrides: Partial<LayoutSuggestion> = {},
): LayoutSuggestion {
    return {
        theme: 'light',
        token: 'on-primary',
        target: 'aa',
        current: { r: 18, g: 18, b: 18, a: 1 },
        proposal: RED,
        deltaLightness: 0.035,
        ratios: [
            { background: 'primary', current: 5.11, projected: 4.62 },
            { background: 'primary-hover', current: 4.16, projected: 4.58 },
        ],
        ...overrides,
    };
}

function inviable(overrides: Partial<LayoutSuggestion> = {}): LayoutSuggestion {
    return suggestion({
        proposal: null,
        deltaLightness: 0,
        ratios: [
            { background: 'primary', current: 5.11, projected: 5.11 },
            { background: 'primary-hover', current: 4.16, projected: 4.16 },
        ],
        ...overrides,
    });
}

function mountPanel(
    suggestions: LayoutSuggestion[],
    overrides: {
        target?: LayoutEditTarget;
        accept?: (theme: LayoutTheme, token: LayoutTokenId) => void;
        acceptBoth?: (token: LayoutTokenId) => void;
    } = {},
) {
    const accept = overrides.accept ?? vi.fn();
    const acceptBoth = overrides.acceptBoth ?? vi.fn();

    const wrapper = mount(SuggestionsPanel, {
        props: {
            suggestions,
            accept,
            acceptBoth,
            target: overrides.target ?? 'aa',
            'onUpdate:target': (value: LayoutEditTarget) => {
                void wrapper.setProps({ target: value });
            },
        },
        global: { plugins: [i18n()] },
    });

    return wrapper;
}

describe('meta', () => {
    it('marca a meta ativa e troca ao clicar', async () => {
        const wrapper = mountPanel([suggestion()]);
        const aa = wrapper.get('[data-test="suggestion-target-aa"]');
        const aaa = wrapper.get('[data-test="suggestion-target-aaa"]');

        expect(aa.attributes('aria-pressed')).toBe('true');
        expect(aaa.attributes('aria-pressed')).toBe('false');

        await aaa.trigger('click');

        expect(aaa.attributes('aria-pressed')).toBe('true');
    });

    it('mostra o vazio quando nada falha', () => {
        const wrapper = mountPanel([]);

        expect(wrapper.find('[data-test="suggestions-none"]').exists()).toBe(
            true,
        );
        expect(wrapper.findAll('[data-test="suggestion-row"]')).toHaveLength(0);
    });
});

describe('linha com proposta', () => {
    it('mostra tema, token, proposta, delta e razoes', () => {
        const wrapper = mountPanel([suggestion()]);
        const row = wrapper.get('[data-test="suggestion-row"]');

        expect(row.attributes('data-theme')).toBe('light');
        expect(row.attributes('data-token')).toBe('on-primary');
        expect(row.text()).toContain('on-primary');
        expect(wrapper.get('[data-test="suggestion-hex"]').text()).toBe(
            formatHex(RED),
        );
        expect(wrapper.get('[data-test="suggestion-delta"]').text()).toContain(
            '0,035',
        );
        expect(
            wrapper.get('[data-test="suggestion-ratio-primary"]').text(),
        ).toContain(formatRatio(4.62, 'pt_BR'));
        expect(
            wrapper.get('[data-test="suggestion-ratio-primary-hover"]').text(),
        ).toContain(formatRatio(4.58, 'pt_BR'));
        expect(
            wrapper.find('[data-test="suggestion-inviable-badge"]').exists(),
        ).toBe(false);
    });

    it('aceita no tema da linha com um clique explicito', async () => {
        const accept = vi.fn();
        const wrapper = mountPanel([suggestion()], { accept });

        await wrapper.get('[data-test="suggestion-accept"]').trigger('click');

        expect(accept).toHaveBeenCalledWith('light', 'on-primary');
    });
});

describe('linha inviavel', () => {
    it('avisa sem oferecer aceite', () => {
        const wrapper = mountPanel([inviable()]);

        expect(
            wrapper.get('[data-test="suggestion-inviable-badge"]').text(),
        ).toContain('Inviável');
        expect(
            wrapper.get('[data-test="suggestion-inviable"]').text(),
        ).toContain('AA');
        expect(wrapper.find('[data-test="suggestion-accept"]').exists()).toBe(
            false,
        );
        expect(wrapper.find('[data-test="suggestion-hex"]').exists()).toBe(
            false,
        );
    });
});

describe('aceitar nos dois temas', () => {
    function bothThemes(): LayoutSuggestion[] {
        return [suggestion({ theme: 'light' }), suggestion({ theme: 'dark' })];
    }

    it('aparece quando o mesmo token tem proposta nos dois temas', async () => {
        const acceptBoth = vi.fn();
        const wrapper = mountPanel(bothThemes(), { acceptBoth });
        const buttons = wrapper.findAll('[data-test="suggestion-accept-both"]');

        expect(buttons).toHaveLength(2);

        await buttons[0]!.trigger('click');

        expect(acceptBoth).toHaveBeenCalledWith('on-primary');
    });

    it('some quando o outro tema e inviavel', () => {
        const wrapper = mountPanel([
            suggestion({ theme: 'light' }),
            inviable({ theme: 'dark' as LayoutTheme }),
        ]);

        expect(
            wrapper.find('[data-test="suggestion-accept-both"]').exists(),
        ).toBe(false);
    });
});
