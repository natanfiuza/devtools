import { DOMWrapper, flushPromises, mount } from '@vue/test-utils';
import { defineComponent, h, nextTick } from 'vue';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import { parseColor } from '@/lib/color';
import {
    generateLayoutSystem,
    type LayoutToken,
} from '@/tools/layout-harmonizer/engine';
import TokenCell from '@/tools/layout-harmonizer/components/TokenCell.vue';
import { exportMessages } from './support/langMessages';

/**
 * Editor de token (F3): popover com copia local, aplicar/cancelar, alpha
 * ignorado, bloqueio imediato e restaurar desabilitado sem edicao.
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

function mountCell(
    overrides: {
        locked?: boolean;
        edited?: boolean;
        suggested?: boolean;
        editable?: boolean;
    } = {},
) {
    const props = {
        token: token(),
        purpose: 'Finalidade do token',
        checks: [],
        copy: vi.fn(async () => true),
        applyColor: vi.fn(),
        toggleLock: vi.fn(),
        restoreColor: vi.fn(),
        ...overrides,
    };

    return {
        props,
        wrapper: mount(TokenCell, { props, global: { plugins: [i18n()] } }),
    };
}

function editorElement(): Element | null {
    return document.body.querySelector('[data-test="token-editor"]');
}

function editor(target: string): DOMWrapper<Element> {
    const element = document.body.querySelector(`[data-test="${target}"]`);

    if (!element) {
        throw new Error(`elemento ausente: ${target}`);
    }

    return new DOMWrapper(element);
}

async function openEditor(wrapper: ReturnType<typeof mountCell>['wrapper']) {
    await wrapper.get('[data-test="token-edit"]').trigger('click');
    await flushPromises();
}

afterEach(() => {
    document.body.innerHTML = '';
});

describe('abrir e fechar', () => {
    it('abre o popover com o token atual', async () => {
        const { wrapper } = mountCell();

        await openEditor(wrapper);

        expect(editorElement()).not.toBeNull();
        expect(editor('token-editor-hex').text()).toBe(token().hex);
        expect(editor('token-color-input').element).toBeInstanceOf(
            HTMLInputElement,
        );
        expect(editor('token-editor-apply').attributes('disabled')).toBe(
            undefined,
        );
    });

    it('cancelar fecha sem aplicar', async () => {
        const { wrapper, props } = mountCell();

        await openEditor(wrapper);
        await editor('token-editor-cancel').trigger('click');
        await flushPromises();

        expect(props.applyColor).not.toHaveBeenCalled();
        expect(editorElement()).toBeNull();
    });
});

describe('aplicar', () => {
    it('envia a cor digitada opaca e fecha', async () => {
        const { wrapper, props } = mountCell();

        await openEditor(wrapper);
        await editor('token-color-input').setValue('#ff0000');
        await nextTick();
        await editor('token-editor-apply').trigger('click');
        await flushPromises();

        expect(props.applyColor).toHaveBeenCalledWith({
            r: 255,
            g: 0,
            b: 0,
            a: 1,
        });
        expect(editorElement()).toBeNull();
    });

    it('texto invalido desabilita aplicar e mantem o popover', async () => {
        const { wrapper, props } = mountCell();

        await openEditor(wrapper);
        await editor('token-color-input').setValue('nao-e-cor');
        await nextTick();

        expect(editor('token-editor-apply').attributes('disabled')).toBe('');

        await editor('token-editor-apply').trigger('click');

        expect(props.applyColor).not.toHaveBeenCalled();
        expect(editorElement()).not.toBeNull();
    });

    it('declara que o alpha e ignorado e aplica opaco', async () => {
        const { wrapper, props } = mountCell();

        await openEditor(wrapper);
        await editor('token-color-input').setValue('rgba(255, 0, 0, 0.4)');
        await nextTick();

        expect(editor('token-editor-alpha').text()).toContain('alpha');

        await editor('token-editor-apply').trigger('click');
        await flushPromises();

        expect(props.applyColor).toHaveBeenCalledWith({
            r: 255,
            g: 0,
            b: 0,
            a: 1,
        });
    });
});

describe('bloqueio e restaurar', () => {
    it('reflete o bloqueio e delega a alternancia', async () => {
        const { wrapper, props } = mountCell({ locked: true });

        await openEditor(wrapper);

        expect(editor('token-editor-lock').attributes('aria-pressed')).toBe(
            'true',
        );

        await editor('token-editor-lock').trigger('click');

        expect(props.toggleLock).toHaveBeenCalledTimes(1);
    });

    it('desabilita restaurar quando nao ha edicao', async () => {
        const { wrapper } = mountCell();

        await openEditor(wrapper);

        expect(editor('token-editor-restore').attributes('disabled')).toBe('');
    });

    it('pede confirmacao antes de restaurar e so entao fecha', async () => {
        const { wrapper, props } = mountCell({ edited: true });

        await openEditor(wrapper);

        expect(editor('token-editor-restore').attributes('disabled')).toBe(
            undefined,
        );

        await editor('token-editor-restore').trigger('click');
        await flushPromises();

        // Nada foi restaurado ainda: o override e o bloqueio so saem no confirm.
        expect(props.restoreColor).not.toHaveBeenCalled();
        expect(editor('token-restore-confirm').exists()).toBe(true);

        await editor('token-restore-cancel').trigger('click');
        await flushPromises();

        expect(props.restoreColor).not.toHaveBeenCalled();
        // O bloco de confirmacao some junto com o cancelamento.
        expect(
            document.body.querySelector('[data-test="token-restore-confirm"]'),
        ).toBeNull();
        expect(editorElement()).not.toBeNull();

        await editor('token-editor-restore').trigger('click');
        await flushPromises();
        await editor('token-restore-confirm-button').trigger('click');
        await flushPromises();

        expect(props.restoreColor).toHaveBeenCalledTimes(1);
        expect(editorElement()).toBeNull();
    });

    it('usa um id unico quando dois editores estao abertos', async () => {
        // `useId` e por aplicacao Vue, entao as duas celulas vivem no mesmo
        // app - e assim que dois editores ficam abertos na tela de verdade.
        const shared = {
            token: token(),
            purpose: 'Finalidade do token',
            checks: [],
            copy: vi.fn(async () => true),
        };

        const parent = defineComponent({
            render: () =>
                h('div', [h(TokenCell, shared), h(TokenCell, shared)]),
        });

        const wrapper = mount(parent, {
            global: { plugins: [i18n()] },
            attachTo: document.body,
        });

        const buttons = wrapper.findAll('[data-test="token-edit"]');

        expect(buttons).toHaveLength(2);

        await buttons[0]!.trigger('click');
        await buttons[1]!.trigger('click');
        await flushPromises();

        const ids = Array.from(
            document.body.querySelectorAll<HTMLInputElement>(
                '[data-test="token-color-input"]',
            ),
        ).map((input) => input.id);

        expect(ids).toHaveLength(2);
        expect(new Set(ids).size).toBe(2);
        expect(ids[0]).toContain('layout-token-color-value-');
    });
});

describe('controles da celula', () => {
    it('copia o estado do bloqueio no cabecalho', async () => {
        const { wrapper, props } = mountCell({ locked: true });
        const lock = wrapper.get('[data-test="token-lock"]');

        expect(lock.attributes('aria-pressed')).toBe('true');

        await lock.trigger('click');

        expect(props.toggleLock).toHaveBeenCalledTimes(1);
    });

    it('desabilita editar e bloquear quando a entrada e invalida', () => {
        const { wrapper } = mountCell({ editable: false });

        expect(
            wrapper.get('[data-test="token-edit"]').attributes('disabled'),
        ).toBe('');
        expect(
            wrapper.get('[data-test="token-lock"]').attributes('disabled'),
        ).toBe('');
    });

    it('mostra o selo quando ha sugestao aplicavel', () => {
        const { wrapper } = mountCell({ suggested: true });

        expect(wrapper.find('[data-test="token-suggested"]').exists()).toBe(
            true,
        );
    });
});
