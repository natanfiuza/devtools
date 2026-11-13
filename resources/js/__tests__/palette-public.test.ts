import { flushPromises, mount } from '@vue/test-utils';
import { defineComponent, h, nextTick } from 'vue';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { buildPaletteScale, parseColor, type Rgba } from '@/lib/color';
import { applyTranslations, createI18nInstance } from '@/lib/i18n';
import PublicPalette from '@/pages/colors/PublicPalette.vue';
import type { PalettePublic, PublicPaletteProps } from '@/types/colors';

/**
 * Pagina publica de uma paleta (F4, ADR-124).
 *
 * O `ExportPanel` do gerador tem teste proprio; aqui ele e trocado por um stub
 * para conferir a fiacao: a escala persistida chega congelada, a extraida cai
 * na exportacao simples, a duplicacao respeita o login e o experimento de base
 * so existe na memoria da pagina - ate o momento de duplicar.
 */

const {
    routerMock,
    pageState,
    exportProps,
    writeClipboard,
    downloadText,
    fetchMock,
} = vi.hoisted(() => ({
    routerMock: {
        get: vi.fn(),
        post: vi.fn(),
        patch: vi.fn(),
        delete: vi.fn(),
    },
    pageState: {
        url: '/en/colors/p/oceano',
        props: {
            localeSlug: 'en',
            auth: { user: null as { id: number } | null },
            seo: { meta: { title: 'Oceano' } },
        },
    },
    exportProps: { current: null as Record<string, unknown> | null },
    writeClipboard: vi.fn(),
    downloadText: vi.fn(),
    fetchMock: vi.fn(),
}));

vi.stubGlobal('fetch', fetchMock);

vi.mock('@inertiajs/vue3', () => ({
    router: routerMock,
    usePage: () => pageState,
    Head: defineComponent({
        name: 'InertiaHead',
        props: { title: { type: String, default: '' } },
        render: () => h('div'),
    }),
    Link: defineComponent({
        name: 'InertiaLink',
        props: { href: { type: String, default: '' } },
        setup(props, { slots, attrs }) {
            return () =>
                h(
                    'a',
                    { ...attrs, href: props.href },
                    slots.default ? slots.default() : [],
                );
        },
    }),
}));

vi.mock('@/lib/clipboard', () => ({ writeClipboard }));
vi.mock('@/lib/download', () => ({ downloadText, downloadBlob: vi.fn() }));

vi.mock(
    '@/tools/palette-generator/components/ExportPanel.vue',
    async () => {
        const { defineComponent, h } = await import('vue');

        return {
            default: defineComponent({
                name: 'ExportPanelStub',
                props: {
                    scale: { type: Object, required: true },
                    copy: { type: Function, required: true },
                },
                setup(props) {
                    exportProps.current = props as unknown as Record<
                        string,
                        unknown
                    >;

                    return () =>
                        h('div', { 'data-test': 'export-panel-stub' });
                },
            }),
        };
    },
);

const TRADUCOES = {
    colors: {
        list: {
            kind_scale: 'ESCALA',
            kind_extracted: 'DA_IMAGEM',
            colors_count: '{count} CORES',
            usage_zero: 'SEM_USO',
            usage: 'USADA {count}',
            updated_at: 'ATUALIZADA {date}',
        },
        public: {
            copy_hex: 'COPIAR_HEX',
            copied: 'COPIADO_TUDO',
            copied_hex: 'COPIADO {hex}',
            extracted_title: 'CORES_DA_IMAGEM',
            extracted_description: 'ORDEM_DA_IMAGEM',
            copy_all: 'COPIAR_TODAS',
            download_json: 'BAIXAR_JSON',
            download_hex: 'BAIXAR_HEX',
            duplicate: 'DUPLICAR',
            duplicate_login: 'ENTRAR_PARA_DUPLICAR',
            experiment_toggle: 'TESTAR_BASE',
            experiment_title: 'TESTAR_BASE_TITULO',
            experiment_description: 'SO_NO_NAVEGADOR',
            experiment_label: 'NOVA_BASE',
            experiment_invalid: 'COR_INVALIDA',
            experiment_reset: 'VOLTAR_BASE',
            experiment_duplicate_hint: 'DUPLICAR_COM_BASE',
            favorite: 'FAVORITAR',
            favorited: 'FAVORITADA',
            favorite_error: 'ERRO_FAVORITO',
        },
    },
};

function i18n() {
    const instance = createI18nInstance();

    applyTranslations(instance, {
        locale: 'pt-BR',
        translations: TRADUCOES,
    });

    return instance;
}

function colorOf(input: string): Rgba {
    const parsed = parseColor(input);

    if (!parsed.ok) {
        throw new Error(`cor de teste invalida: ${input}`);
    }

    return parsed.color.rgba;
}

const SCALE = buildPaletteScale(colorOf('#6366f1'));

const SCALE_ITEMS = SCALE.tones.map((tone) => ({
    hex: tone.hex,
    tone: tone.tone,
}));

const EXTRACTED_HEXES = ['#ff0000', '#ff7f00', '#ffff00', '#00ff00', '#0000ff'];

function palette(overrides: Partial<PalettePublic> = {}): PalettePublic {
    return {
        id: '01KPALETTE00000000000000001',
        name: 'Oceano',
        description: 'Azuis profundos',
        kind: 'scale',
        algorithm_version: 1,
        colors: { base: '#6366f1', items: SCALE_ITEMS },
        colors_count: 11,
        duplications_count: 0,
        slug: 'oceano',
        tags: [{ id: 'tag-1', name: 'web' }],
        updated_at: '2026-11-10T12:00:00+00:00',
        ...overrides,
    };
}

function props(
    overrides: Partial<PalettePublic> = {},
    extra: Partial<PublicPaletteProps> = {},
): PublicPaletteProps {
    return {
        palette: palette(overrides),
        favorited: false,
        canFavorite: false,
        ogUrl: null,
        ...extra,
    };
}

function mountPage(
    overrides: Partial<PalettePublic> = {},
    extra: Partial<PublicPaletteProps> = {},
) {
    return mount(PublicPalette, {
        props: props(overrides, extra),
        global: { plugins: [i18n()] },
        attachTo: document.body,
    });
}

function query<T extends HTMLElement>(selector: string): T {
    const element = document.querySelector<T>(selector);

    if (element === null) {
        throw new Error(`elemento ausente: ${selector}`);
    }

    return element;
}

function exists(selector: string): boolean {
    return document.querySelector(selector) !== null;
}

async function click(selector: string): Promise<void> {
    query<HTMLButtonElement>(selector).click();
    await nextTick();
}

async function typeBase(value: string): Promise<void> {
    const input = query<HTMLInputElement>(
        '[data-test="public-experiment-input"]',
    );

    input.value = value;
    input.dispatchEvent(new Event('input'));
    await nextTick();
}

describe('Pagina publica de paleta', () => {
    beforeEach(() => {
        routerMock.get.mockReset();
        routerMock.post.mockReset();
        routerMock.patch.mockReset();
        routerMock.delete.mockReset();
        writeClipboard.mockReset();
        writeClipboard.mockResolvedValue(undefined);
        downloadText.mockReset();
        fetchMock.mockReset();
        fetchMock.mockResolvedValue({
            ok: true,
            json: async () => ({ favorited: true }),
        });
        exportProps.current = null;
        pageState.url = '/en/colors/p/oceano';
        pageState.props.auth = { user: null };
        document.body.innerHTML = '';
    });

    it('mostra nome, metadados, tags e as cores persistidas', () => {
        mountPage();

        const page = query('[data-test="public-palette"]');

        expect(
            query('[data-test="public-palette-name"]').textContent,
        ).toContain('Oceano');
        expect(
            query('[data-test="public-palette-description"]').textContent,
        ).toContain('Azuis profundos');
        expect(
            query('[data-test="public-palette-tags"]').textContent,
        ).toContain('web');
        expect(page.textContent).toContain('11 CORES');
        expect(
            query('[data-test="public-palette-usage"]').textContent,
        ).toContain('SEM_USO');
        expect(page.textContent).toContain('ATUALIZADA');

        const swatches = document.querySelectorAll(
            '[data-test="public-swatch"]',
        );

        expect(swatches).toHaveLength(11);
        expect(swatches[0]?.textContent).toContain(SCALE_ITEMS[0]?.hex);
    });

    it('entrega a escala congelada ao painel de exportacao', () => {
        mountPage();

        // O `data-test` que a pagina passa vence o do stub (fallthrough).
        expect(exists('[data-test="public-export"]')).toBe(true);
        expect(exists('[data-test="public-extracted-export"]')).toBe(false);

        const scale = exportProps.current?.scale as {
            algorithmVersion: number;
            tones: { hex: string; tone: number }[];
        };

        expect(scale.algorithmVersion).toBe(1);
        expect(scale.tones).toHaveLength(11);
        expect(scale.tones[6]?.hex).toBe(SCALE_ITEMS[6]?.hex);
    });

    it('copia o HEX do swatch e avisa', async () => {
        mountPage();

        await click('[data-test="public-swatch"]');

        expect(writeClipboard).toHaveBeenCalledWith(SCALE_ITEMS[0]?.hex);
        expect(
            query('[data-test="public-copy-feedback"]').textContent,
        ).toContain('COPIADO');
    });

    it('paleta extraida troca o exportador por copia e download da lista', async () => {
        mountPage({
            kind: 'extracted',
            colors: {
                base: '#ff0000',
                items: EXTRACTED_HEXES.map((hex) => ({ hex })),
            },
            colors_count: 5,
        });

        expect(exists('[data-test="public-export"]')).toBe(false);
        expect(
            query('[data-test="public-extracted-code"]').textContent,
        ).toContain('#00ff00');

        await click('[data-test="public-extracted-copy"]');

        expect(writeClipboard).toHaveBeenCalledWith(EXTRACTED_HEXES.join('\n'));

        await click('[data-test="public-extracted-download-json"]');

        expect(downloadText).toHaveBeenCalledWith(
            'oceano.json',
            expect.stringContaining('"colors"'),
        );

        await click('[data-test="public-extracted-download-hex"]');

        expect(downloadText).toHaveBeenCalledWith(
            'oceano.txt',
            `${EXTRACTED_HEXES.join('\n')}\n`,
            expect.stringContaining('text/plain'),
        );
    });

    it('visitante recebe a intencao de duplicar, nao o botao', () => {
        mountPage();

        expect(exists('[data-test="public-duplicate"]')).toBe(false);

        const link = query<HTMLAnchorElement>(
            '[data-test="public-duplicate-login"]',
        );

        expect(link.getAttribute('href')).toBe(
            '/en/colors/p/oceano/duplicate',
        );
    });

    it('duplica a propria paleta sem corpo quando nada foi experimentado', async () => {
        pageState.props.auth = { user: { id: 7 } };

        mountPage();

        await click('[data-test="public-duplicate"]');

        expect(routerMock.post).toHaveBeenCalledTimes(1);
        expect(routerMock.post).toHaveBeenCalledWith(
            '/en/colors/p/oceano/duplicate',
            {},
            { preserveScroll: true },
        );
    });

    it('testa uma base nova so na memoria da pagina', async () => {
        pageState.props.auth = { user: { id: 7 } };

        mountPage();

        expect(exists('[data-test="public-experiment"]')).toBe(false);

        await click('[data-test="public-experiment-toggle"]');

        expect(exists('[data-test="public-experiment"]')).toBe(true);

        await typeBase('nao-e-cor');

        expect(
            query('[data-test="public-experiment-invalid"]').textContent,
        ).toContain('COR_INVALIDA');
        expect(exists('[data-test="public-experiment-preview"]')).toBe(false);

        await typeBase('#ff0000');

        expect(exists('[data-test="public-experiment-invalid"]')).toBe(false);

        const preview = document.querySelectorAll(
            '[data-test="public-experiment-preview"] span',
        );

        expect(preview).toHaveLength(11);
        expect(pageState.url).toBe('/en/colors/p/oceano');
        expect(routerMock.get).not.toHaveBeenCalled();
    });

    it('duplica com a base experimentada enviando a escala da tela', async () => {
        pageState.props.auth = { user: { id: 7 } };

        mountPage();

        await click('[data-test="public-experiment-toggle"]');
        await typeBase('#ff0000');
        await click('[data-test="public-duplicate"]');

        expect(routerMock.post).toHaveBeenCalledTimes(1);

        const call = routerMock.post.mock.calls[0] as [
            string,
            {
                colors: { base: string; items: { hex: string; tone: number }[] };
                roles: { light: Record<string, string>; dark: Record<string, string> };
                tags: string[];
            },
        ];

        expect(call[0]).toBe('/en/colors/p/oceano/duplicate');
        expect(call[1].colors.base).toBe('#ff0000');
        expect(call[1].colors.items).toHaveLength(11);
        expect(call[1].colors.items[0]?.tone).toBe(50);
        expect(Object.keys(call[1].roles.light)).toHaveLength(8);
        expect(Object.keys(call[1].roles.dark)).toHaveLength(8);
        expect(call[1].tags).toEqual(['web']);
    });

    it('volta para a base publicada e duplica o retrato original', async () => {
        pageState.props.auth = { user: { id: 7 } };

        mountPage();

        await click('[data-test="public-experiment-toggle"]');
        await typeBase('#ff0000');
        await click('[data-test="public-experiment-reset"]');

        expect(
            query<HTMLInputElement>('[data-test="public-experiment-input"]')
                .value,
        ).toBe('#6366f1');

        await click('[data-test="public-duplicate"]');

        expect(routerMock.post).toHaveBeenCalledWith(
            '/en/colors/p/oceano/duplicate',
            {},
            { preserveScroll: true },
        );
    });

    it('abre o experimento quando volta do login com ?duplicate=1', async () => {
        pageState.url = '/en/colors/p/oceano?duplicate=1';

        mountPage();
        await nextTick();

        expect(exists('[data-test="public-experiment"]')).toBe(true);
    });

    it('so mostra a estrela quando a conta pode favoritar', () => {
        mountPage();

        expect(exists('[data-test="public-favorite"]')).toBe(false);

        mountPage({}, { canFavorite: true });

        expect(exists('[data-test="public-favorite"]')).toBe(true);
    });

    it('favorita e desfavorita de forma otimista pela API', async () => {
        mountPage({}, { canFavorite: true });

        expect(
            query('[data-test="public-favorite"]').textContent,
        ).toContain('FAVORITAR');

        await click('[data-test="public-favorite"]');
        await flushPromises();

        expect(
            query('[data-test="public-favorite"]').textContent,
        ).toContain('FAVORITADA');
        expect(
            query('[data-test="public-favorite"]').getAttribute(
                'aria-pressed',
            ),
        ).toBe('true');
        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(fetchMock.mock.calls[0]?.[0]).toBe(
            '/api/tools/colors/palettes/01KPALETTE00000000000000001/favorite',
        );
        expect(fetchMock.mock.calls[0]?.[1]).toMatchObject({
            method: 'POST',
            credentials: 'same-origin',
        });

        await click('[data-test="public-favorite"]');
        await flushPromises();

        expect(
            query('[data-test="public-favorite"]').textContent,
        ).toContain('FAVORITAR');
        expect(fetchMock.mock.calls[1]?.[1]).toMatchObject({
            method: 'DELETE',
        });
    });

    it('volta a estrela e avisa quando a API falha', async () => {
        fetchMock.mockResolvedValue({
            ok: false,
            json: async () => ({ error: { code: 'rate_limited' } }),
        });

        mountPage({}, { canFavorite: true });

        await click('[data-test="public-favorite"]');
        await flushPromises();

        expect(
            query('[data-test="public-favorite"]').textContent,
        ).toContain('FAVORITAR');
        expect(
            query('[data-test="public-favorite"]').getAttribute(
                'aria-pressed',
            ),
        ).toBe('false');
        expect(
            query('[data-test="public-favorite-error"]').textContent,
        ).toContain('ERRO_FAVORITO');
    });
});
