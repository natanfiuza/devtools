import { DOMWrapper, mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import { parseColor, type Rgba } from '@/lib/color';
import LayoutWorkspace from '@/tools/layout-harmonizer/LayoutWorkspace.vue';
import { useFrozenLayout } from '@/tools/layout-harmonizer/composables/useFrozenLayout';
import { generateLayoutSystem } from '@/tools/layout-harmonizer/engine';
import { emptyEditState } from '@/tools/layout-harmonizer/lib/editState';
import { buildSnapshot } from '@/tools/layout-harmonizer/lib/snapshot';
import type { LayoutStateInputsV1 } from '@/tools/layout-harmonizer/lib/state';
import { exportMessages } from './support/langMessages';

/**
 * Regeneracao do quadro congelado (ADR-169, emenda da F6).
 *
 * Abrir um sistema compativel ja oferece "Regenerar com a versao atual", sem
 * exigir trocar uma entrada; o clique pede confirmacao e so entao entrega a
 * decisao ao painel.
 */

function i18n() {
    const instance = createI18nInstance();

    applyTranslations(instance, {
        locale: 'pt_BR',
        translations: exportMessages().pt_BR,
    });

    return instance;
}

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

function mountWorkspace(options: { mode?: 'compatible' | 'readonly' } = {}) {
    const regenerate = vi.fn();
    const controller = useFrozenLayout(
        { snapshot: snapshotOf(), inputs: INPUTS },
        { mode: options.mode },
    );

    const wrapper = mount(LayoutWorkspace, {
        props: { controller, regenerate },
        global: { plugins: [i18n()] },
        attachTo: document.body,
    });

    return { controller, wrapper, regenerate };
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

describe('aviso de regeneracao no quadro congelado', () => {
    it('oferece regenerar ao abrir, sem trocar entrada', () => {
        const { wrapper } = mountWorkspace();

        expect(
            wrapper.find('[data-test="layout-regenerate-banner"]').exists(),
        ).toBe(true);
        expect(wrapper.find('[data-test="layout-regenerate"]').exists()).toBe(
            true,
        );
    });

    it('troca o texto para pendente quando a entrada muda', async () => {
        const { wrapper, controller, regenerate } = mountWorkspace();

        controller.strategy.value = 'triadic';
        await nextTick();

        expect(
            wrapper.find('[data-test="layout-pending-regeneration"]').exists(),
        ).toBe(true);
        expect(
            wrapper.find('[data-test="layout-regenerate-banner"]').exists(),
        ).toBe(false);
        expect(regenerate).not.toHaveBeenCalled();
    });

    it('readonly tambem oferece regenerar', () => {
        const { wrapper, controller } = mountWorkspace({ mode: 'readonly' });

        expect(controller.editable.value).toBe(false);
        expect(wrapper.find('[data-test="layout-regenerate"]').exists()).toBe(
            true,
        );
    });
});

describe('confirmacao antes de regenerar', () => {
    it('abre o dialogo, cancela sem regenerar e confirma uma vez', async () => {
        const { wrapper, regenerate } = mountWorkspace();

        await wrapper.get('[data-test="layout-regenerate"]').trigger('click');
        await nextTick();

        expect(regenerate).not.toHaveBeenCalled();
        expect(dialog('layout-regenerate-dialog').exists()).toBe(true);

        await dialog('layout-regenerate-cancel').trigger('click');

        expect(regenerate).not.toHaveBeenCalled();

        await wrapper.get('[data-test="layout-regenerate"]').trigger('click');
        await nextTick();
        await dialog('layout-regenerate-confirm').trigger('click');

        expect(regenerate).toHaveBeenCalledTimes(1);
    });

    it('conta as edicoes sem bloqueio que serao descartadas', async () => {
        const { wrapper, controller } = mountWorkspace();

        controller.applyColor('light', 'primary', color('#ff0000'));
        await nextTick();

        await wrapper.get('[data-test="layout-regenerate"]').trigger('click');
        await nextTick();

        expect(dialog('layout-regenerate-dialog').text()).toContain('1');
    });
});
