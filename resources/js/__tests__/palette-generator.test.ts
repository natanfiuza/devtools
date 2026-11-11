import { flushPromises, mount } from '@vue/test-utils';
import { nextTick, reactive } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { COPY_CONFIRMATION_MS } from '@/composables/useCopyFeedback';
import {
    buildHarmony,
    buildPaletteScale,
    parseColor,
    type Rgba,
} from '@/lib/color';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import { readPaletteDraft, savePaletteDraft } from '@/lib/saveDraft';
import type { ExtractionResult } from '@/tools/palette-generator/lib/extractContract';
import Tool from '@/tools/palette-generator/Tool.vue';

/**
 * Montagem do Palette Generator (F2 + F3).
 *
 * O gerador inteiro: picker compacto, escala, copia, exportacao e os estados
 * vazio/invalido; na F3, as abas de harmonias e de extracao de imagem. A
 * matematica ja esta coberta em `color-scale`, `color-harmony`,
 * `palette-exporters` e `palette-extraction`; aqui o que importa e a tela
 * ligada a ela. Decodificacao, busca de URL e worker sao mockados: o
 * comportamento deles tem testes proprios.
 */

const { writeClipboard } = vi.hoisted(() => ({ writeClipboard: vi.fn() }));
const { downloadText } = vi.hoisted(() => ({ downloadText: vi.fn() }));
const { usePageMock, visitMock, postMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    visitMock: vi.fn(),
    postMock: vi.fn(),
}));
const { decodeImageMock, fetchImageMock, runMock, cancelMock, disposeMock } =
    vi.hoisted(() => ({
        decodeImageMock: vi.fn(),
        fetchImageMock: vi.fn(),
        runMock: vi.fn(),
        cancelMock: vi.fn(),
        disposeMock: vi.fn(),
    }));

vi.mock('@inertiajs/vue3', () => ({
    usePage: usePageMock,
    router: { visit: visitMock, post: postMock },
}));
vi.mock('@/lib/clipboard', () => ({ writeClipboard }));
vi.mock('@/lib/download', () => ({ downloadText }));
vi.mock('@/tools/palette-generator/lib/decodeImage', () => ({
    ACCEPTED_IMAGE_TYPES: ['image/png'],
    decodeImage: decodeImageMock,
}));
vi.mock('@/tools/palette-generator/lib/fetchImage', () => ({
    fetchImage: fetchImageMock,
}));
vi.mock('@/tools/palette-generator/lib/extract.workerHost', () => ({
    spawnExtractionWorker: () => null,
}));
vi.mock('@/tools/palette-generator/lib/extractionClient', () => ({
    createExtractionClient: () => ({
        run: runMock,
        cancel: cancelMock,
        dispose: disposeMock,
        thread: () => 'main',
    }),
}));

function colorOf(input: string): Rgba {
    const parsed = parseColor(input);

    if (!parsed.ok) {
        throw new Error(`cor de teste invalida: ${input}`);
    }

    return parsed.color.rgba;
}

const EXAMPLE = colorOf('#6366f1');
const SCALE = buildPaletteScale(EXAMPLE);

/** Os tetos que a pagina recebe de `ColorsToolProps` (config/tools.php). */
const IMAGE_LIMITS = {
    maxBytes: 10 * 1024 * 1024,
    maxSide: 4096,
    maxPixels: 16_000_000,
    analysisSide: 512,
    fetchTimeoutMs: 8000,
};

/** Limites do modal e tags da conta, tambem vindos de `ColorsToolProps`. */
const PALETTE_PROPS = {
    nameMaxLength: 80,
    descriptionMaxLength: 500,
    tagsPerPalette: 10,
    tagNameMaxLength: 30,
    tags: [{ id: 'tag-web', name: 'web' }],
};

const TOOL_URL = '/pt-br/tools/colors/palette-generator';

/** O `usePage` que o Inertia devolveria; o teste troca auth e URL. */
const pageState = reactive<{
    url: string;
    props: { auth: { user: { id: number } | null; isAdmin: boolean } };
}>({
    url: TOOL_URL,
    props: { auth: { user: null, isAdmin: false } },
});

/** O que `decodeImage` devolveria para uma imagem de 1x1 (canvas fora do jsdom). */
const DECODED = {
    ok: true as const,
    image: {
        data: new Uint8ClampedArray(4),
        width: 1,
        height: 1,
        sampledWidth: 1,
        sampledHeight: 1,
        downscaled: false,
    },
};

/** Resultado pronto de `extractPalette` (a matematica tem teste proprio). */
const EXTRACTED: ExtractionResult = {
    colors: [
        {
            rgba: { r: 255, g: 0, b: 0, a: 1 },
            hex: '#ff0000',
            weight: 3,
            percentage: 75,
        },
        {
            rgba: { r: 0, g: 0, b: 255, a: 1 },
            hex: '#0000ff',
            weight: 1,
            percentage: 25,
        },
    ],
    requested: 11,
    algorithmVersion: 1,
    sufficient: true,
    visiblePixels: 4,
};

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
                        preview: { eyedropper: 'Conta-gotas da tela' },
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
                    tabs: {
                        scale: 'Escala',
                        harmonies: 'Harmonias',
                        extraction: 'Extração',
                    },
                    harmonies: {
                        title: 'Harmonias',
                        description: 'Cores que combinam com a base.',
                        kind_label: 'Tipo de harmonia',
                        method_label: 'Método',
                        method_oklch: 'OKLCH',
                        method_hsl: 'HSL',
                        kind_complementary: 'Complementar',
                        kind_analogous: 'Análoga',
                        kind_triadic: 'Triádica',
                        kind_tetradic: 'Tetrádica',
                        kind_monochromatic: 'Monocromática',
                        base_badge: 'Base',
                        clipped_badge: 'Fora do sRGB',
                        achromatic_note:
                            'A base é acromática; a harmonia fica reduzida.',
                        color_label: 'Cor {index} da harmonia',
                        copy: 'Copiar',
                        use_as_base: 'Usar como base',
                        copied: 'Copiado: {value}',
                    },
                    extraction: {
                        title: 'Cores dominantes da imagem',
                        description: 'Extraia as cores dominantes.',
                        file_label: 'Imagem',
                        file_hint: 'Até {size}.',
                        url_label: 'URL da imagem',
                        url_placeholder: 'https://exemplo.com/foto.jpg',
                        url_hint: 'Precisa ser HTTPS e permitir CORS.',
                        import_url: 'Importar',
                        importing: 'Importando…',
                        count_label: 'Quantidade de cores',
                        count_option: '{count} cores',
                        cancel: 'Cancelar',
                        preview_alt: 'Prévia da imagem importada',
                        status: {
                            reading: 'Baixando a imagem…',
                            decoding: 'Decodificando…',
                            analyzing: 'Extraindo as cores dominantes…',
                        },
                        results_title: 'Cores dominantes · {count}',
                        percentage: '{value}% dos pixels',
                        copy: 'Copiar',
                        copied: 'Copiado: {value}',
                        use_as_base: 'Usar como base',
                        use_file: 'Usar um arquivo local',
                        insufficient:
                            'A imagem tem menos de {requested} cores distintas; foram encontradas {found}.',
                        algorithm_version: 'Algoritmo v{version}',
                        source_file: 'Arquivo: {name}',
                        source_url: 'Imagem de {host}',
                        errors: {
                            invalid_url: 'A URL não é válida.',
                            not_https: 'Somente URLs HTTPS são aceitas.',
                            cors: 'A origem não permite a leitura direta (CORS). Use um arquivo local.',
                            network: 'Não foi possível baixar a imagem.',
                            timeout: 'A imagem demorou demais para responder.',
                            too_large: 'A imagem passa do limite de tamanho.',
                            not_image:
                                'O endereço não aponta para uma imagem aceita.',
                            decode_failed:
                                'Não foi possível decodificar a imagem.',
                            dimensions_too_large:
                                'A imagem passa dos limites de dimensão.',
                            too_many_pixels: 'A imagem tem pixels demais.',
                            empty_image: 'A imagem não tem pixels visíveis.',
                            unavailable:
                                'A extração não está disponível neste navegador.',
                            cancelled: 'Importação cancelada.',
                        },
                    },
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
                    save: {
                        action: 'Salvar paleta',
                        draft_restored: 'Sua paleta voltou. Revise e salve.',
                    },
                },
            },
            colors: {
                form: {
                    title: 'Salvar paleta',
                    description:
                        'Dê um nome, escolha quem pode ver e adicione tags.',
                    name_label: 'Nome',
                    name_placeholder: 'Oceano',
                    description_label: 'Descrição',
                    description_placeholder: 'Uma paleta de azuis calmos.',
                    visibility_label: 'Visibilidade',
                    visibility_private: 'Privada',
                    visibility_private_hint: 'Só você pode vê-la.',
                    visibility_public: 'Pública',
                    visibility_public_hint: 'Ela ganha uma página pública.',
                    tags_label: 'Tags',
                    tags_placeholder: 'Adicionar uma tag',
                    tags_hint: '{count}/{max} tags · até {length} caracteres',
                    tags_limit: 'Você atingiu o limite de tags ({max}).',
                    tags_too_long: 'Uma tag pode ter até {length} caracteres.',
                    tags_remove: 'Remover {name}',
                    tags_suggestions: 'Suas tags:',
                    insufficient:
                        'A imagem tinha menos cores que o pedido; a paleta é salva com {found}.',
                    submit: 'Salvar paleta',
                    saving: 'Salvando…',
                    cancel: 'Cancelar',
                    errors: {
                        name_required: 'Dê um nome à paleta.',
                        name_too_long: 'O nome pode ter até {max} caracteres.',
                        description_too_long:
                            'A descrição pode ter até {max} caracteres.',
                    },
                },
            },
        },
    });

    return instance;
}

function mountTool() {
    return mount(Tool, {
        props: { image: IMAGE_LIMITS, palette: PALETTE_PROPS },
        global: { plugins: [i18n()] },
    });
}

function toneCard(wrapper: ReturnType<typeof mountTool>, tone: number) {
    return wrapper.get(`[data-test="tone-card-${tone}"]`);
}

/** Escolhe um arquivo no seletor escondido do `FileDropzone`. */
async function chooseFile(
    wrapper: ReturnType<typeof mountTool>,
    name = 'foto.png',
): Promise<void> {
    const input = wrapper.get('[data-test="extraction-file"]');
    const file = new File([new Uint8Array([1, 2, 3])], name, {
        type: 'image/png',
    });

    Object.defineProperty(input.element, 'files', {
        value: [file],
        configurable: true,
    });
    await input.trigger('change');
    await flushPromises();
}

function harmonyHexes(wrapper: ReturnType<typeof mountTool>): string[] {
    return wrapper
        .findAll('[data-test^="harmony-color-"] code')
        .map((node) => node.text());
}

beforeEach(() => {
    document.body.innerHTML = '';
    sessionStorage.clear();
    pageState.url = TOOL_URL;
    pageState.props.auth.user = null;
    pageState.props.auth.isAdmin = false;
    usePageMock.mockReset();
    usePageMock.mockReturnValue(pageState);
    visitMock.mockReset();
    postMock.mockReset();
    writeClipboard.mockReset();
    writeClipboard.mockResolvedValue(undefined);
    downloadText.mockReset();
    decodeImageMock.mockReset();
    decodeImageMock.mockResolvedValue(DECODED);
    fetchImageMock.mockReset();
    runMock.mockReset();
    runMock.mockResolvedValue({ ok: true, result: EXTRACTED });
    cancelMock.mockReset();
    disposeMock.mockReset();
    URL.createObjectURL = vi.fn(() => 'blob:preview');
    URL.revokeObjectURL = vi.fn();
});

describe('Tool', () => {
    it('monta a escala do exemplo com os 11 tons', () => {
        const wrapper = mountTool();

        expect(wrapper.findAll('[data-test^="tone-card-"]')).toHaveLength(11);
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
        expect(wrapper.find('[data-test="tone-nearest-600"]').exists()).toBe(
            true,
        );
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
        ).toContain(SCALE.tones[5]?.contrast.white.ratio.toFixed(2) ?? '');
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
        expect(wrapper.findAll('[data-test^="tone-card-"]')).toHaveLength(11);
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

        await wrapper
            .get('[data-test="export-name"]')
            .setValue('Minha Paleta!');

        expect(wrapper.get('[data-test="export-slug"]').text()).toContain(
            'minha-paleta',
        );
        expect(wrapper.find('[data-test="export-name-warning"]').exists()).toBe(
            true,
        );
    });

    it('o conta-gotas da tela define a base preservando o alpha', async () => {
        const Stub = class {
            open(): Promise<{ sRGBHex: string }> {
                return Promise.resolve({ sRGBHex: '#00ff00' });
            }
        };

        (window as unknown as { EyeDropper?: unknown }).EyeDropper = Stub;

        try {
            const wrapper = mountTool();

            await wrapper.get('[data-test="alpha-slider"]').setValue('50');
            await wrapper
                .get('[data-test="picker-eyedropper"]')
                .trigger('click');
            await flushPromises();

            expect(wrapper.get('[data-test="base-value"]').text()).toBe(
                '#00ff0080',
            );
            expect(wrapper.find('[data-test="base-alpha-note"]').exists()).toBe(
                true,
            );
            expect(toneCard(wrapper, 50).get('code').text()).toMatch(
                /^#[0-9a-f]{8}$/,
            );
        } finally {
            delete (window as unknown as { EyeDropper?: unknown }).EyeDropper;
        }
    });

    it('sem EyeDropper nao mostra o botao de conta-gotas', () => {
        const wrapper = mountTool();

        expect(wrapper.find('[data-test="picker-eyedropper"]').exists()).toBe(
            false,
        );
    });

    it('o icone de copiar vira check e volta', async () => {
        vi.useFakeTimers();

        try {
            const wrapper = mountTool();
            const button = wrapper.get('[data-test="scale-copy"]');

            expect(button.find('[data-test="copy-icon"]').exists()).toBe(true);

            await button.trigger('click');
            await vi.advanceTimersByTimeAsync(0);
            await nextTick();

            expect(button.find('[data-test="copied-icon"]').exists()).toBe(
                true,
            );
            expect(button.find('[data-test="copy-icon"]').exists()).toBe(false);

            await vi.advanceTimersByTimeAsync(COPY_CONFIRMATION_MS);
            await nextTick();

            expect(button.find('[data-test="copy-icon"]').exists()).toBe(true);
            expect(button.find('[data-test="copied-icon"]').exists()).toBe(
                false,
            );
        } finally {
            vi.useRealTimers();
        }
    });

    it('troca entre as abas de escala, harmonias e extração', async () => {
        const wrapper = mountTool();

        expect(wrapper.find('[data-test="tone-scale"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="harmony-panel"]').exists()).toBe(
            false,
        );

        await wrapper
            .get('[data-test="page-tab-harmonies"]')
            .trigger('mousedown');

        expect(wrapper.find('[data-test="harmony-panel"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="tone-scale"]').exists()).toBe(false);

        await wrapper
            .get('[data-test="page-tab-extraction"]')
            .trigger('mousedown');

        expect(wrapper.find('[data-test="extraction-panel"]').exists()).toBe(
            true,
        );
    });

    it('harmonias mudam com o tipo e o método e definem a base', async () => {
        const wrapper = mountTool();

        await wrapper
            .get('[data-test="page-tab-harmonies"]')
            .trigger('mousedown');

        expect(wrapper.findAll('[data-test^="harmony-color-"]')).toHaveLength(
            2,
        );
        expect(wrapper.get('[data-test="harmony-base-0"]').text()).toBe('Base');

        await wrapper
            .get('[data-test="harmony-kind-monochromatic"]')
            .trigger('click');

        expect(wrapper.findAll('[data-test^="harmony-color-"]')).toHaveLength(
            5,
        );

        const oklch = harmonyHexes(wrapper);

        await wrapper.get('[data-test="harmony-method-hsl"]').trigger('click');

        expect(harmonyHexes(wrapper)).not.toEqual(oklch);
        expect(
            wrapper
                .get('[data-test="harmony-method-hsl"]')
                .attributes('aria-pressed'),
        ).toBe('true');

        const target = buildHarmony(EXAMPLE, 'monochromatic', 'hsl').colors[1];

        await wrapper.get('[data-test="harmony-use-1"]').trigger('click');
        await wrapper.get('[data-test="page-tab-scale"]').trigger('mousedown');

        expect(wrapper.get('[data-test="base-value"]').text()).toBe(
            target?.hex,
        );
    });

    it('base acromática reduz a harmonia e avisa', async () => {
        const wrapper = mountTool();

        await wrapper.get('[data-test="color-input"]').setValue('#808080');
        await wrapper
            .get('[data-test="page-tab-harmonies"]')
            .trigger('mousedown');

        expect(wrapper.findAll('[data-test^="harmony-color-"]')).toHaveLength(
            1,
        );
        expect(wrapper.find('[data-test="harmony-achromatic"]').exists()).toBe(
            true,
        );
    });

    it('estado vazio e cor inválida valem na aba de harmonias', async () => {
        const wrapper = mountTool();

        await wrapper.get('[data-test="color-input"]').setValue('');
        await wrapper
            .get('[data-test="page-tab-harmonies"]')
            .trigger('mousedown');

        expect(wrapper.find('[data-test="palette-empty"]').exists()).toBe(true);
        expect(wrapper.find('[data-test="harmony-panel"]').exists()).toBe(
            false,
        );

        await wrapper.get('[data-test="color-input"]').setValue('nao-e-cor');

        expect(wrapper.find('[data-test="harmonies-stale"]').exists()).toBe(
            true,
        );
        expect(wrapper.find('[data-test="harmony-panel"]').exists()).toBe(true);
    });

    it('extrai cores de um arquivo, usa como base e preserva ao trocar de aba', async () => {
        const wrapper = mountTool();

        await wrapper
            .get('[data-test="page-tab-extraction"]')
            .trigger('mousedown');
        await chooseFile(wrapper);

        expect(decodeImageMock).toHaveBeenCalledWith(
            expect.any(Blob),
            IMAGE_LIMITS,
        );
        expect(runMock).toHaveBeenCalledWith(
            expect.objectContaining({ width: 1, height: 1, count: 11 }),
        );
        expect(
            wrapper.findAll('[data-test^="extracted-swatch-"]'),
        ).toHaveLength(2);
        expect(
            wrapper.get('[data-test="extracted-percentage-0"]').text(),
        ).toContain('75');
        expect(wrapper.get('[data-test="extraction-source"]').text()).toContain(
            'foto.png',
        );
        expect(
            wrapper.get('[data-test="extraction-version"]').text(),
        ).toContain('v1');

        await wrapper.get('[data-test="extracted-use-0"]').trigger('click');
        await wrapper.get('[data-test="page-tab-scale"]').trigger('mousedown');

        expect(wrapper.get('[data-test="base-value"]').text()).toBe('#ff0000');

        await wrapper
            .get('[data-test="page-tab-extraction"]')
            .trigger('mousedown');

        expect(wrapper.find('[data-test="extraction-results"]').exists()).toBe(
            true,
        );
    });

    it('extrai a quantidade de cores escolhida', async () => {
        const wrapper = mountTool();

        await wrapper
            .get('[data-test="page-tab-extraction"]')
            .trigger('mousedown');
        await wrapper.get('[data-test="extraction-count-9"]').trigger('click');
        await chooseFile(wrapper);

        expect(runMock).toHaveBeenCalledWith(
            expect.objectContaining({ count: 9 }),
        );
    });

    it('URL bloqueada por CORS oferece o arquivo local', async () => {
        const wrapper = mountTool();

        await wrapper
            .get('[data-test="page-tab-extraction"]')
            .trigger('mousedown');
        fetchImageMock.mockResolvedValue({ ok: false, error: 'cors' });

        await wrapper
            .get('[data-test="extraction-url"]')
            .setValue('https://example.com/foto.png');
        await wrapper.get('[data-test="extraction-import"]').trigger('click');
        await flushPromises();

        expect(fetchImageMock).toHaveBeenCalledWith(
            'https://example.com/foto.png',
            IMAGE_LIMITS,
            expect.any(AbortSignal),
        );
        expect(wrapper.get('[data-test="extraction-error"]').text()).toContain(
            'CORS',
        );
        expect(wrapper.find('[data-test="extraction-use-file"]').exists()).toBe(
            true,
        );
        expect(wrapper.find('[data-test="extraction-results"]').exists()).toBe(
            false,
        );
    });

    it('erro de decodificação aparece como alerta', async () => {
        const wrapper = mountTool();

        await wrapper
            .get('[data-test="page-tab-extraction"]')
            .trigger('mousedown');
        decodeImageMock.mockResolvedValue({
            ok: false,
            error: 'decode_failed',
        });

        await chooseFile(wrapper);

        expect(wrapper.get('[data-test="extraction-error"]').text()).toContain(
            'decodificar',
        );
        expect(wrapper.find('[data-test="extraction-results"]').exists()).toBe(
            false,
        );
    });

    it('cancelar volta ao estado ocioso e encerra o trabalho', async () => {
        const wrapper = mountTool();

        await wrapper
            .get('[data-test="page-tab-extraction"]')
            .trigger('mousedown');
        runMock.mockReturnValue(new Promise(() => undefined));

        await chooseFile(wrapper);

        expect(wrapper.find('[data-test="extraction-status"]').exists()).toBe(
            true,
        );

        await wrapper.get('[data-test="extraction-cancel"]').trigger('click');

        expect(wrapper.find('[data-test="extraction-status"]').exists()).toBe(
            false,
        );
        expect(cancelMock).toHaveBeenCalled();
    });
});

describe('Tool · salvar', () => {
    function dialog(): HTMLElement | null {
        return document.querySelector('[data-test="palette-save-dialog"]');
    }

    async function openDialog(): Promise<void> {
        const wrapper = mountTool();

        await wrapper.get('[data-test="palette-save"]').trigger('click');
        await flushPromises();
    }

    async function typeName(value: string): Promise<void> {
        const input = document.querySelector(
            '[data-test="save-name"]',
        ) as HTMLInputElement | null;

        if (input === null) {
            throw new Error('campo de nome ausente');
        }

        input.value = value;
        input.dispatchEvent(new Event('input'));
        await nextTick();
    }

    it('visitante guarda o rascunho e segue para o login', async () => {
        const wrapper = mountTool();

        await wrapper.get('[data-test="palette-save"]').trigger('click');

        expect(visitMock).toHaveBeenCalledWith(
            '/dashboard/colors/palettes/create',
        );
        expect(dialog()).toBeNull();

        const draft = readPaletteDraft();

        expect(draft).not.toBeNull();
        expect(draft?.kind).toBe('scale');
        expect(draft?.base).toBe('#6366f1');
    });

    it('autenticado abre o modal e envia a escala', async () => {
        pageState.props.auth.user = { id: 7 };
        await openDialog();

        expect(dialog()).not.toBeNull();

        await typeName('Oceano');
        document
            .querySelector('[data-test="palette-save-dialog"] form')
            ?.dispatchEvent(
                new Event('submit', { bubbles: true, cancelable: true }),
            );
        await flushPromises();

        expect(postMock).toHaveBeenCalledTimes(1);

        const [url, body] = postMock.mock.calls[0] as [
            string,
            Record<string, unknown>,
        ];

        expect(url).toBe('/dashboard/colors/palettes');
        expect(body.name).toBe('Oceano');
        expect(body.kind).toBe('scale');
        expect(body.visibility).toBe('private');
        expect(body.colors).toMatchObject({ base: '#6366f1' });
        expect((body.colors as { items: unknown[] }).items).toHaveLength(11);
        expect(body.tags).toEqual([]);
    });

    it('login concluído restaura o rascunho com ?save=1', async () => {
        pageState.props.auth.user = { id: 7 };
        pageState.url = `${TOOL_URL}?save=1`;
        savePaletteDraft({
            kind: 'scale',
            base: '#0ea5e9',
            tab: 'scale',
            extracted: null,
        });

        const wrapper = mountTool();

        await flushPromises();

        expect(readPaletteDraft()).toBeNull();
        expect(wrapper.get('[data-test="base-value"]').text()).toBe('#0ea5e9');
        expect(dialog()).not.toBeNull();
        expect(wrapper.get('[data-test="palette-feedback"]').text()).toContain(
            'Sua paleta voltou',
        );
    });

    it('visitante com ?save=1 não restaura nem abre o modal', async () => {
        pageState.url = `${TOOL_URL}?save=1`;
        savePaletteDraft({
            kind: 'scale',
            base: '#0ea5e9',
            tab: 'scale',
            extracted: null,
        });

        const wrapper = mountTool();

        await flushPromises();

        expect(wrapper.get('[data-test="base-value"]').text()).toBe('#6366f1');
        expect(dialog()).toBeNull();
        expect(readPaletteDraft()).not.toBeNull();
    });

    it('cancelar fecha o modal sem enviar', async () => {
        pageState.props.auth.user = { id: 7 };
        await openDialog();

        const cancel = document.querySelector(
            '[data-test="save-cancel"]',
        ) as HTMLButtonElement | null;

        if (cancel === null) {
            throw new Error('botão de cancelar ausente');
        }

        cancel.click();
        await flushPromises();

        expect(dialog()).toBeNull();
        expect(postMock).not.toHaveBeenCalled();
    });
});
