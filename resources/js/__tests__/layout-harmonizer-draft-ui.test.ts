import { DOMWrapper, flushPromises, mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import { parseColor } from '@/lib/color';
import { Dialog } from '@/components/ui/dialog';
import ImportDraftDialog from '@/tools/layout-harmonizer/components/ImportDraftDialog.vue';
import LayoutHarmonizerPanel from '@/tools/layout-harmonizer/LayoutHarmonizerPanel.vue';
import {
    LAYOUT_DRAFT_KEY,
    browserLocalStorage,
    clearLayoutDraft,
    writeLayoutDraft,
} from '@/tools/layout-harmonizer/lib/draft';
import { generateLayoutSystem } from '@/tools/layout-harmonizer/engine';
import { emptyEditState } from '@/tools/layout-harmonizer/lib/editState';
import { buildSnapshot } from '@/tools/layout-harmonizer/lib/snapshot';
import {
    buildLayoutState,
    type LayoutSystemStateV1,
} from '@/tools/layout-harmonizer/lib/state';
import { exportMessages } from './support/langMessages';

/**
 * Rascunho no navegador (ADR-161, emenda da F6).
 *
 * O dialogo de importacao so apaga o rascunho pelo botao explicito; e o
 * anonimo tem uma acao para descartar o rascunho salvo, que limpa o quadro
 * junto para nao gravar o mesmo estado de novo.
 */

const { routerMock, pageState } = vi.hoisted(() => ({
    routerMock: {
        get: vi.fn(),
        post: vi.fn(),
        patch: vi.fn(),
        delete: vi.fn(),
        on: vi.fn(() => vi.fn()),
        visit: vi.fn(),
    },
    pageState: {
        props: {
            locale: 'pt_BR',
            localeSlug: 'pt-br',
            locales: [
                {
                    code: 'en',
                    slug: 'en',
                    html: 'en',
                    dir: 'ltr',
                    name: 'English',
                },
                {
                    code: 'pt_BR',
                    slug: 'pt-br',
                    html: 'pt-BR',
                    dir: 'ltr',
                    name: 'Português (Brasil)',
                },
            ],
            auth: { user: null },
        },
        url: '/pt-br/tools/colors/layout-harmonizer',
    },
}));

vi.mock('@inertiajs/vue3', () => ({
    router: routerMock,
    usePage: () => pageState,
}));

vi.mock('@/lib/download', () => ({
    downloadText: vi.fn(),
    downloadBlob: vi.fn(),
}));

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
        }),
        emptyEditState(),
        'aa',
    );

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

function dialog(target: string): DOMWrapper<Element> {
    const element = document.body.querySelector(`[data-test="${target}"]`);

    if (!element) {
        throw new Error(`elemento ausente: ${target}`);
    }

    return new DOMWrapper(element);
}

afterEach(() => {
    document.body.innerHTML = '';
});

describe('dialogo de importacao', () => {
    async function mountDialog() {
        const wrapper = mount(ImportDraftDialog, {
            props: { open: true, supported: true },
            global: { plugins: [i18n()] },
            attachTo: document.body,
        });

        // O conteudo do Dialog vai por teleport e so entra no DOM no proximo
        // tick.
        await nextTick();

        return wrapper;
    }

    it('fechar pelo dialogo nao descarta o rascunho', async () => {
        const wrapper = await mountDialog();

        wrapper.findComponent(Dialog).vm.$emit('update:open', false);
        await nextTick();

        expect(wrapper.emitted('discard')).toBeUndefined();
    });

    it('o botao explicito descarta', async () => {
        const wrapper = await mountDialog();

        await dialog('layout-draft-discard').trigger('click');

        expect(wrapper.emitted('discard')).toHaveLength(1);
    });

    it('importar nao descarta', async () => {
        const wrapper = await mountDialog();

        await dialog('layout-draft-import').trigger('click');

        expect(wrapper.emitted('import')).toHaveLength(1);
        expect(wrapper.emitted('discard')).toBeUndefined();
    });
});

describe('descarte do rascunho anonimo', () => {
    beforeEach(() => {
        clearLayoutDraft();
        routerMock.on.mockClear();
    });

    it('oferece descartar e limpa o quadro e o storage', async () => {
        const storage = browserLocalStorage();
        const state = stateFixture();

        expect(storage).not.toBeNull();
        writeLayoutDraft({ state, originPaletteId: null }, storage);

        const wrapper = mount(LayoutHarmonizerPanel, {
            props: {
                state,
                seedText: null,
                authenticated: false,
                limits: {
                    nameMaxLength: 80,
                    descriptionMaxLength: 500,
                    tagsPerSystem: 10,
                    tagNameMaxLength: 30,
                },
            },
            global: { plugins: [i18n()] },
            attachTo: document.body,
        });

        expect(
            wrapper.find('[data-test="layout-draft-discard-stored"]').exists(),
        ).toBe(true);

        await wrapper
            .get('[data-test="layout-draft-discard-stored"]')
            .trigger('click');
        await nextTick();
        await dialog('layout-draft-discard-confirm').trigger('click');
        await flushPromises();

        expect(storage?.getItem(LAYOUT_DRAFT_KEY)).toBeNull();
        expect(
            wrapper.find('[data-test="layout-draft-discard-stored"]').exists(),
        ).toBe(false);
        expect(wrapper.find('[data-test="layout-empty"]').exists()).toBe(true);
    });
});
