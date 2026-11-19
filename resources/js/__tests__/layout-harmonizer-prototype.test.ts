import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { parseColor } from '@/lib/color';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import LayoutSystemPrototypePanel from '@/tools/layout-harmonizer/components/LayoutSystemPrototypePanel.vue';
import {
    LAYOUT_ALGORITHM_VERSION,
    LAYOUT_SCHEMA_VERSION,
    generateLayoutSystem,
} from '@/tools/layout-harmonizer/engine';
import { emptyEditState } from '@/tools/layout-harmonizer/lib/editState';
import {
    LAYOUT_ROLE_MAP,
    layoutPrototypeColors,
} from '@/tools/layout-harmonizer/lib/layoutRoleMap';
import { buildSnapshot } from '@/tools/layout-harmonizer/lib/snapshot';
import {
    boardsFromSnapshot,
    buildLayoutState,
    type LayoutSystemStateV1,
} from '@/tools/layout-harmonizer/lib/state';
import { LAYOUT_SUGGESTIONS_VERSION } from '@/tools/layout-harmonizer/lib/suggestions';
import { exportMessages } from './support/langMessages';

/**
 * Protótipo do sistema de layout (F4, ADR-173).
 *
 * O mapa de papéis é fixo e lê os tokens gravados no snapshot; o painel só
 * troca template e tema. Não há editor de papéis nem exportação: o quadro
 * continua sendo o único lugar de editar um token.
 */

// O motor gera 66 tokens por fixture; sob a suite inteira em paralelo o
// seletor de 5 s padrao aperta. O limite maior so cobre a montagem pesada.
vi.setConfig({ testTimeout: 20_000 });

function i18n() {
    const instance = createI18nInstance();

    applyTranslations(instance, {
        locale: 'pt_BR',
        translations: exportMessages().pt_BR,
    });

    return instance;
}

function stateFixture(): LayoutSystemStateV1 {
    const parsed = parseColor('#3b82f6');

    if (!parsed.ok) {
        throw new Error('cor de teste invalida');
    }

    const snapshot = buildSnapshot(
        generateLayoutSystem({
            seed: parsed.color.rgba,
            strategy: 'analogous',
            background: null,
            secondary: null,
            overrides: undefined,
        }),
        emptyEditState(),
        'aa',
    );

    snapshot.schemaVersion = LAYOUT_SCHEMA_VERSION;
    snapshot.algorithmVersion = LAYOUT_ALGORITHM_VERSION;
    snapshot.suggestionsVersion = LAYOUT_SUGGESTIONS_VERSION;

    return buildLayoutState(
        {
            seedText: '#3b82f6',
            backgroundText: '',
            secondaryText: '',
            strategy: 'analogous',
        },
        null,
        snapshot,
    );
}

function mountPanel(state: LayoutSystemStateV1 = stateFixture()) {
    return mount(LayoutSystemPrototypePanel, {
        props: { state },
        global: { plugins: [i18n()] },
    });
}

describe('mapa fixo de papéis', () => {
    it('cobre os oito papéis do protótipo com tokens do layout', () => {
        const boards = boardsFromSnapshot(stateFixture().snapshot);

        expect(Object.keys(LAYOUT_ROLE_MAP).sort()).toEqual([
            'accent',
            'background',
            'border',
            'primary',
            'secondary',
            'surface',
            'text',
            'text_muted',
        ]);

        for (const token of Object.values(LAYOUT_ROLE_MAP)) {
            expect(boards.light.tokens[token]).toBeDefined();
            expect(boards.dark.tokens[token]).toBeDefined();
        }
    });

    it('entrega o HEX gravado em cada tema', () => {
        const boards = boardsFromSnapshot(stateFixture().snapshot);
        const light = layoutPrototypeColors(boards.light);
        const dark = layoutPrototypeColors(boards.dark);

        expect(light.primary).toBe(boards.light.tokens.primary.hex);
        expect(light.text).toBe(boards.light.tokens['on-background'].hex);
        expect(light.text_muted).toBe(
            boards.light.tokens['on-surface-variant'].hex,
        );
        expect(light.border).toBe(boards.light.tokens.hairline.hex);
        expect(dark.primary).toBe(boards.dark.tokens.primary.hex);
        expect(dark.background).toBe(boards.dark.tokens.background.hex);
    });
});

describe('painel de protótipo do sistema', () => {
    it('abre no site claro com o quadro gravado', () => {
        const state = stateFixture();
        const boards = boardsFromSnapshot(state.snapshot);
        const wrapper = mountPanel(state);

        expect(
            wrapper.find('[data-test="layout-system-prototype"]').exists(),
        ).toBe(true);
        expect(
            wrapper
                .find('[data-test="layout-prototype-template-site"]')
                .attributes('aria-pressed'),
        ).toBe('true');
        expect(
            wrapper
                .find('[data-test="layout-prototype-theme-light"]')
                .attributes('aria-pressed'),
        ).toBe('true');

        const svg = wrapper.find('[data-test="prototype-preview"] svg');

        expect(svg.attributes('width')).toBe('1200');
        // A primária é a mesma nos dois temas; o fundo é o que muda.
        expect(svg.html()).toContain(boards.light.tokens.background.hex);
        expect(svg.html()).not.toContain(boards.dark.tokens.background.hex);

        // O rótulo acessível é o mesmo texto que a string traduz.
        expect(
            wrapper.find('[data-test="prototype-preview"]').html(),
        ).toContain('Prévia do protótipo: Site, Claro');

        wrapper.unmount();
    });

    it('troca o tema sem perder o tamanho do template', async () => {
        const state = stateFixture();
        const boards = boardsFromSnapshot(state.snapshot);
        const wrapper = mountPanel(state);

        await wrapper
            .find('[data-test="layout-prototype-theme-dark"]')
            .trigger('click');

        const svg = wrapper.find('[data-test="prototype-preview"] svg');

        expect(svg.attributes('width')).toBe('1200');
        expect(svg.html()).toContain(boards.dark.tokens.background.hex);
        expect(svg.html()).not.toContain(boards.light.tokens.background.hex);
        expect(
            wrapper.find('[data-test="prototype-preview"]').html(),
        ).toContain('Prévia do protótipo: Site, Escuro');

        wrapper.unmount();
    });

    it('troca para o celular mantendo o tema escolhido', async () => {
        const state = stateFixture();
        const boards = boardsFromSnapshot(state.snapshot);
        const wrapper = mountPanel(state);

        await wrapper
            .find('[data-test="layout-prototype-theme-dark"]')
            .trigger('click');
        await wrapper
            .find('[data-test="layout-prototype-template-mobile"]')
            .trigger('click');

        const svg = wrapper.find('[data-test="prototype-preview"] svg');

        expect(svg.attributes('width')).toBe('390');
        expect(svg.attributes('height')).toBe('844');
        expect(svg.html()).toContain(boards.dark.tokens.background.hex);
        expect(
            wrapper.find('[data-test="prototype-preview"]').html(),
        ).toContain('Prévia do protótipo: Celular, Escuro');

        wrapper.unmount();
    });

    it('não oferece editor de papéis nem exportação', () => {
        const wrapper = mountPanel();

        expect(wrapper.find('[data-test="role-editor"]').exists()).toBe(false);
        expect(
            wrapper.find('[data-test="prototype-export-svg"]').exists(),
        ).toBe(false);
        expect(wrapper.find('[data-test="prototype-copy-svg"]').exists()).toBe(
            false,
        );

        wrapper.unmount();
    });
});
