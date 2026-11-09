import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import ChannelSliders from '@/components/colors/ChannelSliders.vue';
import { useColorState } from '@/composables/useColorState';
import type { Rgba } from '@/lib/color';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';

/**
 * Sliders de canal RGB/HSL e o contrato de escrita que eles usam.
 *
 * O componente so emite; quem prende ao intervalo e preserva alpha/matiz e o
 * estado (`useColorState`), entao as duas metades sao testadas juntas.
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
                        channels: {
                            title: 'Canais',
                            rgb: 'RGB',
                            hsl: 'HSL',
                            red: 'Vermelho',
                            green: 'Verde',
                            blue: 'Azul',
                            hue: 'Matiz',
                            saturation: 'Saturação',
                            lightness: 'Luminosidade',
                        },
                    },
                },
            },
        },
    });

    return instance;
}

describe('useColorState - canais', () => {
    it('prende o canal RGB ao intervalo e sincroniza o texto', () => {
        const state = useColorState({ initial: RED });

        state.setRgbChannel('r', 300);
        expect(state.rgba.value.r).toBe(255);

        state.setRgbChannel('g', -20);
        expect(state.rgba.value.g).toBe(0);
        expect(state.text.value).toBe('#ff0000');
    });

    it('mantem o alpha ao mexer no RGB', () => {
        const state = useColorState({ initial: { ...RED, a: 0.4 } });

        state.setRgbChannel('g', 128);

        expect(state.rgba.value.a).toBeCloseTo(0.4, 6);
        expect(state.text.value.endsWith('66')).toBe(true);
    });

    it('reconstroi a cor com s e l atuais ao mexer no matiz', () => {
        const state = useColorState({ initial: RED });

        state.setHslChannel('h', 120);

        expect(state.rgba.value.r).toBe(0);
        expect(state.rgba.value.g).toBe(255);
        expect(state.rgba.value.b).toBe(0);
        expect(state.hsl.value.h).toBeCloseTo(120, 6);
    });

    it('normaliza o matiz 360 para 0', () => {
        const state = useColorState({ initial: RED });

        state.setHslChannel('h', 360);

        expect(state.hsl.value.h).toBeCloseTo(0, 6);
        expect(state.rgba.value.r).toBe(255);
    });

    it('hsl usa o matiz ancora no cinza', () => {
        const state = useColorState({ initial: RED });

        state.setHue(200);
        state.setSaturationValue(0, 50);

        expect(state.hsl.value.h).toBeCloseTo(200, 6);
        expect(state.hsl.value.s).toBeCloseTo(0, 6);

        // O caminho passa por sRGB de 8 bits, entao a saturacao volta
        // quantizada - perto de 60, nao exatamente 60.
        state.setHslChannel('s', 60);
        expect(state.hsl.value.h).toBeCloseTo(200, 0);
        expect(state.hsl.value.s).toBeCloseTo(60, 0);

        state.setHslChannel('s', 0);
        expect(state.hsl.value.h).toBeCloseTo(200, 6);
    });

    it('preto e branco nao apagam o matiz ancora', () => {
        const state = useColorState({ initial: RED });

        state.setHue(120);

        state.setHslChannel('l', 0);
        expect(state.rgba.value).toMatchObject({ r: 0, g: 0, b: 0 });
        expect(state.hsl.value.h).toBeCloseTo(120, 6);

        state.setHslChannel('l', 100);
        expect(state.rgba.value).toMatchObject({ r: 255, g: 255, b: 255 });
        expect(state.hsl.value.h).toBeCloseTo(120, 6);
    });

    it('prende saturacao e luz ao intervalo', () => {
        const state = useColorState({ initial: RED });

        state.setHslChannel('s', 250);
        expect(state.hsl.value.s).toBeCloseTo(100, 6);

        state.setHslChannel('l', -50);
        expect(state.rgba.value).toMatchObject({ r: 0, g: 0, b: 0 });
    });
});

describe('ChannelSliders', () => {
    function mountSliders(overrides: Record<string, unknown> = {}) {
        return mount(ChannelSliders, {
            props: {
                rgba: RED,
                hsl: { h: 200, s: 50, l: 40 },
                ...overrides,
            },
            global: { plugins: [i18n()] },
        });
    }

    it('mostra tres canais por aba', async () => {
        const wrapper = mountSliders();

        expect(
            wrapper.find('[data-test="channel-slider-r"]').exists(),
        ).toBe(true);
        expect(
            wrapper.findAll('input[type="range"]'),
        ).toHaveLength(3);
        expect(
            wrapper.find('[data-test="channel-tab-hsl"]').text(),
        ).toBe('HSL');

        await wrapper.get('[data-test="channel-tab-hsl"]').trigger('mousedown');

        expect(
            wrapper.find('[data-test="channel-slider-h"]').exists(),
        ).toBe(true);
        expect(
            wrapper.findAll('input[type="range"]'),
        ).toHaveLength(3);
    });

    it('emite set-rgb no arraste e finalize no fim do gesto', async () => {
        const wrapper = mountSliders();
        const slider = wrapper.get('[data-test="channel-slider-g"]');

        await slider.setValue('128');

        expect(wrapper.emitted('set-rgb')).toEqual([['g', 128]]);
        expect(wrapper.emitted('finalize')).toHaveLength(1);
    });

    it('emite set-hsl no campo numerico', async () => {
        const wrapper = mountSliders();

        await wrapper.get('[data-test="channel-tab-hsl"]').trigger('mousedown');

        const input = wrapper.get('[data-test="channel-value-h"]');

        (input.element as HTMLInputElement).value = '120';
        await input.trigger('change');

        expect(wrapper.emitted('set-hsl')).toEqual([['h', 120]]);
        expect(wrapper.emitted('finalize')).toHaveLength(1);
    });

    it('ignora campo numerico vazio', async () => {
        const wrapper = mountSliders();
        const input = wrapper.get('[data-test="channel-value-r"]');

        (input.element as HTMLInputElement).value = '';
        await input.trigger('change');

        expect(wrapper.emitted('set-rgb')).toBeUndefined();
        expect(wrapper.emitted('finalize')).toBeUndefined();
    });
});
