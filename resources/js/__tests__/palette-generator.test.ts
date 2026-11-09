import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { buildPaletteScale, parseColor, type Rgba } from '@/lib/color';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import Tool from '@/tools/palette-generator/Tool.vue';

/**
 * Montagem do Palette Generator (F2).
 *
 * O gerador inteiro: picker compacto, escala, copia, exportacao e os estados
 * vazio/invalido. A matematica ja esta coberta em `color-scale` e
 * `palette-exporters`; aqui o que importa e a tela ligada a ela.
 */

const { writeClipboard } = vi.hoisted(() => ({ writeClipboard: vi.fn() }));
const { downloadText } = vi.hoisted(() => ({ downloadText: vi.fn() }));

vi.mock('@/lib/clipboard', () => ({ writeClipboard }));
vi.mock('@/lib/download', () => ({ downloadText }));

function colorOf(input: string): Rgba {
    const parsed = parseColor(input);

    if (!parsed.ok) {
        throw new Error(`cor de teste invalida: ${input}`);
    }

    return parsed.color.rgba;
}

const EXAMPLE = colorOf('#6366f1');
const SCALE = buildPaletteScale(EXAMPLE);

function i18n() {
    const instance = createI18nInstance();

    applyTranslations(instance, {
        locale: 'pt-BR',
        translations: {
            tools: {
                ui: {
                    actions: {
                        copied: 'Copiado',
                        copy_manual: 'Selecione e copie',
                    },
                },
                shared: {
                    color_picker: {
                        color_input: {
                            label: 'Cor',
                            placeholder: '#000000',
                            hint: 'Dica',
                            empty: 'Digite uma cor',
                            syntax: 'Formato inválido',
                            range: 'Fora do intervalo',
                            unknown_name: 'Nome desconhecido',
                        },
                        area: { label: 'Área', hint: 'Dica' },
                        hue: { label: 'Matiz' },
                        alpha: { label: 'Opacidade' },
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
                        preview: { native: 'Escolher no seletor' },
                        formats: {
                            title: 'Formatos',
                            hex: 'HEX',
                            hex8: 'HEX8',
                            rgb: 'RGB',
                            rgba: 'RGBA',
                            hsl: 'HSL',
                            hsla: 'HSLA',
                            oklch: 'OKLCH',
                            oklab: 'OKLab',
                            name: 'Nome CSS',
                            copy: 'Copiar {format}',
                            copied: 'Copiado: {value}',
                            name_hint: 'Dica do nome',
                        },
                    },
                },
                'palette-generator': {
                    name: 'Color Palette Generator',
                    base: {
                        title: 'Cor base',
                        badge: 'Base',
                        copy: 'Copiar {format}',
                        copied: 'Copiado: {value}',
                        nearest: 'Tom mais próximo: {tone}',
                        not_500: 'A base não é o tom 500.',
                        matches_500: 'A base é o tom 500.',
                        clipped: 'Croma reduzido para caber no sRGB.',
                        alpha_note: 'Contraste composto por polaridade.',
                    },
                    scale: {
                        title: 'Escala de 11 tons',
                        description: 'Descrição da escala.',
                        copy_scale: 'Copiar escala',
                        copied_scale: 'Escala copiada: {count} tons',
                        tone_copy: 'Copiar {tone}',
                        tone_copied: 'Copiado: {value}',
                        contrast_black: 'Texto preto {ratio}:1',
                        contrast_white: 'Texto branco {ratio}:1',
                        pass: 'passa {level}',
                        fail: 'não passa {level}',
                        clipped_badge: 'Croma limitado',
                        clipped_summary: 'Tons com croma limitado: {tones}',
                        algorithm_version: 'Algoritmo v{version}',
                        base_tone_badge: 'Mais próximo',
                    },
                    export: {
                        title: 'Exportar',
                        description: 'Descrição da exportação.',
                        name_label: 'Nome da paleta',
                        name_hint: 'Usado nas variáveis: {slug}',
                        name_invalid: 'O nome será normalizado para {slug}.',
                        copy: 'Copiar',
                        copied: '{name} copiado',
                        download: 'Baixar',
                        tabs: {
                            css: 'CSS',
                            scss: 'SCSS',
                            json: 'JSON',
                            tailwind_v3: 'Tailwind v3',
                            tailwind_v4: 'Tailwind v4',
                        },
                    },
                    empty: {
                        title: 'Escolha uma cor base',
                        body: 'Corpo do estado vazio.',
                        restore: 'Restaurar exemplo',
                    },
                    state: {
                        stale: 'A cor digitada não é válida; a escala anterior continua na tela.',
                    },
                },
            },
        },
    });

    return instance;
}

function mountTool() {
    return mount(Tool, { global: { plugins: [i18n()] } });
}

function toneCard(wrapper: ReturnType<typeof mountTool>, tone: number) {
    return wrapper.get(`[data-test="tone-card-${tone}"]`);
}

beforeEach(() => {
    writeClipboard.mockReset();
    writeClipboard.mockResolvedValue(undefined);
    downloadText.mockReset();
});

describe('Tool', () => {
    it('monta a escala do exemplo com os 11 tons', () => {
        const wrapper = mountTool();

        expect(
            wrapper.findAll('[data-test^="tone-card-"]'),
        ).toHaveLength(11);
        expect(wrapper.get('[data-test="base-value"]').text()).toBe('#6366f1');
        expect(wrapper.get('[data-test="base-nearest"]').text()).toContain(
            '600',
        );
        expect(wrapper.get('[data-test="base-tone-note"]').text()).toContain(
            'não é o tom 500',
        );
        expect(wrapper.get('[data-test="scale-version"]').text()).toContain(
            'v1',
        );
        expect(
            wrapper.find('[data-test="tone-nearest-600"]').exists(),
        ).toBe(true);
    });

    it('mostra o contraste calculado em cada tom', () => {
        const wrapper = mountTool();
        const expected = SCALE.tones[5]?.contrast.black.ratio.toFixed(2);

        expect(
            toneCard(wrapper, 500)
                .get('[data-test="tone-contrast-black-500"]')
                .text(),
        ).toContain(`${expected}:1`);
        expect(
            toneCard(wrapper, 500)
                .get('[data-test="tone-contrast-white-500"]')
                .text(),
        ).toContain(
            SCALE.tones[5]?.contrast.white.ratio.toFixed(2) ?? '',
        );
    });

    it('copia um tom e a escala inteira', async () => {
        const wrapper = mountTool();

        await wrapper.get('[data-test="tone-copy-500"]').trigger('click');
        await flushPromises();

        expect(writeClipboard).toHaveBeenCalledWith(SCALE.tones[5]?.hex);

        await wrapper.get('[data-test="scale-copy"]').trigger('click');
        await flushPromises();

        const copied = String(writeClipboard.mock.calls[1]?.[0] ?? '');

        expect(copied).toContain('50: #');
        expect(copied).toContain('950: #');
        expect(wrapper.get('[data-test="palette-feedback"]').text()).toContain(
            'Escala copiada: 11 tons',
        );
    });

    it('limpar o campo mostra o estado vazio e restaurar volta', async () => {
        const wrapper = mountTool();

        await wrapper.get('[data-test="color-input"]').setValue('');

        expect(wrapper.find('[data-test="palette-empty"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="tone-scale"]').exists()).toBe(false);

        await wrapper.get('[data-test="palette-restore"]').trigger('click');

        expect(wrapper.find('[data-test="tone-scale"]').exists()).toBe(true);
        expect(wrapper.get('[data-test="base-value"]').text()).toBe('#6366f1');
    });

    it('texto invalido mantem a escala anterior e avisa', async () => {
        const wrapper = mountTool();

        await wrapper.get('[data-test="color-input"]').setValue('nao-e-cor');

        expect(wrapper.find('[data-test="palette-stale"]').exists()).toBe(true);
        expect(wrapper.get('[data-test="base-value"]').text()).toBe('#6366f1');
        expect(
            wrapper.findAll('[data-test^="tone-card-"]'),
        ).toHaveLength(11);
    });

    it('troca de aba, copia e baixa a exportacao ativa', async () => {
        const wrapper = mountTool();

        await wrapper.get('[data-test="export-tab-json"]').trigger('mousedown');

        const code = wrapper.get('[data-test="export-code"]').text();

        expect(code).toContain('"algorithmVersion": 1');
        expect(code).toContain('"50": "#');

        await wrapper.get('[data-test="export-copy"]').trigger('click');
        await flushPromises();

        expect(writeClipboard).toHaveBeenCalledWith(
            expect.stringContaining('"algorithmVersion": 1'),
        );
        expect(wrapper.get('[data-test="palette-feedback"]').text()).toContain(
            'primary.json copiado',
        );

        await wrapper.get('[data-test="export-download"]').trigger('click');

        expect(downloadText).toHaveBeenCalledWith(
            'primary.json',
            expect.stringContaining('"colors"'),
            'application/json;charset=utf-8',
        );
    });

    it('avisa o slug quando o nome precisa ser normalizado', async () => {
        const wrapper = mountTool();

        await wrapper.get('[data-test="export-name"]').setValue('Minha Paleta!');

        expect(wrapper.get('[data-test="export-slug"]').text()).toContain(
            'minha-paleta',
        );
        expect(
            wrapper.find('[data-test="export-name-warning"]').exists(),
        ).toBe(true);
    });

    it('o seletor nativo preserva o alpha nos 11 tons', async () => {
        const wrapper = mountTool();

        await wrapper.get('[data-test="alpha-slider"]').setValue('50');
        await wrapper.get('[data-test="picker-native"]').setValue('#00ff00');

        expect(wrapper.get('[data-test="base-value"]').text()).toMatch(
            /^#[0-9a-f]{8}$/,
        );
        expect(
            wrapper.find('[data-test="base-alpha-note"]').exists(),
        ).toBe(true);
        expect(toneCard(wrapper, 50).get('code').text()).toMatch(
            /^#[0-9a-f]{8}$/,
        );
    });
});
