import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import ColorArea from '@/components/colors/ColorArea.vue';
import {
    opaqueColor,
    withCurrentAlpha,
} from '@/components/colors/lib/applyHex';
import { useColorState } from '@/composables/useColorState';
import type { Rgba } from '@/lib/color';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import Tool from '@/tools/html-color-picker/Tool.vue';

/**
 * Comportamento do estado e da area 2D (ADR-117).
 *
 * O estado e testado fora do componente: a regra de que texto invalido nao
 * derruba a ultima cor, de que o matiz sobrevive ao cinza e de que so o
 * `finalize` escreve no historico vive ali.
 */

const RED: Rgba = { r: 255, g: 0, b: 0, a: 1 };

function i18n() {
    const instance = createI18nInstance();

    applyTranslations(instance, {
        locale: 'pt-BR',
        translations: {
            tools: {
                shared: {
                    color_picker: {
                        area: { label: 'Área', hint: 'Dica' },
                    },
                },
            },
        },
    });

    return instance;
}

describe('useColorState', () => {
    it('inicia com o HEX opaco', () => {
        const state = useColorState({ initial: RED });

        expect(state.text.value).toBe('#ff0000');
        expect(state.error.value).toBeNull();
    });

    it('aplica texto valido e mantem a cor quando o texto e invalido', () => {
        const state = useColorState({ initial: RED });

        expect(state.setFromText('#00ff00')).toBe(true);
        expect(state.rgba.value.g).toBe(255);
        expect(state.text.value).toBe('#00ff00');

        expect(state.setFromText('rgb(300, 0, 0)')).toBe(false);
        expect(state.error.value).not.toBeNull();
        expect(state.rgba.value.g).toBe(255);
    });

    it('normaliza o texto e registra a cor no finalize', () => {
        const finalized: Rgba[] = [];
        const state = useColorState({
            initial: RED,
            onFinalize: (color) => finalized.push(color),
        });

        state.setFromText('#0f0');
        expect(state.finalizeText()).toBe(true);
        expect(state.text.value).toBe('#00ff00');

        state.setAlpha(0.5);
        expect(state.text.value.endsWith('80')).toBe(true);

        state.finalize();
        expect(finalized).toHaveLength(2);
        expect(finalized[0]?.g).toBe(255);
        expect(finalized[1]?.a).toBeCloseTo(0.5, 2);
    });

    it('preserva o matiz ancora ao passar pelo cinza', () => {
        const state = useColorState({ initial: RED });

        state.setFromHsv({ h: 120, s: 100, v: 100 });
        expect(state.hsv.value.h).toBeCloseTo(120, 6);

        state.setSaturationValue(0, 50);
        expect(state.hsv.value.h).toBeCloseTo(120, 6);

        state.setSaturationValue(100, 100);
        expect(state.rgba.value.g).toBe(255);
        expect(state.rgba.value.r).toBe(0);
    });

    it('prende alpha e saturacao ao intervalo', () => {
        const state = useColorState({ initial: RED });

        state.setAlpha(2);
        expect(state.rgba.value.a).toBe(1);

        state.setAlpha(-1);
        expect(state.rgba.value.a).toBe(0);

        state.setFromHsv({ h: 400, s: 200, v: -50 });
        expect(state.rgba.value.r).toBe(0);
        expect(state.rgba.value.g).toBe(0);
        expect(state.rgba.value.b).toBe(0);
    });

    it('descarta o metadado de gamut ao mudar de fonte', () => {
        const state = useColorState({ initial: RED });

        state.setFromText('oklch(70% 0.4 150)');
        expect(state.outOfGamut.value).toBe(true);
        expect(state.gamutSource.value).not.toBeNull();

        state.setColor(RED);
        expect(state.outOfGamut.value).toBe(false);
        expect(state.gamutSource.value).toBeNull();
    });
});

describe('applyHex', () => {
    it('preserva o alpha no seletor nativo', () => {
        const applied = withCurrentAlpha('#00ff00', { ...RED, a: 0.25 });

        expect(applied).not.toBeNull();
        expect(applied?.g).toBe(255);
        expect(applied?.a).toBe(0.25);
    });

    it('ignora HEX invalido', () => {
        expect(withCurrentAlpha('nope', RED)).toBeNull();
        expect(opaqueColor('nope')).toBeNull();
    });

    it('conta-gotas e imagem aplicam cor opaca', () => {
        const applied = opaqueColor('#123456');

        expect(applied).not.toBeNull();
        expect(applied?.r).toBe(18);
        expect(applied?.a).toBe(1);
    });
});

describe('Tool', () => {
    it('monta a superficie completa', () => {
        const wrapper = mount(Tool, { global: { plugins: [i18n()] } });

        expect(wrapper.find('[data-test="color-area"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="color-input"]').exists()).toBe(true);
        expect(
            wrapper.find('[data-test="native-color-input"]').exists(),
        ).toBe(true);
        expect(wrapper.find('[data-test="alpha-slider"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="channels-tabs"]').exists()).toBe(true);
        expect(
            wrapper.find('[data-test="channel-slider-r"]').exists(),
        ).toBe(true);
    });

    it('o seletor nativo mantem o alpha atual', async () => {
        localStorage.clear();

        const wrapper = mount(Tool, { global: { plugins: [i18n()] } });

        await wrapper.get('[data-test="alpha-slider"]').setValue('50');
        await wrapper.get('[data-test="native-color-input"]').setValue('#00ff00');

        const field = wrapper.get<HTMLInputElement>('[data-test="color-input"]');

        expect(field.element.value).toBe('#00ff0080');
    });
});

describe('ColorArea', () => {
    it('anda com o teclado e finaliza', async () => {
        const wrapper = mount(ColorArea, {
            props: { hsv: { h: 200, s: 50, v: 50 } },
            global: { plugins: [i18n()] },
        });

        const area = wrapper.get('[data-test="color-area"]');

        await area.trigger('keydown', { key: 'ArrowRight' });
        await area.trigger('keydown', { key: 'ArrowUp', shiftKey: true });

        expect(wrapper.emitted('change')).toEqual([
            [51, 50],
            [50, 60],
        ]);
        expect(wrapper.emitted('finalize')).toHaveLength(2);
    });

    it('nao passa dos limites', async () => {
        const wrapper = mount(ColorArea, {
            props: { hsv: { h: 0, s: 0, v: 0 } },
            global: { plugins: [i18n()] },
        });

        await wrapper
            .get('[data-test="color-area"]')
            .trigger('keydown', { key: 'ArrowLeft' });
        await wrapper
            .get('[data-test="color-area"]')
            .trigger('keydown', { key: 'ArrowDown', shiftKey: true });

        expect(wrapper.emitted('change')).toEqual([
            [0, 0],
            [0, 0],
        ]);
    });
});
